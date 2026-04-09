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
 * Scheduled task: clean up expired LTI draft registrations.
 *
 * When catalog_provider generates a draft registration for each catalog entry,
 * it creates a row in enrol_lti_app_registration with status=0 (draft/incomplete).
 * If the consumer never completes the registration flow these drafts accumulate.
 * This task deletes draft rows older than 24 hours.
 *
 * @package     local_ltifederation
 * @copyright   2026 David Castro
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ltifederation\task;

/**
 * Deletes incomplete (draft) enrol_lti_app_registration rows created more than
 * 24 hours ago.  Complete registrations (status=1) are never touched.
 */
class cleanup_draft_registrations extends \core\task\scheduled_task {
    /** Seconds in 24 hours. */
    const MAX_DRAFT_AGE = 86400;

    /**
     * Human-readable task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_cleanup_draft_registrations', 'local_ltifederation');
    }

    /**
     * Execute the cleanup task.
     *
     * Deletes rows from enrol_lti_app_registration where:
     *  - status = 0  (incomplete / draft)
     *  - timecreated < now - 86400  (older than 24 hours)
     */
    public function execute(): void {
        global $DB;

        $cutoff = time() - self::MAX_DRAFT_AGE;

        $deleted = $DB->delete_records_select(
            'enrol_lti_app_registration',
            'status = :status AND timecreated < :cutoff',
            ['status' => 0, 'cutoff' => $cutoff]
        );

        mtrace("local_ltifederation cleanup_draft_registrations: deleted {$deleted} expired draft registration(s).");
    }
}
