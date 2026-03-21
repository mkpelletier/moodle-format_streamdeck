<?php
/**
 * Streamdeck main content output class.
 *
 * Extends core content for custom template/data (Netflix-style).
 *
 * @package    format_streamdeck
 * @copyright  2025
 * @license    http://www.gnu.org/licenses/gpl-3.0.txt GNU GPL v3 or later
 */

namespace format_streamdeck\output\courseformat;

use core_courseformat\output\local\content as core_content;
use renderer_base;
use moodle_url;
use stdClass;

class content extends core_content {

    /**
     * Constructor.
     *
     * @param \core_courseformat\base $format The course format instance.
     */
    public function __construct($format) {
        parent::__construct($format);
    }

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
        global $PAGE;

        try {
            $course     = $this->format->get_course();
            $modinfo    = $this->format->get_modinfo();
            $completion = new \completion_info($course);
            $context    = \context_course::instance($course->id);

            $data = new stdClass();

            // ─── 1. Determine current section + high-level view flags ───
            $currentsection            = $this->detect_current_section();
            $data->currentsection      = $currentsection;
            $data->issectionview       = $currentsection > 0;
            $data->showhero            = !$data->issectionview;
            $data->showsectiontiles    = !$data->issectionview;
            $data->showallmodulesheader = $data->showsectiontiles;

            // ─── 2. Hero / overview vs. section-view defaults ───
            if ($data->showhero) {
                $this->build_hero_view($data, $course, $context, $modinfo, $completion);
            } else {
                $this->build_section_view_hero_defaults($data, $course, $context);
            }

            // ─── 3. Section tiles + continue watching row (overview only) ───
            $this->build_section_tiles($data, $course, $modinfo, $completion);

            // ─── 4. Section view (episode + related activities + navigation) ───
            $this->build_section_view($data, $course, $modinfo, $completion, $currentsection);

            // ─── 5. Schedule + syllabus (More info) ───
            $this->build_schedule_and_syllabus($data, $course, $context);

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

    // ==================================================================
    //  PRIVATE HELPERS – high level orchestration pieces
    // ==================================================================

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
     * @param \completion_info  $completion
     */
    private function build_hero_view(
        stdClass $data,
        stdClass $course,
        \context_course $context,
        \course_modinfo $modinfo,
        \completion_info $completion
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
                $data->duration = $rounded . ' week' . ($rounded > 1 ? 's' : '');
            } else {
                $data->duration = $days . ' day' . ($days > 1 ? 's' : '');
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

                    foreach ($teachers as $t) {
                        $entry        = new stdClass();
                        $entry->id    = $t->id;
                        $entry->name  = \core_user::get_fullname($t);

                        // Role label per teacher.
                        $entry->rolelabel = '';
                            $userroles = get_user_roles($context, $t->id, false);
                            if (!empty($userroles)) {
                                $primaryrole = reset($userroles);
                                $role        = $DB->get_record('role', ['id' => $primaryrole->roleid]);
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

                            $userpicture      = new \user_picture($t);
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

        // ────
        // Announcements / News forum icon (section 0).
        // ────
        $data->hasannouncementicon      = false;
        $data->announcementiconurl      = '';
        $data->announcementunreadcount  = 0;
        $data->announcementforumid      = 0;

        try {
            require_once($CFG->dirroot . '/mod/forum/lib.php');

            // Use Moodle's own helper to get the news forum for this course.
            $newsforum = forum_get_course_forum($course->id, 'news');

            if ($newsforum) {
                // Get the course module for this forum instance.
                $newsforumcm = get_coursemodule_from_instance('forum', $newsforum->id, $course->id, false, IGNORE_MISSING);

                if ($newsforumcm) {
                    // Get full cm_info object for visibility checks.
                    $cm = $modinfo->get_cm($newsforumcm->id);

                    // For announcements, we relax uservisible slightly:
                    // Show if visible OR if it's just hidden from students but exists.
                    // This mirrors how Moodle navigation treats announcements.
                    if ($cm->visible || $cm->uservisible) {
                        $data->hasannouncementicon = true;
                        $data->announcementiconurl = (new moodle_url('/mod/forum/view.php', [
                            'id' => $cm->id,
                        ]))->out(false);
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
            $data->announcementiconurl     = '';
            $data->announcementunreadcount = 0;
            $data->announcementforumid     = 0;
        }

        // ────────────────────────────────────────────
        // General forum icon (section 0).
        // ────────────────────────────────────────────
        $data->hasgeneralforumicon = false;
        $data->generalforumurl     = '';

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
                        $data->generalforumurl = (new moodle_url('/mod/forum/view.php', [
                            'id' => $cm->id,
                        ]))->out(false);
                        break;
                    }
                }
            }
        } catch (\Throwable $e) {
            debugging('STREAMDECK: error building general forum icon data: ' . $e->getMessage(), DEBUG_DEVELOPER);
            $data->hasgeneralforumicon = false;
            $data->generalforumurl     = '';
        }

        // ────────────────────────────────────────────────────────
        // Participants icon (capability-gated).
        // ────────────────────────────────────────────────────────
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

        // ─────────────────────────────────────────────
        // Live class (BigBlueButton) icon (section 0).
        // ─────────────────────────────────────────────
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
        $total    = 0;
        $complete = 0;

        foreach ($modinfo->get_cms() as $cm) {
            if ($cm->uservisible && $cm->completion != COMPLETION_TRACKING_NONE) {
                $total++;
                $cdata = $completion->get_data($cm, true, $USER->id);
                if ($cdata->completionstate == COMPLETION_COMPLETE ||
                    $cdata->completionstate == COMPLETION_COMPLETE_PASS) {
                    $complete++;
                }
            }
        }

        $data->hascompletion     = $total > 0;
        $data->completionpercent = $total ? (int) round(($complete / $total) * 100) : 0;
        $data->completiontext    = $total ? "$complete/$total complete" : '';

        // First incomplete activity for "Play / Resume" CTA.
        $firstincompleteurl = null;
        foreach ($modinfo->get_section_info_all() as $sectioninfo) {
            if (!$sectioninfo->uservisible) {
                continue;
            }
            foreach ($modinfo->sections[$sectioninfo->section] ?? [] as $cmid) {
                $cm = $modinfo->get_cm($cmid);
                if ($cm->uservisible && $cm->completion != COMPLETION_TRACKING_NONE) {
                    $cdata = $completion->get_data($cm, true, $USER->id);
                    if ($cdata->completionstate != COMPLETION_COMPLETE &&
                        $cdata->completionstate != COMPLETION_COMPLETE_PASS) {
                        $firstincompleteurl = new moodle_url('/mod/' . $cm->modname . '/view.php', [
                            'id' => $cmid,
                        ]);
                        break 2;
                    }
                }
            }
        }
        
        // Build grades modal
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
     * Build section tiles (Netflix-style cards) and continue-watching row
     * for the overview page.
     *
     * @param stdClass          $data
     * @param stdClass          $course
     * @param \course_modinfo   $modinfo
     * @param \completion_info  $completion
     */
    private function build_section_tiles(
        stdClass $data,
        stdClass $course,
        \course_modinfo $modinfo,
        \completion_info $completion
    ): void {
        global $PAGE, $USER;

        $data->sections         = [];
        $data->continuewatching = [];

        if (!$data->showsectiontiles) {
            return;
        }

            /** @var \format_streamdeck\output\renderer $frenderer */
            $frenderer = $PAGE->get_renderer('format_streamdeck');

 foreach ($modinfo->get_section_info_all() as $sectioninfo) {
    if ($sectioninfo->section == 0 || !$sectioninfo->visible || !$sectioninfo->available) {
        continue;
    }

    $section         = new stdClass();
    $section->name   = $this->format->get_section_name($sectioninfo);
    $section->url    = $this->format->get_view_url($sectioninfo->section)->out(false);

    $sectionimage = '';
    if ($frenderer && method_exists($frenderer, 'get_section_image_for_sectioninfo')) {
            $sectionimage = $frenderer->get_section_image_for_sectioninfo($sectioninfo);
    }

    $section->image    = $sectionimage;
    $section->hasimage = !empty($sectionimage);
            $section->locked   = !$sectioninfo->available;

            // Progress within the section.
            $complete = 0;
            $total    = 0;

            foreach ($modinfo->sections[$sectioninfo->section] ?? [] as $cmid) {
                $cm = $modinfo->get_cm($cmid);
                if ($cm->uservisible && $cm->completion != COMPLETION_TRACKING_NONE) {
                    $total++;
                    $cdata = $completion->get_data($cm, true, $USER->id);
                    if ($cdata->completionstate == COMPLETION_COMPLETE ||
                        $cdata->completionstate == COMPLETION_COMPLETE_PASS) {
                        $complete++;
                    }
                }
            }

            $section->progress    = $total ? (int) round(($complete / $total) * 100) : 0;
            $section->hasprogress = $total > 0;
            $section->done        = $section->progress === 100 && $total > 0;

            $data->sections[] = $section;

            if ($section->hasprogress && $section->progress > 0 && $section->progress < 100) {
                $data->continuewatching[] = $section;
            }
        }
    }

    /**
     * Build section-view data: episode, related activities, related resources,
     * completion gating for "Next episode", and prev/next section navigation.
     *
     * @param stdClass          $data
     * @param stdClass          $course
     * @param \course_modinfo   $modinfo
     * @param \completion_info  $completion
     * @param int               $currentsection
     */
    private function build_section_view(
        stdClass $data,
        stdClass $course,
        \course_modinfo $modinfo,
        \completion_info $completion,
        int $currentsection
    ): void {
        global $CFG, $DB, $PAGE, $USER;

        // Defaults.
        $data->hasepisode        = false;
        $data->episode           = null;
        $data->is_scorm_episode  = false;
        $data->relatedactivities = [];

        $data->courseurl       = (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false);
        $data->hasnextsection  = false;
        $data->nextsectionurl  = '';
        $data->nextsectionname = '';
        $data->hasprevsection  = false;
        $data->prevsectionurl  = '';
        $data->prevsectionname = '';
        $data->canshownextbutton = false;

        if (!$data->issectionview || !isset($modinfo->sections[$currentsection])) {
            return;
        }

        $sectioncms  = $modinfo->sections[$currentsection];
        $sectioninfo = $modinfo->get_section_info($currentsection);

        // Do not render Streamdeck episode view for hidden/unavailable sections,
        // even for teachers. Hidden sections are edited in core's edit UI.
        if (!$sectioninfo->visible || !$sectioninfo->available) {
            return;
        }

        $data->currentsectionname = $this->format->get_section_name($sectioninfo);
        $data->episodenumber      = $currentsection;

        // Episode candidate types.
        $episodecandidates = ['lesson', 'scorm', 'lti', 'url', 'advurl', 'h5pactivity'];

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
                    debugging('STREAMDECK: could not get icon url for cmid ' . $cm->id . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
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
        
        
        $episodecm          = null;
        $youtubeurlcandidate = null;
        $related             = [];

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
            $item->completionenabled = $cm->completion != COMPLETION_TRACKING_NONE;

            $cdata           = $completion->get_data($cm, true, $USER->id);
            $item->iscomplete = in_array(
                $cdata->completionstate,
                [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS],
                true
            );
            
            // ─── Forum-specific meta for Streamdeck styling ───
            if ($cm->modname === 'forum') {
                $item->isforum = true;

                // Default safe values.
                $item->forumdiscussions = 0;
                $item->forumposts = 0;
                $item->forumunread = 0;

                    // Fetch lightweight counts from DB if tables exist.
                    if ($DB->get_manager()->table_exists('forum_discussions')) {
                        $item->forumdiscussions = (int)$DB->count_records('forum_discussions', [
                            'course' => $course->id,
                            'forum'  => $cm->instance,
                        ]);
                    }

                    if ($DB->get_manager()->table_exists('forum_posts')) {
                        $item->forumposts = (int)$DB->count_records_sql(
                            "SELECT COUNT(p.id)
                               FROM {forum_posts} p
                               JOIN {forum_discussions} d ON d.id = p.discussion
                              WHERE d.forum = :forumid",
                            ['forumid' => $cm->instance]
                        );
                    }

                    // Unread count (direct query, no forum API).
                    try {
                        $item->forumunread = (int)$DB->count_records_sql(
                            "SELECT COUNT(p.id)
                               FROM {forum_posts} p
                               JOIN {forum_discussions} d ON d.id = p.discussion
                          LEFT JOIN {forum_read} r ON r.postid = p.id AND r.userid = :userid
                              WHERE d.forum = :forumid
                                AND r.id IS NULL",
                            ['userid' => $USER->id, 'forumid' => $cm->instance]
                        );
                    } catch (\Throwable $e) {
                        $item->forumunread = 0;
                    }
            } else {
                $item->isforum = false;
            }

            $item->introhtml      = '';
            $item->youtubeembedurl = '';
            $item->h5pembedurl    = '';
            $item->h5phtml        = '';
            
            // Forum and assignment flags and preview data for the modal.
                $item->isforum = false;
                $item->isassign = false;
                $item->forumdiscussions = 0;
                $item->forumposts = 0;
                $item->forumunread = 0;
                $item->forumpreviewhtml = '';
                $item->assignpreviewhtml = '';

                // Forum.
                if ($cm->modname === 'forum') {
                    $item->isforum = true;

                        if ($DB->get_manager()->table_exists('forum_discussions')) {
                            $item->forumdiscussions = (int)$DB->count_records('forum_discussions', [
                                'course' => $course->id,
                                'forum'  => $cm->instance,
                            ]);
                        }

                        if ($DB->get_manager()->table_exists('forum_posts')) {
                            $item->forumposts = (int)$DB->count_records_sql(
                                "SELECT COUNT(p.id)
                                   FROM {forum_posts} p
                                   JOIN {forum_discussions} d ON d.id = p.discussion
                                  WHERE d.forum = :forumid",
                                ['forumid' => $cm->instance]
                            );
                        }

                        // Unread count.
                        try {
                            $item->forumunread = (int)$DB->count_records_sql(
                                "SELECT COUNT(p.id)
                                   FROM {forum_posts} p
                                   JOIN {forum_discussions} d ON d.id = p.discussion
                                   LEFT JOIN {forum_read} r
                                          ON r.postid = p.id AND r.userid = :userid
                                  WHERE d.forum = :forumid
                                    AND r.id IS NULL",
                                ['userid' => $USER->id, 'forumid' => $cm->instance]
                            );
                        } catch (\Throwable $e) {
                            $item->forumunread = 0;
                        }

                        $item->forumpreviewhtml = $this->build_forum_preview_html($cm, $course);
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

            // Intro + embed meta.
                $cmrecord = $cm->get_course_module_record();

                if (!empty($cmrecord->intro) && isset($cmrecord->introformat)) {
                    $item->introhtml = format_text($cmrecord->intro, $cmrecord->introformat, [
                        'context' => $cm->context,
                        'para'    => true,
                    ]);
                }

                // YOUTUBE EMBEDDING (URL module).
                if ($cm->modname === 'url') {
                    $externalurl = $this->get_url_module_external_url($cm->instance);
                    if (!empty($externalurl)) {
                        $youtubeembedurl = $this->extract_youtube_embed_url($externalurl);
                        if (!empty($youtubeembedurl)) {
                            $item->youtubeembedurl = $youtubeembedurl;
                            $item->url             = $externalurl;
                            if ($youtubeurlcandidate === null) {
                                $youtubeurlcandidate = $item;
                            }
                        }
                    }
                }

                // YOUTUBE EMBEDDING (ADVANCED URL module).
                if ($cm->modname === 'advurl') {
                    $externalurl = $this->get_advurl_module_external_url($cm->instance);
                    if (!empty($externalurl)) {
                        $youtubeembedurl = $this->extract_youtube_embed_url($externalurl);
                        if (!empty($youtubeembedurl)) {
                            $item->youtubeembedurl = $youtubeembedurl;
                            $item->url             = $externalurl;
                            if ($youtubeurlcandidate === null) {
                                $youtubeurlcandidate = $item;
                            }
                        }
                    }
                }

                // H5P handling – exporter → embed.php → view.php iframe.
                if ($cm->modname === 'h5pactivity') {
                    $item->h5phtml     = '';
                    $item->h5pembedurl = '';

                    $exporterclass = '\mod_h5pactivity\output\activity_exporter';
                    if (class_exists($exporterclass)) {
                        try {
                            $h5precord = $DB->get_record('h5pactivity', ['id' => $cm->instance], '*', IGNORE_MISSING);
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
                            debugging('STREAMDECK: exporter inline render threw: ' . $e->getMessage(), DEBUG_DEVELOPER);
                            $item->h5phtml = '';
                        }
                    } else {
                        // Include H5P core libraries if present (covers different dir layouts).
                        $h5p_candidates = [
                            $CFG->dirroot . '/public/h5p/h5plib/v127/joubel/core/h5p.classes.php',
                            $CFG->dirroot . '/public/h5p/h5plib/h5p.classes.php',
                            $CFG->dirroot . '/h5p/h5plib/v127/joubel/core/h5p.classes.php',
                            $CFG->dirroot . '/h5p/h5plib/h5p.classes.php',
                        ];
                        foreach ($h5p_candidates as $cand) {
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
                $scormid = $cm->id;
                $scoid   = $DB->get_field('scorm_scoes', 'id', ['scorm' => $scormid], IGNORE_MISSING);
                $baseurl = new moodle_url('/mod/scorm/view.php', ['id' => $cm->id]);
                $embedurl        = $baseurl->out(false) . '&mode=embed';
                $item->embedurl  = $embedurl;
                $item->canembed  = true;
                $data->is_scorm_episode = true;
            } elseif (($cm->modname === 'url' || $cm->modname === 'advurl') && !empty($item->youtubeembedurl)) {
                $item->embedurl = $item->youtubeembedurl;
                $item->canembed = true;
            } elseif ($cm->modname !== 'h5pactivity') {
                $embeddablemods  = ['page', 'lesson', 'scorm', 'lti', 'url', 'advurl'];
                $item->canembed  = in_array($cm->modname, $embeddablemods, true) && !empty($item->url);
                $item->embedurl  = $item->canembed ? $item->url : '';
            } else {
                $item->canembed = false;
                $item->embedurl = '';
            }

            if ($episodecm === null && in_array($cm->modname, $episodecandidates, true)) {
                $episodecm = $item;
            }

            $related[] = $item;
        }

        // Select final episode: YouTube URL (if any) takes precedence.
        $finalepisode = $youtubeurlcandidate ?? $episodecm;

        if ($finalepisode !== null) {
            $data->hasepisode = true;
            $data->episode    = $finalepisode;

            $data->relatedactivities = array_values(
                array_filter($related, function ($r) use ($finalepisode) {
                    return (isset($r->modname) ? !in_array($r->modname, ['label', 'resource']) : true)
                        && ((int) $r->cmid !== (int) $finalepisode->cmid);
                })
            );

            $allepisodecomplete = $finalepisode->completionenabled ? $finalepisode->iscomplete : true;
            $allrelatedcomplete = true;

            foreach ($data->relatedactivities as $ra) {
                if ($ra->completionenabled && !$ra->iscomplete) {
                    $allrelatedcomplete = false;
                    break;
                }
            }

            $data->canshownextbutton = $allepisodecomplete && $allrelatedcomplete;
        } else {
            $data->hasepisode        = false;
            $data->episode           = null;
            $data->relatedactivities = array_values(
                array_filter($related, function ($r) {
                    return isset($r->modname) ? !in_array($r->modname, ['label', 'resource']) : true;
                })
            );
            $data->canshownextbutton = false;
        }

        $data->hasrelatedactivities = !empty($data->relatedactivities);
        $data->hasrelatedsidebar = $data->hasrelatedresources || $data->hasrelatedactivities;

        // Previous / next section navigation.
            $sectionsall = $modinfo->get_section_info_all();

            // Previous visible section (>0, <current).
            for ($i = $currentsection - 1; $i >= 1; $i--) {
                if (!isset($sectionsall[$i])) {
                    continue;
                }
                $sinfo = $sectionsall[$i];
                if (!$sinfo->uservisible) {
                    continue;
                }
                $data->hasprevsection  = true;
                $data->prevsectionname = $this->format->get_section_name($sinfo);
                $data->prevsectionurl  = $this->format->get_view_url($sinfo->section)->out(false);
                break;
            }

            // Next visible section.
            $maxsectionnum = max(array_keys($modinfo->sections));
            for ($i = $currentsection + 1; $i <= $maxsectionnum; $i++) {
                if (!isset($sectionsall[$i])) {
                    continue;
                }
                $sinfo = $sectionsall[$i];
                if (!$sinfo->uservisible) {
                    continue;
                }
                $data->hasnextsection  = true;
                $data->nextsectionname = $this->format->get_section_name($sinfo);
                $data->nextsectionurl  = $this->format->get_view_url($sinfo->section)->out(false);
                break;
            }
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
                require_once $CFG->dirroot . '/mod/courseschedule/lib.php';

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
                'introhtml'      => $syllabus['introhtml'],
                'outcomeshtml'   => $syllabus['outcomeshtml'],
                'assessmentshtml'=> $syllabus['assessmentshtml'],
                'materialshtml'  => $syllabus['materialshtml'],
            ];

            if (has_capability('moodle/course:update', $context)) {
                $data->editsyllabusurl = (new moodle_url('/course/format/streamdeck/syllabus.php', [
                    'id' => $course->id,
                ]))->out(false);
            } else {
                $data->editsyllabusurl = null;
            }
    }

    // ==================================================================
    //  LOW-LEVEL UTILITIES (unchanged logic, reused by helpers)
    // ==================================================================

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

            // 2. If not configured, use default shortnames if present.
            if (empty($roleids)) {
                $defaults = [];

                $editingrole = $DB->get_record('role', ['shortname' => 'editingteacher'], 'id');
                if ($editingrole) {
                    $defaults[] = $editingrole->id;
                }

                $teacherrole = $DB->get_record('role', ['shortname' => 'teacher'], 'id');
                if ($teacherrole) {
                    $defaults[] = $teacherrole->id;
                }

                $roleids = $defaults;
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
     * Get the external URL from a URL module instance.
     *
     * Queries the mdl_url table to retrieve the externalurl field for the given instance ID.
     *
     * @param int $instanceid The instance ID of the URL module
     * @return string The external URL, or empty string if not found
     */
    private function get_url_module_external_url(int $instanceid): string {
        global $DB;

            $urlrecord = $DB->get_record('url', ['id' => $instanceid], 'externalurl');
            if ($urlrecord && !empty($urlrecord->externalurl)) {
                return $urlrecord->externalurl;
            }

        return '';
    }

    /**
     * Get the external URL from an Advanced URL module instance.
     *
     * Queries the mdl_advurl table to retrieve the externalurl field for the given instance ID.
     * Mirrors the logic of get_url_module_external_url() for consistency.
     *
     * @param int $instanceid The instance ID of the Advanced URL module
     * @return string The external URL, or empty string if not found
     */
    private function get_advurl_module_external_url(int $instanceid): string {
        global $DB;

        try {
            // Defensive: check if the advurl table exists before querying.
            if (!$DB->get_manager()->table_exists('advurl')) {
                return '';
            }

            $advurlrecord = $DB->get_record('advurl', ['id' => $instanceid], 'externalurl', IGNORE_MISSING);
            if ($advurlrecord && !empty($advurlrecord->externalurl)) {
                return $advurlrecord->externalurl;
            }

        } catch (\Throwable $e) {
            debugging(
                'STREAMDECK: get_advurl_module_external_url failed for instance ' . $instanceid . ': ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
        }

        return '';
    }


    /**
     * Build a forum preview for the Streamdeck modal.
     *
     * Behaviour:
     *  - If the forum has exactly ONE discussion, render that discussion
     *    (all posts) inline in the modal, plus a Reply button and an
     *    iframe wrapper for the full discuss.php page.
     *  - If the forum has MULTIPLE discussions, render a short list of
     *    recent discussions with links.
     *
     * No calls to mod/forum/lib.php are made, to avoid API/signature issues.
     *
     * @param \cm_info  $cm     Course module info for the forum.
     * @param \stdClass $course Course record.
     * @return string HTML snippet or empty string on failure.
     */
    private function build_forum_preview_html(\cm_info $cm, \stdClass $course): string {
        global $DB;

        try {
            $context = $cm->context;

            // Fetch all discussions for this forum.
            $alldiscs = $DB->get_records('forum_discussions',
                ['forum' => $cm->instance],
                'timemodified ASC' // Oldest first.
            );

            if (empty($alldiscs)) {
                return '';
            }

            $count = count($alldiscs);

            // ── Case 1: exactly one discussion → inline full discussion ──
            if ($count === 1) {
                /** @var \stdClass $discussion */
                $discussion = reset($alldiscs);

                // Fetch all posts in this discussion, ordered by creation time.
                $posts = $DB->get_records('forum_posts',
                    ['discussion' => $discussion->id],
                    'created ASC'
                );

                if (empty($posts)) {
                    return '';
                }

                $discussionurl = (new moodle_url('/mod/forum/discuss.php', ['d' => $discussion->id]))->out(false);

                $o  = \html_writer::start_div('streamdeck-forum-preview streamdeck-forum-single');

                // Use discussion name as title.
                $o .= \html_writer::tag(
                    'h3',
                    format_string($discussion->name, true, ['context' => $context]),
                    ['class' => 'streamdeck-forum-preview-title']
                );

                // Posts container.
                $o .= \html_writer::start_div('streamdeck-forum-posts');

                foreach ($posts as $post) {
                    $o .= $this->render_forum_post($post, $context);
                }

                $o .= \html_writer::end_div(); // .streamdeck-forum-posts

                // Reply button that JS will use to show the iframe.
                $o .= \html_writer::start_tag('button', [
                    'type' => 'button',
                    'class' => 'btn btn-primary streamdeck-forum-reply-btn',
                    'data-discussion-url' => $discussionurl,
                ]);
                $o .= get_string('reply', 'forum');
                $o .= \html_writer::end_tag('button');

                // Hidden iframe wrapper – populated by JS on first open.
                $o .= \html_writer::start_div('streamdeck-forum-reply-frame-wrapper', [
                    'data-discussion-id' => $discussion->id,
                    'hidden' => 'hidden',
                ]);

                // Optional loading placeholder.
                $o .= \html_writer::div('', 'streamdeck-forum-reply-frame-loading');

                // Iframe itself; src set lazily via JS.
                $o .= \html_writer::tag('iframe', '', [
                    'class' => 'streamdeck-forum-reply-frame',
                    'src' => '',
                    'loading' => 'lazy',
                    'title' => format_string($discussion->name, true, ['context' => $context]),
                    'frameborder' => '0',
                    'allowfullscreen' => 'allowfullscreen',
                ]);

                $o .= \html_writer::end_div(); // .streamdeck-forum-reply-frame-wrapper

                $o .= \html_writer::end_div(); // .streamdeck-forum-preview

                return $o;
            }

            // ── Case 2: multiple discussions → short list ──
            // Sort by latest activity for the preview.
            $recentdiscs = $DB->get_records('forum_discussions',
                ['forum' => $cm->instance, 'course' => $course->id],
                'timemodified DESC',
                'id, name, timemodified',
                0,
                5 // show latest 5
            );

            if (empty($recentdiscs)) {
                $recentdiscs = $alldiscs; // Fallback, in case course filter did not match.
            }

            $o  = \html_writer::start_div('streamdeck-forum-preview');

            // Forum title.
            $o .= \html_writer::tag(
                'h3',
                format_string($cm->name, true, ['context' => $context]),
                ['class' => 'streamdeck-forum-preview-title']
            );

            $o .= \html_writer::start_tag('ul', ['class' => 'streamdeck-forum-preview-list']);

            foreach ($recentdiscs as $disc) {
                $discurl = new moodle_url('/mod/forum/discuss.php', ['d' => $disc->id]);

                $o .= \html_writer::start_tag('li', ['class' => 'streamdeck-forum-preview-item']);
                $o .= \html_writer::link(
                    $discurl,
                    format_string($disc->name, true, ['context' => $context]),
                    ['class' => 'streamdeck-forum-preview-link']
                );
                $o .= \html_writer::end_tag('li');
            }

            $o .= \html_writer::end_tag('ul');

            // View all discussions (cmid‑based).
            $o .= \html_writer::link(
                new moodle_url('/mod/forum/view.php', ['id' => $cm->id]),
                get_string('viewalldiscussions', 'forum'),
                ['class' => 'streamdeck-forum-preview-all']
            );

            $o .= \html_writer::end_div(); // .streamdeck-forum-preview

            return $o;

        } catch (\Throwable $e) {
            debugging(
                'STREAMDECK: build_forum_preview_html failed for cmid ' . $cm->id . ': ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
            return '';
        }
    }

        /**
         * Render a single forum post for inline display in the Streamdeck modal.
         *
         * @param \stdClass $post    Forum post record.
         * @param \context  $context Course module context.
         * @return string HTML for the post.
         */
        private function render_forum_post(\stdClass $post, \context $context): string {
            global $DB, $OUTPUT;

            try {
                $user = $DB->get_record('user', ['id' => $post->userid], '*', IGNORE_MISSING);
                if (!$user) {
                    $user = (object)['id' => 0, 'firstname' => 'Unknown', 'lastname' => 'User'];
                }

                $userpicture = new \user_picture($user);
                $userpicture->size = 50;
                $avatarhtml = $OUTPUT->render($userpicture);

                $fullname = fullname($user);
                $posttime = userdate($post->created, get_string('strftimedatetimeshort', 'langconfig'));

                $message = format_text($post->message, $post->messageformat, [
                    'context' => $context,
                    'para'    => true,
                ]);

                $o  = \html_writer::start_div('streamdeck-forum-post');
                $o .= \html_writer::start_div('streamdeck-forum-post-header');
                $o .= \html_writer::div($avatarhtml, 'streamdeck-forum-post-avatar');
                $o .= \html_writer::start_div('streamdeck-forum-post-meta');
                $o .= \html_writer::tag('strong', $fullname, ['class' => 'streamdeck-forum-post-author']);
                $o .= \html_writer::tag('span', $posttime, ['class' => 'streamdeck-forum-post-time']);
                $o .= \html_writer::end_div(); // .streamdeck-forum-post-meta
                $o .= \html_writer::end_div(); // .streamdeck-forum-post-header
                $o .= \html_writer::div($message, 'streamdeck-forum-post-message');
                $o .= \html_writer::end_div(); // .streamdeck-forum-post

                return $o;

            } catch (\Throwable $e) {
                debugging(
                    'STREAMDECK: render_forum_post failed for post ' . $post->id . ': ' . $e->getMessage(),
                    DEBUG_DEVELOPER
                );
                return '';
            }
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

                // "View / submit" button that triggers iframe load.
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
                $o .= \html_writer::end_div(); // .streamdeck-assign-frame-wrapper

                $o .= \html_writer::end_div(); // .streamdeck-assign-preview

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
     * @return string The YouTube embed URL (https://www.youtube.com/embed/VIDEO_ID), or empty string if not a valid YouTube URL
     */
    private function extract_youtube_embed_url(string $text): string {
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