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
 * Defines the format class (format_streamdeck extending core_courseformat\base) for Moodle 5.1+.
 * Handles section management with robust fallbacks for orphaned/unused sections.
 *
 * @package    format_streamdeck
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Streamdeck course format class.
 *
 * Extends core base for Moodle 5.1+.
 * Supports reactive components and inplace editing.
 */
class format_streamdeck extends \core_courseformat\base {
    /** @var string[] Font Awesome (free, solid) icons offered for the "About this episode" card. */
    public const ABOUT_ICONS = [
        'circle-info', 'bullseye', 'list-check', 'lightbulb', 'graduation-cap', 'book-open',
        'compass', 'star', 'flag', 'brain', 'puzzle-piece', 'clipboard-list',
    ];

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
     * Learners move through the course with the hero, the episode cards and the episode navigation, so the drawer
     * is only offered to editors, where it helps reorganise the course.
     *
     * @return bool
     */
    public function uses_course_index(): bool {
        global $PAGE;
        return $PAGE->user_is_editing();
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
                'seasonlabel' => [
                    'default' => '',
                    'type' => PARAM_TEXT,
                ],
                'accentcolour' => [
                    'default' => '',
                    'type' => PARAM_TEXT,
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

            $courseformatoptions['seasonlabel']['label'] = get_string('seasonlabel', 'format_streamdeck');
            $courseformatoptions['seasonlabel']['help'] = 'seasonlabel';
            $courseformatoptions['seasonlabel']['help_component'] = 'format_streamdeck';
            $courseformatoptions['seasonlabel']['element_type'] = 'text';
            $courseformatoptions['seasonlabel']['element_attributes'] = [
                ['placeholder' => get_string('season', 'format_streamdeck'), 'maxlength' => 50],
            ];

            $courseformatoptions['accentcolour']['label'] = get_string('accentcolour', 'format_streamdeck');
            $courseformatoptions['accentcolour']['help'] = 'accentcolour';
            $courseformatoptions['accentcolour']['help_component'] = 'format_streamdeck';
            $courseformatoptions['accentcolour']['element_type'] = 'text';
            $courseformatoptions['accentcolour']['element_attributes'] = [
                ['placeholder' => \format_streamdeck\local\accent_colour::DEFAULT, 'maxlength' => 7, 'size' => 9],
            ];
        }
        return $courseformatoptions;
    }

    /**
     * Course and section settings: the course settings get a colour picker beside the accent colour field.
     *
     * @param MoodleQuickForm $mform
     * @param bool $forsection
     * @return array
     */
    public function create_edit_form_elements(&$mform, $forsection = false) {
        global $PAGE;
        $elements = parent::create_edit_form_elements($mform, $forsection);
        if (!$forsection && $mform->elementExists('accentcolour')) {
            $PAGE->requires->js_call_amd('format_streamdeck/accent_picker', 'init', [
                'id_accentcolour',
                \format_streamdeck\local\accent_colour::DEFAULT,
            ]);
        }
        return $elements;
    }

    /**
     * The accent colour must be a colour (#rrggbb), or empty for the default.
     *
     * @param array $data
     * @param array $files
     * @param array $errors
     * @return array
     */
    public function edit_form_validation($data, $files, $errors) {
        $errors = parent::edit_form_validation($data, $files, $errors);
        $accent = trim($data['accentcolour'] ?? '');
        if ($accent !== '' && !\format_streamdeck\local\accent_colour::is_valid($accent)) {
            $errors['accentcolour'] = get_string('accentcolourinvalid', 'format_streamdeck');
        }
        return $errors;
    }

