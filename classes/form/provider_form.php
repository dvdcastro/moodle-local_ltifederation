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
 * Provider connection form for local_ltifederation.
 *
 * @package     local_ltifederation
 * @copyright   2026 David Castro
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ltifederation\form;

require_once($GLOBALS['CFG']->libdir . '/formslib.php');

/**
 * Form for adding/editing an LTI provider connection.
 */
class provider_form extends \moodleform {
    /**
     * Define the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        // Hidden ID for edit mode.
        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        // Label.
        $mform->addElement('text', 'label', get_string('label', 'local_ltifederation'), ['size' => '60']);
        $mform->setType('label', PARAM_TEXT);
        $mform->addRule('label', null, 'required', null, 'client');
        $mform->addHelpButton('label', 'label', 'local_ltifederation');

        // Provider URL.
        $mform->addElement('text', 'providerurl', get_string('providerurl', 'local_ltifederation'), ['size' => '80']);
        $mform->setType('providerurl', PARAM_URL);
        $mform->addRule('providerurl', null, 'required', null, 'client');
        $mform->addHelpButton('providerurl', 'providerurl', 'local_ltifederation');

        // Web service token.
        $mform->addElement('passwordunmask', 'wstoken', get_string('wstoken', 'local_ltifederation'), ['size' => '60']);
        $mform->setType('wstoken', PARAM_ALPHANUMEXT);
        $mform->addRule('wstoken', null, 'required', null, 'client');
        $mform->addHelpButton('wstoken', 'wstoken', 'local_ltifederation');

        // Auto-sync.
        $mform->addElement('advcheckbox', 'autosync', get_string('autosync', 'local_ltifederation'));
        $mform->setType('autosync', PARAM_BOOL);
        $mform->setDefault('autosync', 0);
        $mform->addHelpButton('autosync', 'autosync', 'local_ltifederation');

        // Action buttons.
        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * Validate form data.
     *
     * @param array $data  Form data.
     * @param array $files Files.
     * @return array Validation errors.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Validate that providerurl is a valid URL.
        if (!empty($data['providerurl'])) {
            $parsed = parse_url($data['providerurl']);
            if (!$parsed || empty($parsed['scheme']) || empty($parsed['host'])) {
                $errors['providerurl'] = get_string('invalidurl', 'core');
            }
        }

        return $errors;
    }
}
