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
 * Syllabus editing form for Streamdeck course format.
 *
 * Each syllabus section uses Moodle's standard HTML editor (TinyMCE / Atto).
 *
 * @package    format_streamdeck
 * @subpackage form
 * @author     Mathieu Pelletier <mathieu@sats.ac.za>
 * @copyright  2025
 * @license    http://www.gnu.org/licenses/gpl-3.0.txt GNU GPL v3 or later
 */

namespace format_streamdeck\form;

defined('MOODLE_INTERNAL') || die();

use moodleform;

require_once($CFG->libdir . '/formslib.php');

/**
 * Syllabus editing form for the Streamdeck course format.
 *
 * @package    format_streamdeck
 */
class syllabus_form extends moodleform {
    /**
     * Define syllabus editing elements.
     */
    public function definition() {
        global $COURSE;

        $mform = $this->_form;

        $mform->addElement('hidden', 'courseid', $this->_customdata['courseid'] ?? 0);
        $mform->setType('courseid', PARAM_INT);

        $editoroptions = [
            'maxfiles'  => 0,
            'trusttext' => true,
            'context'   => \context_course::instance($COURSE->id),
        ];

        // Introduction / welcome.
        $mform->addElement('header', 'introheader', get_string('syllabusintro', 'format_streamdeck'));
        $mform->addElement(
            'editor',
            'introhtml',
            get_string('syllabusintro', 'format_streamdeck'),
            null,
            $editoroptions
        );
        $mform->setType('introhtml', PARAM_RAW);

        // Outcomes.
        $mform->addElement('header', 'outcomesheader', get_string('syllabusoutcomes', 'format_streamdeck'));
        $mform->addElement(
            'editor',
            'outcomeshtml',
            get_string('syllabusoutcomes', 'format_streamdeck'),
            null,
            $editoroptions
        );
        $mform->setType('outcomeshtml', PARAM_RAW);

        // Assessments.
        $mform->addElement('header', 'assessmentsheader', get_string('syllabusassessments', 'format_streamdeck'));
        $mform->addElement(
            'editor',
            'assessmentshtml',
            get_string('syllabusassessments', 'format_streamdeck'),
            null,
            $editoroptions
        );
        $mform->setType('assessmentshtml', PARAM_RAW);

        // Study materials.
        $mform->addElement('header', 'materialsheader', get_string('syllabusmaterials', 'format_streamdeck'));
        $mform->addElement(
            'editor',
            'materialshtml',
            get_string('syllabusmaterials', 'format_streamdeck'),
            null,
            $editoroptions
        );
        $mform->setType('materialshtml', PARAM_RAW);

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * Simple debug-friendly validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = [];
        try {
            // No required fields for now – keep it flexible.
            return $errors;
        } catch (\Throwable $e) {
            debugging('STREAMDECK: syllabus_form validation error: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return $errors;
        }
    }
}
