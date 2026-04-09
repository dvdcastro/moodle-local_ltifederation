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
 * Web service function and service definitions for local_ltifederation.
 *
 * @package     local_ltifederation
 * @copyright   2026 David Castro
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_ltifederation_get_tool_catalog' => [
        'classname'   => 'local_ltifederation\external\get_tool_catalog',
        'description' => 'Returns the LTI 1.3 tool catalog published by this Moodle site.',
        'type'        => 'read',
        'capabilities' => 'local/ltifederation:providecatalog',
        'loginrequired' => true,
        'ajax'        => false,
    ],
];

$services = [
    'LTI Federation Catalog' => [
        'functions'       => ['local_ltifederation_get_tool_catalog'],
        'restrictedusers' => 1,
        'enabled'         => 1,
        'shortname'       => 'ltifederationcatalog',
    ],
];
