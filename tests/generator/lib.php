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
 * Data generator for local_ltifederation tests.
 *
 * @package     local_ltifederation
 * @category    test
 * @copyright   2026 David Castro
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_ltifederation_generator extends testing_data_generator {
    /**
     * Create a provider record.
     *
     * Accepted fields: label, providerurl, wstoken, autosync.
     *
     * @param array|stdClass $record Data for the provider record.
     * @return stdClass The created provider record.
     */
    public function create_providers($record = null): stdClass {
        global $DB;

        $record = (object) (array) $record;

        $defaults = [
            'label'        => 'Test Provider',
            'providerurl'  => 'https://provider.example.com',
            'wstoken'      => 'testtoken',
            'autosync'     => 0,
            'syncstatus'   => null,
            'syncmessage'  => null,
            'lastsync'     => null,
            'timecreated'  => time(),
            'timemodified' => time(),
        ];

        foreach ($defaults as $field => $value) {
            if (!isset($record->$field)) {
                $record->$field = $value;
            }
        }

        $record->id = $DB->insert_record('local_ltifed_providers', $record);
        return $record;
    }

    /**
     * Create a catalog cache entry record.
     *
     * Accepted fields: providerid (by label or numeric id), name, coursefullname,
     * ltiversion, regstate, remotestatus, registration_url, registration_token.
     *
     * @param array|stdClass $record Data for the catalog entry.
     * @return stdClass The created catalog entry record.
     */
    public function create_catalog_entries($record = null): stdClass {
        global $DB;

        $record = (object) (array) $record;

        // Allow providerid to be specified by provider label.
        if (isset($record->providerid) && !is_numeric($record->providerid)) {
            $provider = $DB->get_record('local_ltifed_providers', ['label' => $record->providerid], '*', MUST_EXIST);
            $record->providerid = $provider->id;
        }

        $defaults = [
            'remoteid'           => 0,
            'remoteuuid'         => \core\uuid::generate(),
            'name'               => 'Test Tool',
            'description'        => '',
            'coursefullname'     => 'Test Course',
            'ltiversion'         => 'LTI-1p3',
            'registration_url'   => '',
            'registration_token' => '',
            'logo_url'           => '',
            'remotestatus'       => 0,
            'lti_type_id'        => null,
            'regstate'           => 'none',
            'regerror'           => null,
            'timeregistered'     => null,
            'timefetched'        => time(),
        ];

        foreach ($defaults as $field => $value) {
            if (!isset($record->$field)) {
                $record->$field = $value;
            }
        }

        $record->id = $DB->insert_record('local_ltifed_catalog_cache', $record);
        return $record;
    }
}
