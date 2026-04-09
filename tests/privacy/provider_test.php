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
 * Unit tests for local_ltifederation\privacy\provider.
 *
 * @package     local_ltifederation
 * @copyright   2026 David Castro
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_ltifederation\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\types\database_table;
use core_privacy\local\metadata\types\external_location;

/**
 * Test class for the privacy provider.
 *
 * @covers \local_ltifederation\privacy\provider
 */
class provider_test extends \advanced_testcase {
    /**
     * Setup: reset before each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Test that the provider class implements the correct interface.
     */
    public function test_provider_implements_metadata_provider_interface(): void {
        $this->assertInstanceOf(
            \core_privacy\local\metadata\provider::class,
            new provider()
        );
    }

    /**
     * Test that get_metadata() returns a collection instance.
     */
    public function test_get_metadata_returns_collection(): void {
        $collection = new collection('local_ltifederation');
        $result = provider::get_metadata($collection);
        $this->assertInstanceOf(collection::class, $result);
    }

    /**
     * Test that get_metadata() declares the local_ltifed_providers table.
     */
    public function test_metadata_includes_providers_table(): void {
        $collection = new collection('local_ltifederation');
        $result = provider::get_metadata($collection);

        $items = $result->get_collection();
        $tablenames = array_map(function ($item) {
            return $item->get_name();
        }, $items);

        $this->assertContains(
            'local_ltifed_providers',
            $tablenames,
            'Metadata should include the local_ltifed_providers database table.'
        );
    }

    /**
     * Test that get_metadata() declares the local_ltifed_catalog_cache table.
     */
    public function test_metadata_includes_catalog_cache_table(): void {
        $collection = new collection('local_ltifederation');
        $result = provider::get_metadata($collection);

        $items = $result->get_collection();
        $tablenames = array_map(function ($item) {
            return $item->get_name();
        }, $items);

        $this->assertContains(
            'local_ltifed_catalog_cache',
            $tablenames,
            'Metadata should include the local_ltifed_catalog_cache database table.'
        );
    }

    /**
     * Test that get_metadata() declares the external location for remote provider communication.
     */
    public function test_metadata_includes_external_location(): void {
        $collection = new collection('local_ltifederation');
        $result = provider::get_metadata($collection);

        $items = $result->get_collection();
        $hasexternal = false;
        foreach ($items as $item) {
            if ($item instanceof external_location) {
                $hasexternal = true;
                break;
            }
        }

        $this->assertTrue(
            $hasexternal,
            'Metadata should include an external_location entry for remote Moodle provider communication.'
        );
    }

    /**
     * Test that the metadata collection contains at least 3 items (2 DB tables + 1 external location).
     */
    public function test_metadata_collection_has_minimum_items(): void {
        $collection = new collection('local_ltifederation');
        $result = provider::get_metadata($collection);

        $this->assertGreaterThanOrEqual(
            3,
            count($result->get_collection()),
            'Metadata collection should have at least 3 items.'
        );
    }
}
