<?php
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

defined('MOODLE_INTERNAL') || die();

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
            // This method handles validation, permissions, database updates, and returns the inplace_editable object.
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