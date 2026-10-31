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
 * The course's accent colour: the "cherry red" of buttons, progress, badges and highlights, which a course designer
 * can change in the course settings.
 *
 * styles.css sets the default as design tokens (--sd-accent and friends). A course with its own colour gets the same
 * tokens worked out from it, for each colour mode: darkened on light backgrounds and lightened on dark ones until it
 * stands out (3:1, the contrast WCAG asks of controls) and black or white text on it reads (4.5:1), using whichever
 * of the two reads better.
 *
 * @package    format_streamdeck
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class accent_colour {
    /** @var string The default accent. */
    public const DEFAULT = '#e50914';

    /** @var float The contrast the accent keeps with the page behind it. */
    private const MIN_CONTRAST = 3.0;

    /** @var float The contrast of text on the accent. */
    private const MIN_TEXT_CONTRAST = 4.5;

    /** @var int[] The lightest surface the accent sits on in dark mode (--sd-surface-3). */
    private const DARK_SURFACE = [0x26, 0x26, 0x26];

    /** @var int[] The page in light mode. */
    private const LIGHT_SURFACE = [0xff, 0xff, 0xff];

    /** @var int[] Text on the accent when black reads better than white (--sd-surface-1 in dark mode). */
    private const DARK_TEXT = [0x11, 0x11, 0x11];

    /**
     * Whether a value is a colour this setting accepts: #rrggbb.
     *
     * @param string|null $value
     * @return bool
     */
    public static function is_valid(?string $value): bool {
        return (bool) preg_match('/^#[0-9a-f]{6}$/i', trim((string) $value));
    }

    /**
     * The course's own accent, or null when it uses the default.
     *
     * @param \stdClass|int $course
     * @return string|null #rrggbb, lower case.
     */
    public static function for_course($course): ?string {
        $value = course_get_format($course)->get_format_options()['accentcolour'] ?? '';
        if (!self::is_valid($value)) {
            return null;
        }
        $value = strtolower(trim($value));
        return $value === self::DEFAULT ? null : $value;
    }

    /**
     * The design tokens for an accent, for each colour mode.
     *
     * @param string $hex #rrggbb
     * @return array ['dark' => tokens, 'light' => tokens], each token name => CSS value.
     */
    public static function tokens(string $hex): array {
        $rgb = self::to_rgb($hex);
        $dark = self::until_contrast($rgb, [255, 255, 255], self::DARK_SURFACE);
        $light = self::until_contrast($rgb, [0, 0, 0], self::LIGHT_SURFACE);
        return [
            'dark' => self::mode_tokens($dark, self::mix($dark, [255, 255, 255], 0.12), 0.15, 0.35),
            'light' => self::mode_tokens($light, self::mix($light, [0, 0, 0], 0.15), 0.08, 0.3),
        ];
    }

    /**
     * A style block giving a page the course's accent, or '' for the default.
     *
     * The selectors outrank styles.css's token rules (one attribute more), whatever order the two load in. Dark
     * regions inside a light page (data-bs-theme="dark") keep the dark tokens, as they do by default.
     *
     * @param \stdClass|int $course
     * @return string
     */
    public static function style_for_course($course): string {
        $hex = self::for_course($course);
        if ($hex === null) {
            return '';
        }
        $tokens = self::tokens($hex);
        $block = function (array $values): string {
            $declarations = [];
            foreach ($values as $name => $value) {
                $declarations[] = "{$name}: {$value};";
            }
            return implode(' ', $declarations);
        };
        $css = ':root:root, [data-bs-theme="dark"][data-bs-theme] { ' . $block($tokens['dark']) . ' } ' .
            '[data-bs-theme="light"][data-bs-theme] { ' . $block($tokens['light']) . ' }';
        return \html_writer::tag('style', $css, ['id' => 'format-streamdeck-accent']);
    }

    /**
     * The tokens for one mode.
     *
     * @param int[] $accent
     * @param int[] $hover
     * @param float $soft Opacity of the tint behind badges and stages.
     * @param float $ring Opacity of focus rings and glows.
     * @return array
     */
    private static function mode_tokens(array $accent, array $hover, float $soft, float $ring): array {
        $channels = implode(', ', $accent);
        $ontext = self::contrast($accent, [255, 255, 255]) >= self::contrast($accent, self::DARK_TEXT)
            ? '#fff' : self::to_hex(self::DARK_TEXT);
        return [
            '--sd-accent' => self::to_hex($accent),
            '--sd-accent-rgb' => $channels,
            '--sd-accent-hover' => self::to_hex($hover),
            '--sd-accent-soft' => "rgba({$channels}, {$soft})",
            '--sd-accent-ring' => "rgba({$channels}, {$ring})",
            '--sd-on-accent' => $ontext,
        ];
    }

    /**
     * Move a colour towards black or white, a step at a time, until it stands out from a background and text on it
     * reads.
     *
     * @param int[] $rgb
     * @param int[] $towards Black or white.
     * @param int[] $background
     * @return int[]
     */
    private static function until_contrast(array $rgb, array $towards, array $background): array {
        for ($amount = 0.0; $amount < 1.0; $amount += 0.05) {
            $colour = self::mix($rgb, $towards, $amount);
            $text = max(self::contrast($colour, [255, 255, 255]), self::contrast($colour, self::DARK_TEXT));
            if (self::contrast($colour, $background) >= self::MIN_CONTRAST && $text >= self::MIN_TEXT_CONTRAST) {
                return $colour;
            }
        }
        return $towards;
    }

    /**
     * Mix two colours.
     *
     * @param int[] $from
     * @param int[] $to
     * @param float $amount 0 (all $from) to 1 (all $to).
     * @return int[]
     */
    private static function mix(array $from, array $to, float $amount): array {
        return array_map(fn($a, $b) => (int) round($a + ($b - $a) * $amount), $from, $to);
    }

    /**
     * WCAG contrast ratio between two colours.
     *
     * @param int[] $a
     * @param int[] $b
     * @return float 1 to 21.
     */
    public static function contrast(array $a, array $b): float {
        [$light, $dark] = [self::luminance($a), self::luminance($b)];
        if ($dark > $light) {
            [$light, $dark] = [$dark, $light];
        }
        return ($light + 0.05) / ($dark + 0.05);
    }

    /**
     * WCAG relative luminance.
     *
     * @param int[] $rgb
     * @return float
     */
    private static function luminance(array $rgb): float {
        $linear = array_map(function ($channel) {
            $c = $channel / 255;
            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, $rgb);
        return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
    }

    /**
     * #rrggbb to [r, g, b].
     *
     * @param string $hex
     * @return int[]
     */
    public static function to_rgb(string $hex): array {
        return array_map('hexdec', str_split(substr(trim($hex), 1), 2));
    }

    /**
     * [r, g, b] to #rrggbb.
     *
     * @param int[] $rgb
     * @return string
     */
    private static function to_hex(array $rgb): string {
        return vsprintf('#%02x%02x%02x', $rgb);
    }
}
