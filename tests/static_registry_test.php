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
 * Static registry tests.
 *
 * @package    filter_shortcodes
 * @copyright  2018 Frédéric Massart
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use filter_shortcodes\local\registry\static_registry;

global $CFG;
require_once($CFG->dirroot . '/filter/shortcodes/lib/helpers.php');


/**
 * Static registry tests.
 *
 * @package    filter_shortcodes
 * @copyright  2018 Frédéric Massart
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filter_shortcodes_static_registry_testcase extends advanced_testcase {

    public function test_get_definitions() {
        $registry = new static_registry([
            filter_shortcodes_definition_from_data('abc', ['component' => 'core', 'callback' => 'intval']),
            filter_shortcodes_definition_from_data('def', ['component' => 'filter_shortcodes', 'callback' => 'strlen']),
            filter_shortcodes_definition_from_data('def', ['component' => 'core_course', 'callback' => 'next']),
            filter_shortcodes_definition_from_data('ghi', ['component' => 'core_course', 'callback' => 'next']),
        ]);
        $defs = iterator_to_array($registry->get_definitions(), false);

        $this->assertCount(4, $defs);
        $this->assertEquals('abc', $defs[0]->shortcode);
        $this->assertEquals('intval', $defs[0]->callback);
        $this->assertEquals('def', $defs[1]->shortcode);
        $this->assertEquals('strlen', $defs[1]->callback);
        $this->assertEquals('def', $defs[2]->shortcode);
        $this->assertEquals('next', $defs[2]->callback);
        $this->assertEquals('ghi', $defs[3]->shortcode);
        $this->assertEquals('next', $defs[3]->callback);
    }

    public function test_get_handler() {
        $noop = function($text) {
            return $text;
        };
        $registry = new static_registry([
            filter_shortcodes_definition_from_data('abc', ['component' => 'core',
                'callback' => 'filter_shortcodes_fixture_return_two', 'wraps' => true]),
            filter_shortcodes_definition_from_data('def', ['component' => 'filter_shortcodes',
                'callback' => 'filter_shortcodes_fixture_return_two']),
            filter_shortcodes_definition_from_data('def', ['component' => 'core_course',
                'callback' => 'filter_shortcodes_fixture_return_one', 'wraps' => true]),
            filter_shortcodes_definition_from_data('ghi', ['component' => 'core_course',
                'callback' => 'filter_shortcodes_fixture_return_one']),
        ]);

        // Not handled.
        $handler = $registry->get_handler('notthere');
        $this->assertNull($handler);

        // Typical response.
        $handler = $registry->get_handler('abc');
        $processor = $handler->processor;
        $this->assertTrue($handler->wraps);
        $this->assertEquals('two', $processor('abc', [], null, (object) [], $noop));

        // When duplicates we pick the first.
        $handler = $registry->get_handler('def');
        $processor = $handler->processor;
        $this->assertFalse($handler->wraps);
        $this->assertEquals('two', $processor('def', [], null, (object) [], $noop));

        // Second call returns the same instance.
        $this->assertSame($handler, $registry->get_handler('def'));

        // Yet another one.
        $handler = $registry->get_handler('ghi');
        $processor = $handler->processor;
        $this->assertFalse($handler->wraps);
        $this->assertEquals('one', $processor('ghi', [], null, (object) [], $noop));
    }

}

/**
 * Fixture function always returning one.
 *
 * @return string
 */
function filter_shortcodes_fixture_return_one() {
    return 'one';
}

/**
 * Fixture function always returning two.
 *
 * @return string
 */
function filter_shortcodes_fixture_return_two() {
    return 'two';
}
