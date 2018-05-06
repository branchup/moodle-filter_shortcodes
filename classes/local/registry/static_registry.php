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
 * Static registry.
 *
 * @package    filter_shortcodes
 * @copyright  2018 Frédéric Massart
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace filter_shortcodes\local\registry;
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/filter/shortcodes/lib/helpers.php');

/**
 * Static registry class.
 *
 * @package    filter_shortcodes
 * @copyright  2018 Frédéric Massart
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class static_registry implements registry {

    /** @var array Static cache. */
    protected $cache = [];
    /** @var array The definitions arranged per shortcode, supports multiple definitions per shortcode. */
    protected $definitions;

    /**
     * Constructor.
     *
     * @param array $definitions The definition objects.
     */
    public function __construct(array $definitions) {
        $this->definitions = array_reduce($definitions, function($carry, $definition) {
            $tag = $definition->shortcode;
            if (!isset($carry[$tag])) {
                $carry[$tag] = [];
            }
            $carry[$tag][] = $definition;
            return $carry;
        }, []);
    }

    /**
     * Get the definitions.
     *
     * @return \Iterator
     */
    public function get_definitions() {
        return new \RecursiveIteratorIterator(
            new \RecursiveArrayIterator(
                $this->definitions,
                \RecursiveArrayIterator::CHILD_ARRAYS_ONLY
            )
        );
    }

    /**
     * Get a handler.
     *
     * @param string $shortcode The shortcode.
     * @return object|null
     */
    public function get_handler($shortcode) {
        if (!array_key_exists($shortcode, $this->cache)) {
            $handler = null;
            if (array_key_exists($shortcode, $this->definitions)) {
                $handler = filter_shortcodes_handler_from_definition(reset($this->definitions[$shortcode]));
            }
            $this->cache[$shortcode] = $handler;
        }
        return $this->cache[$shortcode];
    }

}
