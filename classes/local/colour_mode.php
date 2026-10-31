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
 * Light/dark colour mode for pages in Streamdeck courses.
 *
 * Each learner chooses light, dark or auto, stored as a user preference. Where the site runs a Boost-based theme
 * with Boost's own colour modes switched on (Moodle 5.3+), Boost owns the choice and this class steps aside.
 *
 * @package    format_streamdeck
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class colour_mode {
    /** @var string Light mode. */
    public const LIGHT = 'light';

    /** @var string Dark mode. */
    public const DARK = 'dark';

    /** @var string Follow the device's colour scheme. */
    public const AUTO = 'auto';

    /** @var string The user preference storing the chosen mode. */
    public const PREFERENCE = 'format_streamdeck_colourmode';

    /** @var string[] Page type prefixes that keep Moodle's default look. */
    private const EXCLUDED_PAGETYPES = [
        'local-unifiedgrader-',
        'course-format-streamdeck-syllabus',
    ];

    /**
     * The modes a learner can choose from.
     *
     * @return string[]
     */
    public static function get_modes(): array {
        return [self::LIGHT, self::DARK, self::AUTO];
    }

    /**
     * Whether a value is one of the known modes.
     *
     * @param string|null $mode
     * @return bool
     */
    public static function is_valid_mode(?string $mode): bool {
        return in_array($mode, self::get_modes(), true);
    }

    /**
     * Whether Boost's own colour modes are switched on, in which case they decide the mode.
     *
     * @return bool
     */
    public static function is_core_managed(): bool {
        if (!class_exists(\theme_boost\colour_mode::class)) {
            return false;
        }
        return \theme_boost\colour_mode::is_boost_theme() && \theme_boost\colour_mode::is_enabled();
    }

    /**
     * Whether the Streamdeck colour mode applies to a page.
     *
     * @param \moodle_page $page
     * @return bool
     */
    public static function applies_to_page(\moodle_page $page): bool {
        if (empty($page->course->id) || $page->course->id == SITEID) {
            return false;
        }
        if (($page->course->format ?? '') !== 'streamdeck') {
            return false;
        }
        if ($page->user_is_editing()) {
            return false;
        }
        $pagetype = (string) $page->pagetype;
        foreach (self::EXCLUDED_PAGETYPES as $prefix) {
            if (str_starts_with($pagetype, $prefix)) {
                return false;
            }
        }
        return true;
    }

    /**
     * The site default, used until a learner makes a choice.
     *
     * @return string
     */
    public static function get_site_default(): string {
        $default = get_config('format_streamdeck', 'defaultcolourmode');
        return self::is_valid_mode($default) ? $default : self::DARK;
    }

    /**
     * The mode chosen by the current user, or the site default.
     *
     * @return string
     */
    public static function get_current_mode(): string {
        if (isloggedin() && !isguestuser()) {
            $preference = get_user_preferences(self::PREFERENCE);
            if (self::is_valid_mode($preference)) {
                return $preference;
            }
        }
        return self::get_site_default();
    }

    /**
     * Whether the current user may store a choice.
     *
     * @return bool
     */
    public static function can_choose_mode(): bool {
        return isloggedin() && !isguestuser() && !self::is_core_managed();
    }

    /**
     * Template context for the view menu: Appearance (this class) and Display (display_mode).
     *
     * The Appearance group is left out when the learner cannot choose, for example when Boost owns the colour mode;
     * the Display group is always there.
     *
     * @return array
     */
    public static function export_menu(): array {
        $current = self::get_current_mode();
        $options = [];
        foreach (self::get_modes() as $mode) {
            $options[] = [
                'mode' => $mode,
                'label' => get_string('colourmode:' . $mode, 'format_streamdeck'),
                'icon' => ['light' => 'fa-sun', 'dark' => 'fa-moon', 'auto' => 'fa-circle-half-stroke'][$mode],
                'selected' => $mode === $current,
            ];
        }
        return [
            'canchoosecolour' => self::can_choose_mode(),
            'currentmode' => $current,
            'options' => $options,
        ] + display_mode::export_menu();
    }
}
