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
 * Standard processor tests.
 *
 * @package    filter_shortcodes
 * @copyright  2018 Frédéric Massart
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace filter_shortcodes;
defined('MOODLE_INTERNAL') || die();

use context_system;
use core_text;
use filter_shortcodes\local\registry\static_registry;
use filter_shortcodes\local\processor\standard_processor;

global $CFG;
require_once($CFG->dirroot . '/filter/shortcodes/lib/helpers.php');

/**
 * Standard processor testcase
 *
 * @package    filter_shortcodes
 * @copyright  2018 Frédéric Massart
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class standard_processor_test extends \advanced_testcase {

    /**
     * Process.
     *
     * @covers \filter_shortcodes\local\processor\standard_processor::process
     */
    public function test_process(): void {
        $this->resetAfterTest();
        $dg = $this->getDataGenerator();
        $u1 = $dg->create_user(['firstname' => 'François', 'lastname' => 'O\'Brian']);
        $registry = new static_registry([
            filter_shortcodes_definition_from_data('decorate', ['component' => 'core_test',
                'callback' => 'filter_shortcodes\filter_shortcodes_standard_processor_decorate', 'wraps' => true, ]),
            filter_shortcodes_definition_from_data('fullname', ['component' => 'core_test',
                'callback' => 'filter_shortcodes\filter_shortcodes_standard_processor_fullname', ]),
            filter_shortcodes_definition_from_data('uppercase', ['component' => 'core_test',
                'callback' => 'filter_shortcodes\filter_shortcodes_standard_processor_uppercase', 'wraps' => true, ]),
        ]);
        $processor = new standard_processor($registry);
        $env = filter_shortcodes_make_env(context_system::instance());

        $this->setUser($u1);
        $processor->set_env($env);
        $content = "Hello [fullname], welcome to the [uppercase][decorate]best[/decorate] school[/uppercase] ever!";
        $expected = 'Hello François O\'Brian, welcome to the @BEST@ SCHOOL ever!';
        $this->assertEquals($expected, $processor->process($content));
    }

}

/**
 * Fixture processor.
 *
 * @param string $tag The tag.
 * @param array $args The arguments.
 * @param string $content The content.
 * @param object $env The env.
 * @param Closure $next The next function.
 * @return string
 */
function filter_shortcodes_standard_processor_decorate($tag, $args, $content, $env, $next) {
    $decorator = isset($args['decorator']) ? $args['decorator'] : '@';
    return $next("{$decorator}{$content}{$decorator}");
}

/**
 * Fixture processor.
 *
 * @param string $tag The tag.
 * @param array $args The arguments.
 * @param string $content The content.
 * @param object $env The env.
 * @param Closure $next The next function.
 * @return string
 */
function filter_shortcodes_standard_processor_uppercase($tag, $args, $content, $env, $next) {
    return core_text::strtoupper($next($content));
}

/**
 * Fixture processor.
 *
 * @param string $tag The tag.
 * @param array $args The arguments.
 * @param string $content The content.
 * @param object $env The env.
 * @param Closure $next The next function.
 * @return string
 */
function filter_shortcodes_standard_processor_fullname($tag, $args, $content, $env, $next) {
    global $USER;
    return fullname($USER);
}
