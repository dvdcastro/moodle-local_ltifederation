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
 * Scheduled task: sync all auto-sync LTI provider catalogs.
 *
 * @package     local_ltifederation
 * @copyright   2026 David Castro
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ltifederation\task;

/**
 * Scheduled task that enqueues individual sync_tools adhoc tasks for every
 * provider that has autosync=1.  Duplicates are suppressed by passing
 * $checkforexisting=true to queue_adhoc_task().
 */
class sync_all_providers extends \core\task\scheduled_task {
    /**
     * Human-readable task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_sync_all_providers', 'local_ltifederation');
    }

    /**
     * Execute the scheduled task.
     *
     * Loops over all providers with autosync=1 and enqueues an individual
     * sync_tools adhoc task for each one.  The $checkforexisting=true flag
     * ensures already-queued tasks are not duplicated.
     */
    public function execute(): void {
        global $DB;

        $providers = $DB->get_records('local_ltifed_providers', ['autosync' => 1]);

        if (empty($providers)) {
            mtrace('local_ltifederation sync_all_providers: no providers with autosync enabled.');
            return;
        }

        $queued = 0;
        foreach ($providers as $provider) {
            $task = new sync_tools();
            $task->set_custom_data(['providerid' => (int) $provider->id]);

            // Queue the adhoc task; $checkforexisting=true prevents duplicates.
            \core\task\manager::queue_adhoc_task($task, true);
            mtrace("local_ltifederation sync_all_providers: queued sync for provider '{$provider->label}' (id={$provider->id}).");
            $queued++;
        }

        mtrace("local_ltifederation sync_all_providers: {$queued} sync task(s) queued.");
    }
}
