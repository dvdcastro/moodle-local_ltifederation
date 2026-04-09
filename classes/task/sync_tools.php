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
 * Adhoc task: sync LTI tool catalog from a remote provider.
 *
 * @package     local_ltifederation
 * @copyright   2026 David Castro
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ltifederation\task;

defined('MOODLE_INTERNAL') || die();

use local_ltifederation\encryption_helper;

/**
 * Adhoc task that fetches the tool catalog from a remote provider and upserts
 * into local_ltifed_catalog_cache.
 */
class sync_tools extends \core\task\adhoc_task {

    /**
     * Human-readable task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_sync_tools', 'local_ltifederation');
    }

    /**
     * Execute the sync.
     *
     * Custom data must include: providerid (int).
     */
    public function execute(): void {
        global $DB;

        $customdata = $this->get_custom_data();
        $providerid = (int) ($customdata->providerid ?? 0);

        if ($providerid <= 0) {
            mtrace('local_ltifederation sync_tools: invalid providerid in custom data.');
            return;
        }

        $provider = $DB->get_record('local_ltifed_providers', ['id' => $providerid]);
        if (!$provider) {
            mtrace("local_ltifederation sync_tools: provider ID {$providerid} not found.");
            return;
        }

        mtrace("local_ltifederation: syncing catalog from provider '{$provider->label}' ({$provider->providerurl})");

        // Decrypt the web service token.
        $wstoken = encryption_helper::decrypt($provider->wstoken);

        // Build the WS REST call URL.
        $wsurl = rtrim($provider->providerurl, '/') . '/webservice/rest/server.php';
        $params = http_build_query([
            'wsfunction'       => 'local_ltifederation_get_tool_catalog',
            'wstoken'          => $wstoken,
            'moodlewsrestformat' => 'json',
        ]);
        $fullurl = $wsurl . '?' . $params;

        // Call the remote web service.
        try {
            $curl = new \curl();
            $response = $curl->get($fullurl);
            $errno = $curl->get_errno();

            if ($errno !== 0) {
                throw new \coding_exception("cURL error {$errno}: {$response}");
            }
        } catch (\Exception $e) {
            $this->mark_provider_error($provider, 'HTTP request failed: ' . $e->getMessage());
            return;
        }

        // Parse the JSON response.
        $tools = json_decode($response);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->mark_provider_error($provider, 'Invalid JSON response: ' . json_last_error_msg());
            return;
        }

        // Check for Moodle WS error response.
        if (isset($tools->exception) || isset($tools->errorcode)) {
            $errmsg = $tools->message ?? ($tools->errorcode ?? 'Unknown WS error');
            $this->mark_provider_error($provider, 'WS error: ' . $errmsg);
            return;
        }

        if (!is_array($tools)) {
            $this->mark_provider_error($provider, 'Expected array from WS, got: ' . gettype($tools));
            return;
        }

        mtrace("  Found " . count($tools) . " tool(s).");

        // Track remote UUIDs present in this response.
        $remoteUUIDs = [];

        $now = time();

        foreach ($tools as $tool) {
            $remoteuuid = $tool->uuid ?? null;
            if (empty($remoteuuid)) {
                // Cannot upsert without a UUID; skip.
                continue;
            }

            $remoteUUIDs[] = $remoteuuid;

            // Check if we already have a cache entry for this tool.
            $existing = $DB->get_record('local_ltifed_catalog_cache', [
                'providerid'  => $providerid,
                'remoteuuid'  => $remoteuuid,
            ]);

            $record = new \stdClass();
            $record->providerid         = $providerid;
            $record->remoteid           = (int) ($tool->id ?? 0);
            $record->remoteuuid         = $remoteuuid;
            $record->name               = substr($tool->name ?? '', 0, 255);
            $record->description        = $tool->description ?? null;
            $record->coursefullname     = substr($tool->coursefullname ?? '', 0, 255);
            $record->ltiversion         = substr($tool->ltiversion ?? '', 0, 15);
            $record->registration_url   = $tool->registration_url ?? null;
            $record->registration_token = substr($tool->registration_token ?? '', 0, 255);
            $record->logo_url           = $tool->logo_url ?? null;
            $record->remotestatus       = (int) ($tool->remotestatus ?? 0);
            $record->timefetched        = $now;

            if ($existing) {
                $record->id = $existing->id;
                // Preserve local registration state unless explicitly clearing.
                $DB->update_record('local_ltifed_catalog_cache', $record);
                mtrace("  Updated tool: {$record->name}");
            } else {
                $record->lti_type_id    = null;
                $record->regstate       = 'none';
                $record->regerror       = null;
                $record->timeregistered = null;
                $DB->insert_record('local_ltifed_catalog_cache', $record);
                mtrace("  Inserted tool: {$record->name}");
            }
        }

        // Mark tools no longer in the remote response as remotestatus=1.
        if (!empty($remoteUUIDs)) {
            list($notinsql, $notinparams) = $DB->get_in_or_equal($remoteUUIDs, SQL_PARAMS_NAMED, 'uuid', false);
            $DB->execute(
                "UPDATE {local_ltifed_catalog_cache}
                    SET remotestatus = 1
                  WHERE providerid = :providerid
                    AND remoteuuid {$notinsql}",
                array_merge(['providerid' => $providerid], $notinparams)
            );
        }

        // Update provider sync status.
        $DB->update_record('local_ltifed_providers', (object) [
            'id'          => $providerid,
            'lastsync'    => $now,
            'syncstatus'  => 'ok',
            'syncmessage' => null,
            'timemodified' => $now,
        ]);

        mtrace("local_ltifederation: sync completed for provider '{$provider->label}'.");
    }

    /**
     * Mark a provider sync as failed.
     *
     * @param \stdClass $provider The provider record.
     * @param string    $message  Error message.
     */
    private function mark_provider_error(\stdClass $provider, string $message): void {
        global $DB;
        mtrace("local_ltifederation sync_tools ERROR for '{$provider->label}': {$message}");
        $DB->update_record('local_ltifed_providers', (object) [
            'id'           => $provider->id,
            'lastsync'     => time(),
            'syncstatus'   => 'error',
            'syncmessage'  => $message,
            'timemodified' => time(),
        ]);
    }
}
