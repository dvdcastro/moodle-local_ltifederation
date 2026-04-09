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
 * Unit tests for local_ltifederation\task\cleanup_draft_registrations.
 *
 * @package     local_ltifederation
 * @copyright   2026 David Castro
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ltifederation\task;

/**
 * Test class for cleanup_draft_registrations scheduled task.
 *
 * @covers \local_ltifederation\task\cleanup_draft_registrations
 */
class cleanup_draft_registrations_test extends \advanced_testcase {
    /**
     * Helper: insert a row into enrol_lti_app_registration.
     *
     * @param int    $status       0=draft/incomplete, 1=complete.
     * @param int    $timecreated  Timestamp.
     * @return int   The new record id.
     */
    private function create_app_registration(int $status, int $timecreated): int {
        global $DB;
        return $DB->insert_record('enrol_lti_app_registration', (object) [
            'name'         => 'Test Registration ' . $timecreated,
            'uniqueid'     => \core\uuid::generate(),
            'status'       => $status,
            'timecreated'  => $timecreated,
            'timemodified' => $timecreated,
        ]);
    }

    /**
     * Setup: reset DB before each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Test that old incomplete (draft) registrations are deleted.
     */
    public function test_old_draft_registrations_are_deleted(): void {
        global $DB;

        // Create a draft registration that is 25 hours old.
        $oldtime = time() - (25 * HOURSECS);
        $oldid   = $this->create_app_registration(0, $oldtime);

        $task = new cleanup_draft_registrations();
        $task->execute();

        $this->assertFalse(
            $DB->record_exists('enrol_lti_app_registration', ['id' => $oldid]),
            'Old draft registration should have been deleted.'
        );
    }

    /**
     * Test that recent draft registrations (less than 24 hours old) are kept.
     */
    public function test_recent_draft_registrations_are_kept(): void {
        global $DB;

        // Create a draft registration that is only 1 hour old.
        $recenttime = time() - HOURSECS;
        $recentid   = $this->create_app_registration(0, $recenttime);

        $task = new cleanup_draft_registrations();
        $task->execute();

        $this->assertTrue(
            $DB->record_exists('enrol_lti_app_registration', ['id' => $recentid]),
            'Recent draft registration (< 24h) should NOT be deleted.'
        );
    }

    /**
     * Test that complete registrations (status=1) are never deleted, even if old.
     */
    public function test_complete_registrations_are_never_deleted(): void {
        global $DB;

        // Create a complete registration that is 48 hours old.
        $oldtime    = time() - (48 * HOURSECS);
        $completeid = $this->create_app_registration(1, $oldtime);

        $task = new cleanup_draft_registrations();
        $task->execute();

        $this->assertTrue(
            $DB->record_exists('enrol_lti_app_registration', ['id' => $completeid]),
            'Complete registration (status=1) should never be deleted regardless of age.'
        );
    }

    /**
     * Test that the task correctly handles a mix of old/recent draft and complete registrations.
     */
    public function test_mixed_registrations_selective_cleanup(): void {
        global $DB;

        $now      = time();
        $old      = $now - (30 * HOURSECS);
        $recent   = $now - HOURSECS;

        $olddraftid      = $this->create_app_registration(0, $old);    // Should be deleted.
        $recentdraftid   = $this->create_app_registration(0, $recent);  // Should be kept.
        $oldcompleteid   = $this->create_app_registration(1, $old);    // Should be kept.
        $recentcompleteid = $this->create_app_registration(1, $recent); // Should be kept.

        $task = new cleanup_draft_registrations();
        $task->execute();

        $this->assertFalse(
            $DB->record_exists('enrol_lti_app_registration', ['id' => $olddraftid]),
            'Old draft should be deleted.'
        );
        $this->assertTrue(
            $DB->record_exists('enrol_lti_app_registration', ['id' => $recentdraftid]),
            'Recent draft should be kept.'
        );
        $this->assertTrue(
            $DB->record_exists('enrol_lti_app_registration', ['id' => $oldcompleteid]),
            'Old complete registration should be kept.'
        );
        $this->assertTrue(
            $DB->record_exists('enrol_lti_app_registration', ['id' => $recentcompleteid]),
            'Recent complete registration should be kept.'
        );
    }

    /**
     * Test that running the task when there are no draft registrations does not error.
     */
    public function test_no_error_when_no_drafts(): void {
        $task = new cleanup_draft_registrations();
        // Should complete without exception.
        $task->execute();
        $this->assertTrue(true, 'Task executed without error when no draft registrations exist.');
    }
}
