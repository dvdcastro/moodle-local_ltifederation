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
 * Unit tests for local_ltifederation\catalog_provider.
 *
 * @package     local_ltifederation
 * @copyright   2026 David Castro
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ltifederation;

global $CFG;
require_once($CFG->dirroot . '/enrol/lti/tests/helper.php');

/**
 * Test class for catalog_provider.
 *
 * @covers \local_ltifederation\catalog_provider
 */
class catalog_provider_test extends \advanced_testcase {
    /**
     * Create a minimal enrol_lti_tools record and associated enrolment.
     *
     * @param \stdClass $course The course object.
     * @param string    $ltiversion LTI version string (e.g. 'LTI-1p3').
     * @param int       $enrolstatus Enrolment status: 0=active, 1=disabled.
     * @param string    $toolname Name for the enrolment instance.
     * @return int  The new enrol_lti_tools id.
     */
    private function create_lti_tool(
        \stdClass $course,
        string $ltiversion = 'LTI-1p3',
        int $enrolstatus = 0,
        string $toolname = 'Test LTI Tool'
    ): int {
        global $DB;

        // Create an enrol instance on the course.
        $enrolid = $DB->insert_record('enrol', (object) [
            'enrol'       => 'lti',
            'courseid'    => $course->id,
            'name'        => $toolname,
            'status'      => $enrolstatus,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $contextid = \context_course::instance($course->id)->id;

        // Create lti tools record.
        $uuid = \core\uuid::generate();
        $toolid = $DB->insert_record('enrol_lti_tools', (object) [
            'enrolid'       => $enrolid,
            'contextid'     => $contextid,
            'ltiversion'    => $ltiversion,
            'institution'   => '',
            'lang'          => 'en',
            'timezone'      => '99',
            'maxenrolled'   => 0,
            'maildisplay'   => 2,
            'city'          => '',
            'country'       => '',
            'gradesync'     => 0,
            'gradesynccompletion' => 0,
            'membersync'    => 0,
            'membersyncmode' => 0,
            'roleinstructor' => 3,
            'rolelearner'   => 5,
            'uuid'          => $uuid,
            'timecreated'   => time(),
            'timemodified'  => time(),
        ]);

        return $toolid;
    }

    /**
     * Setup: reset Moodle and set up private key for mod_lti (needed by catalog_provider).
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);

        // Mod_lti requires a private key to be set for draft registration creation.
        // Generate one if not set.
        if (!get_config('mod_lti', 'privatekey')) {
            $res = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            openssl_pkey_export($res, $privatekey);
            set_config('privatekey', $privatekey, 'mod_lti');
            set_config('kid', 'test-kid-' . time(), 'mod_lti');
        }
    }

    /**
     * Test that get_catalog() returns only LTI-1p3 tools.
     */
    public function test_only_lti1p3_tools_are_returned(): void {
        $course = $this->getDataGenerator()->create_course();

        // Create one LTI-1p3 tool and one legacy LTI 1.1 tool.
        $this->create_lti_tool($course, 'LTI-1p3', 0, 'LTI 1.3 Tool');
        $this->create_lti_tool($course, 'LTI-1.0', 0, 'Legacy LTI Tool');

        $this->setAdminUser();
        $catalog = catalog_provider::get_catalog();

        // Should only have the LTI-1p3 tool.
        $this->assertCount(1, $catalog);
        $this->assertEquals('LTI-1p3', $catalog[0]->ltiversion);
        $this->assertEquals('LTI 1.3 Tool', $catalog[0]->name);
    }

    /**
     * Test that disabled (status=1) enrolment tools are excluded.
     */
    public function test_disabled_tools_are_excluded(): void {
        $course = $this->getDataGenerator()->create_course();

        // Active tool.
        $this->create_lti_tool($course, 'LTI-1p3', 0, 'Active Tool');
        // Disabled tool.
        $this->create_lti_tool($course, 'LTI-1p3', 1, 'Disabled Tool');

        $this->setAdminUser();
        $catalog = catalog_provider::get_catalog();

        $this->assertCount(1, $catalog);
        $this->assertEquals('Active Tool', $catalog[0]->name);
    }

    /**
     * Test that returned catalog entries have all required fields.
     */
    public function test_catalog_entry_has_required_fields(): void {
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Test Course']);
        $this->create_lti_tool($course, 'LTI-1p3', 0, 'My Tool');

        $this->setAdminUser();
        $catalog = catalog_provider::get_catalog();

        $this->assertNotEmpty($catalog);
        $entry = $catalog[0];

        $this->assertIsInt($entry->id);
        $this->assertIsString($entry->uuid);
        $this->assertIsString($entry->name);
        $this->assertIsString($entry->description);
        $this->assertIsString($entry->coursefullname);
        $this->assertEquals('LTI-1p3', $entry->ltiversion);
        $this->assertNotEmpty($entry->registration_url);
        $this->assertStringContainsString('/enrol/lti/register.php', $entry->registration_url);
        $this->assertNotEmpty($entry->registration_token);
        $this->assertIsInt($entry->remotestatus);
        $this->assertEquals(0, $entry->remotestatus);
        $this->assertIsInt($entry->timecreated);
        $this->assertIsInt($entry->timemodified);
    }

    /**
     * Test that when there are no LTI-1p3 tools, an empty array is returned.
     */
    public function test_empty_catalog_when_no_tools(): void {
        $this->setAdminUser();
        $catalog = catalog_provider::get_catalog();
        $this->assertIsArray($catalog);
        $this->assertEmpty($catalog);
    }

    /**
     * Test that each returned tool gets a unique registration token.
     */
    public function test_each_tool_gets_unique_registration_token(): void {
        $course = $this->getDataGenerator()->create_course();
        $this->create_lti_tool($course, 'LTI-1p3', 0, 'Tool A');
        $this->create_lti_tool($course, 'LTI-1p3', 0, 'Tool B');

        $this->setAdminUser();
        $catalog = catalog_provider::get_catalog();

        $this->assertCount(2, $catalog);
        $this->assertNotEquals($catalog[0]->registration_token, $catalog[1]->registration_token);
    }

    /**
     * Test that coursefullname is populated from the course record.
     */
    public function test_coursefullname_is_populated(): void {
        $course = $this->getDataGenerator()->create_course(['fullname' => 'My Wonderful Course']);
        $this->create_lti_tool($course, 'LTI-1p3', 0, 'Tool in Course');

        $this->setAdminUser();
        $catalog = catalog_provider::get_catalog();

        $this->assertNotEmpty($catalog);
        $this->assertEquals('My Wonderful Course', $catalog[0]->coursefullname);
    }
}
