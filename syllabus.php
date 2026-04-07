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
 * Syllabus editor for Streamdeck course format.
 *
 * @package    format_streamdeck
 * @copyright  2025 Mathieu Pelletier <mathieu@sats.ac.za>
 * @license    http://www.gnu.org/licenses/gpl-3.0.txt GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use format_streamdeck\form\syllabus_form;

$id = optional_param('id', 0, PARAM_INT); // Course id.

if (empty($id)) {
    // No course id – go back somewhere safe.
    redirect(new moodle_url('/course/index.php'));
}

$course = get_course($id);
require_login($course);

$context = context_course::instance($course->id);
require_capability('moodle/course:update', $context);

$PAGE->set_context($context);
$PAGE->set_pagelayout('course');
$PAGE->set_url('/course/format/streamdeck/syllabus.php', ['id' => $course->id]);
$PAGE->set_title(get_string('syllabusheading', 'format_streamdeck'));
$PAGE->set_heading(format_string($course->fullname));

// Instantiate the form with explicit action URL.
$actionurl = new moodle_url('/course/format/streamdeck/syllabus.php', ['id' => $course->id]);
$mform = new syllabus_form($actionurl);

// Fetch existing record (if any).
global $DB;
$record = $DB->get_record('format_streamdeck_syllabus', ['courseid' => $course->id]);

if ($mform->is_cancelled()) {
    $courseurl = new moodle_url('/course/view.php', ['id' => $course->id]);
    redirect($courseurl);
} else if ($data = $mform->get_data()) {
    $now = time();

    $tostore = (object)[
        'courseid'        => $course->id,
        'introhtml'       => $data->introhtml['text'] ?? '',
        'outcomeshtml'    => $data->outcomeshtml['text'] ?? '',
        'assessmentshtml' => $data->assessmentshtml['text'] ?? '',
        'materialshtml'   => $data->materialshtml['text'] ?? '',
        'timemodified'    => $now,
    ];

    try {
        if ($record) {
            $tostore->id = $record->id;
            $DB->update_record('format_streamdeck_syllabus', $tostore);
        } else {
            $DB->insert_record('format_streamdeck_syllabus', $tostore);
        }

        // Redirect back to course with success message.
        $courseurl = new moodle_url('/course/view.php', ['id' => $course->id]);
        redirect($courseurl, get_string('syllabussaved', 'format_streamdeck'), null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (\Throwable $e) {
        debugging('STREAMDECK: Error saving syllabus: ' . $e->getMessage(), DEBUG_DEVELOPER);
        // Stay on form; could add notification here if desired.
    }
} else {
    // Populate form with existing values.
    $defaults = ['courseid' => $course->id];
    if ($record) {
        $defaults['introhtml']       = ['text' => $record->introhtml, 'format' => FORMAT_HTML];
        $defaults['outcomeshtml']    = ['text' => $record->outcomeshtml, 'format' => FORMAT_HTML];
        $defaults['assessmentshtml'] = ['text' => $record->assessmentshtml, 'format' => FORMAT_HTML];
        $defaults['materialshtml']   = ['text' => $record->materialshtml, 'format' => FORMAT_HTML];
    }
    $mform->set_data($defaults);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('syllabusheading', 'format_streamdeck'));
$mform->display();
echo $OUTPUT->footer();
