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
 * Unit tests for local_ltifederation\task\sync_all_providers.
 *
 * @package     local_ltifederation
 * @copyright   2026 David Castro
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ltifederation\task;

/**
 * Test class for sync_all_providers scheduled task.
 *
 * @covers \local_ltifederation\task\sync_all_providers
 */
class sync_all_providers_test extends \advanced_testcase {
    /**
     * Helper: insert a provider record.
     *
     * @param string $label     Provider label.
     * @param int    $autosync  Whether auto-sync is enabled (0 or 1).
     * @return int  The new record id.
     */
    private function create_provider(string $label, int $autosync): int {
        global $DB;
        return $DB->insert_record('local_ltifed_providers', (object) [
            'label'        => $label,
            'providerurl'  => 'https://' . strtolower(str_replace(' ', '', $label)) . '.example.com',
            'wstoken'      => 'dummytoken',
            'autosync'     => $autosync,
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Setup: reset DB and env before each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Test that only providers with autosync=1 get an adhoc task queued.
     */
    public function test_only_autosync_providers_are_queued(): void {
        global $DB;

        // Create two providers: one with autosync=1, one without.
        $this->create_provider('AutoSync Provider', 1);
        $this->create_provider('NoSync Provider', 0);

        // Run the task.
        $task = new sync_all_providers();
        $task->execute();

        // Verify that exactly one adhoc task was queued.
        $adhoctasks = $DB->get_records('task_adhoc', ['classname' => '\local_ltifederation\task\sync_tools']);
        $this->assertCount(1, $adhoctasks, 'Expected exactly one adhoc task for the autosync provider.');

        // Verify it was queued for the correct provider.
        $queued = reset($adhoctasks);
        $customdata = json_decode($queued->customdata);
        $provider = $DB->get_record('local_ltifed_providers', ['label' => 'AutoSync Provider']);
        $this->assertEquals($provider->id, $customdata->providerid);
    }

    /**
     * Test that when there are no autosync providers, no adhoc tasks are queued.
     */
    public function test_no_tasks_queued_when_no_autosync_providers(): void {
        global $DB;

        $this->create_provider('NoSync 1', 0);
        $this->create_provider('NoSync 2', 0);

        $task = new sync_all_providers();
        $task->execute();

        $adhoctasks = $DB->get_records('task_adhoc', ['classname' => '\local_ltifederation\task\sync_tools']);
        $this->assertCount(0, $adhoctasks, 'Expected no adhoc tasks when no providers have autosync enabled.');
    }

    /**
     * Test that multiple autosync providers each get a separate adhoc task.
     */
    public function test_multiple_autosync_providers_each_get_queued(): void {
        global $DB;

        $this->create_provider('AutoSync A', 1);
        $this->create_provider('AutoSync B', 1);
        $this->create_provider('NoSync C', 0);

        $task = new sync_all_providers();
        $task->execute();

        $adhoctasks = $DB->get_records('task_adhoc', ['classname' => '\local_ltifederation\task\sync_tools']);
        $this->assertCount(2, $adhoctasks, 'Expected two adhoc tasks for two autosync providers.');
    }

    /**
     * Test that running the task twice does not create duplicate adhoc tasks
     * (because queue_adhoc_task is called with $checkforexisting=true).
     */
    public function test_duplicate_tasks_are_not_created(): void {
        global $DB;

        $this->create_provider('AutoSync Provider', 1);

        $task = new sync_all_providers();
        $task->execute();

        // Run it a second time.
        $task->execute();

        // There should still be only one adhoc task queued (deduplicated).
        $adhoctasks = $DB->get_records('task_adhoc', ['classname' => '\local_ltifederation\task\sync_tools']);
        $this->assertCount(1, $adhoctasks, 'Expected only one adhoc task even when the scheduled task runs twice.');
    }
}
