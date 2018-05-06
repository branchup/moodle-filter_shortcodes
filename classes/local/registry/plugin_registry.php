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
 * Plugin registry.
 *
 * @package    filter_shortcodes
 * @copyright  2018 Frédéric Massart
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace filter_shortcodes\local\registry;
defined('MOODLE_INTERNAL') || die();

use cache;
use core_component;

require_once($CFG->dirroot . '/filter/shortcodes/lib/helpers.php');

/**
 * Plugin registry class.
 *
 * This browses Moodle plugins to find shortcodes and caches the result.
 *
 * @package    filter_shortcodes
 * @copyright  2018 Frédéric Massart
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plugin_registry implements registry {

    /** @var cache The cache to browse for definitions. */
    protected $cache;
    /** @var registry The static registry. */
    protected $registry;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->cache = cache::make('filter_shortcodes', 'handlers');
    }

    /**
     * Get the definitions.
     *
     * @return \Iterator
     */
    public function get_definitions() {
        $this->init();
        return $this->registry->get_definitions();
    }

    /**
     * Get a handler.
     *
     * @param string $shortcode The shortcode.
     * @return object|null
     */
    public function get_handler($shortcode) {
        $this->init();
        return $this->registry->get_handler($shortcode);
    }

    /**
     * Fetch all the definitions.
     *
     * @return array
     */
    protected function fetch_definitions() {
        $saferead = function($file){
            $shortcodes = [];
            include($file);
            return $shortcodes;
        };

        $pluginman = \core_plugin_manager::instance();
        $stringman = get_string_manager();
        $definitions = [];

        $types = core_component::get_plugin_types();
        foreach ($types as $plugintype => $typedir) {

            $plugins = core_component::get_plugin_list($plugintype);
            foreach ($plugins as $name => $rootdir) {
                $component = $plugintype . '_' . $name;
                $info = $pluginman->get_plugin_info($component);

                // Skip unfound or disabled plugins. Note that the plugin manager can return null when
                // the status is unknown. In that case we keep the plugin (e.g. local plugins).
                if (!$info || $info->is_enabled() === false) {
                    continue;
                }

                // Is the file there? I wish we could use core_component::get_plugin_list_with_file().
                // But we cannot because only a few files are mapped, and ours isn't.
                $file = $rootdir . '/db/shortcodes.php';
                if (!file_exists($file)) {
                    continue;
                }

                $shortcodes = $saferead($file);
                foreach ($shortcodes as $shortcode => $data) {
                    $data['component'] = $component;
                    $definitions[] = filter_shortcodes_definition_from_data($shortcode, $data);
                }
            }
        }

        return $definitions;
    }

    /**
     * Load the things if need be.
     *
     * @return void
     */
    protected function init() {
        if ($this->registry === null) {
            $definitions = $this->cache->get('definitions');
            if ($definitions === false) {
                $definitions = $this->fetch_definitions();
                $this->cache->set('definitions', $definitions);
            }
            $this->registry = new static_registry($definitions);
        }
    }

}
