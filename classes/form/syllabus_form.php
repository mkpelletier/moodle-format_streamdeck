<?php
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
        $mform->addElement('editor', 'introhtml', get_string('syllabusintro', 'format_streamdeck'), null, $editoroptions);
        $mform->setType('introhtml', PARAM_RAW);

        // Outcomes.
        $mform->addElement('header', 'outcomesheader', get_string('syllabusoutcomes', 'format_streamdeck'));
        $mform->addElement('editor', 'outcomeshtml', get_string('syllabusoutcomes', 'format_streamdeck'), null, $editoroptions);
        $mform->setType('outcomeshtml', PARAM_RAW);

        // Assessments.
        $mform->addElement('header', 'assessmentsheader', get_string('syllabusassessments', 'format_streamdeck'));
        $mform->addElement('editor', 'assessmentshtml', get_string('syllabusassessments', 'format_streamdeck'), null, $editoroptions);
        $mform->setType('assessmentshtml', PARAM_RAW);

        // Study materials.
        $mform->addElement('header', 'materialsheader', get_string('syllabusmaterials', 'format_streamdeck'));
        $mform->addElement('editor', 'materialshtml', get_string('syllabusmaterials', 'format_streamdeck'), null, $editoroptions);
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