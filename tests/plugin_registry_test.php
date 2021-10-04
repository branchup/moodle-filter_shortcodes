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
 * Plugin registry tests.
 *
 * @package    filter_shortcodes
 * @copyright  2018 Frédéric Massart
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use filter_shortcodes\local\registry\plugin_registry;

global $CFG;

/**
 * Plugin registry tests.
 *
 * @package    filter_shortcodes
 * @copyright  2018 Frédéric Massart
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filter_shortcodes_plugin_registry_testcase extends advanced_testcase {

    public function test_get_definitions() {
        $this->resetAfterTest();
        filter_set_global_state('shortcodes', TEXTFILTER_ON);

        $registry = new plugin_registry();
        $defs = iterator_to_array($registry->get_definitions(), false);

        // We do not know about the other plugins that may be installed on the system, so
        // let's just check that we find our own shortcodes.
        $this->assertTrue(count($defs) >= 1);
        $this->assertNotEmpty(array_filter($defs, function($def) {
            return $def->shortcode == 'off' && $def->component == 'filter_shortcodes';
        }));
    }

    public function test_get_handler() {
        $this->resetAfterTest();
        filter_set_global_state('shortcodes', TEXTFILTER_ON);

        $registry = new plugin_registry();
        $handler = $registry->get_handler('off');
        $this->assertTrue($handler->wraps);

        $noop = function($text) {
            return $text;
        };
        $env = filter_shortcodes_make_env(context_system::instance());
        $processor = $handler->processor;
        $content = 'is [not] processed';
        $this->assertEquals($content, $processor('off', [], $content, $env, $noop));
    }

}
