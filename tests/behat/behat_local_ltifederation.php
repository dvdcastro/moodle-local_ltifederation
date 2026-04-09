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
 * Custom Behat step definitions for local_ltifederation.
 *
 * @package     local_ltifederation
 * @category    test
 * @copyright   2026 David Castro
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, as Behat requires us to extend behat_base.

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;

/**
 * Custom Behat context class for LTI Federation plugin steps.
 */
class behat_local_ltifederation extends behat_base {
    /**
     * Navigate to the tool catalog page for a named provider.
     *
     * @When I navigate to the tool catalog for :providerlabel
     *
     * @param string $providerlabel  The label of the provider.
     */
    public function i_navigate_to_tool_catalog_for(string $providerlabel): void {
        global $DB;

        $provider = $DB->get_record('local_ltifed_providers', ['label' => $providerlabel]);
        if (!$provider) {
            throw new \RuntimeException("LTI provider with label '{$providerlabel}' not found in database.");
        }

        $url = new moodle_url('/local/ltifederation/admin/tool_catalog.php', ['providerid' => $provider->id]);
        $this->getSession()->visit($this->locate_path($url->out(false)));
        $this->wait_for_pending_js();
    }

    /**
     * Assert that a message appears OR another message appears (soft assertion for async operations).
     *
     * Useful for registration steps that may succeed or fail depending on environment.
     *
     * @Then I should see :first or I should see :second
     *
     * @param string $first   First expected string.
     * @param string $second  Second expected string.
     */
    public function i_should_see_or_i_should_see(string $first, string $second): void {
        $page = $this->getSession()->getPage()->getText();
        if (strpos($page, $first) === false && strpos($page, $second) === false) {
            throw new \Behat\Mink\Exception\ExpectationException(
                "Neither '{$first}' nor '{$second}' found on the page.",
                $this->getSession()
            );
        }
    }
}
