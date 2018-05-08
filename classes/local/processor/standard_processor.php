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
 * Standard processor.
 *
 * @package    filter_shortcodes
 * @copyright  2018 Frédéric Massart
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace filter_shortcodes\local\processor;
defined('MOODLE_INTERNAL') || die();

use core_text;
use stdClass;
use filter_shortcodes\local\registry\registry;

require_once($CFG->dirroot . '/filter/shortcodes/lib/helpers.php');

/**
 * Standard processor class.
 *
 * @package    filter_shortcodes
 * @copyright  2018 Frédéric Massart
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class standard_processor implements processor {

    /** @var env The environment. */
    protected $env;
    /** @var registry The registry. */
    protected $registry;

    /**
     * Constructor
     *
     * @param registry $registry The registry.
     */
    public function __construct(registry $registry) {
        $this->registry = $registry;
    }

    /**
     * Internal processing.
     *
     * @param string $text The text.
     * @param Closure $next The function to pipe the resulting content through, if needed.
     * @return string
     */
    protected function internal_process($text, $next) {
        return filter_shortcodes_process_text($text, function($shortcode) use ($next) {
            $handler = $this->registry->get_handler($shortcode);
            if (!$handler) {
                return;
            }
            $processor = $handler->processor;
            return (object) [
                'hascontent' => $handler->wraps,
                'contentprocessor' => function($args, $content) use ($processor, $shortcode, $next) {
                    // We decorate the handler method to pass through the other needed arguments.
                    return $processor($shortcode, $args, $content, $this->env, $next);
                }
            ];
        });
    }

    /**
     * The filtering occurs here.
     *
     * @param string $text The content to process.
     * @return string The resulting text.
     */
    public function process($text) {
        if ($this->env === null) {
            throw new coding_exception('The environment must be set between process calls.');
        }

        $env = $this->env;
        $result = $this->internal_process($text, function($text) use ($env) {
            $result = $this->process($text);
            $this->set_env($env);
            return $result;
        });

        $this->env = null;
        return $result;
    }

    /**
     * Set the environment.
     *
     * @param stdClass $env The environment, must conform to filter_shortcodes_make_env.
     */
    public function set_env(stdClass $env) {
        $this->env = $env;
    }

}
