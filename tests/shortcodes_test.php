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
 * Built-in shortcodes tests.
 *
 * @package    filter_shortcodes
 * @copyright  2026 ByCS Lernplattform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace filter_shortcodes;

/**
 * Built-in shortcodes tests.
 *
 * @package    filter_shortcodes
 * @copyright  2026 ByCS Lernplattform
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class shortcodes_test extends \advanced_testcase {
    #[\PHPUnit\Framework\Attributes\Group('baseline')]
    /**
     * The fullname shortcode escapes HTML special characters of the name.
     *
     * @covers \filter_shortcodes\shortcodes::handle
     */
    public function test_fullname_is_escaped(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user([
            'firstname' => 'Bob" onmouseover="alert(1)',
            'lastname' => 'Smith',
        ]);
        $this->setUser($user);

        $result = shortcodes::handle('fullname', (object) [], null, (object) [], fn($c) => $c);

        $this->assertStringNotContainsString('"', $result);
        $this->assertSame(s(fullname($user)), $result);
    }

    #[\PHPUnit\Framework\Attributes\Group('baseline')]
    /**
     * The fullname shortcode returns a regular name unchanged.
     *
     * @covers \filter_shortcodes\shortcodes::handle
     */
    public function test_fullname_regular_name(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['firstname' => 'Jürgen', 'lastname' => 'Müller']);
        $this->setUser($user);

        $result = shortcodes::handle('fullname', (object) [], null, (object) [], fn($c) => $c);

        $this->assertSame(fullname($user), $result);
        $this->assertStringContainsString('Jürgen', $result);
    }
}
