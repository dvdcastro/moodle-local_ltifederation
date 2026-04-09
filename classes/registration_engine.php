<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Registration engine for server-to-server LTI Dynamic Registration.
 *
 * @package     local_ltifederation
 * @copyright   2026 David Castro
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ltifederation;

require_once($GLOBALS['CFG']->dirroot . '/mod/lti/locallib.php');

use Firebase\JWT\JWT;
use mod_lti\local\ltiopenid\jwks_helper;
use mod_lti\local\ltiopenid\registration_helper;

/**
 * Handles server-to-server LTI Dynamic Registration for catalog tools.
 */
class registration_engine {
    /**
     * Minimum seconds between registration attempts for the same cache entry
     * (rate-limiting guard, uses timefetched field as a proxy for last attempt).
     */
    const MIN_RETRY_INTERVAL = 60;

    /**
     * Register a tool from the catalog cache using LTI Dynamic Registration.
     *
     * Steps:
     * 1. HTTPS enforcement: registration URL must use HTTPS (unless localhost).
     * 2. Anti-SSRF check: registration URL host must match provider URL host.
     * 3. Idempotency check: if already registered, skip.
     * 4. Orphan detection: check for an existing lti_types row by launch URL.
     * 5. Rate limiting: do not retry within 60 seconds.
     * 6. Generate JWT registration token (RS256, signed with mod_lti private key).
     * 7. Build full registration URL and GET it.
     * 8. Follow the registration flow.
     * 9. Find new lti_types row by clientid, mark state=1.
     * 10. Update cache entry regstate.
     *
     * @param \stdClass $cacheentry  A row from local_ltifed_catalog_cache.
     * @param \stdClass $provider    A row from local_ltifed_providers.
     * @throws \moodle_exception     On SSRF detection or other fatal errors.
     */
    public function register_tool(\stdClass $cacheentry, \stdClass $provider): void {
        global $DB, $CFG;

        $regurl      = $cacheentry->registration_url ?? '';
        $providerurl = $provider->providerurl ?? '';

        // --- Step 1: HTTPS enforcement ---
        if (!empty($regurl)) {
            $scheme  = strtolower(parse_url($regurl, PHP_URL_HOST) ? (parse_url($regurl, PHP_URL_SCHEME) ?? '') : '');
            $reghost = parse_url($regurl, PHP_URL_HOST);
            $islocalhost = in_array(strtolower($reghost ?? ''), ['localhost', '127.0.0.1', '::1'], true);

            if (!$islocalhost && strtolower(parse_url($regurl, PHP_URL_SCHEME) ?? '') !== 'https') {
                $this->update_cache_error($cacheentry->id, 'Registration URL must use HTTPS.');
                throw new \moodle_exception('error_https_required', 'local_ltifederation');
            }
        }

        // --- Step 2: Anti-SSRF validation ---
        if (empty($regurl)) {
            $this->update_cache_error($cacheentry->id, 'Registration URL is empty.');
            throw new \moodle_exception('error_ssrf_blocked', 'local_ltifederation');
        }

        $reghost      = parse_url($regurl, PHP_URL_HOST);
        $providerhost = parse_url($providerurl, PHP_URL_HOST);

        if (empty($reghost) || strtolower($reghost) !== strtolower($providerhost ?? '')) {
            $errmsg = "SSRF blocked: registration host '{$reghost}' does not match provider host '{$providerhost}'.";
            $this->update_cache_error($cacheentry->id, $errmsg);
            mtrace("local_ltifederation registration_engine: {$errmsg}");
            throw new \moodle_exception('error_ssrf_blocked', 'local_ltifederation');
        }

        // --- Step 3: Idempotency check (by lti_type_id) ---
        if (!empty($cacheentry->lti_type_id)) {
            $existing = $DB->get_record('lti_types', ['id' => $cacheentry->lti_type_id]);
            if ($existing && $existing->state == LTI_TOOL_STATE_CONFIGURED) {
                // Already registered and configured; nothing to do.
                mtrace("local_ltifederation registration_engine: tool '{$cacheentry->name}' already registered (lti_type_id={$cacheentry->lti_type_id}), skipping.");
                return;
            }
        }

        // Also check by registration token to avoid duplicate registrations.
        if (!empty($cacheentry->registration_token)) {
            $existingbytoken = $DB->get_record(
                'enrol_lti_app_registration',
                ['uniqueid' => $cacheentry->registration_token]
            );
            if ($existingbytoken && $existingbytoken->status == 1) {
                // Already a complete registration for this token.
                mtrace("local_ltifederation registration_engine: tool '{$cacheentry->name}' already has a complete app_registration, skipping.");
                return;
            }
        }

        // --- Step 4: Orphan detection ---
        // Check for an existing lti_types row that shares the same base URL (launch URL pattern).
        // The launch URL for LTI Advantage tools follows a known pattern from enrol_lti.
        $launchurl = rtrim($providerurl, '/') . '/enrol/lti/launch.php';
        $orphan = $DB->get_record_select(
            'lti_types',
            "baseurl = ? AND ltiversion = 'LTI-1.3.0'",
            [$launchurl]
        );
        if ($orphan) {
            // Link the orphan to the cache entry instead of registering again.
            mtrace("local_ltifederation registration_engine: orphan lti_types row found (id={$orphan->id}), linking to cache entry '{$cacheentry->name}'.");
            $DB->update_record('local_ltifed_catalog_cache', (object) [
                'id'             => $cacheentry->id,
                'lti_type_id'    => $orphan->id,
                'regstate'       => 'registered',
                'regerror'       => null,
                'timeregistered' => time(),
            ]);
            return;
        }

        // --- Step 5: Rate limiting ---
        // Use timefetched as a proxy for "last sync attempt time".
        // If a registration was attempted in the last 60 seconds, skip.
        if (!empty($cacheentry->timefetched) && (time() - $cacheentry->timefetched) < self::MIN_RETRY_INTERVAL) {
            if ($cacheentry->regstate === 'error') {
                mtrace("local_ltifederation registration_engine: rate limit — skipping retry for '{$cacheentry->name}' (last attempt {$cacheentry->timefetched}).");
                return;
            }
        }

        // --- Step 6: Generate JWT registration token ---
        $sub   = registration_helper::get()->new_clientid();
        $scope = registration_helper::REG_TOKEN_OP_NEW_REG;
        $now   = time();
        $payload = [
            'sub'   => $sub,
            'scope' => $scope,
            'iat'   => $now,
            'exp'   => $now + HOURSECS,
        ];

        try {
            $privatekey = jwks_helper::get_private_key();
            if (empty($privatekey['key'])) {
                throw new \coding_exception('mod_lti private key is not configured.');
            }
            $regtoken = JWT::encode($payload, $privatekey['key'], 'RS256', $privatekey['kid']);
        } catch (\Exception $e) {
            $errmsg = 'JWT encoding failed: ' . $e->getMessage();
            $this->update_cache_error($cacheentry->id, $errmsg);
            mtrace("local_ltifederation registration_engine ERROR for '{$cacheentry->name}': {$errmsg}");
            throw new \moodle_exception('error_ws_call_failed', 'local_ltifederation', '', $e->getMessage());
        }

        // --- Step 7: Build full registration URL ---
        $confurl = new \moodle_url('/mod/lti/openid-configuration.php');
        $fullurl = new \moodle_url($regurl);
        $fullurl->param('openid_configuration', $confurl->out(false));
        $fullurl->param('registration_token', $regtoken);

        // --- Step 8: GET the full registration URL ---
        // This triggers enrol_lti's register.php on the provider side which in turn
        // makes a POST to our /mod/lti/openid-configuration endpoint.
        mtrace("local_ltifederation registration_engine: attempting registration for tool '{$cacheentry->name}' via {$fullurl->out(false)}");

        try {
            $curl = new \curl();
            $curl->setopt(['CURLOPT_FOLLOWLOCATION' => true, 'CURLOPT_MAXREDIRS' => 5]);
            $result = $curl->get($fullurl->out(false));
            $errno  = $curl->get_errno();
            if ($errno !== 0) {
                throw new \coding_exception("cURL error {$errno}: {$result}");
            }
        } catch (\Exception $e) {
            $errmsg = 'HTTP request failed: ' . $e->getMessage();
            $this->update_cache_error($cacheentry->id, $errmsg);
            mtrace("local_ltifederation registration_engine ERROR for '{$cacheentry->name}': {$errmsg}");
            throw new \moodle_exception('error_ws_call_failed', 'local_ltifederation', '', $e->getMessage());
        }

        // --- Step 9: Find newly created lti_types row by clientid (sub) ---
        $ltityperecord = $DB->get_record('lti_types', ['clientid' => $sub]);
        if ($ltityperecord) {
            // Set state to configured.
            $DB->set_field('lti_types', 'state', LTI_TOOL_STATE_CONFIGURED, ['id' => $ltityperecord->id]);

            // --- Step 10: Update cache entry ---
            $DB->update_record('local_ltifed_catalog_cache', (object) [
                'id'             => $cacheentry->id,
                'lti_type_id'    => $ltityperecord->id,
                'regstate'       => 'registered',
                'regerror'       => null,
                'timeregistered' => time(),
            ]);
            mtrace("local_ltifederation registration_engine: tool '{$cacheentry->name}' registered successfully (lti_types id={$ltityperecord->id}).");
        } else {
            // Registration may have worked but we didn't find the lti_types row.
            // Mark as pending for manual verification.
            $DB->update_record('local_ltifed_catalog_cache', (object) [
                'id'       => $cacheentry->id,
                'regstate' => 'pending',
                'regerror' => 'Registration request sent; lti_types row not yet found.',
            ]);
            mtrace("local_ltifederation registration_engine: tool '{$cacheentry->name}' registration sent but lti_types row not found — marked pending.");
        }
    }

    /**
     * Update a cache entry to error state.
     *
     * @param int    $cacheentryid  The cache entry id.
     * @param string $message       Error message.
     */
    private function update_cache_error(int $cacheentryid, string $message): void {
        global $DB;
        $DB->update_record('local_ltifed_catalog_cache', (object) [
            'id'       => $cacheentryid,
            'regstate' => 'error',
            'regerror' => $message,
        ]);
    }
}
