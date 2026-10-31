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
 * Streamdeck main content output class.
 *
 * Extends core content for custom template/data (Netflix-style).
 *
 * @package    format_streamdeck
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_streamdeck\output\courseformat;

use core_courseformat\output\local\content as core_content;
use renderer_base;
use moodle_url;
use stdClass;

/**
 * Streamdeck content output class.
 *
 * @package    format_streamdeck
 */
class content extends core_content {
    /** @var string[] Modules listed under "Related resources" rather than as activities. */
    public const RESOURCE_TYPES = ['label', 'resource'];

    /** @var string[] Learning activities that can play on the episode stage. */
    public const LEARNING_TYPES = ['lesson', 'scorm', 'lti', 'url', 'advurl', 'h5pactivity'];

    /** @var string[] Activities that take the stage when a unit has no learning activity. */
    public const ASSESSMENT_TYPES = ['quiz', 'assign', 'workshop'];

    /**
     * Get the name of the template to use for this templatable.
     *
     * @param renderer_base $renderer The renderer requesting the template name
     * @return string
     */
    public function get_template_name(renderer_base $renderer): string {
        // Per doc: use local/ for course template.
        return 'format_streamdeck/local/content';
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * This method now delegates to smaller helpers to keep the logic readable:
     *   - detect_current_section()
     *   - build_hero_or_defaults()
     *   - build_section_tiles()
     *   - build_section_view()
     *   - build_schedule_and_syllabus()
     *
     * @param renderer_base $output typically, the renderer that's calling this function
     * @return stdClass data context for a mustache template
     */
    public function export_for_template(renderer_base $output): stdClass {
        global $PAGE, $USER;

        try {
            $course     = $this->format->get_course();
            $modinfo    = $this->format->get_modinfo();
            $progress   = new \format_streamdeck\local\course_progress($modinfo, new \completion_info($course), (int) $USER->id);
            $context    = \context_course::instance($course->id);

            $data = new stdClass();

            // 1. Determine current section and high-level view flags.
            $currentsection            = $this->detect_current_section();
            $data->currentsection      = $currentsection;
            $data->issectionview       = $currentsection > 0;
            $data->showhero            = !$data->issectionview;
            $data->showsectiontiles    = !$data->issectionview;
            $data->showallmodulesheader = $data->showsectiontiles;

            // 2. Hero / overview vs. section-view defaults.
            if ($data->showhero) {
                $this->build_hero_view($data, $course, $context, $modinfo, $progress);
            } else {
                $this->build_section_view_hero_defaults($data, $course, $context);
            }

            // 3. Section tiles and continue watching row (overview only).
            $this->build_section_tiles($data, $course, $modinfo, $progress);

            // 4. Section view (episode, related activities, navigation).
            $this->build_section_view($data, $course, $modinfo, $progress, $currentsection);

            // 5. Schedule and syllabus (More info).
            $this->build_schedule_and_syllabus($data, $course, $context);

            // 6. Plugin display settings.
            $this->apply_display_settings($data);

            return $data;
        } catch (\Throwable $e) {
            debugging('STREAMDECK: Error in export_for_template: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return $this->export_for_template_core($output); // Fallback to core.
        }
    }

    /**
     * Helper to safely fall back in editing mode.
     *
     * @param renderer_base $renderer
     * @return stdClass
     */
    protected function export_for_template_core(renderer_base $renderer): stdClass {
        try {
            $core = new \core_courseformat\output\local\content($this->format);
            return $core->export_for_template($renderer);
        } catch (\Throwable $e) {
            debugging('STREAMDECK: Error in core fallback: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return new stdClass();
        }
    }

    /**
     * Apply plugin display settings to the template data.
     *
     * @param stdClass $data Template data object.
     */
    private function apply_display_settings(stdClass $data): void {
        // Read from per-course format options (not global config).
        $formatoptions = $this->format->get_format_options();

        // Hero title font.
        $herofont = !empty($formatoptions['herofont']) ? $formatoptions['herofont'] : 'Lobster';
        $data->herofont = $herofont;

        // Build the font-family CSS value.
        $googlefonts = ['Lobster', 'Playfair Display', 'Merriweather', 'Oswald', 'Raleway',
            'Montserrat', 'Poppins', 'Lora'];
        if (in_array($herofont, $googlefonts)) {
            $data->herofontcss = "'" . $herofont . "', cursive";
            $data->herofonturlparam = str_replace(' ', '+', $herofont);
            $data->herofontgoogle = true;
        } else {
            $data->herofontcss = $herofont . ', sans-serif';
            $data->herofontgoogle = false;
        }

        // Right drawer toggle visibility.
        $data->enabledrawertoggle = !empty($formatoptions['enabledrawertoggle']);

        // The view menu: appearance (unless Boost owns the colour mode) and display.
        $data->colourmode = \format_streamdeck\local\colour_mode::export_menu();
    }

    /**
     * Detect "current section" from URL parameters.
     *
     * Streamdeck shows hero only on landing (section 0).
     *
     * @return int section number (0 = course landing)
     */
    private function detect_current_section(): int {
        $expandsection = optional_param('expandsection', 0, PARAM_INT);
        if ($expandsection > 0) {
            return $expandsection;
        }
        $section = optional_param('section', 0, PARAM_INT);
        if ($section > 0) {
            return $section;
        }
        return 0;
    }

    /**
     * Build hero view (course meta, instructor, completion, resume button).
     *
     * Only used on the course landing page (section 0).
     *
     * @param stdClass          $data
     * @param stdClass          $course
     * @param \context_course   $context
     * @param \course_modinfo   $modinfo
     * @param \format_streamdeck\local\course_progress $progress
     */
    private function build_hero_view(
        stdClass $data,
        stdClass $course,
        \context_course $context,
        \course_modinfo $modinfo,
        \format_streamdeck\local\course_progress $progress
    ): void {
        global $CFG, $USER, $DB, $PAGE;

        // Course title, image & summary.
        $data->fullname = format_string($course->fullname, true, ['context' => $context]);

        $courseimageurl = \core_course\external\course_summary_exporter::get_course_image($course);
        if (!empty($courseimageurl) && is_string($courseimageurl)) {
            $data->courseimage    = $courseimageurl;
            $data->hascourseimage = true;
        } else {
            $data->courseimage    = '';
            $data->hascourseimage = false;
        }

        $data->summary = format_text($course->summary, $course->summaryformat, ['context' => $context]);

        // Metadata: year, shortname, duration.
        $startdate = $course->startdate;
        $enddate   = $course->enddate; // 0 if not set.

        $data->year      = userdate($startdate, '%Y');
        $data->shortname = format_string($course->shortname, true, ['context' => $context]);

        if ($enddate && $enddate > $startdate) {
            $diff  = $enddate - $startdate;
            $days  = (int) floor($diff / DAYSECS);
            $weeks = (int) floor($days / 7);

            if ($weeks >= 6) {
                $rounded = (int) (round($weeks / 12) * 12);
                $rounded = $rounded === 0 ? 12 : $rounded;
                $data->duration = get_string('numweeks', 'moodle', $rounded);
            } else {
                $data->duration = get_string($days === 1 ? 'numday' : 'numdays', 'moodle', $days);
            }
        } else {
            $data->duration = '—';
        }

        // Instructor / teaching team.
        $data->hasinstructor        = false;
        $data->instructor           = '—';
        $data->instructoravatarhtml = '';
        $data->instructorprofileurl = '';
        $data->instructorrolelabel  = '';
        $data->instructorteam       = [];
        $data->multipleinstructors  = false;
        $data->instructorgrouplabel = null;

        // Use central helper that respects admin-configured teacher roles.
        $teachers = $this->get_hero_teachers($context);

        if (!empty($teachers)) {
            $instructorteam = [];

            // Batch-fetch all roles referenced by these teachers to avoid N+1 queries.
            $allroleids = [];
            $userrolesbatch = [];
            foreach ($teachers as $t) {
                $userroles = get_user_roles($context, $t->id, false);
                $userrolesbatch[$t->id] = $userroles;
                foreach ($userroles as $ur) {
                    $allroleids[$ur->roleid] = $ur->roleid;
                }
            }
            $rolecache = !empty($allroleids)
                ? $DB->get_records_list('role', 'id', array_values($allroleids))
                : [];

            foreach ($teachers as $t) {
                $entry        = new stdClass();
                $entry->id    = $t->id;
                $entry->name  = \core_user::get_fullname($t);

                // Role label per teacher (using pre-fetched data).
                $entry->rolelabel = '';
                $userroles = $userrolesbatch[$t->id] ?? [];
                if (!empty($userroles)) {
                    $primaryrole = reset($userroles);
                    $role = $rolecache[$primaryrole->roleid] ?? null;
                    if ($role) {
                        $entry->rolelabel = role_get_name($role, $context, ROLENAME_ALIAS);
                    }
                }

                if (empty($entry->rolelabel)) {
                    $entry->rolelabel = get_string('teacher', 'role');
                }

                // Avatar HTML.
                try {
                    $usercontext      = \context_user::instance($t->id);
                    $t->contextid     = $usercontext->id;
                    $t->picture       = $t->picture ?? 0;

                    $userpicture          = new \user_picture($t);
                    $userpicture->size    = 100;
                    $userpicture->link    = false;
                    $userpicture->alttext = false;

                    $corerenderer     = $PAGE->get_renderer('core');
                    $entry->avatarhtml = $corerenderer->render($userpicture);
                } catch (\Throwable $e) {
                    debugging(
                        'STREAMDECK: Error rendering instructor avatar HTML (multi): ' .
                        $e->getMessage(),
                        DEBUG_DEVELOPER
                    );
                    $entry->avatarhtml = '';
                }

                // Contact URL: prefer satsmail if installed, otherwise core messaging.
                $satsmailinstalled = \core_component::get_plugin_directory('local', 'satsmail') !== null;
                if ($satsmailinstalled) {
                    $entry->contacturl = (new moodle_url('/local/satsmail/create_draft.php', [
                        'c'  => $course->id,
                        'to' => $t->id,
                    ]))->out(false);
                } else {
                    $entry->contacturl = (new moodle_url('/message/index.php', [
                        'id' => $t->id,
                    ]))->out(false);
                }

                $instructorteam[] = $entry;
            }

            $data->instructorteam      = $instructorteam;
            $data->hasinstructor       = !empty($instructorteam);
            $data->multipleinstructors = count($instructorteam) > 1;

            // Determine the group label: use admin-configured singular/plural labels.
            if (!empty($instructorteam)) {
                $config = get_config('format_streamdeck');

                if (count($instructorteam) === 1) {
                    // Singular.
                    $defaultlabel = get_string('instructorlabel_default', 'format_streamdeck');
                    $label = !empty($config->instructorlabel)
                        ? $config->instructorlabel
                        : $defaultlabel;
                } else {
                    // Plural.
                    $defaultlabel = get_string('instructorlabelplural_default', 'format_streamdeck');
                    $label = !empty($config->instructorlabelplural)
                        ? $config->instructorlabelplural
                        : $defaultlabel;
                }

                $data->instructorgrouplabel = format_string($label, true);
            }

            // Primary instructor for hero meta.
            $primary                     = $instructorteam[0];
            $data->instructor            = $primary->name;
            $data->instructorrolelabel   = $primary->rolelabel;
            $data->instructoravatarhtml  = $primary->avatarhtml;
            $data->instructorprofileurl  = $primary->contacturl;
        }

        // Announcements / News forum icon (section 0).
        $data->hasannouncementicon      = false;
        $data->announcementcontextid    = 0;
        $data->announcementunreadcount  = 0;
        $data->announcementforumid      = 0;

        try {
            require_once($CFG->dirroot . '/mod/forum/lib.php');

            // Use Moodle's own helper to get the news forum for this course.
            $newsforum = forum_get_course_forum($course->id, 'news');

            if ($newsforum) {
                // Get the course module for this forum instance.
                $newsforumcm = get_coursemodule_from_instance(
                    'forum',
                    $newsforum->id,
                    $course->id,
                    false,
                    IGNORE_MISSING
                );

                if ($newsforumcm) {
                    // Get full cm_info object for visibility checks. Re-read the course modules, as
                    // forum_get_course_forum() creates the forum on a course's first view.
                    $cm = get_fast_modinfo($course)->get_cm($newsforumcm->id);

                    // For announcements, we relax uservisible slightly:
                    // Show if visible OR if it's just hidden from students but exists.
                    // This mirrors how Moodle navigation treats announcements.
                    if ($cm->visible || $cm->uservisible) {
                        $data->hasannouncementicon = true;
                        $data->announcementcontextid = $cm->context->id;
                        $data->announcementforumid = (int)$newsforum->id;

                        // Default 0, then try to compute unread posts.
                        $data->announcementunreadcount = 0;

                        // Respect forum tracking settings: if tracking disabled, treat as 0 unread
                        // to avoid a constant wiggle when users don't track read state.
                        if (!forum_tp_can_track_forums($newsforum) || !forum_tp_is_tracked($newsforum)) {
                            $data->announcementunreadcount = 0;
                        } else {
                            // Use Moodle's own API for unread count by forum for this user.
                            $count = forum_tp_count_forum_unread_posts($cm, $course, $USER->id);
                            $data->announcementunreadcount = (int)$count;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            debugging('STREAMDECK: error building announcements icon data: ' . $e->getMessage(), DEBUG_DEVELOPER);
            $data->hasannouncementicon     = false;
            $data->announcementcontextid   = 0;
            $data->announcementunreadcount = 0;
            $data->announcementforumid     = 0;
        }

        // General forum icon (section 0).
        $data->hasgeneralforumicon   = false;
        $data->generalforumcontextid = 0;

        try {
            // Scan section 0 only for the first visible, non-news forum.
            if (!empty($modinfo->sections[0])) {
                foreach ($modinfo->sections[0] as $cmid) {
                    $cm = $modinfo->get_cm($cmid);
                    if (!$cm->uservisible || $cm->modname !== 'forum') {
                        continue;
                    }
                    // Skip the news/announcements forum.
                    $instance = $DB->get_record('forum', ['id' => $cm->instance], 'id, type', IGNORE_MISSING);
                    if ($instance && $instance->type !== 'news') {
                        $data->hasgeneralforumicon = true;
                        $data->generalforumcontextid = $cm->context->id;
                        break;
                    }
                }
            }
        } catch (\Throwable $e) {
            debugging('STREAMDECK: error building general forum icon data: ' . $e->getMessage(), DEBUG_DEVELOPER);
            $data->hasgeneralforumicon   = false;
            $data->generalforumcontextid = 0;
        }

        // Participants icon (capability-gated).
        $data->hasparticipantsicon = false;
        $data->participantsurl     = '';

        try {
            $context = \context_course::instance($course->id);
            if (has_capability('moodle/course:viewparticipants', $context)) {
                $data->hasparticipantsicon = true;
                $data->participantsurl = (new moodle_url('/user/index.php', [
                    'id' => $course->id,
                ]))->out(false);
            }
        } catch (\Throwable $e) {
            debugging('STREAMDECK: error building participants icon data: ' . $e->getMessage(), DEBUG_DEVELOPER);
            $data->hasparticipantsicon = false;
            $data->participantsurl     = '';
        }

        // Live class (BigBlueButton) icon (section 0).
        $data->hasliveclassicon = false;
        $data->liveclassurl     = '';

        try {
            $bbbcm = null;

            if (!empty($modinfo->sections[0])) {
                foreach ($modinfo->sections[0] as $cmid) {
                    $cm = $modinfo->get_cm($cmid);
                    if (!$cm->uservisible || $cm->modname !== 'bigbluebuttonbn') {
                        continue;
                    }
                    $bbbcm = $cm;
                    break; // First visible BBB in section 0.
                }
            }

            if ($bbbcm) {
                $data->hasliveclassicon = true;
                $data->liveclassurl = (new moodle_url('/mod/bigbluebuttonbn/view.php', [
                    'id' => $bbbcm->id,
                ]))->out(false);
            }
        } catch (\Throwable $e) {
            debugging('STREAMDECK: error building live class icon data: ' . $e->getMessage(), DEBUG_DEVELOPER);
            $data->hasliveclassicon = false;
            $data->liveclassurl     = '';
        }

        // Completion across the whole course + resume button.
        $counts = $progress->get_course_counts();
        $data->hascompletion     = $counts['total'] > 0;
        $data->completionpercent = \format_streamdeck\local\course_progress::percent($counts);
        $data->completiontext    = $counts['total']
            ? get_string('activitiescomplete', 'format_streamdeck', $counts)
            : '';

        // First incomplete activity for "Play / Resume" CTA.
        $next = $progress->get_first_incomplete();
        $firstincompleteurl = $next ? new moodle_url('/mod/' . $next->modname . '/view.php', ['id' => $next->id]) : null;

        // Build grades modal.
        $gradeurl = new moodle_url('/grade/report/user/index.php', [
            'id'     => $course->id,
            'userid' => $USER->id,
        ]);

        $data->gradesurl = $gradeurl->out(false);

        $data->syllabustitle = get_string('syllabus', 'format_streamdeck');
        $data->playurl       = $firstincompleteurl
            ? $firstincompleteurl->out(false)
            : (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false);
        $data->playlabel     = $firstincompleteurl
            ? get_string('resume', 'format_streamdeck')
            : get_string('start', 'format_streamdeck');
    }

    /**
     * Hero defaults when viewing a specific section (no big hero).
     *
     * @param stdClass        $data
     * @param stdClass        $course
     * @param \context_course $context
     */
    private function build_section_view_hero_defaults(
        stdClass $data,
        stdClass $course,
        \context_course $context
    ): void {
        $data->fullname            = format_string($course->fullname, true, ['context' => $context]);
        $data->courseimage         = '';
        $data->hascourseimage      = false;
        $data->summary             = '';
        $data->year                = '';
        $data->shortname           = '';
        $data->duration            = '';
        $data->hasinstructor       = false;
        $data->instructor          = '';
        $data->instructoravatarhtml = '';
        $data->instructorprofileurl = '';
        $data->instructorrolelabel  = '';
        $data->instructorteam       = [];
        $data->multipleinstructors  = false;
        $data->instructorgrouplabel = null;
        $data->hascompletion        = false;
        $data->completionpercent    = 0;
        $data->completiontext       = '';
        $data->playurl              = (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false);
        $data->playlabel            = get_string('start', 'format_streamdeck');
        $data->syllabustitle        = get_string('syllabus', 'format_streamdeck');
    }

    /**
     * Build the episode cards for the overview page, grouped into seasons when the course uses them.
     *
     * @param stdClass          $data
     * @param stdClass          $course
     * @param \course_modinfo   $modinfo
     * @param \format_streamdeck\local\course_progress $progress
     */
    private function build_section_tiles(
        stdClass $data,
        stdClass $course,
        \course_modinfo $modinfo,
        \format_streamdeck\local\course_progress $progress
    ): void {
        global $PAGE;

        $data->sections = [];
        $data->hasseasons = false;
        $data->seasons = [];

        if (!$data->showsectiontiles) {
            return;
        }

        /** @var \format_streamdeck\output\renderer $frenderer */
        $frenderer = $PAGE->get_renderer('format_streamdeck');
        $episodes = new \format_streamdeck\local\episodes($this->format, $modinfo, $progress);

        $cards = [];
        foreach ($modinfo->get_section_info_all() as $sectioninfo) {
            // Subsections are shown inside their parent episode, not as episodes of their own.
            if ($sectioninfo->section == 0 || !empty($sectioninfo->component) || !$sectioninfo->visible) {
                continue;
            }
            // Restricted sections stay on the shelf as locked cards, unless set to be hidden entirely.
            if (!$sectioninfo->uservisible && empty($sectioninfo->availableinfo)) {
                continue;
            }

            $section         = new stdClass();
            $section->num    = (int) $sectioninfo->section;
            $section->name   = $this->format->get_section_name($sectioninfo);
            $section->locked = !$sectioninfo->uservisible;
            $section->url    = $section->locked ? '' : $this->format->get_view_url($sectioninfo->section)->out(false);
            $section->lockedinfo = $section->locked
                ? \core_availability\info::format_info($sectioninfo->availableinfo, $course)
                : '';

            $sectionimage = '';
            if ($frenderer && method_exists($frenderer, 'get_section_image_for_sectioninfo')) {
                $sectionimage = $frenderer->get_section_image_for_sectioninfo($sectioninfo);
            }
            $section->image    = $sectionimage;
            $section->hasimage = !empty($sectionimage);

            $counts = $progress->get_section_counts($sectioninfo->section);
            $section->progress    = \format_streamdeck\local\course_progress::percent($counts);
            $section->hasprogress = $counts['total'] > 0;
            $section->done        = $section->hasprogress && $counts['complete'] === $counts['total'];

            foreach ($episodes->get_metadata($sectioninfo) as $key => $value) {
                $section->$key = $value;
            }

            $cards[$sectioninfo->section] = $section;
        }
        $data->sections = array_values($cards);

        // Seasons: one tab per season, opening on the one the learner is working through.
        $seasons = $this->format->get_seasons();
        if (!$seasons) {
            return;
        }
        $label = $this->format->get_season_label();
        $next = $progress->get_first_incomplete();
        $current = 0;
        foreach ($seasons as $index => $season) {
            if ($next && in_array((int) $next->sectionnum, $season['sections'], true)) {
                $current = $index;
            }
        }
        foreach ($seasons as $index => $season) {
            $seasoncards = array_values(array_filter(array_map(fn($num) => $cards[$num] ?? null, $season['sections'])));
            if (!$seasoncards) {
                continue;
            }
            $number = $index + 1;
            $data->seasons[] = (object) [
                'id' => 'streamdeck-season-' . $number,
                'kicker' => $label . ' ' . $number,
                'name' => $season['name'] !== '' ? $season['name'] : get_string('seasonopening', 'format_streamdeck'),
                'sections' => $seasoncards,
                'selected' => $index === $current,
                'episodecount' => count($seasoncards) === 1
                    ? get_string('seasonepisode', 'format_streamdeck')
                    : get_string('seasonepisodes', 'format_streamdeck', count($seasoncards)),
            ];
        }
        $data->hasseasons = count($data->seasons) > 0;
        $data->seasonlabel = $label;
    }

    /**
     * Build section-view data: episode, related activities, related resources,
     * completion gating for "Next episode", and prev/next section navigation.
     *
     * @param stdClass          $data
     * @param stdClass          $course
     * @param \course_modinfo   $modinfo
     * @param \format_streamdeck\local\course_progress $progress
     * @param int               $currentsection
     */
    private function build_section_view(
        stdClass $data,
        stdClass $course,
        \course_modinfo $modinfo,
        \format_streamdeck\local\course_progress $progress,
        int $currentsection
    ): void {
        global $CFG, $DB, $PAGE, $USER;

        // Defaults.
        $data->hasepisode        = false;
        $data->episode           = null;
        $data->relatedactivities = [];

        $data->courseurl       = (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false);
        $data->hasnextsection  = false;
        $data->nextsectionurl  = '';
        $data->nextsectionname = '';
        $data->hasprevsection  = false;
        $data->prevsectionurl  = '';
        $data->prevsectionname = '';
        $data->canshownextbutton = false;

        if (!$data->issectionview) {
            return;
        }

        // An episode with no activities yet still gets its header, empty state and navigation.
        $sectioncms  = $modinfo->sections[$currentsection] ?? [];
        $sectioninfo = $modinfo->get_section_info($currentsection);

        // Do not render Streamdeck episode view for hidden/unavailable sections,
        // even for teachers. Hidden sections are edited in core's edit UI.
        if (!$sectioninfo || !$sectioninfo->visible || !$sectioninfo->available) {
            return;
        }

        $data->currentsectionname = $this->format->get_section_name($sectioninfo);
        $data->episodenumber      = $currentsection;
        $minutes = max(0, (int) $sectioninfo->duration);
        $data->episodedurationtext = $minutes ? format_time($minutes * MINSECS) : '';
        $data->episodeseasonname = '';
        $data->about = $sectioninfo->showabout ? $this->get_episode_about($sectioninfo) : '';
        if ($data->about !== '') {
            $options = $this->format->get_format_options($sectioninfo);
            $title = trim((string) ($options['abouttitle'] ?? ''));
            $data->abouttitle = $title !== ''
                ? format_string($title, true, ['context' => \context_course::instance($course->id)])
                : get_string('about', 'format_streamdeck');
            $icon = (string) ($options['abouticon'] ?? '');
            $data->abouticon = in_array($icon, \format_streamdeck::ABOUT_ICONS, true) ? $icon : '';
        }
        foreach ($this->format->get_seasons() as $index => $season) {
            if (in_array($currentsection, $season['sections'], true)) {
                $data->episodeseasonname = $this->format->get_season_label() . ' ' . ($index + 1) .
                    ($season['name'] !== '' ? ': ' . $season['name'] : '');
            }
        }

        // Collect "Related Resources" (mod_label / mod_resource).
        $relatedresources = [];
        foreach ($sectioncms as $cmid) {
            $cm = $modinfo->get_cm($cmid);
            if (!$cm->uservisible) {
                continue;
            }

            // Only pick label or resource modules for the "Related Resources" area.
            if ($cm->modname === 'label' || $cm->modname === 'resource') {
                // Try to get the module icon URL like we do for activities.
                $iconurl = '';
                try {
                    $icon = $cm->get_icon_url();
                    $iconurl = $icon ? $icon->out(false) : '';
                } catch (\Throwable $e) {
                    debugging(
                        'STREAMDECK: could not get icon url for cmid ' . $cm->id . ': ' . $e->getMessage(),
                        DEBUG_DEVELOPER
                    );
                    $iconurl = '';
                }

                $labeltext = format_string($cm->name, true, ['context' => $cm->context]);
                $linkurl   = $cm->url ? $cm->url->out(false) : '';

                // For LABELS: extract the first <a href="..."> from $cm->content.
                if ($cm->modname === 'label') {
                    $html = $cm->content ?? '';

                    if (!empty($html)) {
                        $doc = new \DOMDocument();
                        @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
                        $anchors = $doc->getElementsByTagName('a');
                        if ($anchors->length > 0) {
                            /** @var \DOMElement $a */
                            $a = $anchors->item(0);
                            $href = $a->getAttribute('href');
                            if (!empty($href)) {
                                $linkurl = $href;
                            }
                        }
                    }
                }

                $relatedresources[] = (object)[
                    'label' => $labeltext,
                    'url'   => $linkurl,
                    'icon'  => $iconurl,
                ];
            }
        }
        $data->relatedresources = $relatedresources;
        $data->hasrelatedresources = !empty($relatedresources);

        $related = [];

        // Batch-prefetch module instance data to avoid N+1 queries inside the loop.
        $urlids = [];
        $advurlids = [];
        $h5pids = [];
        $forumids = [];
        foreach ($sectioncms as $cmid) {
            $cm = $modinfo->get_cm($cmid);
            if (!$cm->uservisible) {
                continue;
            }
            if ($cm->modname === 'url') {
                $urlids[] = $cm->instance;
            } else if ($cm->modname === 'advurl') {
                $advurlids[] = $cm->instance;
            } else if ($cm->modname === 'h5pactivity') {
                $h5pids[] = $cm->instance;
            } else if ($cm->modname === 'forum') {
                $forumids[] = $cm->instance;
            }
        }
        $urlcache = !empty($urlids)
            ? $DB->get_records_list('url', 'id', $urlids, '', 'id, externalurl')
            : [];
        $advurlcache = [];
        if (!empty($advurlids)) {
            try {
                if ($DB->get_manager()->table_exists('advurl')) {
                    $advurlcache = $DB->get_records_list('advurl', 'id', $advurlids, '', 'id, externalurl');
                }
            } catch (\Throwable $e) {
                $advurlcache = [];
            }
        }
        $h5pcache = !empty($h5pids)
            ? $DB->get_records_list('h5pactivity', 'id', $h5pids)
            : [];
        // Batch forum discussion and post counts.
        $forumdisccounts = [];
        $forumpostcounts = [];
        $forumunreadcounts = [];
        if (!empty($forumids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($forumids, SQL_PARAMS_NAMED, 'fid');
            if ($DB->get_manager()->table_exists('forum_discussions')) {
                $sql = "SELECT forum, COUNT(*) AS cnt
                          FROM {forum_discussions}
                         WHERE forum $insql
                      GROUP BY forum";
                $rows = $DB->get_records_sql($sql, $inparams);
                foreach ($rows as $row) {
                    $forumdisccounts[$row->forum] = (int)$row->cnt;
                }
            }
            if ($DB->get_manager()->table_exists('forum_posts')) {
                $sql = "SELECT d.forum, COUNT(p.id) AS cnt
                          FROM {forum_posts} p
                          JOIN {forum_discussions} d ON d.id = p.discussion
                         WHERE d.forum $insql
                      GROUP BY d.forum";
                $rows = $DB->get_records_sql($sql, $inparams);
                foreach ($rows as $row) {
                    $forumpostcounts[$row->forum] = (int)$row->cnt;
                }
            }
            try {
                $unreadparams = $inparams;
                $unreadparams['userid'] = $USER->id;
                $sql = "SELECT d.forum, COUNT(p.id) AS cnt
                          FROM {forum_posts} p
                          JOIN {forum_discussions} d ON d.id = p.discussion
                     LEFT JOIN {forum_read} r ON r.postid = p.id AND r.userid = :userid
                         WHERE d.forum $insql
                           AND r.id IS NULL
                      GROUP BY d.forum";
                $rows = $DB->get_records_sql($sql, $unreadparams);
                foreach ($rows as $row) {
                    $forumunreadcounts[$row->forum] = (int)$row->cnt;
                }
            } catch (\Throwable $e) {
                // Forum read tracking may not be available.
                $forumunreadcounts = [];
            }
        }

        foreach ($sectioncms as $cmid) {
            $cm = $modinfo->get_cm($cmid);
            if (!$cm->uservisible) {
                continue;
            }

            $item            = new stdClass();
            $item->id        = $cm->id;
            $item->cmid      = $cm->id;
            $item->modname   = $cm->modname;
            $item->name      = format_string($cm->name, true, ['context' => $cm->context]);
            $item->url       = $cm->url ? $cm->url->out(false) : '';
            $item->icon      = $cm->get_icon_url()->out(false);
            $item->completionenabled = $progress->is_tracked($cm->id);
            $item->iscomplete = $progress->is_complete($cm->id);

            $item->isforum = false;

            $item->introhtml      = '';
            $item->youtubeembedurl = '';
            $item->h5pembedurl    = '';
            $item->h5phtml        = '';

            // Forum and assignment preview data for the modal (using batch-prefetched counts).
            $item->isassign = false;
            $item->forumpreviewhtml = '';
            $item->assignpreviewhtml = '';

            if ($cm->modname === 'forum') {
                $item->isforum = true;
                $item->forumdiscussions = $forumdisccounts[$cm->instance] ?? 0;
                $item->forumposts = $forumpostcounts[$cm->instance] ?? 0;
                $item->forumunread = $forumunreadcounts[$cm->instance] ?? 0;
                try {
                    $item->forumpreviewhtml = \format_streamdeck\output\discussion::render_preview($cm);
                } catch (\Throwable $e) {
                    debugging(
                        'STREAMDECK: discussion preview failed for cmid ' . $cm->id . ': ' . $e->getMessage(),
                        DEBUG_DEVELOPER
                    );
                    $item->forumpreviewhtml = '';
                }
            }

            // Assignment.
            if ($cm->modname === 'assign') {
                $item->isassign = true;

                try {
                    $item->assignpreviewhtml = $this->build_assign_preview_html($cm, $course);
                } catch (\Throwable $e) {
                    debugging(
                        'STREAMDECK: error collecting assign meta for cmid ' . $cm->id . ': ' . $e->getMessage(),
                        DEBUG_DEVELOPER
                    );
                    $item->assignpreviewhtml = '';
                }
            }

            // YOUTUBE EMBEDDING (URL module — using batch-prefetched data).
            if ($cm->modname === 'url') {
                $urlrec = $urlcache[$cm->instance] ?? null;
                $externalurl = ($urlrec && !empty($urlrec->externalurl)) ? $urlrec->externalurl : '';
                if (!empty($externalurl)) {
                    $youtubeembedurl = $this->extract_youtube_embed_url($externalurl);
                    if (!empty($youtubeembedurl)) {
                        $item->youtubeembedurl = $youtubeembedurl;
                        $item->url             = $externalurl;
                    }
                }
            }

            // YOUTUBE EMBEDDING (ADVANCED URL module — using batch-prefetched data).
            if ($cm->modname === 'advurl') {
                $advurlrec = $advurlcache[$cm->instance] ?? null;
                $externalurl = ($advurlrec && !empty($advurlrec->externalurl)) ? $advurlrec->externalurl : '';
                if (!empty($externalurl)) {
                    $youtubeembedurl = $this->extract_youtube_embed_url($externalurl);
                    if (!empty($youtubeembedurl)) {
                        $item->youtubeembedurl = $youtubeembedurl;
                        $item->url             = $externalurl;
                    }
                }
            }

            // H5P handling - exporter, embed.php, view.php iframe.
            if ($cm->modname === 'h5pactivity') {
                $item->h5phtml     = '';
                $item->h5pembedurl = '';

                $exporterclass = '\mod_h5pactivity\output\activity_exporter';
                if (class_exists($exporterclass)) {
                    try {
                        $h5precord = $h5pcache[$cm->instance] ?? null;
                        if ($h5precord) {
                            $h5prenderer = $PAGE->get_renderer('mod_h5pactivity');
                            if ($h5prenderer) {
                                $exporter   = new $exporterclass($h5precord, [
                                    'context' => $cm->context,
                                    'cm'      => $cm,
                                    'course'  => $course,
                                ]);
                                $renderable   = $exporter->export($h5prenderer);
                                $item->h5phtml = $h5prenderer->render($renderable);
                            }
                        }
                    } catch (\Throwable $e) {
                        debugging(
                            'STREAMDECK: exporter inline render threw: ' . $e->getMessage(),
                            DEBUG_DEVELOPER
                        );
                        $item->h5phtml = '';
                    }
                } else {
                    // Include H5P core libraries if present (covers different dir layouts).
                    $h5pcandidates = [
                        $CFG->dirroot . '/public/h5p/h5plib/v127/joubel/core/h5p.classes.php',
                        $CFG->dirroot . '/public/h5p/h5plib/h5p.classes.php',
                        $CFG->dirroot . '/h5p/h5plib/v127/joubel/core/h5p.classes.php',
                        $CFG->dirroot . '/h5p/h5plib/h5p.classes.php',
                    ];
                    foreach ($h5pcandidates as $cand) {
                        if (file_exists($cand)) {
                            require_once($cand);
                        }
                    }
                }

                if (empty($item->h5phtml)) {
                    $fs         = get_file_storage();
                    $packageurl = '';

                    $files = $fs->get_area_files(
                        $cm->context->id,
                        'mod_h5pactivity',
                        'package',
                        0,
                        'sortorder DESC, filename',
                        false
                    );
                    if (empty($files)) {
                        $files = $fs->get_area_files(
                            $cm->context->id,
                            'mod_h5pactivity',
                            'packagefile',
                            0,
                            'sortorder DESC, filename',
                            false
                        );
                    }

                    if (!empty($files)) {
                        $file       = reset($files);
                        $packageurl = moodle_url::make_pluginfile_url(
                            $file->get_contextid(),
                            $file->get_component(),
                            $file->get_filearea(),
                            $file->get_itemid(),
                            $file->get_filepath(),
                            $file->get_filename()
                        )->out(false);
                    }

                    if (!empty($packageurl)) {
                        $embedurl      = $CFG->wwwroot . '/h5p/embed.php?url=' .
                            rawurlencode($packageurl) . '&component=mod_h5pactivity';
                        $item->h5phtml =
                            '<div class="streamdeck-h5p-embed-wrapper">' .
                            '<iframe src="' . s($embedurl) . '" title="' . s($item->name) . '" ' .
                            'frameborder="0" width="100%" allowfullscreen>' .
                            '</iframe>' .
                            '</div>';
                    } else {
                        $viewurl      = new moodle_url('/mod/h5pactivity/view.php', ['id' => $cm->id]);
                        $item->h5phtml =
                            '<div class="streamdeck-h5p-iframe-wrapper">' .
                            '<iframe src="' . $viewurl->out(false) . '" title="' . s($item->name) . '" ' .
                            'frameborder="0" width="100%" allowfullscreen>' .
                            '</iframe>' .
                            '</div>';
                    }
                }
            }

            // Generic embeddability flags.
            if ($cm->modname === 'scorm') {
                $item->embedurl = (new moodle_url('/mod/scorm/view.php', ['id' => $cm->id, 'mode' => 'embed']))->out(false);
                $item->canembed = true;
            } else if (($cm->modname === 'url' || $cm->modname === 'advurl') && !empty($item->youtubeembedurl)) {
                $item->embedurl = $item->youtubeembedurl;
                $item->canembed = true;
            } else if ($cm->modname !== 'h5pactivity') {
                $embeddablemods  = ['page', 'lesson', 'scorm', 'lti', 'url', 'advurl'];
                $item->canembed  = in_array($cm->modname, $embeddablemods, true) && !empty($item->url);
                $item->embedurl  = $item->canembed ? $item->url : '';
            } else {
                $item->canembed = false;
                $item->embedurl = '';
            }

            $related[] = $item;
        }

        $pinnedcmid = (int) $sectioninfo->featuredcmid;
        $finalepisode = self::select_featured_activity($related, $pinnedcmid);

        // Labels and resources live in "Related resources"; everything else not on stage is a related activity.
        $data->relatedactivities = array_values(array_filter($related, function ($r) use ($finalepisode) {
            return !in_array($r->modname, self::RESOURCE_TYPES, true)
                && ($finalepisode === null || (int) $r->cmid !== (int) $finalepisode->cmid);
        }));
        $data->hasrelatedactivities = !empty($data->relatedactivities);
        $data->hasrelatedsidebar = $data->hasrelatedresources || $data->hasrelatedactivities;
        $data->hasleftcolumn = $data->hasrelatedsidebar || $data->about !== '';

        if ($finalepisode !== null) {
            // Activities that cannot play inside the page (quizzes, assignments...) get a launch panel instead.
            $finalepisode->introhtml = $this->get_activity_intro($modinfo->get_cm($finalepisode->cmid));
            $finalepisode->isstage = $finalepisode->h5phtml === '' && empty($finalepisode->canembed);
            if ($finalepisode->isstage) {
                $finalepisode->stage = $this->build_activity_stage($modinfo->get_cm($finalepisode->cmid), $finalepisode);
            }
            $data->hasepisode = true;
            $data->episode    = $finalepisode;
        }
        $data->noepisode = !$data->hasepisode;

        // The "Next episode" button waits until everything with completion tracking in this unit is done.
        $data->canshownextbutton = true;
        foreach (array_merge($data->relatedactivities, $finalepisode ? [$finalepisode] : []) as $item) {
            if ($item->completionenabled && !$item->iscomplete) {
                $data->canshownextbutton = false;
                break;
            }
        }

        // Previous / next episode: the nearest visible top-level sections. Delegated sections (subsections)
        // are shown inside their parent, so they are not episodes of their own.
        $sectionsall = $modinfo->get_section_info_all();
        $isepisode = function (\section_info $sinfo): bool {
            return $sinfo->section > 0 && $sinfo->uservisible && empty($sinfo->component);
        };

        foreach (array_reverse($sectionsall) as $sinfo) {
            if ($sinfo->section < $currentsection && $isepisode($sinfo)) {
                $data->hasprevsection  = true;
                $data->prevsectionname = $this->format->get_section_name($sinfo);
                $data->prevsectionurl  = $this->format->get_view_url($sinfo->section)->out(false);
                break;
            }
        }

        foreach ($sectionsall as $sinfo) {
            if ($sinfo->section > $currentsection && $isepisode($sinfo)) {
                $data->hasnextsection  = true;
                $data->nextsectionname = $this->format->get_section_name($sinfo);
                $data->nextsectionurl  = $this->format->get_view_url($sinfo->section)->out(false);
                break;
            }
        }
    }

    /**
     * Choose the activity that takes centre stage on an episode page.
     *
     * In order: the activity a teacher pinned in the section settings, a YouTube link, the first learning
     * activity, the first assessment, then any other activity. Labels and resources never take the stage.
     *
     * @param stdClass[] $items Section items in course order, each with cmid, modname and youtubeembedurl.
     * @param int $pinnedcmid The section's featured activity, or 0 for automatic.
     * @return stdClass|null
     */
    public static function select_featured_activity(array $items, int $pinnedcmid = 0): ?stdClass {
        $candidates = array_values(array_filter($items, function ($item) {
            return !in_array($item->modname, self::RESOURCE_TYPES, true);
        }));

        $rules = [
            fn($item) => $pinnedcmid && (int) $item->cmid === $pinnedcmid,
            fn($item) => !empty($item->youtubeembedurl),
            fn($item) => in_array($item->modname, self::LEARNING_TYPES, true),
            fn($item) => in_array($item->modname, self::ASSESSMENT_TYPES, true),
        ];
        foreach ($rules as $rule) {
            foreach ($candidates as $item) {
                if ($rule($item)) {
                    return $item;
                }
            }
        }
        return $candidates[0] ?? null;
    }

    /**
     * The section's "About this episode" text, formatted, or '' when it is empty.
     *
     * @param \section_info $sectioninfo
     * @return string
     */
    private function get_episode_about(\section_info $sectioninfo): string {
        $options = $this->format->get_format_options($sectioninfo);
        $about = (string) ($options['about'] ?? '');
        if (trim(strip_tags($about, '<img>')) === '') {
            return '';
        }
        return format_text($about, (int) ($options['aboutformat'] ?? FORMAT_HTML), [
            'context' => \context_course::instance($this->format->get_courseid()),
        ]);
    }

    /**
     * The activity's description, formatted, or '' when it has none.
     *
     * @param \cm_info $cm
     * @return string
     */
    private function get_activity_intro(\cm_info $cm): string {
        global $DB;

        if (!plugin_supports('mod', $cm->modname, FEATURE_MOD_INTRO, true)) {
            return '';
        }
        $instance = $DB->get_record($cm->modname, ['id' => $cm->instance], 'id, intro, introformat', IGNORE_MISSING);
        if (!$instance || trim(strip_tags($instance->intro, '<img>')) === '') {
            return '';
        }
        return format_module_intro($cm->modname, $instance, $cm->id);
    }

    /**
     * Launch panel for an activity that is featured but cannot be embedded, such as a quiz or an assignment.
     *
     * @param \cm_info $cm
     * @param stdClass $item The section item built for the activity.
     * @return array Template context for format_streamdeck/local/activity_stage.
     */
    private function build_activity_stage(\cm_info $cm, stdClass $item): array {
        global $CFG, $USER;

        $facts = [];
        foreach (\core\activity_dates::get_dates_for_module($cm, $USER->id) as $date) {
            $facts[] = [
                'label' => $date['label'],
                'value' => userdate($date['timestamp'], get_string('strftimedatetime', 'langconfig')),
            ];
        }

        $action = 'open';
        if ($cm->modname === 'quiz') {
            require_once($CFG->dirroot . '/mod/quiz/lib.php');
            try {
                $quiz = \mod_quiz\quiz_settings::create_for_cmid($cm->id, $USER->id)->get_quiz();
                if ($quiz->timelimit > 0) {
                    $facts[] = ['label' => get_string('timelimit', 'quiz'), 'value' => format_time($quiz->timelimit)];
                }
                $facts[] = [
                    'label' => get_string('attemptsallowed', 'quiz'),
                    'value' => $quiz->attempts ? $quiz->attempts : get_string('unlimited'),
                ];
                $attempts = quiz_get_user_attempts($quiz->id, $USER->id, 'all');
                $last = end($attempts);
                if ($last && $last->state === \mod_quiz\quiz_attempt::IN_PROGRESS) {
                    $action = 'continue';
                } else if ($last) {
                    $action = 'review';
                } else {
                    $action = 'start';
                }
            } catch (\Throwable $e) {
                debugging('STREAMDECK: quiz stage data for cmid ' . $cm->id . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        } else if (in_array($cm->modname, self::ASSESSMENT_TYPES, true)) {
            $action = $item->iscomplete ? 'review' : 'start';
        }

        return [
            'modulename' => get_string('modulename', $cm->modname),
            'isassessment' => in_array($cm->modname, self::ASSESSMENT_TYPES, true),
            'facts' => $facts,
            'hasfacts' => !empty($facts),
            'actionlabel' => get_string('stageaction:' . $action, 'format_streamdeck'),
        ];
    }

    /**
     * Build schedule (courseschedule module) and syllabus (format_streamdeck_syllabus).
     *
     * @param stdClass        $data
     * @param stdClass        $course
     * @param \context_course $context
     */
    private function build_schedule_and_syllabus(
        stdClass $data,
        stdClass $course,
        \context_course $context
    ): void {
        global $CFG, $DB, $PAGE;

        $data->hasschedule  = false;
        $data->schedulehtml = '';

        try {
            $modinfo   = $this->format->get_modinfo();
            $pluginman = \core\plugin_manager::instance();
            $plugininfo = $pluginman->get_plugin_info('mod_courseschedule');
            $enabled    = $plugininfo && !empty($plugininfo->is_enabled());

            if ($enabled) {
                require_once($CFG->dirroot . '/mod/courseschedule/lib.php');

                $instances  = $modinfo->get_instances_of('courseschedule');
                $schedulecm = null;

                foreach ($instances as $cm) {
                    if ($cm->uservisible) {
                        $schedulecm = $cm;
                        break;
                    }
                }

                if ($schedulecm) {
                    $instance  = $DB->get_record('courseschedule', ['id' => $schedulecm->instance], '*', MUST_EXIST);
                    $schedule  = courseschedule_build_schedule($course, $instance);
                    $html      = courseschedule_render_schedule($schedule);

                    $data->hasschedule  = true;
                    $data->schedulehtml = $html;
                }
            }
        } catch (\Throwable $e) {
            debugging('STREAMDECK: error embedding course schedule: ' . $e->getMessage(), DEBUG_DEVELOPER);
            $data->hasschedule  = false;
            $data->schedulehtml = '';
        }

        // Syllabus.
        /** @var \format_streamdeck\output\renderer $frenderer */
        $frenderer = $PAGE->get_renderer('format_streamdeck');
        $syllabus  = $frenderer->get_syllabus_for_course($course);

        $data->syllabus = (object) [
            'hascontent' =>
                !empty($syllabus['introhtml']) ||
                !empty($syllabus['outcomeshtml']) ||
                !empty($syllabus['assessmentshtml']) ||
                !empty($syllabus['materialshtml']),
            'introhtml'       => $syllabus['introhtml'],
            'outcomeshtml'    => $syllabus['outcomeshtml'],
            'assessmentshtml' => $syllabus['assessmentshtml'],
            'materialshtml'   => $syllabus['materialshtml'],
        ];

        if (has_capability('moodle/course:update', $context)) {
            $data->editsyllabusurl = (new moodle_url('/course/format/streamdeck/syllabus.php', [
                'id' => $course->id,
            ]))->out(false);
        } else {
            $data->editsyllabusurl = null;
        }
    }

    /**
     * Get course users that should appear as instructors in the hero.
     *
     * Resolution order:
     *  1. Use admin-selected roles in format_streamdeck/teacherroles.
     *  2. If setting empty, fall back to roles with shortnames editingteacher/teacher.
     *
     * There is deliberately NO capability-based fallback, to avoid
     * accidental inclusion of designers, managers, etc.
     *
     * @param \context_course $context
     * @return array of user objects (u.* fields)
     */
    private function get_hero_teachers(\context_course $context): array {
        global $DB;

        $teachers = [];

        try {
            // 1. Admin-configured role IDs.
            $config = get_config('format_streamdeck');
            $roleids = [];

            if (!empty($config->teacherroles)) {
                // Stored as comma-separated list of IDs.
                $roleids = array_filter(array_map('intval', explode(',', $config->teacherroles)));
            }

            // 2. If not configured, use default shortnames if present (single query).
            if (empty($roleids)) {
                [$insql, $inparams] = $DB->get_in_or_equal(
                    ['editingteacher', 'teacher'],
                    SQL_PARAMS_NAMED,
                    'rsn'
                );
                $defaultroles = $DB->get_records_select('role', "shortname $insql", $inparams, '', 'id');
                $roleids = array_keys($defaultroles);
            }

            if (empty($roleids)) {
                // Nothing configured and no default roles: safely return empty.
                return [];
            }

            // Fetch all users with ANY of the selected roles in this course context.
            // Call get_role_users() once per role ID to avoid the multi-role notice.
            $allteachers = [];
            foreach ($roleids as $roleid) {
                $roleusers = get_role_users(
                    $roleid,
                    $context,
                    false,
                    'u.*',
                    'u.lastname ASC'
                );

                if (is_array($roleusers)) {
                    $allteachers = array_merge($allteachers, $roleusers);
                }
            }

            // Deduplicate by user ID (in case a user has multiple roles).
            $seen = [];
            foreach ($allteachers as $user) {
                if (!isset($seen[$user->id])) {
                    $seen[$user->id] = $user;
                }
            }

            $teachers = array_values($seen);
        } catch (\Throwable $e) {
            debugging(
                'STREAMDECK: get_hero_teachers failed: ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
            $teachers = [];
        }

        return $teachers;
    }

    /**
     * Build an assignment preview body for the Streamdeck modal.
     *
     * Embeds the assignment view page in an iframe so students can submit inline.
     *
     * @param \cm_info $cm
     * @param \stdClass $course
     * @return string
     */
    private function build_assign_preview_html(\cm_info $cm, \stdClass $course): string {
        global $DB, $USER;

        try {
            $context = $cm->context;

            // Get the assign record.
            $assign = $DB->get_record('assign', ['id' => $cm->instance], '*', IGNORE_MISSING);
            if (!$assign) {
                return '';
            }

            $assignurl = new \moodle_url('/mod/assign/view.php', ['id' => $cm->id]);

            $o  = \html_writer::start_div('streamdeck-assign-preview');

            // Title.
            $o .= \html_writer::tag(
                'h3',
                format_string($cm->name, true, ['context' => $context]),
                ['class' => 'streamdeck-assign-preview-title']
            );

            // Due date (if set).
            if (!empty($assign->duedate)) {
                $duedate = userdate(
                    $assign->duedate,
                    get_string('strftimedatetimeshort', 'langconfig')
                );
                $o .= \html_writer::div(
                    get_string('duedate', 'assign') . ': ' . $duedate,
                    'streamdeck-assign-preview-duedate'
                );
            }

            // Status for current user.
            $statuslabel = get_string('nosubmissionyet', 'assign');

            $submission = $DB->get_record('assign_submission', [
                'assignment' => $assign->id,
                'userid'     => $USER->id,
                'latest'     => 1,
            ], '*', IGNORE_MISSING);

            if ($submission) {
                if (!empty($submission->status) && $submission->status === 'submitted') {
                    $statuslabel = get_string('submissionstatus_submitted', 'assign');
                } else {
                    $statuslabel = get_string('submissionstatus_draft', 'assign');
                }
            }

            $grade = $DB->get_record('assign_grades', [
                'assignment' => $assign->id,
                'userid'     => $USER->id,
            ], '*', IGNORE_MISSING);

            if ($grade && $grade->grade !== null) {
                $statuslabel = get_string('submissionstatus_graded', 'assign');
            }

            $o .= \html_writer::div(
                $statuslabel,
                'streamdeck-assign-preview-status'
            );

            // View / submit button that triggers iframe load.
            $o .= \html_writer::start_tag('button', [
                'type'  => 'button',
                'class' => 'btn btn-primary streamdeck-assign-view-btn',
                'data-assign-url' => $assignurl->out(false),
            ]);
            $o .= get_string('viewsubmission', 'assign');
            $o .= \html_writer::end_tag('button');

            // Hidden iframe wrapper (same pattern as forum reply).
            $o .= \html_writer::start_div('streamdeck-assign-frame-wrapper', ['hidden' => 'hidden']);
            $o .= \html_writer::tag('iframe', '', [
                'class'           => 'streamdeck-assign-frame',
                'title'           => format_string($cm->name, true, ['context' => $context]),
                'allowfullscreen' => 'allowfullscreen',
            ]);
            $o .= \html_writer::end_div(); // Frame wrapper div.

            $o .= \html_writer::end_div(); // Preview div.

            return $o;
        } catch (\Throwable $e) {
            debugging(
                'STREAMDECK: build_assign_preview_html failed for cmid ' . $cm->id . ': ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
            return '';
        }
    }

    /**
     * Extract YouTube video ID from a URL and return an embeddable iframe URL.
     *
     * Supports multiple YouTube URL formats:
     * - https://www.youtube.com/watch?v=VIDEO_ID
     * - https://youtu.be/VIDEO_ID
     * - https://www.youtube.com/embed/VIDEO_ID
     *
     * @param string $text The URL string to parse
     * @return string The YouTube embed URL, or empty string if not a valid YouTube URL
     */
    public static function extract_youtube_embed_url(string $text): string {
        if (stripos($text, 'youtube.com') === false && stripos($text, 'youtu.be') === false) {
            return '';
        }

        $pattern = "~(?:https?://)?(?:www\.)?(?:youtube\.com/(?:watch\?v=|embed/)|youtu\.be/)([A-Za-z0-9_-]{6,})~i";

        if (preg_match($pattern, $text, $matches)) {
            $videoid = $matches[1];

            if (preg_match('~^[A-Za-z0-9_-]{6,}$~', $videoid)) {
                return 'https://www.youtube.com/embed/' . $videoid;
            }
        }

        return '';
    }
}
