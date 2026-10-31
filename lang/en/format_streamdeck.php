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
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['about'] = 'About this episode';
$string['about_help'] = 'What learners will get out of this episode, such as its learning objectives. It is shown on the episode page, under the related resources and activities, when "Show About this episode" is ticked.';
$string['abouticon'] = 'Icon';
$string['abouticon:book-open'] = 'Open book';
$string['abouticon:brain'] = 'Brain';
$string['abouticon:bullseye'] = 'Target';
$string['abouticon:circle-info'] = 'Information';
$string['abouticon:clipboard-list'] = 'Clipboard';
$string['abouticon:compass'] = 'Compass';
$string['abouticon:flag'] = 'Flag';
$string['abouticon:graduation-cap'] = 'Graduation cap';
$string['abouticon:lightbulb'] = 'Light bulb';
$string['abouticon:list-check'] = 'Checklist';
$string['abouticon:puzzle-piece'] = 'Puzzle piece';
$string['abouticon:star'] = 'Star';
$string['abouticon_help'] = 'An icon shown before the title of the "About this episode" card.';
$string['abouttitle'] = 'Title';
$string['abouttitle_help'] = 'The heading of the card, such as "Learning objectives" or "In this episode". Leave empty for "About this episode".';
$string['accentcolour'] = 'Accent colour';
$string['accentcolour_help'] = 'The colour of buttons, progress bars, badges and highlights across the course. Leave empty for the default red. The colour is adjusted slightly in light and dark mode where needed, so that it stays visible against the page, and text on it is black or white, whichever is easier to read.';
$string['accentcolourinvalid'] = 'Enter a colour as # followed by six hexadecimal digits, for example #1db954, or leave it empty for the default.';
$string['accentcolourreset'] = 'Use the default';
$string['activitiescomplete'] = '{$a->complete}/{$a->total} complete';
$string['activitytoolbar'] = 'Activity navigation';
$string['addreply'] = 'Add a reply…';
$string['addsections'] = 'Add section';
$string['alldiscussions'] = 'All discussions';
$string['allmodules'] = 'Learning Episodes';
$string['announcements'] = 'Announcements';
$string['announcementsall'] = 'See all announcements';
$string['appearance'] = 'Appearance';
$string['assessment'] = 'Assessment';
$string['backtocourse'] = 'Learning episodes';
$string['backtoepisode'] = 'Back to {$a}';
$string['colourmode:auto'] = 'Auto';
$string['colourmode:dark'] = 'Dark';
$string['colourmode:light'] = 'Light';
$string['completed'] = 'Completed';
$string['composerallgroups'] = 'All my groups';
$string['composerattach'] = 'Attach files';
$string['composerfollow'] = 'Follow';
$string['composerformatting'] = 'Formatting';
$string['composermore'] = 'More';
$string['composerpin'] = 'Pin';
$string['composerprivate'] = 'Private';
$string['composersendnow'] = 'Send now';
$string['composertags'] = 'Tags';
$string['currentsection'] = 'This section';
$string['defaultcolourmode'] = 'Default colour mode';
$string['defaultcolourmode_desc'] = 'The colour mode learners see in Streamdeck courses until they choose their own. ' .
    'Auto follows the device setting. Where Boost\'s own colour modes are switched on, they take over.';
$string['deletesection'] = 'Delete section';
$string['discuss'] = 'Discuss';
$string['discussions'] = 'Discussions';
$string['display'] = 'Display';
$string['display:standard'] = 'Standard';
$string['display:tv'] = 'TV';
$string['duesoon'] = 'Due {$a}';
$string['duration'] = 'Running time (minutes)';
$string['duration_help'] = 'How long this episode takes, in minutes. It is shown on the episode card and the episode page. Leave at 0 to show nothing.';
$string['editsectionname'] = 'Edit section name';
$string['editsyllabus'] = 'Edit syllabus';
$string['enabledrawertoggle'] = 'Show right drawer toggle';
$string['enabledrawertoggle_desc'] = 'When enabled, the right drawer toggle (blocks column) will be visible in view mode, ' .
    'allowing students to access the block drawer.';
$string['enabledrawertoggle_help'] = 'When enabled, a toggle button for the right drawer (blocks column) ' .
    'will be visible in course view mode. This allows students to access blocks ' .
    'such as calendar, recent activity, and other sidebar content.';
$string['episode'] = 'Lesson';
$string['episodeempty'] = 'Nothing has been added to this episode yet.';
$string['episodenavigation'] = 'Episode navigation';
$string['episodesidebar'] = 'Related to this episode';
$string['featuredactivity'] = 'Featured activity';
$string['featuredactivity_help'] = 'The activity shown centre stage on this episode\'s page. Automatic picks a YouTube link first, ' .
    'then the first lesson, SCORM package, external tool, URL or H5P activity, and if the section has none of those, ' .
    'its first quiz, assignment or workshop.';
$string['featuredactivityauto'] = 'Automatic';
$string['generalforum'] = 'Discussion forum';
$string['gradesempty'] = 'No grades available yet.';
$string['gradesmodal'] = 'Your grades';
$string['herofont'] = 'Hero title font';
$string['herofont_desc'] = 'Select the font family used for the course title in the hero area.';
$string['herofont_help'] = 'Choose the font used for the course title in the hero banner. ' .
    'Google Fonts (Lobster, Montserrat, Poppins, etc.) are loaded automatically. ' .
    'System Default uses your browser\'s default sans-serif font.';
