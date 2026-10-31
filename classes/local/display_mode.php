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
 * Standard or TV ("10-foot") display for pages in Streamdeck courses.
 *
 * No media query identifies a TV, so the display is a choice remembered per device (the same learner uses a laptop
 * and a TV), defaulted by recognising TV browsers. A ?display= link sets it too, so teachers can share a TV link.
 *
 * @package    format_streamdeck
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class display_mode {
    /** @var string The usual layout. */
    public const STANDARD = 'standard';

    /** @var string Large type, TV-safe margins and remote navigation. */
    public const TV = 'tv';

    /** @var string The cookie holding this device's choice, written by the browser. */
    public const COOKIE = 'streamdeck_display';

    /** @var string User agent fragments of TV browsers and TV sticks. */
    private const TV_USER_AGENTS = '/SMART-TV|SmartTV|Tizen|Web0S|webOS|NetCast|BRAVIA|Android TV|GoogleTV' .
        '|AFT[A-Z]|CrKey|HbbTV|Roku|AppleTV/i';

    /**
     * The display modes.
     *
     * @return string[]
     */
    public static function get_modes(): array {
        return [self::STANDARD, self::TV];
    }

    /**
     * Whether a value is one of the display modes.
     *
     * @param string|null $mode
     * @return bool
     */
    public static function is_valid_mode(?string $mode): bool {
        return in_array($mode, self::get_modes(), true);
    }

    /**
     * Whether a user agent belongs to a TV browser.
     *
     * @param string $useragent
     * @return bool
     */
    public static function is_tv_browser(string $useragent): bool {
        return (bool) preg_match(self::TV_USER_AGENTS, $useragent);
    }

    /**
     * The display for this request: a ?display= link, then this device's choice, then a TV browser, then standard.
     *
     * @return string
     */
    public static function get_current_mode(): string {
        $param = optional_param('display', '', PARAM_ALPHA);
        if (self::is_valid_mode($param)) {
            return $param;
        }
        $cookie = clean_param($_COOKIE[self::COOKIE] ?? '', PARAM_ALPHA);
        if (self::is_valid_mode($cookie)) {
            return $cookie;
        }
        return self::is_tv_browser((string) \core_useragent::get_user_agent_string()) ? self::TV : self::STANDARD;
    }

    /**
     * Attributes for the cookie the browser writes, following the site's own cookie settings.
     *
     * @return string Beginning with a separator, e.g. "; Path=/; Max-Age=31536000; SameSite=Lax".
     */
    public static function get_cookie_attributes(): string {
        global $CFG;

        $attributes = [
            'Path=' . ($CFG->sessioncookiepath ?? '/'),
            'Max-Age=' . YEARSECS,
            'SameSite=Lax',
        ];
        if (!empty($CFG->sessioncookiedomain)) {
            $attributes[] = 'Domain=' . $CFG->sessioncookiedomain;
        }
        if (is_moodle_cookie_secure()) {
            $attributes[] = 'Secure';
        }
        return '; ' . implode('; ', $attributes);
    }

    /**
     * Template context for the Display group of the view menu.
     *
     * @return array
     */
    public static function export_menu(): array {
        $current = self::get_current_mode();
        $options = [];
        foreach (self::get_modes() as $mode) {
            $options[] = [
                'mode' => $mode,
                'label' => get_string('display:' . $mode, 'format_streamdeck'),
                'icon' => $mode === self::TV ? 'fa-tv' : 'fa-laptop',
                'selected' => $mode === $current,
            ];
        }
        return [
            'currentdisplay' => $current,
            'displayoptions' => $options,
            'cookieattributes' => self::get_cookie_attributes(),
        ];
    }
}
