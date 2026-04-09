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
 * Admin settings for local_ltifederation.
 *
 * @package     local_ltifederation
 * @copyright   2026 David Castro
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_ltifederation',
        get_string('pluginname', 'local_ltifederation')
    );

    // Role: provider, consumer, or both.
    $settings->add(new admin_setting_configselect(
        'local_ltifederation/role',
        get_string('setting_role', 'local_ltifederation'),
        get_string('setting_role_desc', 'local_ltifederation'),
        'both',
        [
            'provider' => get_string('role_provider', 'local_ltifederation'),
            'consumer' => get_string('role_consumer', 'local_ltifederation'),
            'both'     => get_string('role_both', 'local_ltifederation'),
        ]
    ));

    $ADMIN->add('localplugins', $settings);

    // Provider admin page: linked only when role includes provider.
    $role = get_config('local_ltifederation', 'role');
    if ($role === 'consumer' || $role === 'both' || $role === null) {
        $ADMIN->add('localplugins', new admin_externalpage(
            'local_ltifederation_providers',
            get_string('provider_connections', 'local_ltifederation'),
            new moodle_url('/local/ltifederation/admin/provider_connections.php'),
            'local/ltifederation:manageproviders'
        ));
    }
}