$string['hidefromothers'] = 'Hide section';
$string['instructorlabel'] = 'Instructor label (singular)';
$string['instructorlabel_default'] = 'Teacher';
$string['instructorlabel_desc'] = 'Label to display in the hero when there is one instructor ' .
    '(e.g., "Teacher", "Instructor", "Facilitator").';
$string['instructorlabelplural'] = 'Instructor label (plural)';
$string['instructorlabelplural_default'] = 'Teachers';
$string['instructorlabelplural_desc'] = 'Label to display in the hero when there are multiple instructors ' .
    '(e.g., "Teachers", "Instructors", "Facilitators").';
$string['lessonlabel'] = 'Lesson';
$string['lessons'] = 'Lessons';
$string['liveclass'] = 'Live class';
$string['locked'] = 'Locked';
$string['moreinfo'] = 'More info';
$string['new'] = 'New';
$string['newsectionname'] = 'New name for section {$a}';
$string['nextepisode'] = 'Next episode';
$string['nextepisodelocked'] = 'Complete the activities above to unlock the next episode.';
$string['noannouncements'] = 'No announcements yet.';
$string['nodiscussions'] = 'No discussions yet.';
$string['numnewannouncements'] = '{$a} new';
$string['numreplies'] = '{$a} replies';
$string['onenewannouncement'] = '1 new';
$string['onereply'] = '1 reply';
$string['openfulldiscussion'] = 'Open in the forum';
$string['orphanedsection'] = 'Orphaned activities';
$string['participants'] = 'Participants';
$string['percentcomplete'] = '{$a}% complete';
$string['play'] = 'Fullscreen';
$string['playfullscreen'] = 'Play full screen';
$string['playfullscreenhint'] = 'Press OK to play full screen. Press Back to return.';
$string['plugin_description'] = 'A streaming-inspired, episode-based course format that applies familiar media UI ' .
    'patterns to Moodle to reduce cognitive load and keep learners focused on the content.';
$string['pluginname'] = 'Streamdeck';
$string['pluginname_help'] = 'Netflix-style streaming course format';
$string['postannouncement'] = 'Post an announcement';
$string['previousepisode'] = 'Previous episode';
$string['privacy:metadata:googlefonts'] = 'The course page loads the hero title font from Google Fonts.';
$string['privacy:metadata:googlefonts:ipaddress'] = 'The IP address of the browser requesting the font.';
$string['privacy:metadata:preference:colourmode'] = 'The colour mode (light, dark or auto) chosen for Streamdeck courses.';
$string['relatedactivities'] = 'Related Activities';
$string['relatedresources'] = 'Related Resources';
$string['replay'] = 'Review lesson';
$string['resume'] = 'Resume';
$string['season'] = 'Season';
$string['seasonepisode'] = '1 episode';
$string['seasonepisodes'] = '{$a} episodes';
$string['seasonlabel'] = 'Season label';
$string['seasonlabel_help'] = 'What this course calls a group of episodes, such as "Season", "Part" or "Module". It is shown on the season tabs and the episode page. Leave empty for "Season".';
$string['seasonname'] = 'Starts a new {$a}';
$string['seasonname_help'] = 'To group episodes, give the first episode of each group a name here, such as "Nouns" or "Verbs". ' .
    'The group runs until the next episode that starts one. Leave empty to continue the current group. ' .
    'When no episode starts a group, the course page shows all episodes together.';
$string['seasonopening'] = 'Introduction';
$string['section0name'] = 'General';
$string['sectionimage'] = 'Section thumbnail';
$string['sectionimage_help'] = 'Upload an image to use as the thumbnail for this section on the course page. If no image is uploaded, the first image from the section description will be used instead.';
$string['sectionname'] = 'Section {$a}';
$string['sectionnamenl'] = 'Section name (no link)';
$string['sectionnameplain'] = 'Section';
$string['sections'] = 'Sections';
$string['showabout'] = 'Show "About this episode"';
$string['showabout_help'] = 'Show a card about this episode, such as its learning objectives, on the episode page. Learners can fold the card away.';
$string['showfromothers'] = 'Show section';
$string['stageaction:continue'] = 'Continue';
$string['stageaction:open'] = 'Open';
$string['stageaction:review'] = 'Review';
$string['stageaction:start'] = 'Start';
$string['start'] = 'Start';
$string['startdiscussion'] = 'Start a discussion';
$string['streamdeck:changesectionoptions'] = 'Change Streamdeck section options';
$string['syllabus'] = 'Syllabus';
$string['syllabusassessments'] = 'Assessment';
$string['syllabusheading'] = 'Course syllabus';
$string['syllabusintro'] = 'Overview';
$string['syllabusmaterials'] = 'Materials';
$string['syllabusoutcomes'] = 'Learning outcomes';
$string['syllabussaved'] = 'Syllabus saved.';
$string['syllabusschedule'] = 'Course schedule';
$string['teacherroles'] = 'Hero instructor roles';
$string['teacherroles_desc'] =
    'Select the roles whose users should appear as instructors in the Streamdeck course hero. ' .
    'By default, this includes the editing teacher and teacher roles where they exist.';
$string['timerlabel'] = 'Time';
$string['timerwarning'] = '{$a} left in this attempt.';
$string['todo'] = 'To do';
$string['togglenavbar'] = 'Show or hide the navigation bar';
$string['viewandreplytodiscussion'] = 'Reply';
$string['viewcourseschedule'] = 'View course schedule';
$string['viewgrades'] = 'View grades';
$string['viewgradescta'] = 'View your grades';
$string['viewmenu'] = 'View options';
$string['whatsnew'] = 'What\'s new';
$string['you'] = 'You';
