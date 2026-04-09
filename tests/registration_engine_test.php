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
 * Unit tests for local_ltifederation\registration_engine.
 *
 * @package     local_ltifederation
 * @copyright   2026 David Castro
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ltifederation;

/**
 * Test class for registration_engine.
 *
 * @covers \local_ltifederation\registration_engine
 */
class registration_engine_test extends \advanced_testcase {
    /** @var \stdClass Fake provider record used in tests. */
    private \stdClass $provider;

    /** @var \stdClass Fake cache entry record used in tests. */
    private \stdClass $cacheentry;

    /**
     * Set up common test fixtures.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);

        // Provider with a known host.
        $this->provider = (object) [
            'id'          => 1,
            'label'       => 'Test Provider',
            'providerurl' => 'https://provider.example.com',
            'wstoken'     => 'dummytoken',
            'autosync'    => 0,
        ];

        // Cache entry pointing to the same host as the provider.
        $this->cacheentry = (object) [
            'id'                 => 99,
            'providerid'         => 1,
            'remoteid'           => 5,
            'remoteuuid'         => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            'name'               => 'Test Tool',
            'description'        => 'A test tool',
            'coursefullname'     => 'Test Course',
            'ltiversion'         => 'LTI-1p3',
            'registration_url'   => 'https://provider.example.com/enrol/lti/register.php?token=abc123',
            'registration_token' => 'abc123',
            'logo_url'           => '',
            'remotestatus'       => 0,
            'lti_type_id'        => null,
            'regstate'           => 'none',
            'regerror'           => null,
        ];

        // Set up mod_lti private key (required by registration_engine JWT generation).
        if (!get_config('mod_lti', 'privatekey')) {
            $res = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            openssl_pkey_export($res, $privatekey);
            set_config('privatekey', $privatekey, 'mod_lti');
            set_config('kid', 'test-kid-' . time(), 'mod_lti');
        }
    }

    /**
     * Test that an SSRF attack is blocked when the registration URL host differs from the provider host.
     */
    public function test_ssrf_blocked_when_host_mismatch(): void {
        // Insert a real provider and cache entry so DB updates work.
        global $DB;
        $providerid = $DB->insert_record('local_ltifed_providers', (object) [
            'label'        => 'Test Provider',
            'providerurl'  => 'https://provider.example.com',
            'wstoken'      => 'dummytoken',
            'autosync'     => 0,
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);

        $cacheentryid = $DB->insert_record('local_ltifed_catalog_cache', (object) [
            'providerid'         => $providerid,
            'remoteid'           => 5,
            'remoteuuid'         => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            'name'               => 'Test Tool',
            'ltiversion'         => 'LTI-1p3',
            'registration_url'   => 'https://attacker.evil.com/enrol/lti/register.php?token=abc',
            'registration_token' => 'abc',
            'remotestatus'       => 0,
            'regstate'           => 'none',
            'timefetched'        => time(),
        ]);

        $provider   = $DB->get_record('local_ltifed_providers', ['id' => $providerid]);
        $cacheentry = $DB->get_record('local_ltifed_catalog_cache', ['id' => $cacheentryid]);

        $engine = new registration_engine();
        $this->expectException(\moodle_exception::class);
        $engine->register_tool($cacheentry, $provider);

        // After exception, verify regstate is 'error'.
        $updated = $DB->get_record('local_ltifed_catalog_cache', ['id' => $cacheentryid]);
        $this->assertEquals('error', $updated->regstate);
        $this->assertStringContainsString('SSRF', $updated->regerror);
    }

    /**
     * Test that a tool that is already registered is skipped (idempotency).
     */
    public function test_idempotency_skips_already_registered_tool(): void {
        global $DB;

        $providerid = $DB->insert_record('local_ltifed_providers', (object) [
            'label'        => 'Test Provider',
            'providerurl'  => 'https://provider.example.com',
            'wstoken'      => 'dummytoken',
            'autosync'     => 0,
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);

        // Create a fake lti_types row to simulate an existing registration.
        $ltitypeid = $DB->insert_record('lti_types', (object) [
            'name'        => 'Test Tool',
            'baseurl'     => 'https://provider.example.com/enrol/lti/launch.php',
            'tooldomain'  => 'provider.example.com',
            'state'       => LTI_TOOL_STATE_CONFIGURED, // 1 = configured.
            'course'      => 1,
            'coursevisible' => 1,
            'ltiversion'  => 'LTI-1.3',
            'clientid'    => 'existingclientid123',
            'timecreated' => time(),
            'timemodified' => time(),
            'createdby'   => 2,
        ]);

        $cacheentryid = $DB->insert_record('local_ltifed_catalog_cache', (object) [
            'providerid'         => $providerid,
            'remoteid'           => 5,
            'remoteuuid'         => 'bbbbbbbb-bbbb-cccc-dddd-eeeeeeeeeeee',
            'name'               => 'Test Tool',
            'ltiversion'         => 'LTI-1p3',
            'registration_url'   => 'https://provider.example.com/enrol/lti/register.php?token=xyz',
            'registration_token' => 'xyz',
            'remotestatus'       => 0,
            'lti_type_id'        => $ltitypeid,
            'regstate'           => 'registered',
            'timefetched'        => time(),
        ]);

        $provider   = $DB->get_record('local_ltifed_providers', ['id' => $providerid]);
        $cacheentry = $DB->get_record('local_ltifed_catalog_cache', ['id' => $cacheentryid]);

        $engine = new registration_engine();
        // Should not throw and should return early.
        $engine->register_tool($cacheentry, $provider);

        // Verify the cache entry was NOT modified (still 'registered').
        $updated = $DB->get_record('local_ltifed_catalog_cache', ['id' => $cacheentryid]);
        $this->assertEquals('registered', $updated->regstate);
    }

    /**
     * Test that an empty registration URL results in an error state.
     */
    public function test_empty_registration_url_causes_error(): void {
        global $DB;

        $providerid = $DB->insert_record('local_ltifed_providers', (object) [
            'label'        => 'Test Provider',
            'providerurl'  => 'https://provider.example.com',
            'wstoken'      => 'dummytoken',
            'autosync'     => 0,
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);

        $cacheentryid = $DB->insert_record('local_ltifed_catalog_cache', (object) [
            'providerid'         => $providerid,
            'remoteid'           => 5,
            'remoteuuid'         => 'cccccccc-bbbb-cccc-dddd-eeeeeeeeeeee',
            'name'               => 'No URL Tool',
            'ltiversion'         => 'LTI-1p3',
            'registration_url'   => '',
            'registration_token' => '',
            'remotestatus'       => 0,
            'regstate'           => 'none',
            'timefetched'        => time(),
        ]);

        $provider   = $DB->get_record('local_ltifed_providers', ['id' => $providerid]);
        $cacheentry = $DB->get_record('local_ltifed_catalog_cache', ['id' => $cacheentryid]);

        $engine = new registration_engine();
        $this->expectException(\moodle_exception::class);
        $engine->register_tool($cacheentry, $provider);

        $updated = $DB->get_record('local_ltifed_catalog_cache', ['id' => $cacheentryid]);
        $this->assertEquals('error', $updated->regstate);
    }

    /**
     * Test that a valid URL on the same host passes the SSRF check
     * and proceeds to the JWT generation phase.
     *
     * We expect it to eventually fail at the HTTP phase (no real server),
     * but it must pass the SSRF check and attempt the JWT.
     */
    public function test_valid_host_passes_ssrf_check(): void {
        global $DB;

        $providerid = $DB->insert_record('local_ltifed_providers', (object) [
            'label'        => 'Test Provider',
            'providerurl'  => 'https://provider.example.com',
            'wstoken'      => 'dummytoken',
            'autosync'     => 0,
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);

        $cacheentryid = $DB->insert_record('local_ltifed_catalog_cache', (object) [
            'providerid'         => $providerid,
            'remoteid'           => 10,
            'remoteuuid'         => 'dddddddd-bbbb-cccc-dddd-eeeeeeeeeeee',
            'name'               => 'Valid Tool',
            'ltiversion'         => 'LTI-1p3',
            'registration_url'   => 'https://provider.example.com/enrol/lti/register.php?token=tok123',
            'registration_token' => 'tok123',
            'remotestatus'       => 0,
            'regstate'           => 'none',
            'timefetched'        => time(),
        ]);

        $provider   = $DB->get_record('local_ltifed_providers', ['id' => $providerid]);
        $cacheentry = $DB->get_record('local_ltifed_catalog_cache', ['id' => $cacheentryid]);

        $engine = new registration_engine();

        // The call will fail at the HTTP layer (no real server at provider.example.com),
        // resulting in a moodle_exception about WS call. This is expected in unit tests.
        // The important thing is it does NOT throw an SSRF exception.
        try {
            $engine->register_tool($cacheentry, $provider);
        } catch (\moodle_exception $e) {
            // Should not be the SSRF error.
            $this->assertNotEquals('error_ssrf_blocked', $e->errorcode);
        }
    }
}
