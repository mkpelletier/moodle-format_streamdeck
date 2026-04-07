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
 * Streamdeck renderer.
 *
 * Extends section_renderer for Moodle 5.0+ course format compliance.
 * Implements required methods for inplace editing and reactive components.
 * Signatures match parent exactly (untyped params, no extras) to avoid fatal mismatches.
 *
 * @package    format_streamdeck
 * @author     Mathieu Pelletier <mathieu@sats.ac.za>
 * @copyright  2025
 * @license    http://www.gnu.org/licenses/gpl-3.0.txt GNU GPL v3 or later
 */

namespace format_streamdeck\output;

use core_courseformat\output\section_renderer;
use renderable;

/**
 * Streamdeck format renderer.
 *
 * @package    format_streamdeck
 */
class renderer extends section_renderer {
    /**
     * Return the section title.
     *
     * @param mixed $section
     * @param mixed $course
     * @return string
     */
    public function section_title($section, $course) {
        try {
            return parent::section_title($section, $course);
        } catch (\Throwable $e) {
            debugging('STREAMDECK: Error in section_title: ' . $e->getMessage(), DEBUG_DEVELOPER);
            $secnum = is_object($section) ? $section->section : (int)$section;
            return get_string('sectionname', 'format_streamdeck', $secnum);
        }
    }

    /**
     * Return the section title without link.
     *
     * @param mixed $section
     * @param mixed $course
     * @return string
     */
    public function section_title_without_link($section, $course) {
        try {
            return parent::section_title_without_link($section, $course);
        } catch (\Throwable $e) {
            debugging('STREAMDECK: Error in section_title_without_link: ' . $e->getMessage(), DEBUG_DEVELOPER);
            $secnum = is_object($section) ? $section->section : (int)$section;
            return get_string('sectionname', 'format_streamdeck', $secnum);
        }
    }

    /**
     * Return the section edit control items.
     *
     * @param mixed $section
     * @param mixed $course
     * @return array
     */
    protected function section_edit_control_items($section, $course) {
        try {
            // Minimalist UI: No extra edit controls (rely on core actions).
            return [];
        } catch (\Throwable $e) {
            debugging('STREAMDECK: Error in section_edit_control_items: ' . $e->getMessage(), DEBUG_DEVELOPER);
            // Fallback to parent if available.
            if (is_callable('parent::section_edit_control_items')) {
                return parent::section_edit_control_items($section, $course);
            }
            return [];
        }
    }

    /**
     * Return the section content.
     *
     * @param renderable $sectionrenderable
     * @return string
     */
    public function section_content(renderable $sectionrenderable) {
        try {
            return parent::section_content($sectionrenderable);
        } catch (\Throwable $e) {
            debugging('STREAMDECK: Error in section_content: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return '';
        }
    }

    /**
     * Fetch syllabus content for a course, with safe fallbacks.
     *
     * @param \stdClass $course
     * @return array Array of raw HTML strings indexed by key.
     */
    public function get_syllabus_for_course(\stdClass $course): array {
        global $DB;

        $result = [
            'introhtml'       => '',
            'outcomeshtml'    => '',
            'assessmentshtml' => '',
            'materialshtml'   => '',
        ];

        $record = $DB->get_record('format_streamdeck_syllabus', ['courseid' => $course->id]);
        if ($record) {
            $result['introhtml']       = $record->introhtml ?? '';
            $result['outcomeshtml']    = $record->outcomeshtml ?? '';
            $result['assessmentshtml'] = $record->assessmentshtml ?? '';
            $result['materialshtml']   = $record->materialshtml ?? '';
        }

        return $result;
    }

    /**
     * Return the image URL for a given section, or empty string if none.
     *
     * Uses Moodle's file_rewrite_pluginfile_urls() on the section summary to
     * resolve @@PLUGINFILE@@ references, using the correct itemid
     * (course_sections.id, NOT section number).
     *
     * @param \section_info $sectioninfo
     * @return string
     */
    public function get_section_image_for_sectioninfo(\section_info $sectioninfo): string {
        $sectionnum = (int)$sectioninfo->section;
        $sectionid  = (int)$sectioninfo->id; // This is course_sections.id (itemid in mdl_files).

        // We still don't want section 0 as an "episode".
        if ($sectionnum <= 0) {
            return '';
        }

        // Priority 1: Uploaded thumbnail from section settings.
        $uploaded = $this->get_uploaded_section_image($sectioninfo);
        if (!empty($uploaded)) {
            return $uploaded;
        }

        // Priority 2: Extract first image from section description.
        $summary = $sectioninfo->summary ?? '';
        if (empty($summary)) {
            return '';
        }

        $context = \context_course::instance($sectioninfo->course);

        // IMPORTANT: Use $sectionid as itemid, not $sectionnum.
        $rewritten = file_rewrite_pluginfile_urls(
            $summary,
            'pluginfile.php',
            $context->id,
            'course',
            'section',
            $sectionid
        );

        if (empty($rewritten)) {
            return '';
        }

        // Extract first <img src="...">.
        $pattern = '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i';

        if (!preg_match($pattern, $rewritten, $matches)) {
            return '';
        }

        $src = $matches[1];
        return $src;
    }

    /**
     * Get the URL of an uploaded section thumbnail image.
     *
     * @param \section_info $sectioninfo The section info object.
     * @return string The image URL, or empty string if none uploaded.
     */
    public function get_uploaded_section_image(\section_info $sectioninfo): string {
        $context = \context_course::instance($sectioninfo->course);
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $context->id,
            'format_streamdeck',
            'sectionimage',
            (int)$sectioninfo->id,
            'sortorder DESC, id ASC',
            false
        );

        if (empty($files)) {
            return '';
        }

        $file = reset($files);
        return \moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename()
        )->out(false);
    }
}
