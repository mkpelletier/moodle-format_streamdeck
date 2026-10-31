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

namespace format_streamdeck\local;

/**
 * Tests for the course's accent colour.
 *
 * @package    format_streamdeck
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(accent_colour::class)]
final class accent_colour_test extends \advanced_testcase {
    /**
     * Only #rrggbb is a colour.
     */
    public function test_is_valid(): void {
        $this->assertTrue(accent_colour::is_valid('#1DB954'));
        $this->assertTrue(accent_colour::is_valid(' #1db954 '));
        foreach (['', '1db954', '#1db95', '#1db9544', 'red', '#1db95g', '#1db954;}', null] as $value) {
            $this->assertFalse(accent_colour::is_valid($value), (string) $value);
        }
    }

    /**
     * Every colour stays visible on the page in both modes, with readable text on it.
     */
    public function test_tokens_keep_contrast(): void {
        foreach (['#e50914', '#1db954', '#ffd400', '#001f5b', '#000000', '#ffffff', '#7b2cbf'] as $hex) {
            $tokens = accent_colour::tokens($hex);
            $dark = accent_colour::to_rgb($tokens['dark']['--sd-accent']);
            $light = accent_colour::to_rgb($tokens['light']['--sd-accent']);
            $this->assertGreaterThanOrEqual(3, accent_colour::contrast($dark, [0x26, 0x26, 0x26]), $hex);
            $this->assertGreaterThanOrEqual(3, accent_colour::contrast($light, [255, 255, 255]), $hex);
            foreach (['dark' => $dark, 'light' => $light] as $mode => $accent) {
                $text = accent_colour::to_rgb($tokens[$mode]['--sd-on-accent'] === '#fff'
                    ? '#ffffff' : $tokens[$mode]['--sd-on-accent']);
                $this->assertGreaterThanOrEqual(4.5, accent_colour::contrast($accent, $text), "$hex $mode");
            }
            $this->assertSame(implode(', ', $dark), $tokens['dark']['--sd-accent-rgb']);
        }
        // A colour that already stands out is kept as chosen; yellow gets dark text.
        $this->assertSame('#1db954', accent_colour::tokens('#1db954')['dark']['--sd-accent']);
        $this->assertSame('#111111', accent_colour::tokens('#ffd400')['dark']['--sd-on-accent']);
    }

    /**
     * The course setting: the default and anything that is not a colour give no style.
     */
    public function test_style_for_course(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        foreach (['', '#E50914', 'red'] as $value) {
            $course = $generator->create_course(['format' => 'streamdeck', 'accentcolour' => $value]);
            $this->assertNull(accent_colour::for_course($course), $value);
            $this->assertSame('', accent_colour::style_for_course($course), $value);
        }

        $course = $generator->create_course(['format' => 'streamdeck', 'accentcolour' => '#1DB954']);
        $this->assertSame('#1db954', accent_colour::for_course($course));
        $style = accent_colour::style_for_course($course);
        $this->assertStringContainsString('[data-bs-theme="light"][data-bs-theme]', $style);
        $this->assertStringContainsString('--sd-accent: #1db954;', $style);
        $this->assertStringContainsString('--sd-accent-rgb-light', \format_streamdeck\output\mobile::accent_style($course));
    }

    /**
     * The course form refuses anything but a colour or nothing.
     */
    public function test_validation(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['format' => 'streamdeck']);
        $format = course_get_format($course);
        $this->assertArrayHasKey('accentcolour', $format->edit_form_validation(['accentcolour' => 'red'], [], []));
        $this->assertSame([], $format->edit_form_validation(['accentcolour' => '#1db954'], [], []));
        $this->assertSame([], $format->edit_form_validation(['accentcolour' => ''], [], []));
    }
}
