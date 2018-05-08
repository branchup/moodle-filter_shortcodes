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
 * Lib helpers tests.
 *
 * @package    filter_shortcodes
 * @copyright  2018 Frédéric Massart
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/filter/shortcodes/lib/helpers.php');

/**
 * Lib helpers tests.
 *
 * @package    filter_shortcodes
 * @copyright  2018 Frédéric Massart
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filter_shortcodes_lib_helpers_testcase extends advanced_testcase {

    /**
     * Parse attributes data provider.
     *
     * @return array
     */
    public function parse_attributes_provider() {
        return [
            [
                '',
                []
            ],
            [
                'a=1',
                ['a' => '1']
            ],
            [
                'myVar=myValue',
                ['myVar' => 'myValue']
            ],
            [
                'myVar=myValue andThis=that',
                ['myVar' => 'myValue', 'andThis' => 'that']
            ],
            [
                'myVar="myValue" "and This"=that',
                ['myVar' => 'myValue', 'and This' => 'that']
            ],
            [
                '   there="are"      spaces="in everything "       ',
                ['there' => 'are', 'spaces' => 'in everything ']
            ],
            [
                'noValueIsTrue and 1 23 too',
                ['noValueIsTrue' => true, 'and' => true, '1' => true, '23' => true, 'too' => true]
            ],
            [
                'name="Kučerová Matěj" t€xt="I love \"apples\", do you?"',
                ['name' => 'Kučerová Matěj', 't€xt' => 'I love "apples", do you?']
            ],
            [
                '123=456 \/;#@a="We accept too much?"',
                ['123' => '456', '\/;#@a' => 'We accept too much?']
            ],
            [
                ' 😀=🏁 🤔',
                ['😀' => '🏁', '🤔' => true]
            ],
            [
                'thisIsNotClosed="So, where does it stop   ',
                ['thisIsNotClosed' => 'So, where does it stop   ']
            ],
            [
                'id=2 uid="1234-5678" disabled "Need \"spaces\"?" "Oh my"=w\'or\'d!',
                [
                    'id' => '2',
                    'uid' => '1234-5678',
                    'disabled' => true,
                    'Need "spaces"?' => true,
                    'Oh my' => "w'or'd!"
                ]
            ],
        ];
    }

    /**
     * Test parse attributes.
     *
     * @dataProvider parse_attributes_provider
     * @param string $attributes The attributes to parse.
     * @param array $expected The expected result.
     */
    public function test_parse_attributes($attributes, $expected) {
        $this->assertEquals($expected, filter_shortcodes_parse_attributes($attributes));
    }

    /**
     * Parse attributes data provider.
     *
     * @return array
     */
    public function process_text_provider() {
        $noop = function() {
        };
        $informantsingle = (object) [
            'hascontent' => false,
            'contentprocessor' => function($attrs, $content) {
                return isset($attrs['text']) ? $attrs['text'] : 'banana';
            }
        ];
        $informantcontent = (object) [
            'hascontent' => true,
            'contentprocessor' => function($attrs, $content) {
                return strtoupper($content);
            }
        ];
        $informantmaker = function($nextinformant) {
            return (object) [
                'hascontent' => true,
                'contentprocessor' => function($attrs, $content) use ($nextinformant) {
                    return strtoupper(filter_shortcodes_process_text($content, $nextinformant));
                }
            ];
        };
        return [
            [
                'Lorem ipsum dolor sit amet, consectetur adipisicing elit.',
                $noop,
                'Lorem ipsum dolor sit amet, consectetur adipisicing elit.',
            ],
            [
                'Lorem ipsum [dolor sit amet, consectetur adipisicing elit.',
                $noop,
                'Lorem ipsum [dolor sit amet, consectetur adipisicing elit.',
            ],
            [
                'Lorem ipsum dolor sit amet, consectetur] adipisicing elit.',
                $noop,
                'Lorem ipsum dolor sit amet, consectetur] adipisicing elit.',
            ],
            [
                'Lorem ipsum] dolor sit [amet, consectetur adipisicing elit.',
                $noop,
                'Lorem ipsum] dolor sit [amet, consectetur adipisicing elit.',
            ],
            [
                'Lorem ipsum dolor sit [amet], consectetur adipisicing elit.',
                $noop,
                'Lorem ipsum dolor sit [amet], consectetur adipisicing elit.',
            ],
            [
                'Lorem ipsum [dolor] sit [/amet], consectetur adipisicing elit.',
                $noop,
                'Lorem ipsum [dolor] sit [/amet], consectetur adipisicing elit.',
            ],
            [
                'Lorem ipsum [dolor] sit amet, [a]consectetur adipisicing[/a] elit.',
                function($tag) use ($informantsingle, $informantcontent) {
                    if ($tag == 'dolor') {
                        return $informantsingle;
                    } else if ($tag == 'a') {
                        return $informantcontent;
                    }
                },
                'Lorem ipsum banana sit amet, CONSECTETUR ADIPISICING elit.',
            ],
            [
                'Lorem ipsum [dolor text="abc"] sit amet, consectetur adipisicing elit.',
                function($tag) use ($informantsingle, $informantcontent) {
                    return $informantsingle;
                },
                'Lorem ipsum abc sit amet, consectetur adipisicing elit.',
            ],
            [
                'Lorem ipsum [dolor param="contains ] <-- this and \"this\""] sit amet, consectetur adipisicing elit.',
                function($tag) use ($informantsingle, $informantcontent) {
                    return $informantsingle;
                },
                'Lorem ipsum banana sit amet, consectetur adipisicing elit.',
            ],
            [
                'Lorem ipsum [dolor text="abc"] sit amet[/dolor], consectetur adipisicing elit.',
                function($tag) use ($informantsingle, $informantcontent) {
                    return $informantsingle;
                },
                'Lorem ipsum abc sit amet[/dolor], consectetur adipisicing elit.',
            ],
            [
                'Lorem ipsum [dolor text="abc"] sit amet[/dolor], consectetur adipisicing elit.',
                function($tag) use ($informantsingle, $informantcontent) {
                    return $informantcontent;
                },
                'Lorem ipsum  SIT AMET, consectetur adipisicing elit.',
            ],
            [
                'Lorem ipsum [dolor text="abc"] sit amet[dolor], consectetur adipisicing[/dolor] elit.',
                function($tag) use ($informantsingle, $informantcontent) {
                    return $informantcontent;
                },
                'Lorem ipsum  SIT AMET[DOLOR], CONSECTETUR ADIPISICING elit.',
            ],
            [
                'Lorem [a] ipsum [dolor text="abc"] sit amet[a], consectetur adipisicing elit.',
                function($tag) use ($informantsingle, $informantcontent) {
                    return $tag == 'a' ? $informantsingle : $informantcontent;
                },
                'Lorem banana ipsum [dolor text="abc"] sit ametbanana, consectetur adipisicing elit.',
            ],
            [
                '[dolor text="Lorem "][upperme][decorate]banana[/decorate][dolor text=" ipsum"][/upperme] ' .
                    'dolor [a][dolor text=" sit amet"]',
                function($tag) use ($informantsingle, $informantcontent, $informantmaker) {
                    if ($tag == 'upperme') {
                        return $informantmaker(function($tag) use ($informantsingle) {
                            if ($tag == 'decorate') {
                                return (object) [
                                    'hascontent' => true,
                                    'contentprocessor' => function($args, $content) {
                                        return '@' . $content . '@';
                                    }
                                ];
                            }
                            return $informantsingle;
                        });
                    }
                    return $informantsingle;
                },
                'Lorem @BANANA@ IPSUM dolor banana sit amet',
            ],
        ];
    }

    /**
     * Test process text.
     *
     * @dataProvider process_text_provider
     * @param string $text The text to parse.
     * @param Closure $informant The informant function.
     * @param string $expected The expected result.
     */
    public function test_process_text($text, $informant, $expected) {
        $this->assertEquals($expected, filter_shortcodes_process_text($text, $informant));
    }

    /**
     * Test definition maker.
     *
     * @expectedException \invalid_parameter_exception
     */
    public function test_filter_shortcodes_definition_from_data_invalid_code() {
        filter_shortcodes_definition_from_data('abc:d', []);
    }

    /**
     * Test definition maker.
     *
     * @expectedException \invalid_parameter_exception
     */
    public function test_filter_shortcodes_definition_from_data_invalid_code_too() {
        filter_shortcodes_definition_from_data('ab_c', []);
    }

    /**
     * Test definition maker.
     *
     * @expectedException \coding_exception
     */
    public function test_filter_shortcodes_definition_from_data_invalid_callback() {
        filter_shortcodes_definition_from_data('abc', ['callback' => 'donot::exist']);
    }

    /**
     * Test definition maker.
     *
     * @expectedException \coding_exception
     */
    public function test_filter_shortcodes_definition_from_data_missing_component() {
        filter_shortcodes_definition_from_data('abc', ['callback' => 'intval']);
    }

    /**
     * Test definition maker.
     */
    public function test_filter_shortcodes_definition_from_data() {
        $result = filter_shortcodes_definition_from_data('abc', ['callback' => 'intval', 'component' => 'b']);
        $this->assertEquals('abc', $result->shortcode);
        $this->assertEquals('intval', $result->callback);
        $this->assertEquals('b', $result->component);
        $this->assertEquals(null, $result->description);

        $result = filter_shortcodes_definition_from_data('abc', ['callback' => 'intval', 'component' => 'core',
            'description' => 'adddots']);
        $this->assertEquals('abc', $result->shortcode);
        $this->assertEquals('intval', $result->callback);
        $this->assertEquals('core', $result->component);
        $this->assertEquals('adddots', $result->description);
    }
}
