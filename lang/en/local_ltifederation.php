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
 * Language strings for local_ltifederation.
 *
 * @package     local_ltifederation
 * @copyright   2026 David Castro
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin name.
$string['pluginname'] = 'LTI Federation';

// Capabilities.
$string['ltifederation:providecatalog'] = 'Provide LTI tool catalog via web service';
$string['ltifederation:manageproviders'] = 'Manage LTI federation provider connections';

// Settings.
$string['setting_role'] = 'Site role';
$string['setting_role_desc'] = 'Select whether this site acts as a provider (exposes tools), consumer (subscribes to remote catalogs), or both.';
$string['role_provider'] = 'Provider (exposes tools)';
$string['role_consumer'] = 'Consumer (subscribes to remote catalogs)';
$string['role_both'] = 'Both (provider and consumer)';

// Admin navigation.
$string['provider_connections'] = 'LTI Provider connections';

// Provider connections page.
$string['provider_connections_heading'] = 'LTI Provider connections';
$string['provider_connections_desc'] = 'Manage connections to remote Moodle sites that publish LTI 1.3 tool catalogs.';
$string['add_provider'] = 'Add provider';
$string['no_providers'] = 'No providers configured. Click "Add provider" to add one.';
$string['provider_label'] = 'Label';
$string['provider_url'] = 'Provider URL';
$string['provider_autosync'] = 'Auto-sync';
$string['provider_lastsync'] = 'Last sync';
$string['provider_syncstatus'] = 'Status';
$string['provider_actions'] = 'Actions';
$string['provider_edit'] = 'Edit';
$string['provider_delete'] = 'Delete';
$string['provider_sync_now'] = 'Sync now';
$string['provider_view_catalog'] = 'View catalog';
$string['provider_delete_confirm'] = 'Are you sure you want to delete provider "{$a}"? This will also remove all cached tool catalog entries.';
$string['provider_saved'] = 'Provider saved successfully.';
$string['provider_deleted'] = 'Provider deleted.';
$string['provider_sync_queued'] = 'Catalog sync has been queued.';
$string['provider_sync_ok'] = 'Sync completed successfully.';
$string['provider_sync_error'] = 'Sync failed: {$a}';
$string['provider_never_synced'] = 'Never';
$string['status_ok'] = 'OK';
$string['status_error'] = 'Error';

// Provider form fields.
$string['label'] = 'Label';
$string['label_help'] = 'A human-readable name to identify this LTI provider (e.g. "Central Moodle").';
$string['label_help_link'] = 'label, local_ltifederation, label';
$string['providerurl'] = 'Provider URL';
$string['providerurl_help'] = 'The base URL of the remote Moodle site that publishes the LTI tool catalog (e.g. https://provider.example.com).';
$string['providerurl_help_link'] = 'providerurl, local_ltifederation, providerurl';
$string['wstoken'] = 'Web service token';
$string['wstoken_help'] = 'The web service token generated on the remote provider site with access to the ltifederationcatalog service.';
$string['wstoken_help_link'] = 'wstoken, local_ltifederation, wstoken';
$string['autosync'] = 'Auto-sync catalog';
$string['autosync_help'] = 'When enabled, this provider\'s catalog will be synced automatically via scheduled tasks.';
$string['autosync_help_link'] = 'autosync, local_ltifederation, autosync';

// Tool catalog page.
$string['tool_catalog_heading'] = 'Tool catalog: {$a}';
$string['tool_catalog_desc'] = 'LTI 1.3 tools available from this provider. Register tools to make them available locally.';
$string['tool_name'] = 'Tool name';
$string['tool_course'] = 'Course';
$string['tool_ltiversion'] = 'LTI version';
$string['tool_regstate'] = 'Registration state';
$string['tool_actions'] = 'Actions';
$string['tool_register'] = 'Register';
$string['tool_reregister'] = 'Re-register';
$string['tool_register_selected'] = 'Register selected';
$string['tool_select_all'] = 'Select all';
$string['no_tools'] = 'No tools found. Click "Sync now" to fetch the catalog from this provider.';
$string['regstate_none'] = 'Not registered';
$string['regstate_pending'] = 'Pending';
$string['regstate_registered'] = 'Registered';
$string['regstate_error'] = 'Error';
$string['tool_registered_ok'] = 'Tool registered successfully.';
$string['tool_registered_error'] = 'Registration failed: {$a}';
$string['tools_registered_count'] = '{$a} tool(s) registered successfully.';
$string['provider_info_label'] = 'Provider';
$string['provider_info_url'] = 'URL';
$string['provider_info_lastsync'] = 'Last sync';
$string['provider_info_status'] = 'Status';
$string['sync_now'] = 'Sync now';
$string['back_to_providers'] = 'Back to providers';

// Tool catalog column help.
$string['tool_name_help'] = 'The name of the LTI tool as published by the remote provider.';
$string['tool_name_help_link'] = 'tool_name, local_ltifederation, tool_name';
$string['tool_course_help'] = 'The course this tool belongs to on the remote provider site.';
$string['tool_course_help_link'] = 'tool_course, local_ltifederation, tool_course';
$string['tool_ltiversion_help'] = 'The LTI protocol version of this tool.';
$string['tool_ltiversion_help_link'] = 'tool_ltiversion, local_ltifederation, tool_ltiversion';
$string['tool_regstate_help'] = 'The current registration state of this tool on this site.';
$string['tool_regstate_help_link'] = 'tool_regstate, local_ltifederation, tool_regstate';

// Task.
$string['task_sync_tools'] = 'Sync LTI tool catalog';

// Errors.
$string['error_invalid_provider'] = 'Invalid provider ID.';
$string['error_ssrf_blocked'] = 'Registration URL host does not match provider URL host. Blocked for security.';
$string['error_already_registered'] = 'This tool is already registered.';
$string['error_no_items_selected'] = 'No tools selected.';
$string['error_provider_not_found'] = 'Provider not found.';
$string['error_ws_call_failed'] = 'Web service call failed: {$a}';
$string['error_invalid_ws_response'] = 'Invalid or unexpected response from provider web service.';
