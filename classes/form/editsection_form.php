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
 * Custom edit section form for format_streamdeck.
 *
 * Extends the core editsection_form to add a filemanager element
 * for uploading section thumbnail images.
 *
 * @package    format_streamdeck
 * @copyright  2025
 * @license    http://www.gnu.org/licenses/gpl-3.0.txt GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/editsection_form.php');

/**
 * Edit section form with thumbnail upload for format_streamdeck.
 */
class format_streamdeck_editsection_form extends editsection_form {
    /** @var array Filemanager options for the section thumbnail. */
    private static $fileoptions = [
        'subdirs' => 0,
        'maxfiles' => 1,
        'accepted_types' => ['image'],
    ];

    /**
     * Form definition. Adds the thumbnail filemanager after the standard fields.
     */
    public function definition() {
        parent::definition();

        $mform = $this->_form;

        $mform->addElement(
            'header',
            'sectionimagehdr',
            get_string('sectionimage', 'format_streamdeck')
        );

        $mform->addElement(
            'filemanager',
            'sectionimage_filemanager',
            get_string('sectionimage', 'format_streamdeck'),
            null,
            self::$fileoptions
        );

        $mform->addHelpButton('sectionimage_filemanager', 'sectionimage', 'format_streamdeck');
    }

    /**
     * Prepare the draft area for the section image before the form is displayed.
     *
     * @param array|object $defaultvalues The default values for the form.
     */
    public function set_data($defaultvalues) {
        $defaultvalues = (object)$defaultvalues;

        if (!empty($defaultvalues->id)) {
            $context = \context_course::instance($defaultvalues->course);
            $draftitemid = file_get_submitted_draft_itemid('sectionimage_filemanager');
            file_prepare_draft_area(
                $draftitemid,
                $context->id,
                'format_streamdeck',
                'sectionimage',
                $defaultvalues->id,
                self::$fileoptions
            );
            $defaultvalues->sectionimage_filemanager = $draftitemid;
        }

        parent::set_data($defaultvalues);
    }

    /**
     * Save the uploaded file after form submission.
     *
     * @return object|false The form data, or false if cancelled.
     */
    public function get_data() {
        $data = parent::get_data();
        if ($data === null || $data === false) {
            return $data;
        }

        if (!empty($data->id) && isset($data->sectionimage_filemanager)) {
            $context = \context_course::instance($data->course);
            file_save_draft_area_files(
                $data->sectionimage_filemanager,
                $context->id,
                'format_streamdeck',
                'sectionimage',
                $data->id,
                self::$fileoptions
            );
        }

        return $data;
    }
}
