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
 * Streamdeck course format – core library functions.
 *
 * Defines the format class (format_streamdeck extending core_courseformat\base) for Moodle 5.0+.
 * Handles section management with robust fallbacks for orphaned/unused sections.
 *
 * @package    format_streamdeck
 * @copyright  2025
 * @license    http://www.gnu.org/licenses/gpl-3.0.txt GNU GPL v3 or later
 */

/**
 * Streamdeck course format class.
 *
 * Extends core base for Moodle 5.0+ compliance.
 * Supports reactive components and inplace editing.
 */
class format_streamdeck extends \core_courseformat\base {
    /**
     * Indicates this format uses sections.
     *
     * @return bool Returns true
     */
    public function uses_sections(): bool {
        try {
            return true;
        } catch (\Throwable $e) {
            debugging('STREAMDECK: Error in uses_sections: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return parent::uses_sections();
        }
    }

    /**
     * Whether this format uses the course index drawer (left nav).
     *
     * Required so Boost/Boost Union knows to build the course index navigation
     * for this format (sections, activities, etc.).
     *
     * @return bool
     */
    public function uses_course_index(): bool {
        return true;
    }

    /**
     * Returns the information about the ajax support in this course format.
     *
     * This enables core course AJAX features such as drag-and-drop
     * moving of activities and sections.
     *
     * @return \stdClass
     */
    public function supports_ajax(): \stdClass {
        try {
            $ajaxsupport = new \stdClass();
            $ajaxsupport->capable = true;
            return $ajaxsupport;
        } catch (\Throwable $e) {
            debugging('STREAMDECK: Error in supports_ajax: ' . $e->getMessage(), DEBUG_DEVELOPER);
            $ajaxsupport = new \stdClass();
            $ajaxsupport->capable = false;
            return $ajaxsupport;
        }
    }

    /**
     * Whether this format uses the new course editor components (Moodle 4+/5+).
     *
     * Required so the reactive course editor / course index work correctly.
     *
     * @return bool
     */
    public function supports_components(): bool {
        return true;
    }

    /**
     * Returns the display name for a section (inplace editable).
     *
     * @param mixed $section Section number (int) or section_info object
     * @return string The section name
     */
    public function get_section_name($section): string {
        // Normalize to section_info object.
        if (!is_object($section)) {
            $section = $this->get_section($section);
        }

        if (!$section) {
            return get_string('orphanedsection', 'format_streamdeck');
        }

        $context = \context_course::instance($this->courseid);

        // If custom name is set, format and return it.
        if ((string)$section->name !== '') {
            return format_string($section->name, true, ['context' => $context]);
        }

        // Fallback to default.
        return $this->get_default_section_name($section);
    }

    /**
     * Returns the default display name for a section.
     *
     * @param \section_info $section The section_info object
     * @return string The default section name
     */
    public function get_default_section_name($section): string {
        if ($section->section == 0) {
            return get_string('section0name', 'format_streamdeck');
        }

        $numsections = $this->get_course()->numsections ?? 0;

        if ($section->section > $numsections) {
            // Try core string for orphaned activities; fallback to plugin string.
            if (get_string_manager()->string_exists('orphanedactivities', 'course')) {
                return get_string('orphanedactivities', 'course');
            }
            // Double-fallback: plugin string (defined below).
            return get_string('orphanedsection', 'format_streamdeck');
        }

        return get_string('sectionname', 'format_streamdeck', $section->section);
    }

    /**
     * Returns whether this course format allows sections to be added.
     *
     * @return bool
     */
    public function supports_add_sections(): bool {
        return true;
    }

    /**
     * Does this course format support to show multiple sections in one page?
     *
     * @return bool
     */
    public function supports_showing_all_sections(): bool {
        return true; // Card grid shows all.
    }

    /**
     * Returns the display options for a section.
     *
     * @param stdClass $course The course
     * @param \section_info $section The section
     * @return array The display options
     */
    public function get_section_display_options(stdClass $course, \section_info $section): array {
        $options = parent::get_section_display_options($course, $section);
        $options['showallactivities'] = false; // Minimalist UI.
        return $options;
    }

    /**
     * Course-level format options for the Streamdeck format.
     *
     * @param bool $foreditform Whether the options are for the course edit form.
     * @return array Format options.
     */
    public function course_format_options($foreditform = false): array {
        static $courseformatoptions = false;
        if ($courseformatoptions === false) {
            $courseformatoptions = [
                'herofont' => [
                    'default' => 'Lobster',
                    'type' => PARAM_TEXT,
                ],
                'enabledrawertoggle' => [
                    'default' => 0,
                    'type' => PARAM_INT,
                ],
            ];
        }
        if ($foreditform && !isset($courseformatoptions['herofont']['label'])) {
            $fontoptions = [
                'Lobster' => 'Lobster',
                'system-ui' => 'System Default',
                'Georgia' => 'Georgia (Serif)',
                'Playfair Display' => 'Playfair Display',
                'Merriweather' => 'Merriweather',
                'Oswald' => 'Oswald',
                'Raleway' => 'Raleway',
                'Montserrat' => 'Montserrat',
                'Poppins' => 'Poppins',
                'Lora' => 'Lora',
            ];
            $courseformatoptions['herofont']['label'] = get_string('herofont', 'format_streamdeck');
            $courseformatoptions['herofont']['help'] = 'herofont';
            $courseformatoptions['herofont']['help_component'] = 'format_streamdeck';
            $courseformatoptions['herofont']['element_type'] = 'select';
            $courseformatoptions['herofont']['element_attributes'] = [$fontoptions];

            $courseformatoptions['enabledrawertoggle']['label'] = get_string('enabledrawertoggle', 'format_streamdeck');
            $courseformatoptions['enabledrawertoggle']['help'] = 'enabledrawertoggle';
            $courseformatoptions['enabledrawertoggle']['help_component'] = 'format_streamdeck';
            $courseformatoptions['enabledrawertoggle']['element_type'] = 'select';
            $courseformatoptions['enabledrawertoggle']['element_attributes'] = [
                [
                    0 => get_string('no'),
                    1 => get_string('yes'),
                ],
            ];
        }
        return $courseformatoptions;
    }

    /**
     * Set up the page for this course format.
     *
     * Adds body classes based on course display settings.
     *
     * @param \moodle_page $page The page object.
     */
    public function page_set_course(\moodle_page $page): void {
        parent::page_set_course($page);
        $options = $this->get_format_options();
        if (!empty($options['enabledrawertoggle'])) {
            $page->add_body_class('streamdeck-show-drawer-toggle');
        }

        // Load our JS on quiz pages for the circular timer and drawer toggle.
        $pagetype = $page->pagetype ?? '';
        if (strpos($pagetype, 'mod-quiz-') === 0 && !$page->user_is_editing()) {
            $page->requires->js_call_amd('format_streamdeck/main', 'init');

            // Pass the quiz timelimit so the circular timer can calculate the correct fraction.
            $cmid = optional_param('cmid', 0, PARAM_INT);
            if (!$cmid) {
                // Attempt pages use 'attempt' param; get cmid from the attempt record.
                $attemptid = optional_param('attempt', 0, PARAM_INT);
                if ($attemptid) {
                    global $DB;
                    $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid], 'quiz');
                    if ($attempt) {
                        $cm = get_coursemodule_from_instance('quiz', $attempt->quiz, $this->courseid);
                        if ($cm) {
                            $cmid = $cm->id;
                        }
                    }
                }
            }
            if ($cmid) {
                global $DB;
                $quizid = $DB->get_field('course_modules', 'instance', ['id' => $cmid]);
                if ($quizid) {
                    $timelimit = (int) $DB->get_field('quiz', 'timelimit', ['id' => $quizid]);
                    if ($timelimit > 0) {
                        $page->requires->js_amd_inline("
                            require([], function() {
                                window.streamdeckQuizTimelimit = {$timelimit};
                            });
                        ");
                    }
                }
            }
        }
        $showbackbtn = in_array($pagetype, [
            'mod-quiz-view',
            'mod-quiz-summary',
            'mod-quiz-review',
            'mod-assign-view',
        ], true);
        if ($showbackbtn) {
            $course = $this->get_course();
            $courseurl = (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false);
            $label = get_string('backtocourse', 'format_streamdeck');
            $page->requires->js_amd_inline("
                require([], function() {
                    // Do not inject the button inside iframes (e.g. streamdeck-assign-frame).
                    if (window.self !== window.top) { return; }
                    var container = document.getElementById('region-main');
                    if (!container) { return; }
                    var existing = container.querySelector('.streamdeck-back-to-course');
                    if (existing) { return; }
                    var btn = document.createElement('a');
                    btn.href = " . json_encode($courseurl) . ";
                    btn.className = 'streamdeck-back-to-course streamdeck-episode-nav-btn streamdeck-episode-nav-btn--ghost';
                    btn.textContent = '← ' + " . json_encode($label) . ";
                    container.insertBefore(btn, container.firstChild);
                });
            ");
        }
    }

    /**
     * Return a custom section edit form with a thumbnail image upload field.
     *
     * @param mixed $action The action attribute for the form.
     * @param array $customdata Custom data to pass to the form.
     * @return \moodleform
     */
    public function editsection_form($action, $customdata = []) {
        global $CFG;
        require_once($CFG->dirroot . '/course/editsection_form.php');
        require_once(__DIR__ . '/classes/form/editsection_form.php');
        if (!array_key_exists('course', $customdata)) {
            $customdata['course'] = $this->get_course();
        }
        return new \format_streamdeck_editsection_form($action, $customdata);
    }
}

/**
 * Callback to extend course navigation (optional).
 *
 * @param \navigation_node $parentnode
 * @param stdClass $course
 * @param \context_course $context
 */
function format_streamdeck_extend_course_navigation($parentnode, $course, $context) {
    // No-op for minimalist UI.
}

/**
 * Callback for course format options (optional, legacy).
 *
 * @param stdClass $course
 * @return array
 */
function format_streamdeck_get_format_options($course): array {
    return [];
}

/**
 * Callback for inplace editable API to update values.
 *
 * This callback is required for inline editing of section names to work.
 * It handles AJAX requests from the Moodle inplace_editable system.
 *
 * @param string $itemtype The type of item being edited (e.g., 'sectionname', 'sectionnamenl')
 * @param int $itemid The ID of the item being edited (section ID)
 * @param mixed $newvalue The new value submitted by the user
 * @return \core\output\inplace_editable The updated inplace_editable object
 */
function format_streamdeck_inplace_editable($itemtype, $itemid, $newvalue) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/course/lib.php');

