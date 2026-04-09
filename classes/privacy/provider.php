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
 * Privacy API implementation for local_ltifederation.
 *
 * This plugin stores only admin-configured provider connections and a cache of
 * LTI tool catalog data fetched from remote providers.  No personal user data
 * is stored or transmitted on behalf of individual users.
 *
 * @package     local_ltifederation
 * @copyright   2026 David Castro
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ltifederation\privacy;

use core_privacy\local\metadata\collection;

/**
 * Null privacy provider — this plugin stores no personal user data.
 *
 * It stores:
 * - Provider connection configuration (URL, token) set by a site admin.
 * - A cache of tool metadata fetched from remote Moodle sites (no user data).
 *
 * Communication with external Moodle sites is performed on behalf of the
 * site admin, not individual users.
 */
class provider implements \core_privacy\local\metadata\provider {
    /**
     * Returns metadata about the data this plugin stores.
     *
     * @param  collection $collection  The initialised collection to add items to.
     * @return collection The collection with metadata items added.
     */
    public static function get_metadata(collection $collection): collection {

        // Provider connection table: stores admin-configured settings only.
        $collection->add_database_table(
            'local_ltifed_providers',
            [
                'label'        => 'privacy:metadata:local_ltifed_providers:label',
                'providerurl'  => 'privacy:metadata:local_ltifed_providers:providerurl',
                'wstoken'      => 'privacy:metadata:local_ltifed_providers:wstoken',
                'autosync'     => 'privacy:metadata:local_ltifed_providers:autosync',
                'lastsync'     => 'privacy:metadata:local_ltifed_providers:lastsync',
                'syncstatus'   => 'privacy:metadata:local_ltifed_providers:syncstatus',
                'syncmessage'  => 'privacy:metadata:local_ltifed_providers:syncmessage',
            ],
            'privacy:metadata:local_ltifed_providers'
        );

        // Tool catalog cache: stores tool metadata fetched from remote providers.
        $collection->add_database_table(
            'local_ltifed_catalog_cache',
            [
                'providerid'         => 'privacy:metadata:local_ltifed_catalog_cache:providerid',
                'remoteuuid'         => 'privacy:metadata:local_ltifed_catalog_cache:remoteuuid',
                'name'               => 'privacy:metadata:local_ltifed_catalog_cache:name',
                'description'        => 'privacy:metadata:local_ltifed_catalog_cache:description',
                'coursefullname'     => 'privacy:metadata:local_ltifed_catalog_cache:coursefullname',
                'ltiversion'         => 'privacy:metadata:local_ltifed_catalog_cache:ltiversion',
                'registration_url'   => 'privacy:metadata:local_ltifed_catalog_cache:registration_url',
                'registration_token' => 'privacy:metadata:local_ltifed_catalog_cache:registration_token',
                'regstate'           => 'privacy:metadata:local_ltifed_catalog_cache:regstate',
                'regerror'           => 'privacy:metadata:local_ltifed_catalog_cache:regerror',
            ],
            'privacy:metadata:local_ltifed_catalog_cache'
        );

        // External systems: this plugin communicates with remote Moodle sites on behalf of the site admin.
        $collection->add_external_location_link(
            'remote_moodle_provider',
            [
                'wstoken' => 'privacy:metadata:remote_moodle_provider:wstoken',
            ],
            'privacy:metadata:remote_moodle_provider'
        );

        return $collection;
    }
}
