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

namespace format_streamdeck;

use core\hook\output\before_html_attributes;
use core\hook\output\before_standard_head_html_generation;
use core\hook\output\before_standard_top_of_body_html_generation;
use format_streamdeck\local\accent_colour;
use format_streamdeck\local\colour_mode;
use format_streamdeck\local\display_mode;
use html_writer;

/**
 * Output hook callbacks for the Streamdeck course format.
 *
 * @package    format_streamdeck
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_listener {
    /**
     * Put the learner's colour mode on the html tag of every page in a Streamdeck course.
     *
     * data-streamdeck-mode also marks the pages the Streamdeck look applies to, so styles.css can leave editing and
     * excluded pages alone. Where Boost owns the colour mode it is set to "core" and Boost sets data-bs-theme.
     *
     * @param before_html_attributes $hook
     */
    public static function before_html_attributes(before_html_attributes $hook): void {
        global $PAGE;

        if (!colour_mode::applies_to_page($PAGE)) {
            return;
        }

        $hook->add_attribute('data-streamdeck-display', display_mode::get_current_mode());

        if (colour_mode::is_core_managed()) {
            $hook->add_attribute('data-streamdeck-mode', 'core');
            return;
        }

        $mode = colour_mode::get_current_mode();

        // Auto is resolved in the head before the first paint; render light until then.
        $hook->add_attribute('data-bs-theme', $mode === colour_mode::AUTO ? colour_mode::LIGHT : $mode);
        $hook->add_attribute('data-streamdeck-mode', $mode);
    }

    /**
     * Mark framed pages, remember a ?display= choice and resolve the auto colour mode, before the page is painted.
     *
     * A course page loaded in a frame (the episode stage, the hero modals, inline forum and assignment frames) gets
     * the streamdeck-in-frame class on <html>, so styles.css drops the chrome the surrounding page already shows.
     *
     * @param before_standard_head_html_generation $hook
     */
    public static function before_standard_head_html_generation(before_standard_head_html_generation $hook): void {
        global $PAGE;

        // The course's accent colour, on every page of the course (editing included).
        if (!empty($PAGE->course->id) && $PAGE->course->id != SITEID && ($PAGE->course->format ?? '') === 'streamdeck') {
            $hook->add_html(accent_colour::style_for_course($PAGE->course));
        }

        if (!colour_mode::applies_to_page($PAGE)) {
            return;
        }

        $js = <<<EOF
            (function() {
                try {
                    if (window.self !== window.top) {
                        document.documentElement.classList.add('streamdeck-in-frame');
                    }
                } catch (e) {
                    document.documentElement.classList.add('streamdeck-in-frame');
                }
            })();
            EOF;

        // A ?display= link (for example one a teacher shares for TVs) is remembered on this device.
        $display = optional_param('display', '', PARAM_ALPHA);
        if (display_mode::is_valid_mode($display)) {
            $cookie = json_encode(display_mode::COOKIE . '=' . $display . display_mode::get_cookie_attributes());
            $js .= "\ndocument.cookie = {$cookie};";
        }

        // Behat keeps the server's choice so that colour assertions do not depend on the machine running the browser.
        if (!colour_mode::is_core_managed() && !defined('BEHAT_SITE_RUNNING')) {
            $js .= <<<EOF

                (function() {
                    var root = document.documentElement;
                    var query = window.matchMedia('(prefers-color-scheme: dark)');
                    var resolve = function() {
                        var mode = root.getAttribute('data-streamdeck-mode');
                        if (mode === 'auto') {
                            root.setAttribute('data-bs-theme', query.matches ? 'dark' : 'light');
                        }
                    };
                    resolve();
                    query.addEventListener('change', resolve);
                })();
                EOF;
        }

        $hook->add_html(html_writer::script($js));
    }

    /**
     * Give every activity page in a Streamdeck course the same top bar: back to the course, and the colour mode menu.
     *
     * @param before_standard_top_of_body_html_generation $hook
     */
    public static function before_standard_top_of_body_html_generation(
        before_standard_top_of_body_html_generation $hook,
    ): void {
        global $PAGE, $OUTPUT;

        if (!colour_mode::applies_to_page($PAGE) || !str_starts_with((string) $PAGE->pagetype, 'mod-')) {
            return;
        }

        $context = [
            'courseurl' => (new \moodle_url('/course/view.php', ['id' => $PAGE->course->id]))->out(false),
            'sectionurl' => null,
            'colourmode' => colour_mode::export_menu(),
        ];

        if ($PAGE->cm && $PAGE->cm->sectionnum > 0) {
            $format = course_get_format($PAGE->course);
            $context['sectionurl'] = $format->get_view_url($PAGE->cm->sectionnum)->out(false);
            $context['sectionname'] = $format->get_section_name($PAGE->cm->sectionnum);
        }

        $PAGE->requires->js_call_amd('format_streamdeck/colour_mode', 'init');
        $PAGE->requires->js_call_amd('format_streamdeck/spatial_nav', 'init');
        if ($PAGE->pagetype === 'mod-forum-post') {
            // In an episode's discussion the post form is the composer (it does nothing outside a frame).
            $PAGE->requires->js_call_amd('format_streamdeck/forum_composer', 'init');
        }

        $hook->add_html($OUTPUT->render_from_template('format_streamdeck/local/activity_toolbar', $context));
    }
}
