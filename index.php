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
 * List the available shortcodes.
 *
 * @package    filter_shortcodes
 * @copyright  2018 Frédéric Massart
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->libdir . '/tablelib.php');

$contextid = optional_param('contextid', SYSCONTEXTID, PARAM_INT);
$context = context::instance_by_id($contextid);

$url = new moodle_url('/filter/shortcodes/index.php', ['contextid' => $contextid]);

require_login();
require_capability('filter/shortcodes:viewlist', $context);

$title = get_string('shortcodeslist', 'filter_shortcodes');

$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('popup');
$PAGE->set_title($title);
$PAGE->set_heading($title);

echo $OUTPUT->header();

$table = new flexible_table('filter_shortcodes');
$table->define_baseurl($url);
$table->define_columns([
    'shortcode',
    'description',
    'component',
]);
$table->define_headers([
    get_string('shortcode', 'filter_shortcodes'),
    get_string('description', 'filter_shortcodes'),
    get_string('plugin', 'core'),
]);
$table->setup();

$stringman = get_string_manager();
$registry = new filter_shortcodes\local\registry\plugin_registry();

$PAGE->requires->string_for_js('more', 'filter_shortcodes');
$PAGE->requires->string_for_js('less', 'filter_shortcodes');
$PAGE->requires->js_amd_inline(<<<EOT
    require(['jquery'], function($) {
        $('body').on('click', '.shortcode-show-more', function(e) {
            e.preventDefault();
            var node = $(e.target);
            var expanded = node.attr('aria-expanded');
            var showmore = !expanded || expanded == 'false';
            var target = node.attr('aria-controls');
            if (showmore) {
                console.log($(target));
                $(target).show();
                node.text(M.util.get_string('less', 'filter_shortcodes'));
                node.attr('aria-expanded', true);
            } else {
                $(target).hide();
                node.text(M.util.get_string('more', 'filter_shortcodes'));
                node.attr('aria-expanded', false);
            }
        });
    });
EOT
);

foreach ($registry->get_definitions() as $def) {
    $description = '';
    if ($def->description) {
        $description = get_string($def->description, $def->component);
        if ($stringman->string_exists($def->description . '_help', $def->component)) {
            $id = uniqid();
            $help = markdown_to_html(get_string($def->description . '_help', $def->component));
            $description = html_writer::div(
                $description .
                ' ' .
                html_writer::tag('a', get_string('more', 'filter_shortcodes'), [
                    'href' => '#',
                    'class' => 'shortcode-show-more',
                    'aria-expanded' => 'false',
                    'aria-controls' => "#{$id}"
                ]) .
                html_writer::div($help, '', [
                    'id' => $id,
                    'style' => 'display: none; margin-top: 1em;',
                ])
            );
        }
    }
    $table->add_data([
        $def->shortcode,
        $description,
        $def->component
    ]);
}

$table->finish_output();

echo $OUTPUT->footer();
