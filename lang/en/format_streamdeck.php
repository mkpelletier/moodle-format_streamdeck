<?php
/**
 * Streamdeck course format – English language strings.
 *
 * Core section editing strings (fallbacks for Topics delegation).
 * Streaming UX affordances (minimal, Netflix-inspired).
 * Includes fallback for orphaned sections to prevent get_string notices.
 *
 * @package    format_streamdeck
 * @category   string
 * @author     Mathieu Pelletier <mathieu@sats.ac.za>
 * @copyright  2025
 * @license    http://www.gnu.org/licenses/gpl-3.0.txt GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Streamdeck';
$string['plugin_description'] = 'A streaming-inspired, episode-based course format that applies familiar media UI patterns to Moodle to reduce cognitive load and keep learners focused on the content.';
$string['pluginname_help'] = 'Netflix-style streaming course format';
$string['resume'] = 'Resume';
$string['start'] = 'Start';
$string['moreinfo'] = 'More info';
$string['allmodules'] = 'Learning Episodes';

// Section names and fallbacks.
$string['sectionname'] = 'Section {$a}';
$string['section0name'] = 'General';
$string['orphanedsection'] = 'Orphaned activities'; // Fallback for sections beyond numsections.
$string['backtocourse'] = 'Learning episodes';

// ──── SECTION EDITING FALLBACKS (for Topics delegation) ────
// These prevent 'Invalid get_string() identifier' errors in edit menus (e.g., hide/show sections)
$string['addsections'] = 'Add section';
$string['currentsection'] = 'This section';
$string['deletesection'] = 'Delete section';
$string['editsection'] = 'Edit section';
$string['editsectionname'] = 'Edit section name';
$string['hidefromothers'] = 'Hide section';
$string['newsectionname'] = 'New name for section {$a}';
$string['sectionnameplain'] = 'Section'; // Separate plain label to avoid duplicate key warnings.
$string['showfromothers'] = 'Show section';
$string['sections'] = 'Sections';
$string['relatedactivities'] = 'Related Activities';
$string['relatedresources'] = 'Related Resources';
$string['play'] = 'Fullscreen';
$string['replay'] = 'Review lesson'; // Button text for completed lesson
$string['completed'] = 'Completed';
$string['todo'] = 'To do';
$string['lessonlabel'] = 'Lesson'; // Used for "Lesson 1", "Lesson 2"
$string['lessons'] = 'Lessons'; // If you need a plural for a heading
$string['episode'] = 'Lesson';

// ──── INPLACE EDITING STRINGS (for section updates) ────
$string['sectionnamenl'] = 'Section name (no link)';

// ──── SYLLABUS / MORE-INFO ────
$string['syllabus'] = 'Syllabus';
$string['syllabusheading'] = 'Course syllabus';
$string['syllabusintro'] = 'Overview';
$string['syllabusoutcomes'] = 'Learning outcomes';
$string['syllabusassessments'] = 'Assessment';
$string['syllabusmaterials'] = 'Materials';
$string['editsyllabus'] = 'Edit syllabus';
$string['syllabussaved'] = 'Syllabus saved.';
$string['syllabusschedule'] = 'Course schedule';
$string['viewcourseschedule'] = 'View course schedule';

$string['announcements'] = 'Announcements';
$string['liveclass'] = 'Live class';
$string['viewgrades'] = 'View grades';
$string['viewgradescta'] = 'View your grades';
$string['gradesmodal'] = 'Your grades';
$string['gradesempty'] = 'No grades available yet.';

// -----Module Settings---------
$string['teacherroles'] = 'Hero instructor roles';
$string['teacherroles_desc'] =
    'Select the roles whose users should appear as instructors in the Streamdeck course hero. ' .
    'By default, this includes the editing teacher and teacher roles where they exist.';
$string['instructorlabel'] = 'Instructor label (singular)';
$string['instructorlabel_desc'] = 'Label to display in the hero when there is one instructor (e.g., "Teacher", "Instructor", "Facilitator").';
$string['instructorlabel_default'] = 'Teacher';

$string['instructorlabelplural'] = 'Instructor label (plural)';
$string['instructorlabelplural_desc'] = 'Label to display in the hero when there are multiple instructors (e.g., "Teachers", "Instructors", "Facilitators").';
$string['instructorlabelplural_default'] = 'Teachers';

// ------ Forums and Discussions ------
$string['viewandreplytodiscussion'] = 'Reply';

// Privacy (required for Moodle 5+ compliance)
$string['privacy:metadata'] = 'The Streamdeck format does not store any personal data.';