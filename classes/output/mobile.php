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

namespace format_streamdeck\output;

use format_streamdeck\local\accent_colour;
use format_streamdeck\local\course_progress;
use format_streamdeck\local\episodes;

/**
 * Moodle App support: the course page as a streaming home and episode pages.
 *
 * The app compiles the template as Angular, so nothing a teacher or learner typed goes into it. The template is
 * fixed, and the course data travels as JSON in otherdata, which mobile/course.js parses for the template to bind.
 *
 * @package    format_streamdeck
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mobile {
    /**
     * The course view for CoreCourseFormatDelegate.
     *
     * @param array $args Arguments from the app; courseid is the course being viewed.
     * @return array templates, javascript and otherdata for the app.
     */
    public static function mobile_course_view(array $args): array {
        global $CFG;

        $course = get_course((int) $args['courseid']);
        require_login($course);

        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => self::course_template($course),
                ],
            ],
            'javascript' => file_get_contents($CFG->dirroot . '/course/format/streamdeck/mobile/course.js'),
            'otherdata' => [
                'data' => json_encode(self::export_course($course)),
                'view' => '0',
                'season' => '0',
            ],
        ];
    }

    /**
     * The app's course template (mobile/course.html, an Angular template), with the course's accent colour.
     *
     * @param \stdClass $course
     * @return string
     */
    public static function course_template(\stdClass $course): string {
        global $CFG;
        $html = file_get_contents($CFG->dirroot . '/course/format/streamdeck/mobile/course.html');
        $style = self::accent_style($course);
        if ($style === '') {
            return $html;
        }
        $wrapper = '<div class="format-streamdeck-app">';
        return str_replace($wrapper, '<div class="format-streamdeck-app" style="' . s($style) . '">', $html);
    }

    /**
     * The course's accent colour as custom properties for the app's wrapper; mobile/mobile.css picks the light or
     * dark set to match the app. A validated #rrggbb, so it is safe in the compiled template.
     *
     * @param \stdClass $course
     * @return string '' for the default accent.
     */
    public static function accent_style(\stdClass $course): string {
        $hex = accent_colour::for_course($course);
        if ($hex === null) {
            return '';
        }
        $style = '';
        foreach (accent_colour::tokens($hex) as $mode => $tokens) {
            foreach (['--sd-accent', '--sd-accent-rgb', '--sd-accent-hover', '--sd-on-accent'] as $name) {
                $style .= "{$name}-{$mode}: {$tokens[$name]}; ";
            }
        }
        return trim($style);
    }

    /**
     * Everything the app shows about the course, for the current learner.
     *
     * @param \stdClass $course
     * @return array
     */
    public static function export_course(\stdClass $course): array {
        global $PAGE, $USER;

        $context = \context_course::instance($course->id);
        /** @var \format_streamdeck $format */
        $format = course_get_format($course);
        $modinfo = get_fast_modinfo($course);
        $progress = new course_progress($modinfo, new \completion_info($course), (int) $USER->id);
        $episodes = new episodes($format, $modinfo, $progress);
        $renderer = $PAGE->get_renderer('format_streamdeck');

        // Episodes.
        $seasons = $format->get_seasons();
        $seasonlabel = $format->get_season_label();
        $episodedata = [];
        foreach ($episodes->get_episode_sections() as $sectioninfo) {
            if (!$sectioninfo->visible || (!$sectioninfo->uservisible && empty($sectioninfo->availableinfo))) {
                continue;
            }
            $num = (int) $sectioninfo->section;
            $counts = $progress->get_section_counts($num);
            $featured = $sectioninfo->uservisible ? $episodes->get_featured_cm($sectioninfo) : null;
            $neighbours = $episodes->get_neighbours($num);

            $seasonname = '';
            foreach ($seasons as $index => $season) {
                if (in_array($num, $season['sections'], true)) {
                    $seasonname = $seasonlabel . ' ' . ($index + 1) . ($season['name'] !== '' ? ': ' . $season['name'] : '');
                }
            }

            $about = self::export_about($format, $sectioninfo, $context);
            $episodedata[] = [
                'num' => $num,
                'name' => $format->get_section_name($sectioninfo),
                'image' => $renderer->get_section_image_for_sectioninfo($sectioninfo),
                'locked' => !$sectioninfo->uservisible,
                'lockedinfo' => $sectioninfo->uservisible
                    ? ''
                    : \core_availability\info::format_info($sectioninfo->availableinfo, $course),
                'progress' => course_progress::percent($counts),
                'hasprogress' => $counts['total'] > 0,
                'done' => $counts['total'] > 0 && $counts['complete'] === $counts['total'],
                'seasonname' => $seasonname,
                'featuredcmid' => $featured ? (int) $featured->id : 0,
                'prev' => $neighbours['prev'] ? (int) $neighbours['prev']->section : 0,
                'next' => $neighbours['next'] ? (int) $neighbours['next']->section : 0,
                'canshownext' => $episodes->is_episode_complete($num),
            ] + $episodes->get_metadata($sectioninfo) + $about;
        }

        // Seasons, opening on the one the learner is working through.
        $next = $progress->get_first_incomplete();
        $shown = array_column($episodedata, 'num');
        $seasondata = [];
        $current = 0;
        foreach ($seasons as $index => $season) {
            $nums = array_values(array_intersect($season['sections'], $shown));
            if (!$nums) {
                continue;
            }
            if ($next && in_array((int) $next->sectionnum, $nums, true)) {
                $current = count($seasondata);
            }
            $seasondata[] = [
                'index' => (string) count($seasondata),
                'kicker' => $seasonlabel . ' ' . ($index + 1),
                'name' => $season['name'] !== '' ? $season['name'] : get_string('seasonopening', 'format_streamdeck'),
                'episodecount' => count($nums) === 1
                    ? get_string('seasonepisode', 'format_streamdeck')
                    : get_string('seasonepisodes', 'format_streamdeck', count($nums)),
                'nums' => $nums,
            ];
        }

        $counts = $progress->get_course_counts();
        $resume = ($next && $next->sectionnum > 0) ? (int) $next->sectionnum : 0;
        $summary = file_rewrite_pluginfile_urls($course->summary, 'pluginfile.php', $context->id, 'course', 'summary', null);

        return [
            'course' => [
                'fullname' => format_string($course->fullname, true, ['context' => $context]),
                'summary' => format_text($summary, $course->summaryformat, ['context' => $context]),
                'image' => (string) \core_course\external\course_summary_exporter::get_course_image($course),
                'hascompletion' => $counts['total'] > 0,
                'percent' => course_progress::percent($counts),
                'completiontext' => $counts['total']
                    ? get_string('activitiescomplete', 'format_streamdeck', $counts)
                    : '',
                'resume' => $resume,
                'hasstarted' => $counts['complete'] > 0,
            ],
            'hasseasons' => count($seasondata) > 0,
            'seasonlabel' => $seasonlabel,
            'currentseason' => (string) $current,
            'seasons' => $seasondata,
            'episodes' => $episodedata,
        ];
    }

    /**
     * The "About this episode" card, if the section shows one.
     *
     * @param \format_streamdeck $format
     * @param \section_info $sectioninfo
     * @param \context_course $context
     * @return array about, abouttitle, abouticon.
     */
    private static function export_about(
        \format_streamdeck $format,
        \section_info $sectioninfo,
        \context_course $context
    ): array {
        $about = ['about' => '', 'abouttitle' => '', 'abouticon' => ''];
        if (!$sectioninfo->showabout || !$sectioninfo->uservisible) {
            return $about;
        }
        $options = $format->get_format_options($sectioninfo);
        $text = (string) ($options['about'] ?? '');
        if (trim(strip_tags($text, '<img>')) === '') {
            return $about;
        }
        $title = trim((string) ($options['abouttitle'] ?? ''));
        $icon = (string) ($options['abouticon'] ?? '');
        return [
            'about' => format_text($text, (int) ($options['aboutformat'] ?? FORMAT_HTML), ['context' => $context]),
            'abouttitle' => $title !== ''
                ? format_string($title, true, ['context' => $context])
                : get_string('about', 'format_streamdeck'),
            'abouticon' => in_array($icon, \format_streamdeck::ABOUT_ICONS, true) ? 'fas-' . $icon : '',
        ];
    }
}
