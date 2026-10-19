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
        'processor' => function ($shortcode, $args, $content, $env, $next) use ($callback) {
            return call_user_func($callback, $shortcode, $args, $content, $env, $next);
        },
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
        'originalformat' => FORMAT_PLAIN,
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
 * Callback output is HTML. Callbacks must escape any untrusted data they include; the parser does not escape
 * their output as a whole. Literal quotes are escaped when inserting output into an allowed HTML attribute.
 *
 * @param string $text The text to parse and replace shortcodes in.
 * @param callable $informant Function returning information about the tag and how to handle it.
 * @return string
 */
function filter_shortcodes_process_text($text, callable $informant) {
    // All syntax delimiters are ASCII. Byte offsets preserve UTF-8 content and allow scanning without copying it.
    $pos = 0;
    $htmlpos = 0;

    while (($firstfind = strpos($text, '[', $pos)) !== false) {
        $lastcloseself = strrpos($text, ']');

        // The tag cannot be closed.
        if ($lastcloseself === false) {
            return $text;
        }

        $start = $firstfind;
        $pos = $firstfind + 1;

        // Find out what the tag is.
        $taglength = strspn($text, 'abcdefghijklmnopqrstuvwxyz0123456789[', $pos);
        $tag = substr($text, $pos, $taglength);
        $pos += $taglength;

        // We have a tag and can we handle it?
        if ($tag && is_object($info = $informant($tag))) {
            if ($lastcloseself < $pos) {
                // The tag does not have an end.
                continue;
            }

            $tagclosed = false;
            $attrsstart = $pos;
            $inquote = false;

            do {
                $char = substr($text, $pos, 1);

                // Detect when we are in quotes, in order to avoid closing the tag too early.
                if ($char == '"') {
                    $inquote = !$inquote || $text[$pos - 1] === '\\';
                }

                // We found the end \o/.
                if (!$inquote && $char == ']') {
                    $tagclosed = true;
                    $pos++;
                    break;
                }

                $pos++;
            } while ($pos <= $lastcloseself); // Stop when we know there is no remaining closing tag.

            // The tag was never closed and we reached the end, leave.
            if (!$tagclosed) {
                return $text;
            }

            // Find the content.
            $openend = $pos;
            $content = null;
            if ($info->hascontent) {
                $closingpos = strpos($text, "[/$tag]", $pos);
                if ($closingpos === false) {
                    // The tag is never closed, we ignore the tag and resume browsing from here.
                    continue;
                }
                $content = substr($text, $pos, $closingpos - $pos);
                $pos = $closingpos + strlen("[/$tag]");
            }

            $scan = filter_shortcodes_scan_shortcode_context($text, $start, $openend, $pos, $htmlpos);
            $htmlpos = $scan['htmlpos'];
            if ($scan['skip']) {
                continue;
            }

            // Inside an HTML tag, only replace a complete shortcode in a quoted, allowed attribute value.
            $intag = $htmlpos < $start
                && strspn($text, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ/[', $htmlpos + 1, 1) === 1;
            if ($intag && !filter_shortcodes_is_in_text_attribute($text, $htmlpos, $start, $pos)) {
                continue;
            }

            // Parse the attributes and prepare the replacement.
            $attrs = filter_shortcodes_parse_attributes(substr($text, $attrsstart, $openend - $attrsstart - 1));
            $contentprocessor = $info->contentprocessor;
            $newcontent = (string) $contentprocessor($attrs, $content);
            if ($intag) {
                // Escape literal quotes without double-encoding entities already produced by the callback.
                $newcontent = strtr($newcontent, ['"' => '&quot;', "'" => '&#039;']);
            }
            $newlength = strlen($newcontent);

            // Apply the replacement and update the search offsets.
            $text = substr($text, 0, $start) . $newcontent . substr($text, $pos);
            $pos = $start + $newlength;
        }
    }

    return $text;
}

/**
 * Scan a shortcode's HTML context, returning scan state and detecting unsafe replacements.
 *
 * Resume from the last unfinished tag or excluded element to avoid rescanning earlier completed HTML. Replacements must leave
 * the text before the saved position unchanged. All offsets are byte offsets with exclusive ends.
 *
 * @param string $text The text containing the shortcode.
 * @param int $start The start of the shortcode.
 * @param int $openend The end of its opening marker.
 * @param int $end The end of the shortcode, including its closing marker when wrapping.
 * @param int $htmlpos The saved HTML scan position, initially zero and outside quotes and tags.
 * @return array The skip flag (true to leave the shortcode unchanged), htmlpos for the next scan.
 */
function filter_shortcodes_scan_shortcode_context($text, $start, $openend, $end, $htmlpos) {
    // Only allow angle brackets in opening markers before a digit, optionally preceded by '='.
    $fragmentpos = $start + strcspn($text, '<>', $start, $openend - $start);
    while ($fragmentpos < $openend) {
        $fragmentpos++;
        if ($text[$fragmentpos] === '=') {
            $fragmentpos++;
        }
        if (strspn($text, '0123456789', $fragmentpos, 1) !== 1) {
            return ['skip' => true, 'htmlpos' => $htmlpos];
        }
        $fragmentpos += strcspn($text, '<>', $fragmentpos, $openend - $fragmentpos);
    }

    if (!filter_shortcodes_has_safe_content_boundaries($text, $openend, $end)) {
        return ['skip' => true, 'htmlpos' => $htmlpos];
    }

    // Scan complete tags before the shortcode, skipping excluded elements as a whole.
    $pos = $htmlpos;
    $textend = strlen($text);
    while (($tagstart = strpos($text, '<', $pos)) !== false && $tagstart < $start) {
        $pos = $tagstart + 1;
        if (!strspn($text, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ/[!?', $pos, 1)) {
            continue;
        }
        $tagend = filter_shortcodes_html_tag_end($text, $pos, $textend);
        if ($tagend >= $start) {
            // Skip declarations, comments, CDATA and processing instructions.
            $skip = strspn($text, '!?', $tagstart + 1, 1) === 1;
            return ['skip' => $skip, 'htmlpos' => $tagstart];
        }
        $pos = $tagend + 1;
        if (
            preg_match(
                '~\G<(textarea|title|style|script|xmp|iframe|noembed|noframes|noscript|plaintext|template)(?=[ \t\r\n\f/>])~i',
                $text,
                $match,
                0,
                $tagstart
            )
        ) {
            $pos = filter_shortcodes_excluded_element_end($text, strtolower($match[1]), $pos, $textend);
            if ($pos > $start) {
                return ['skip' => true, 'htmlpos' => $tagstart];
            }
        }
    }
    return ['skip' => false, 'htmlpos' => $start];
}

/**
 * Find the end of an element excluded from shortcode processing.
 *
 * @param string $text The HTML text.
 * @param string $element The lowercase element name.
 * @param int $start The offset after its opening tag.
 * @param int $end The exclusive scan limit.
 * @return int The offset after the closing tag, or $end if it is not closed.
 */
function filter_shortcodes_excluded_element_end($text, $element, $start, $end) {
    if ($element === 'plaintext') {
        return $end;
    }
    if ($element !== 'template') {
        if ($element === 'script') {
            $close = filter_shortcodes_script_end($text, $start, $end);
        } else if (preg_match('~</' . $element . '(?=[ \t\r\n\f/>])~i', $text, $match, PREG_OFFSET_CAPTURE, $start)) {
            $close = $match[0][1];
        } else {
            return $end;
        }
        if ($close >= $end) {
            return $end;
        }
        return min($end, filter_shortcodes_html_tag_end($text, $close + 1, $end) + 1);
    }

    $depth = 1;
    $pos = $start;
    while (($tagstart = strpos($text, '<', $pos)) !== false && $tagstart < $end) {
        $pos = $tagstart + 1;
        if (!strspn($text, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ/[!?', $pos, 1)) {
            continue;
        }
        $tagend = filter_shortcodes_html_tag_end($text, $pos, $end);
        if ($tagend === $end) {
            return $end;
        }
        $pos = $tagend + 1;
        if (
            !preg_match(
                '~\G<(/?)(textarea|title|style|script|xmp|iframe|noembed|noframes|noscript|plaintext|template)(?=[ \t\r\n\f/>])~i',
                $text,
                $match,
                0,
                $tagstart
            )
        ) {
            continue;
        }
        $name = strtolower($match[2]);
        if ($name === 'template') {
            $depth += $match[1] === '/' ? -1 : 1;
            if ($depth === 0) {
                return $pos;
            }
        } else if ($match[1] === '') {
            $pos = filter_shortcodes_excluded_element_end($text, $name, $pos, $end);
        }
    }
    return $end;
}

/**
 * Check that wrapped content can be removed without exposing following inert HTML.
 *
 * Ordinary elements need not be balanced, but tags must be complete and any raw-text or template elements
 * opened in the content must also close within it. Offsets are byte offsets with an exclusive end.
 *
 * @param string $text The original text.
 * @param int $start The start of the wrapped content.
 * @param int $end The end of the shortcode, including its closing marker.
 * @return bool
 */
function filter_shortcodes_has_safe_content_boundaries($text, $start, $end) {
    $pos = $start;

    // Require CDATA sections to close within the shortcode.
    while (($pos = strpos($text, '<![CDATA[', $pos)) !== false && $pos < $end) {
        $close = strpos($text, ']]>', $pos + 9);
        if ($close === false || $close + 3 > $end) {
            return false;
        }
        $pos = $close + 3;
    }

    $pos = $start;

    while (($tagstart = strpos($text, '<', $pos)) !== false && $tagstart < $end) {
        $pos = $tagstart + 1;
        if (!strspn($text, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ/[!?', $pos, 1)) {
            continue;
        }

        $tagend = filter_shortcodes_html_tag_end($text, $pos, $end);
        if ($tagend === $end) {
            return false;
        }
        $pos = $tagend + 1;

        if (
            !preg_match(
                '~\G<(textarea|title|style|script|xmp|iframe|noembed|noframes|noscript|plaintext|template)(?=[ \t\r\n\f/>])~i',
                $text,
                $match,
                0,
                $tagstart
            )
        ) {
            continue;
        }
        $pos = filter_shortcodes_excluded_element_end($text, strtolower($match[1]), $pos, $end);
        if ($pos >= $end) {
            return false;
        }
    }

    return true;
}

/**
 * Find the closing script tag, conservatively accounting for HTML script escaping and SVG CDATA.
 *
 * @param string $text The original text.
 * @param int $start The byte offset after the opening script tag.
 * @param int $end The ending byte offset, exclusive.
 * @return int The closing tag's opening angle bracket offset, or $end if none was found.
 */
function filter_shortcodes_script_end($text, $start, $end) {
    $state = 'data';
    $cdataend = $start;
    $pos = $start;
    while (preg_match('~<!\[CDATA\[|<!--|-->|</?script(?=[ \t\r\n\f/>])~i', $text, $match, PREG_OFFSET_CAPTURE, $pos)) {
        $token = strtolower($match[0][0]);
        $tokenpos = $match[0][1];
        if ($tokenpos + strlen($token) > $end) {
            break;
        }
        $pos = $tokenpos + strlen($token);
        if ($match[0][0] === '<![CDATA[' && $tokenpos >= $cdataend) {
            // CDATA is case-sensitive and cannot nest. Keep scanning its contents for HTML script escape transitions.
            $cdataend = min($end, filter_shortcodes_html_tag_end($text, $tokenpos + 1, $end) + 1);
        } else if ($token === '<!--') {
            if ($state === 'data') {
                $state = 'escaped';
            }
            // Keep the dashes available: an immediately following '>' ends the escaped state.
            $pos = $tokenpos + 2;
        } else if ($token === '-->') {
            $state = 'data';
        } else if ($token === '<script' && $state === 'escaped') {
            $state = 'doubleescaped';
        } else if ($token === '</script') {
            if ($state === 'doubleescaped') {
                $state = 'escaped';
            } else if ($tokenpos >= $cdataend) {
                return $tokenpos;
            }
        }
    }
    return $end;
}

/**
 * Check whether a shortcode is wholly inside a quoted attribute from the allowlist.
 *
 * @param string $text The original text.
 * @param int $tagstart The opening angle bracket's byte offset.
 * @param int $start The shortcode's starting byte offset.
 * @param int $end The shortcode's ending byte offset, exclusive.
 * @return bool
 */
function filter_shortcodes_is_in_text_attribute($text, $tagstart, $start, $end) {
    $allowedattributes = ['alt', 'title', 'aria-label'];
    $whitespace = " \t\r\n\f";
    $pos = $tagstart + 1;

    // The shortcode must follow a complete opening tag name.
    if (strspn($text, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', $pos, 1) !== 1) {
        return false;
    }
    $pos += strcspn($text, $whitespace . '/<>', $pos, $start - $pos);

    // Read actual attributes, skipping their values so embedded attribute-like text cannot qualify.
    while ($pos < $start) {
        $pos += strspn($text, $whitespace . '/', $pos, $start - $pos);
        if ($pos === $start || $text[$pos] === '<' || $text[$pos] === '>') {
            return false;
        }
        $namestart = $pos;
        $pos += strcspn($text, $whitespace . '=/<>', $pos, $start - $pos);
        $namelength = $pos - $namestart;
        $pos += strspn($text, $whitespace, $pos, $start - $pos);
        if ($pos === $start) {
            return false;
        } else if ($text[$pos] !== '=') {
            continue;
        }

        $pos++;
        $pos += strspn($text, $whitespace, $pos, $start - $pos);
        if ($pos === $start) {
            return false;
        } else if ($text[$pos] !== '"' && $text[$pos] !== "'") {
            // Unquoted values cannot contain a supported shortcode.
            $pos += strcspn($text, $whitespace . '<>', $pos, $start - $pos);
            continue;
        }

        $quote = $text[$pos++];
        $close = strpos($text, $quote, $pos);
        if ($close === false) {
            return false;
        }
        if ($start < $close) {
            // Both markers and any wrapped content must remain within this one attribute value.
            $name = strtolower(substr($text, $namestart, $namelength));
            return $end <= $close && in_array($name, $allowedattributes, true);
        }
        $pos = $close + 1;
    }
    return false;
}

/**
 * Find a tag boundary, respecting quoted attribute values, comments and CDATA sections.
 *
 * @param string $text The original text.
 * @param int $start The byte offset immediately after the opening angle bracket.
 * @param int $end The ending byte offset, exclusive.
 * @return int The boundary offset, or $end if none was found.
 */
function filter_shortcodes_html_tag_end($text, $start, $end) {
    $pos = $start;

    // Quotes and angle brackets do not delimit comments or CDATA sections.
    $comment = $end - $start >= 3 && substr_compare($text, '!--', $start, 3) === 0;
    $cdata = $end - $start >= 8 && substr_compare($text, '![CDATA[', $start, 8) === 0;
    if ($comment || $cdata) {
        $pos += $comment ? 3 : 8;
        if ($comment && $pos < $end) {
            if ($text[$pos] === '>') {
                return $pos;
            } else if ($text[$pos] === '-' && $pos + 1 < $end && $text[$pos + 1] === '>') {
                return $pos + 1;
            }
        }
        $delimiter = $comment ? '-->' : ']]>';
        while ($pos < $end) {
            $pos += strcspn($text, $delimiter[0], $pos, $end - $pos);
            if ($pos + 3 <= $end && substr_compare($text, $delimiter, $pos, 3) === 0) {
                return $pos + 2;
            }
            if ($comment && $pos + 4 <= $end && substr_compare($text, '--!>', $pos, 4) === 0) {
                return $pos + 3;
            }
            $pos++;
        }
        return $end;
    }

    // Bogus declarations and processing instructions end at the first greater-than, regardless of quotes.
    if ($start < $end && ($text[$start] === '!' || $text[$start] === '?')) {
        return $start + strcspn($text, '>', $start, $end - $start);
    }

    // Inside a tag, '<' is literal. Only a quote at the start of an attribute value opens a quoted region.
    $whitespace = " \t\r\n\f";
    if ($pos < $end && $text[$pos] === '/') {
        $pos++;
    }
    $pos += strcspn($text, $whitespace . '/>', $pos, $end - $pos);
    while ($pos < $end) {
        $pos += strspn($text, $whitespace . '/', $pos, $end - $pos);
        if ($pos === $end || $text[$pos] === '>') {
            return $pos;
        }
        // An equals sign at the beginning of an attribute belongs to its name.
        $pos++;
        $pos += strcspn($text, $whitespace . '=>/', $pos, $end - $pos);
        $pos += strspn($text, $whitespace, $pos, $end - $pos);
        if ($pos === $end) {
            return $end;
        } else if ($text[$pos] !== '=') {
            continue;
        }
        $pos++;
        $pos += strspn($text, $whitespace, $pos, $end - $pos);
        if ($pos === $end) {
            return $end;
        }
        if ($text[$pos] === '"' || $text[$pos] === "'") {
            $quote = $text[$pos++];
            $pos += strcspn($text, $quote, $pos, $end - $pos);
            if ($pos === $end) {
                return $end;
            }
            $pos++;
        } else {
            // Quotes within an unquoted value are literal characters.
            $pos += strcspn($text, $whitespace . '>', $pos, $end - $pos);
        }
    }
    return $end;
}