    // Handle section name editing (both 'sectionname' and 'sectionnamenl' itemtypes).
    if ($itemtype === 'sectionname' || $itemtype === 'sectionnamenl') {
        // Retrieve the section record, ensuring it belongs to a streamdeck format course.
        $section = $DB->get_record_sql(
            'SELECT s.* FROM {course_sections} s JOIN {course} c ON s.course = c.id WHERE s.id = ? AND c.format = ?',
            [$itemid, 'streamdeck'],
            MUST_EXIST
        );

        // Delegate to the format's standard section name update method.
        return course_get_format($section->course)->inplace_editable_update_section_name($section, $itemtype, $newvalue);
    }
}

/**
 * Serve files for the streamdeck course format.
 *
 * This callback is required to serve section summary images and other course files
 * when the course is using the streamdeck format.
 *
 * @param stdClass $course The course object
 * @param stdClass $cm The course module object (can be null for course-level files)
 * @param context $context The context object
 * @param string $filearea The file area
 * @param array $args Extra arguments (file path components)
 * @param bool $forcedownload Whether to force download
 * @param array $options Additional options
 * @return bool False if file not found, does not return if successful
 */
function format_streamdeck_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $CFG;

    // Only handle course context files.
    if ($context->contextlevel != CONTEXT_COURSE) {
        return false;
    }

    // Require login to the course.
    require_login($course, true);

    // Handle uploaded section thumbnail images.
    if ($filearea === 'sectionimage') {
        $itemid = array_shift($args);
        $filename = array_pop($args);
        $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

        $fs = get_file_storage();
        $file = $fs->get_file(
            $context->id,
            'format_streamdeck',
            'sectionimage',
            $itemid,
            $filepath,
            $filename
        );

        if (!$file || $file->is_directory()) {
            return false;
        }

        send_stored_file($file, null, 0, $forcedownload, $options);
        return true;
    }

    // Handle section summary files (images embedded in section descriptions).
    if ($filearea === 'section') {
        // The itemid is the section number.
        $itemid = array_shift($args);
        $filename = array_pop($args);
        $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

        $fs = get_file_storage();
        $file = $fs->get_file(
            $context->id,
            'course',
            'section',
            $itemid,
            $filepath,
            $filename
        );

        if (!$file || $file->is_directory()) {
            return false;
        }

        // Send the file.
        send_stored_file($file, null, 0, $forcedownload, $options);
        return true; // Never reached if send_stored_file succeeds.
    }

    // For any other filearea, delegate to core course file serving.
    require_once($CFG->dirroot . '/course/lib.php');
    return course_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, $options);
}
