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
 * Moodle App support for the Streamdeck course format.
 *
 * @package    format_streamdeck
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$addons = [
    'format_streamdeck' => [
        'handlers' => [
            'streamdeck' => [
                'delegate' => 'CoreCourseFormatDelegate',
                'method' => 'mobile_course_view',
                'styles' => [
                    'url' => $CFG->wwwroot . '/course/format/streamdeck/mobile/mobile.css',
                    // Bump whenever mobile/mobile.css changes, so the app downloads it again.
                    'version' => 2026103103,
                ],
                // Learners move through the course with the episode pages, as on the web.
                'displaycourseindex' => false,
            ],
        ],
        'lang' => [
            ['allmodules', 'format_streamdeck'],
            ['backtocourse', 'format_streamdeck'],
            ['completed', 'format_streamdeck'],
            ['episode', 'format_streamdeck'],
            ['episodeempty', 'format_streamdeck'],
            ['locked', 'format_streamdeck'],
            ['new', 'format_streamdeck'],
            ['nextepisode', 'format_streamdeck'],
            ['nextepisodelocked', 'format_streamdeck'],
            ['previousepisode', 'format_streamdeck'],
            ['relatedactivities', 'format_streamdeck'],
            ['resume', 'format_streamdeck'],
            ['start', 'format_streamdeck'],
        ],
    ],
];
