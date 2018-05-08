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
 * Helpers.
 *
 * I'd like to use namespaced functions, but that requires PHP 5.6 and we support
 * Moodle 3.1 which is compatible with PHP 5.4.
 *
 * @package    filter_shortcodes
 * @copyright  2018 Frédéric Massart
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Create a definition from data.
 *
 * This defines what a definition looks like, and what keys are expected to be given as argument.
 *
 * @param string $shortcode The shortcode.
 * @param array $data The data.
 * @return object
 */
function filter_shortcodes_definition_from_data($shortcode, array $data) {
    global $CFG;

    if ($CFG->debugdeveloper) {
        validate_param($shortcode, PARAM_ALPHANUM);

        if (!isset($data['callback']) || !is_callable($data['callback'])) {
            throw new coding_exception("The callback for shortcode '{$shortcode}' is invalid.");
        }

        if (!isset($data['component'])) {
            throw new coding_exception("A shortcode must belong to a component.");
        }

        if (isset($data['description'])) {
            $stringman = get_string_manager();
            if (!$stringman->string_exists($data['description'], $data['component'])) {
                debugging("The definition string for shortcode '{$shortcode}' is invalid.", DEBUG_DEVELOPER);
                $data['description'] = null;
            }
        }
    }

    return (object) [
        'shortcode' => $shortcode,
        'callback' => $data['callback'],
        'component' => $data['component'],
        'description' => isset($data['description']) ? $data['description'] : null,
        'wraps' => isset($data['wraps']) ? (bool) $data['wraps'] : false,
    ];
}

/**
 * Create a handler from a definition.
 *
 * A handler contains information defining how to handle the processing
 * from a definition, it is agnostic of the type of everything else.
 *
 * The object returned contains the key `processor` and `wraps`. The latter
 * determines whether the shortcode is expected to look for a closing tag or
 * not. While the `processor` is a function that pipes the details to the
 * callback in the definition.
 *
 * The processor receives, and forwards:
 *
 * string $shortcode The shortcode operated on, so the callback does not have to be unique per shortcode.
 * array $args The arguments found with the shortcode.
 * string|null $content The content in the shortcode, when it wraps.
 * object $env Environment variables related to the filter.
 * Closure $next The function to pass the content through to process inner shortcodes.
 *
 * It must return the new content.
 *
 * @param stdClass $definition The definition.
 * @return object
 */
function filter_shortcodes_handler_from_definition(stdClass $definition) {
    $callback = $definition->callback;
    return (object) [
        'wraps' => $definition->wraps,
        'processor' => function($shortcode, $args, $content, $env, $next) use ($callback) {
            return call_user_func($callback, $shortcode, $args, $content, $env, $next);
        }
    ];
}

/**
 * Create the processing env
 *
 * @param context $context The content.
 * @param array $options The filter options.
 * @return object
 */
function filter_shortcodes_make_env(context $context, array $options = []) {
    return (object) array_merge([
        'context' => $context,
        'noclean' => false,
        'originalformat' => FORMAT_PLAIN
    ], $options);
}

/**
 * Parse a string of attributes.
 *
 * Attributes and values can contain any character.
 * Spaces around the equal sign are not allowed.
 * Attributes without value are true.
 * To include spaces and special characters in attributes, double quotes must be used.
 *
 * @param string $text The string to parse.
 * @return array
 */
function filter_shortcodes_parse_attributes($text) {
    $attrs = [];

    $pos = 0;
    $end = core_text::strlen($text);

    $inkey = true;
    $inquote = false;

    $key = '';
    $value = '';

    do {
        $char = core_text::substr($text, $pos, 1);
        if (!$inquote) {
            if ($char == ' ') {
                if ($key != '') {
                    $attrs[$key] = $value != '' ? $value : true;
                }
                $key = $value = '';

                $inkey = true;
                $pos++;
                continue;

            } else if ($char == '=' && $inkey && $key != '') {
                $inkey = false;
                $pos++;
                continue;

            } else if ($char == '"') {
                $inquote = true;
                $pos++;
                continue;
            }

        } else {
            if ($char == '"' && $pos && core_text::substr($text, $pos - 1, 1) != '\\') {
                // Detect when we reached the end of a quoted text.
                $inquote = false;
                $pos++;
                continue;

            } else if ($char == '\\' && core_text::substr($text, $pos, 2) == '\\"') {
                // When the quote is being escaped, remove the escaping character.
                $char = '"';
                $pos++;
            }
        }

        // Append the character..
        if ($inkey) {
            $key .= $char;
        } else {
            $value .= $char;
        }

        $pos++;
    } while ($pos < $end);

    // Final push.
    if ($key != '') {
        $attrs[$key] = $value != '' ? $value : true;
    }

    return $attrs;
}

/**
 * Process a text.
 *
 * @param string $text The text to parse and replace shortcodes in.
 * @param callable $informant Function returning information about the tag and how to handle it.
 * @return string
 */
function filter_shortcodes_process_text($text, callable $informant) {
    $charregex = '/^[a-z0-9\[]$/';
    $pos = 0;
    $end = null;

    while (($firstfind = core_text::strpos($text, '[', $pos)) !== false) {
        $lastcloseself = core_text::strrpos($text, ']', $firstfind);

        // The tag cannot be closed.
        if ($lastcloseself === false) {
            return $text;
        }

        $start = $firstfind;
        $end = $end === null ? core_text::strlen($text) : $end;
        $pos = $firstfind + 1;

        // Find out what the tag is.
        $tag = '';
        do {
            $char = core_text::substr($text, $pos, 1);
            if (!preg_match($charregex, $char)) {
                break;
            }
            $tag .= $char;
            $pos++;
        } while ($pos < $end);

        // We have a tag and can we handle it?
        if ($tag && is_object($info = $informant($tag))) {

            if ($lastcloseself < $pos) {
                // The tag does not have an end.
                continue;
            }

            $tagclosed = false;
            $attrs = '';
            $inquote = false;

            do {
                $char = core_text::substr($text, $pos, 1);

                // Detect when we are in quotes, in order to avoid closing the tag too early.
                if ($char == '"') {
                    if ($inquote) {
                        $inquote = core_text::substr($text, $pos - 1, 1) !== '\\' ? false : true;
                    } else {
                        $inquote = true;
                    }
                }

                // We found the end \o/.
                if (!$inquote && $char == ']') {
                    $tagclosed = true;
                    $pos++;
                    break;
                }

                $attrs .= $char;
                $pos++;

            } while ($pos <= $lastcloseself); // Stop when we know there is no remaining closing tag.

            // The tag was never closed and we reached the end, leave.
            if (!$tagclosed) {
                return $text;
            }

            // Find the content.
            $content = null;
            if ($info->hascontent) {
                $closingpos = core_text::strpos($text, "[/$tag]", $pos);
                if ($closingpos === false) {
                    // The tag is never closed, we ignore the tag and resume browsing from here.
                    continue;
                }
                $content = core_text::substr($text, $pos, $closingpos - $pos);
                $pos = $closingpos + core_text::strlen("[/$tag]");
            }

            // Parse the filters, replace the text, and adjust the bounderies of our search.
            $attrs = filter_shortcodes_parse_attributes($attrs);
            $contentprocessor = $info->contentprocessor;
            $newcontent = $contentprocessor($attrs, $content);
            $newlength = core_text::strlen($newcontent);
            $text = core_text::substr($text, 0, $start) . $newcontent . core_text::substr($text, $pos);
            $end = $end + ($newlength - ($pos - $start));
            $pos = $start + $newlength;
        }
    }

    return $text;
}
