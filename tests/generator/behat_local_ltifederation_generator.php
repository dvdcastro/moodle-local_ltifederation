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

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

/**
 * Behat data generator for local_ltifederation.
 *
 * @package     local_ltifederation
 * @category    test
 * @copyright   2026 David Castro
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_ltifederation_generator extends behat_generator_base {
    /**
     * Returns the list of entities that can be created via Behat data generators.
     *
     * @return array
     */
    protected function get_creatable_entities(): array {
        return [
            'providers' => [
                'datagenerator' => 'providers',
                'required'      => ['label'],
            ],
            'catalog_entries' => [
                'datagenerator' => 'catalog_entries',
                'required'      => ['providerid', 'name'],
            ],
        ];
    }

    /**
     * Pre-process catalog_entries data: resolve providerid from label if needed.
     *
     * @param array $elementdata The element data.
     * @return array The updated element data.
     */
    protected function preprocess_catalog_entries(array $elementdata): array {
        global $DB;

        if (isset($elementdata['providerid']) && !is_numeric($elementdata['providerid'])) {
            $provider = $DB->get_record(
                'local_ltifed_providers',
                ['label' => $elementdata['providerid']],
                '*',
                MUST_EXIST
            );
            $elementdata['providerid'] = $provider->id;
        }

        return $elementdata;
    }
}
