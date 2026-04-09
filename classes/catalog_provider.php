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
 * Catalog provider class for local_ltifederation.
 *
 * Queries this site's enrol_lti tools and returns a catalog for remote consumers.
 *
 * @package     local_ltifederation
 * @copyright   2026 David Castro
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ltifederation;

defined('MOODLE_INTERNAL') || die();

use enrol_lti\local\ltiadvantage\repository\application_registration_repository;
use enrol_lti\local\ltiadvantage\repository\deployment_repository;
use enrol_lti\local\ltiadvantage\repository\resource_link_repository;
use enrol_lti\local\ltiadvantage\repository\context_repository;
use enrol_lti\local\ltiadvantage\repository\user_repository;
use enrol_lti\local\ltiadvantage\service\application_registration_service;

/**
 * Provides the LTI tool catalog for this Moodle site.
 */
class catalog_provider {

    /**
     * Get the catalog of published LTI 1.3 tools from this site.
     *
     * Queries enrol_lti_tools joined with enrol, course and context.
     * Only includes LTI-1p3 tools where the enrolment is active (status=0).
     * For each tool, generates a draft registration via enrol_lti's
     * application_registration_service and builds the dynamic registration URL.
     *
     * @return \stdClass[] Array of catalog entry objects.
     */
    public static function get_catalog(): array {
        global $DB, $CFG, $OUTPUT;

        $sql = "SELECT elt.id,
                       elt.uuid,
                       elt.ltiversion,
                       elt.timecreated,
                       elt.timemodified,
                       e.name,
                       e.courseid,
                       e.status AS enrolstatus,
                       c.fullname AS coursefullname,
                       c.summary AS coursesummary
                  FROM {enrol_lti_tools} elt
                  JOIN {enrol} e ON elt.enrolid = e.id
                  JOIN {course} c ON e.courseid = c.id
                 WHERE elt.ltiversion = :ltiversion
                   AND e.status = :status
              ORDER BY elt.timecreated ASC";

        $params = [
            'ltiversion' => 'LTI-1p3',
            'status'     => 0,
        ];

        $rows = $DB->get_records_sql($sql, $params);

        $appregservice = new application_registration_service(
            new application_registration_repository(),
            new deployment_repository(),
            new resource_link_repository(),
            new context_repository(),
            new user_repository()
        );

        $catalog = [];

        foreach ($rows as $row) {
            // Generate a draft registration in enrol_lti_app_registration so the
            // registration URL (with its unique token) is valid for this provider.
            $dto = (object) [
                'name' => $row->name ?: $row->coursefullname,
            ];

            try {
                $draftreg = $appregservice->create_draft_application_registration($dto);
                $uniqueid = $draftreg->get_uniqueid();
            } catch (\Exception $e) {
                // If draft creation fails, skip this tool.
                debugging('ltifederation: could not create draft registration for tool ' . $row->id . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
                continue;
            }

            $registrationurl = new \moodle_url('/enrol/lti/register.php', ['token' => $uniqueid]);

            // Build the catalog entry.
            $entry = new \stdClass();
            $entry->id                  = (int) $row->id;
            $entry->uuid                = $row->uuid ?? '';
            $entry->name                = $row->name ?: $row->coursefullname;
            $entry->description         = strip_tags($row->coursesummary ?? '');
            $entry->coursefullname      = $row->coursefullname;
            $entry->ltiversion          = $row->ltiversion;
            $entry->registration_url    = $registrationurl->out(false);
            $entry->registration_token  = $uniqueid;
            $entry->logo_url            = $OUTPUT->get_compact_logo_url()
                                            ? $OUTPUT->get_compact_logo_url()->out(false)
                                            : '';
            $entry->remotestatus        = 0; // Active on this provider.
            $entry->timecreated         = (int) $row->timecreated;
            $entry->timemodified        = (int) $row->timemodified;

            $catalog[] = $entry;
        }

        return $catalog;
    }
}
