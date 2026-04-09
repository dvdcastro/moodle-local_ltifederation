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

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->dirroot . '/mod/lti/locallib.php');

use Firebase\JWT\JWT;
use mod_lti\local\ltiopenid\jwks_helper;
use mod_lti\local\ltiopenid\registration_helper;

/**
 * Handles server-to-server LTI Dynamic Registration for catalog tools.
 */
class registration_engine {

    /**
     * Register a tool from the catalog cache using LTI Dynamic Registration.
     *
     * Steps:
     * 1. Anti-SSRF check: registration URL host must match provider URL host.
     * 2. Idempotency check: if already registered, skip.
     * 3. Generate JWT registration token (RS256, signed with mod_lti private key).
     * 4. Build full registration URL and GET it.
     * 5. Follow the registration flow (the enrol_lti register.php handles the POST to tool).
     * 6. Find new lti_types row by clientid, mark state=1.
     * 7. Update cache entry regstate.
     *
     * @param \stdClass $cacheentry  A row from local_ltifed_catalog_cache.
     * @param \stdClass $provider    A row from local_ltifed_providers.
     * @throws \moodle_exception     On SSRF detection or other fatal errors.
     */
    public function register_tool(\stdClass $cacheentry, \stdClass $provider): void {
        global $DB, $CFG;

        // --- Step 1: Anti-SSRF validation ---
        $regurl     = $cacheentry->registration_url ?? '';
        $providerurl = $provider->providerurl ?? '';

        if (empty($regurl)) {
            $this->update_cache_error($cacheentry->id, 'Registration URL is empty.');
            throw new \moodle_exception('error_ssrf_blocked', 'local_ltifederation');
        }

        $reghost      = parse_url($regurl, PHP_URL_HOST);
        $providerhost = parse_url($providerurl, PHP_URL_HOST);

        if (empty($reghost) || strtolower($reghost) !== strtolower($providerhost ?? '')) {
            $this->update_cache_error(
                $cacheentry->id,
                "SSRF blocked: registration host '{$reghost}' does not match provider host '{$providerhost}'."
            );
            throw new \moodle_exception('error_ssrf_blocked', 'local_ltifederation');
        }

        // --- Step 2: Idempotency check ---
        if (!empty($cacheentry->lti_type_id)) {
            $existing = $DB->get_record('lti_types', ['id' => $cacheentry->lti_type_id]);
            if ($existing && $existing->state == LTI_TOOL_STATE_CONFIGURED) {
                // Already registered and configured; nothing to do.
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
                return;
            }
        }

        // --- Step 3: Generate JWT registration token ---
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
            $this->update_cache_error($cacheentry->id, 'JWT encoding failed: ' . $e->getMessage());
            throw new \moodle_exception('error_ws_call_failed', 'local_ltifederation', '', $e->getMessage());
        }

        // --- Step 4: Build full registration URL ---
        $confurl  = new \moodle_url('/mod/lti/openid-configuration.php');
        $fullurl  = new \moodle_url($regurl);
        $fullurl->param('openid_configuration', $confurl->out(false));
        $fullurl->param('registration_token', $regtoken);

        // --- Step 5: GET the full registration URL ---
        // This triggers enrol_lti's register.php on the provider side which in turn
        // makes a POST to our /mod/lti/openid-configuration endpoint.
        // In server-to-server mode we GET the provider's register.php; it will POST
        // back to our mod/lti registration endpoint.
        try {
            $curl = new \curl();
            $curl->setopt(['CURLOPT_FOLLOWLOCATION' => true, 'CURLOPT_MAXREDIRS' => 5]);
            $result = $curl->get($fullurl->out(false));
            $errno  = $curl->get_errno();
            if ($errno !== 0) {
                throw new \coding_exception("cURL error {$errno}: {$result}");
            }
        } catch (\Exception $e) {
            $this->update_cache_error($cacheentry->id, 'HTTP request failed: ' . $e->getMessage());
            throw new \moodle_exception('error_ws_call_failed', 'local_ltifederation', '', $e->getMessage());
        }

        // --- Step 6: Find newly created lti_types row by clientid (sub) ---
        $ltityperecord = $DB->get_record('lti_types', ['clientid' => $sub]);
        if ($ltityperecord) {
            // Set state to configured.
            $DB->set_field('lti_types', 'state', LTI_TOOL_STATE_CONFIGURED, ['id' => $ltityperecord->id]);

            // --- Step 7: Update cache entry ---
            $DB->update_record('local_ltifed_catalog_cache', (object) [
                'id'             => $cacheentry->id,
                'lti_type_id'    => $ltityperecord->id,
                'regstate'       => 'registered',
                'regerror'       => null,
                'timeregistered' => time(),
            ]);
        } else {
            // Registration may have worked but we didn't find the lti_types row.
            // Mark as pending for manual verification.
            $DB->update_record('local_ltifed_catalog_cache', (object) [
                'id'       => $cacheentry->id,
                'regstate' => 'pending',
                'regerror' => 'Registration request sent; lti_types row not yet found.',
            ]);
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
