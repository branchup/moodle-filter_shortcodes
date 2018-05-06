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
 * Filter file.
 *
 * @package    filter_shortcodes
 * @copyright  2018 Frédéric Massart
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use filter_shortcodes\local\processor\standard_processor;
use filter_shortcodes\local\registry\plugin_registry;

require_once($CFG->dirroot . '/filter/shortcodes/lib/helpers.php');

/**
 * Filter class.
 *
 * @package    filter_shortcodes
 * @copyright  2018 Frédéric Massart
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filter_shortcodes extends moodle_text_filter {

    /** @var processor The processor. */
    private $processor;

    /**
     * The filtering occurs here.
     *
     * @param string $text HTML content.
     * @param array $options Options passed to the filter.
     * @return string The new content.
     */
    public function filter($text, array $options = []) {
        $env = filter_shortcodes_make_env($this->context, $options);
        $processor = $this->get_processor();
        $processor->set_env($env);
        return $processor->process($text);
    }

    /**
     * Get the processor.
     *
     * @return standard_processor
     */
    private function get_processor() {
        if ($this->processor === null) {
            $this->processor = new standard_processor(new plugin_registry());
        }
        return $this->processor;
    }

}
