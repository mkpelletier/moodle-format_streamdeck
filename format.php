<?php
/**
 * Streamdeck course format entry point.
 *
 * Moodle 5.0+ compliant.
 * - Viewing mode  → Custom Netflix-style UI
 * - Editing mode  → Core Moodle editing UI (drawer + activity cards) – NO DELEGATION NEEDED
 *
 * @package    format_streamdeck
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/licenses/gpl-3.0.txt GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $PAGE, $COURSE;

// ------------------------------------------------------------------
// 1. Legacy topic → section redirect (keep for old links)
// ------------------------------------------------------------------
if ($topic = optional_param('topic', 0, PARAM_INT)) {
    $url = $PAGE->url;
    $url->param('section', $topic);
    redirect($url);
}

// ------------------------------------------------------------------
// 2. Get the format instance (autoloads format_streamdeck from lib.php)
// ------------------------------------------------------------------
    $format = course_get_format($COURSE);

// ------------------------------------------------------------------
// 3. Standard marker handling
// ------------------------------------------------------------------
$context = context_course::instance($COURSE->id);
if ($marker = optional_param('marker', -1, PARAM_INT)) {
    if ($marker >= 0 && has_capability('moodle/course:setcurrentsection', $context) && confirm_sesskey()) {
        course_set_marker($COURSE->id, $marker);
    }
}

// Ensure section 0 exists
course_create_sections_if_missing($COURSE, [0]);

// ------------------------------------------------------------------
// 4. Load our JS only in viewing mode
// ------------------------------------------------------------------
if (!$PAGE->user_is_editing()) {
    $PAGE->requires->js_call_amd('format_streamdeck/main', 'init');
}

// 5. Editing mode → use core course editor with streamdeck format.
if ($PAGE->user_is_editing()) {
    try {
        // Use the core content class with streamdeck format instance.
        // This ensures the reactive course editor JavaScript initializes properly.
        $outputclass = \core_courseformat\output\local\content::class;
        $widget = new $outputclass($format);

        // Use the streamdeck renderer (which extends section_renderer).
        $renderer = $PAGE->get_renderer('format_streamdeck');
        echo $renderer->render($widget);

    } catch (\Throwable $e) {
        // Hard safety net: if something goes wrong, fall back to core default behaviour.
        debugging('STREAMDECK: Edit-mode rendering failed, falling back to core: ' . $e->getMessage(), DEBUG_DEVELOPER);
        echo $PAGE->get_renderer('core')->course_content_header($COURSE);
        echo $PAGE->get_renderer('core')->course_content_footer();
    }
    return;
}

// ------------------------------------------------------------------
// 6. Viewing mode → render our Netflix UI via the custom content class
// ------------------------------------------------------------------
$outputclass = \format_streamdeck\output\courseformat\content::class;
$widget = new $outputclass($format);

$renderer = $PAGE->get_renderer('format_streamdeck');
echo $renderer->render($widget);