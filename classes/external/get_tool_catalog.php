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
 * External function: get_tool_catalog.
 *
 * @package     local_ltifederation
 * @copyright   2026 David Castro
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ltifederation\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_ltifederation\catalog_provider;

/**
 * External function to return the LTI 1.3 tool catalog.
 */
class get_tool_catalog extends external_api {

    /**
     * No parameters required.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Return the LTI tool catalog.
     *
     * @return array
     */
    public static function execute(): array {
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/ltifederation:providecatalog', $context);

        $catalog = catalog_provider::get_catalog();

        $result = [];
        foreach ($catalog as $entry) {
            $result[] = [
                'id'                 => (int) $entry->id,
                'uuid'               => (string) ($entry->uuid ?? ''),
                'name'               => (string) $entry->name,
                'description'        => (string) ($entry->description ?? ''),
                'coursefullname'     => (string) ($entry->coursefullname ?? ''),
                'ltiversion'         => (string) ($entry->ltiversion ?? ''),
                'registration_url'   => (string) ($entry->registration_url ?? ''),
                'registration_token' => (string) ($entry->registration_token ?? ''),
                'logo_url'           => (string) ($entry->logo_url ?? ''),
                'remotestatus'       => (int) ($entry->remotestatus ?? 0),
                'timecreated'        => (int) ($entry->timecreated ?? 0),
                'timemodified'       => (int) ($entry->timemodified ?? 0),
            ];
        }

        return $result;
    }

    /**
     * Describe the return structure.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'id'                 => new external_value(PARAM_INT,  'Tool ID on the provider'),
                'uuid'               => new external_value(PARAM_ALPHANUMEXT, 'Tool UUID'),
                'name'               => new external_value(PARAM_TEXT, 'Tool name'),
                'description'        => new external_value(PARAM_RAW,  'Tool description (HTML stripped)'),
                'coursefullname'     => new external_value(PARAM_TEXT, 'Full name of the course the tool belongs to'),
                'ltiversion'         => new external_value(PARAM_TEXT, 'LTI version string, e.g. LTI-1p3'),
                'registration_url'   => new external_value(PARAM_URL,  'Dynamic registration URL'),
                'registration_token' => new external_value(PARAM_ALPHANUMEXT, 'Unique registration token'),
                'logo_url'           => new external_value(PARAM_URL,  'Site compact logo URL', VALUE_OPTIONAL, ''),
                'remotestatus'       => new external_value(PARAM_INT,  '0=active, 1=removed'),
                'timecreated'        => new external_value(PARAM_INT,  'Unix timestamp when tool was created'),
                'timemodified'       => new external_value(PARAM_INT,  'Unix timestamp when tool was last modified'),
            ])
        );
    }
}
