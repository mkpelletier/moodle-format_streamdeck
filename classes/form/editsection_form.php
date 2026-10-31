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
 * for uploading section thumbnail images, and lists the section's
 * activities in the featured activity select.
 *
 * @package    format_streamdeck
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/editsection_form.php');

/**
 * Edit section form with thumbnail upload for format_streamdeck.
 */
class format_streamdeck_editsection_form extends editsection_form {
    /** @var array Editor options for "About this episode": text only, no file uploads. */
    private const ABOUT_EDITOR_OPTIONS = ['maxfiles' => 0, 'noclean' => false, 'trusttext' => false];

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

        // The format option lists the whole course; offer only this section's activities.
        if ($mform->elementExists('featuredcmid') && !empty($this->_customdata['cs'])) {
            $format = course_get_format($this->_customdata['course']);
            $select = $mform->getElement('featuredcmid');
            $select->removeOptions();
            foreach ($format->get_featured_activity_choices($this->_customdata['cs']->section) as $cmid => $name) {
                $select->addOption($name, $cmid);
            }
        }

        // The "About this episode" text is edited in a rich text editor next to its switch; the format option holds the HTML.
        if ($mform->elementExists('about')) {
            $editor = $mform->createElement(
                'editor',
                'about_editor',
                get_string('about', 'format_streamdeck'),
                ['rows' => 8],
                self::ABOUT_EDITOR_OPTIONS
            );
            $mform->insertElementBefore($editor, 'about');
            $mform->removeElement('about');
            $mform->removeElement('aboutformat');
            $mform->setType('about_editor', PARAM_RAW);
            $mform->addHelpButton('about_editor', 'about', 'format_streamdeck');
            foreach (['abouttitle', 'abouticon', 'about_editor'] as $element) {
                $mform->hideIf($element, 'showabout', 'notchecked');
            }
        }

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

        if (!empty($this->_customdata['cs']) && !isset($defaultvalues->about)) {
            $options = course_get_format($this->_customdata['course'])->get_format_options($this->_customdata['cs']);
            $defaultvalues->about = $options['about'] ?? '';
            $defaultvalues->aboutformat = $options['aboutformat'] ?? FORMAT_HTML;
        }
        $defaultvalues->about_editor = [
            'text' => $defaultvalues->about ?? '',
            'format' => $defaultvalues->aboutformat ?? FORMAT_HTML,
        ];

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

        // A hidden editor (switch off) can come back without its text: keep what is stored rather than let the
        // null that core fills in for missing options wipe it.
        $editor = $data->about_editor ?? [];
        unset($data->about_editor);
        if (array_key_exists('text', $editor)) {
            $format = (int) ($editor['format'] ?? FORMAT_HTML);
            $data->about = clean_text($editor['text'], $format);
            $data->aboutformat = $format;
        } else {
            unset($data->about, $data->aboutformat);
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
