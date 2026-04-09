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
 * Unit tests for the get_tool_catalog external function.
 *
 * @package     local_ltifederation
 * @copyright   2026 David Castro
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ltifederation\external;

use core_external\external_api;
use externallib_advanced_testcase;

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Test class for get_tool_catalog external function.
 *
 * @covers \local_ltifederation\external\get_tool_catalog
 */
class get_tool_catalog_test extends externallib_advanced_testcase {
    /**
     * Helper to create an enrol_lti_tools record.
     *
     * @param \stdClass $course The course object.
     * @param string    $ltiversion LTI version string.
     * @param int       $enrolstatus Enrolment status.
     * @param string    $toolname Tool name.
     */
    private function create_lti_tool(
        \stdClass $course,
        string $ltiversion = 'LTI-1p3',
        int $enrolstatus = 0,
        string $toolname = 'Test Tool'
    ): void {
        global $DB;

        $enrolid = $DB->insert_record('enrol', (object) [
            'enrol'        => 'lti',
            'courseid'     => $course->id,
            'name'         => $toolname,
            'status'       => $enrolstatus,
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);

        $contextid = \context_course::instance($course->id)->id;
        $uuid = \core\uuid::generate();

        $DB->insert_record('enrol_lti_tools', (object) [
            'enrolid'        => $enrolid,
            'contextid'      => $contextid,
            'ltiversion'     => $ltiversion,
            'institution'    => '',
            'lang'           => 'en',
            'timezone'       => '99',
            'maxenrolled'    => 0,
            'maildisplay'    => 2,
            'city'           => '',
            'country'        => '',
            'gradesync'      => 0,
            'gradesynccompletion' => 0,
            'membersync'     => 0,
            'membersyncmode' => 0,
            'roleinstructor' => 3,
            'rolelearner'    => 5,
            'uuid'           => $uuid,
            'timecreated'    => time(),
            'timemodified'   => time(),
        ]);
    }

    /**
     * Set up for tests.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);

        // Set up mod_lti private key.
        if (!get_config('mod_lti', 'privatekey')) {
            $res = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            openssl_pkey_export($res, $privatekey);
            set_config('privatekey', $privatekey, 'mod_lti');
            set_config('kid', 'test-kid-' . time(), 'mod_lti');
        }
    }

    /**
     * Test that the function returns correct structure.
     */
    public function test_execute_returns_correct_structure(): void {
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $this->create_lti_tool($course, 'LTI-1p3', 0, 'My Tool');

        $result = get_tool_catalog::execute();

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        $first = $result[0];
        $this->assertArrayHasKey('id', $first);
        $this->assertArrayHasKey('uuid', $first);
        $this->assertArrayHasKey('name', $first);
        $this->assertArrayHasKey('description', $first);
        $this->assertArrayHasKey('coursefullname', $first);
        $this->assertArrayHasKey('ltiversion', $first);
        $this->assertArrayHasKey('registration_url', $first);
        $this->assertArrayHasKey('registration_token', $first);
        $this->assertArrayHasKey('logo_url', $first);
        $this->assertArrayHasKey('remotestatus', $first);
        $this->assertArrayHasKey('timecreated', $first);
        $this->assertArrayHasKey('timemodified', $first);
    }

    /**
     * Test that a user without the capability cannot call the function.
     */
    public function test_capability_required(): void {
        // Create a regular user without the capability.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->expectException(\required_capability_exception::class);
        get_tool_catalog::execute();
    }

    /**
     * Test that only LTI-1p3 tools are returned (disabled are excluded).
     */
    public function test_only_active_lti1p3_tools_returned(): void {
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $this->create_lti_tool($course, 'LTI-1p3', 0, 'Active Tool');
        $this->create_lti_tool($course, 'LTI-1p3', 1, 'Disabled Tool');
        $this->create_lti_tool($course, 'LTI-1.0', 0, 'Legacy Tool');

        $result = get_tool_catalog::execute();

        $this->assertCount(1, $result);
        $this->assertEquals('Active Tool', $result[0]['name']);
        $this->assertEquals('LTI-1p3', $result[0]['ltiversion']);
    }

    /**
     * Test that the function returns an empty array when there are no tools.
     */
    public function test_returns_empty_array_when_no_tools(): void {
        $this->setAdminUser();
        $result = get_tool_catalog::execute();
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test that the return values match the external_multiple_structure definition.
     */
    public function test_return_values_valid_against_structure(): void {
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $this->create_lti_tool($course, 'LTI-1p3', 0, 'Validated Tool');

        $result = get_tool_catalog::execute();
        $returns = get_tool_catalog::execute_returns();

        // Clean_returnvalue will throw if the result doesn't match the declared structure.
        $cleaned = external_api::clean_returnvalue($returns, $result);
        $this->assertNotEmpty($cleaned);
    }
}