    /**
     * Section-level format options for the Streamdeck format.
     *
     * The featured activity is edited through format_streamdeck_editsection_form, which builds its select element.
     * All but the "About this episode" text are cached in the course modinfo, so they are read as section_info
     * properties without a query.
     *
     * @param bool $foreditform Whether the options are for the section edit form.
     * @return array Format options.
     */
    public function section_format_options($foreditform = false): array {
        $options = [
            'seasonname' => [
                'default' => '',
                'type' => PARAM_TEXT,
                'cache' => true,
                'cachedefault' => '',
            ],
            'duration' => [
                'default' => 0,
                'type' => PARAM_INT,
                'cache' => true,
                'cachedefault' => 0,
            ],
            'featuredcmid' => [
                'default' => 0,
                'type' => PARAM_INT,
                'cache' => true,
                'cachedefault' => 0,
            ],
            'showabout' => [
                'default' => 0,
                'type' => PARAM_BOOL,
                'cache' => true,
                'cachedefault' => 0,
            ],
            // Only the episode page reads these, so they are not cached.
            'abouttitle' => [
                'default' => '',
                'type' => PARAM_TEXT,
            ],
            'abouticon' => [
                'default' => '',
                'type' => PARAM_ALPHANUMEXT,
            ],
            'about' => [
                'default' => '',
                'type' => PARAM_RAW,
            ],
            'aboutformat' => [
                'default' => FORMAT_HTML,
                'type' => PARAM_INT,
            ],
        ];
        if ($foreditform) {
            $label = $this->get_season_label();
            $options['seasonname'] += [
                'label' => get_string('seasonname', 'format_streamdeck', $label),
                'help' => 'seasonname',
                'help_component' => 'format_streamdeck',
                'element_type' => 'text',
                'element_attributes' => [['maxlength' => 100]],
            ];
            $options['showabout'] += [
                'label' => get_string('showabout', 'format_streamdeck'),
                'help' => 'showabout',
                'help_component' => 'format_streamdeck',
                'element_type' => 'advcheckbox',
            ];
            $options['abouttitle'] += [
                'label' => get_string('abouttitle', 'format_streamdeck'),
                'help' => 'abouttitle',
                'help_component' => 'format_streamdeck',
                'element_type' => 'text',
                'element_attributes' => [['maxlength' => 100, 'placeholder' => get_string('about', 'format_streamdeck')]],
            ];
            $icons = ['' => get_string('none')];
            foreach (self::ABOUT_ICONS as $icon) {
                $icons[$icon] = get_string('abouticon:' . $icon, 'format_streamdeck');
            }
            $options['abouticon'] += [
                'label' => get_string('abouticon', 'format_streamdeck'),
                'help' => 'abouticon',
                'help_component' => 'format_streamdeck',
                'element_type' => 'select',
                'element_attributes' => [$icons],
            ];
            // Edited through the about_editor element added by format_streamdeck_editsection_form.
            $options['about'] += ['label' => '', 'element_type' => 'hidden'];
            $options['aboutformat'] += ['label' => '', 'element_type' => 'hidden'];
            $options['duration'] += [
                'label' => get_string('duration', 'format_streamdeck'),
                'help' => 'duration',
                'help_component' => 'format_streamdeck',
                'element_type' => 'text',
                'element_attributes' => [['size' => 4]],
            ];
            // Every activity in the course is a valid value; the section form narrows the list to its own section.
            $options['featuredcmid'] += [
                'label' => get_string('featuredactivity', 'format_streamdeck'),
                'help' => 'featuredactivity',
                'help_component' => 'format_streamdeck',
                'element_type' => 'select',
                'element_attributes' => [$this->get_featured_activity_choices()],
            ];
        }
        return $options;
    }

    /**
     * What this course calls a season, e.g. "Season", "Part" or "Module".
     *
     * @return string
     */
    public function get_season_label(): string {
        $label = $this->courseid ? trim((string) ($this->get_format_options()['seasonlabel'] ?? '')) : '';
        if ($label === '') {
            return get_string('season', 'format_streamdeck');
        }
        return format_string($label, true, ['context' => \context_course::instance($this->courseid)]);
    }

    /**
     * Group the episodes into seasons.
     *
     * A section whose season name is set starts a new season, which runs until the next such section, so seasons
     * follow the order of the sections. Episodes before the first named season form an untitled opening season.
     *
     * @return array List of ['name' => string, 'sections' => int[]]; empty when the course uses no seasons.
     */
    public function get_seasons(): array {
        if (!$this->courseid) {
            return [];
        }
        $context = \context_course::instance($this->courseid);
        $seasons = [];
        $named = false;
        foreach ($this->get_modinfo()->get_section_info_all() as $sectioninfo) {
            if ($sectioninfo->section == 0 || !empty($sectioninfo->component)) {
                continue;
            }
            $name = trim((string) $sectioninfo->seasonname);
            if ($name !== '' || !$seasons) {
                $seasons[] = ['name' => $name === '' ? '' : format_string($name, true, ['context' => $context]), 'sections' => []];
                $named = $named || $name !== '';
            }
            $seasons[count($seasons) - 1]['sections'][] = (int) $sectioninfo->section;
        }
        return $named ? $seasons : [];
    }

    /**
     * Activities that can be featured on an episode page: everything with a page of its own, except labels and resources.
     *
     * @param int|null $sectionnum Limit to one section, or null for the whole course.
     * @return array cmid => name, starting with 0 => "Automatic".
     */
    public function get_featured_activity_choices(?int $sectionnum = null): array {
        $choices = [0 => get_string('featuredactivityauto', 'format_streamdeck')];
        if (!$this->courseid) {
            return $choices;
        }
        foreach ($this->get_modinfo()->get_cms() as $cm) {
            if ($cm->deletioninprogress || !$cm->has_view() || in_array($cm->modname, ['label', 'resource'], true)) {
                continue;
            }
            if ($sectionnum === null || $cm->sectionnum == $sectionnum) {
                $choices[$cm->id] = $cm->get_formatted_name();
            }
        }
        return $choices;
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
    }

    /**
     * Set up activity pages for this course format.
     *
     * Quiz pages get the circular timer, which needs the effective time limit (overrides included).
     *
     * @param \moodle_page $page The page object.
     */
    public function page_set_cm(\moodle_page $page): void {
        global $USER;
        parent::page_set_cm($page);

        if ($page->cm->modname !== 'quiz' || $page->user_is_editing()) {
            return;
        }

        $timelimit = 0;
        try {
            $timelimit = (int) \mod_quiz\quiz_settings::create_for_cmid($page->cm->id, $USER->id)->get_quiz()->timelimit;
        } catch (\moodle_exception $e) {
            debugging('STREAMDECK: Unable to read quiz time limit: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        $page->requires->js_call_amd('format_streamdeck/main', 'init', [['quiztimelimit' => $timelimit]]);
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
 * User preferences this plugin lets the user set through the web services.
 *
 * @return array
 */
function format_streamdeck_user_preferences(): array {
    return [
        \format_streamdeck\local\colour_mode::PREFERENCE => [
            'type' => PARAM_ALPHA,
            'null' => NULL_NOT_ALLOWED,
            'default' => \format_streamdeck\local\colour_mode::get_site_default(),
            'choices' => \format_streamdeck\local\colour_mode::get_modes(),
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
    ];
}

/**
 * Fragment: an episode's discussion thread, to open from the list of discussions or to refresh after a post.
 *
 * The fragment API has already logged the user in to the forum's context.
 *
 * @param array $args context (the forum's module context), discussionid, backtolist.
 * @return string
 */
function format_streamdeck_output_fragment_discussion(array $args): string {
    global $OUTPUT;

    $context = $args['context'];
    if ($context->contextlevel != CONTEXT_MODULE) {
        throw new \moodle_exception('invalidcontext');
    }
    [, $cm] = get_course_and_cm_from_cmid($context->instanceid, 'forum');
    $data = \format_streamdeck\output\discussion::export_thread($cm, (int) $args['discussionid']);
    if ($data === null) {
        return '';
    }
    $data['backtolist'] = !empty($args['backtolist']);
    return $OUTPUT->render_from_template('format_streamdeck/local/discussion', $data);
}

/**
 * Fragment: a forum's panel (the list of discussions, or the announcements feed) for the hero's buttons, and to
 * refresh it after a post.
 *
 * @param array $args context (the forum's module context), alwayslist, view ('news' for the announcements feed).
 * @return string
 */
function format_streamdeck_output_fragment_forum(array $args): string {
    $context = $args['context'];
    if ($context->contextlevel != CONTEXT_MODULE) {
        throw new \moodle_exception('invalidcontext');
    }
    [, $cm] = get_course_and_cm_from_cmid($context->instanceid, 'forum');
    if (($args['view'] ?? '') === 'news') {
        return \format_streamdeck\output\discussion::render_news($cm);
    }
    return \format_streamdeck\output\discussion::render_view($cm, !empty($args['alwayslist']));
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
