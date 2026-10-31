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

namespace format_streamdeck\local;

use format_streamdeck\output\courseformat\content;

/**
 * What the web course page and the Moodle App share about each episode: card metadata, the featured activity
 * and the neighbouring episodes.
 *
 * @package    format_streamdeck
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class episodes {
    /** @var string[] Activity dates (core\activity_dates data ids) that are deadlines. */
    private const DEADLINE_DATES = ['duedate', 'timeclose', 'deadline', 'submissionend', 'assessmentend', 'timeavailableto'];

    /** @var int How long an episode counts as new after something is added to it. */
    private const NEW_PERIOD = WEEKSECS;

    /** @var int How far ahead a deadline counts as due soon. */
    private const DUE_SOON_PERIOD = WEEKSECS;

    /** @var array|null cmid => YouTube embed URL, for URL activities that link to YouTube. */
    private ?array $youtube = null;

    /**
     * Constructor.
     *
     * @param \format_streamdeck $format
     * @param \course_modinfo $modinfo
     * @param course_progress $progress
     */
    public function __construct(
        /** @var \format_streamdeck The course format. */
        private \format_streamdeck $format,
        /** @var \course_modinfo The learner's view of the course. */
        private \course_modinfo $modinfo,
        /** @var course_progress The learner's completion. */
        private course_progress $progress,
    ) {
    }

    /**
     * The top-level sections that are episodes: not section 0, not subsections.
     *
     * @return \section_info[]
     */
    public function get_episode_sections(): array {
        return array_values(array_filter($this->modinfo->get_section_info_all(), function (\section_info $section) {
            return $section->section > 0 && empty($section->component);
        }));
    }

    /**
     * Running time, "New" and "Due soon" for an episode.
     *
     * @param \section_info $sectioninfo
     * @return array durationtext, isnew, hasdue, duetext.
     */
    public function get_metadata(\section_info $sectioninfo): array {
        global $USER;

        $minutes = max(0, (int) $sectioninfo->duration);
        $metadata = [
            'durationtext' => $minutes ? format_time($minutes * MINSECS) : '',
            'isnew' => false,
            'hasdue' => false,
            'duetext' => '',
        ];
        if (!$sectioninfo->uservisible) {
            return $metadata;
        }

        $now = time();
        $started = $this->progress->get_section_counts($sectioninfo->section)['complete'] > 0;
        $nextdue = null;
        foreach ($this->modinfo->sections[$sectioninfo->section] ?? [] as $cmid) {
            $cm = $this->modinfo->get_cm($cmid);
            if (!$cm->uservisible) {
                continue;
            }
            // New: something was added recently and the learner has not begun the episode.
            if (!$started && $cm->added > $now - self::NEW_PERIOD) {
                $metadata['isnew'] = true;
            }
            // Due soon: the nearest deadline in the coming week on something not yet done.
            if ($this->progress->is_complete($cm->id)) {
                continue;
            }
            foreach (\core\activity_dates::get_dates_for_module($cm, $USER->id) as $date) {
                $isdeadline = in_array($date['dataid'] ?? '', self::DEADLINE_DATES, true);
                if ($isdeadline && $date['timestamp'] > $now && $date['timestamp'] <= $now + self::DUE_SOON_PERIOD) {
                    $nextdue = $nextdue === null ? $date['timestamp'] : min($nextdue, $date['timestamp']);
                }
            }
        }
        if ($nextdue !== null) {
            $metadata['hasdue'] = true;
            $metadata['duetext'] = get_string(
                'duesoon',
                'format_streamdeck',
                userdate($nextdue, get_string('strftimedaydatetime', 'langconfig'))
            );
        }
        return $metadata;
    }

    /**
     * The activity on an episode's stage, chosen as on the web course page.
     *
     * @param \section_info $sectioninfo
     * @return \cm_info|null
     */
    public function get_featured_cm(\section_info $sectioninfo): ?\cm_info {
        $items = [];
        foreach ($this->modinfo->sections[$sectioninfo->section] ?? [] as $cmid) {
            $cm = $this->modinfo->get_cm($cmid);
            if (!$cm->uservisible) {
                continue;
            }
            $items[] = (object) [
                'cmid' => $cm->id,
                'modname' => $cm->modname,
                'youtubeembedurl' => $this->get_youtube_urls()[$cm->id] ?? '',
            ];
        }
        $featured = content::select_featured_activity($items, (int) $sectioninfo->featuredcmid);
        return $featured ? $this->modinfo->get_cm($featured->cmid) : null;
    }

    /**
     * The previous and next episodes the learner can open.
     *
     * @param int $sectionnum
     * @return array ['prev' => section_info|null, 'next' => section_info|null]
     */
    public function get_neighbours(int $sectionnum): array {
        $prev = $next = null;
        foreach ($this->get_episode_sections() as $section) {
            if (!$section->uservisible) {
                continue;
            }
            if ($section->section < $sectionnum) {
                $prev = $section;
            } else if ($section->section > $sectionnum && $next === null) {
                $next = $section;
            }
        }
        return ['prev' => $prev, 'next' => $next];
    }

    /**
     * Whether everything with completion tracking in an episode is done, so the next episode can be offered.
     *
     * @param int $sectionnum
     * @return bool
     */
    public function is_episode_complete(int $sectionnum): bool {
        $counts = $this->progress->get_section_counts($sectionnum);
        return $counts['complete'] === $counts['total'];
    }

    /**
     * YouTube embed URLs of the course's URL activities, read in one query per module type.
     *
     * @return array cmid => embed URL
     */
    private function get_youtube_urls(): array {
        global $DB;

        if ($this->youtube !== null) {
            return $this->youtube;
        }
        $this->youtube = [];
        $dbman = $DB->get_manager();
        foreach (['url', 'advurl'] as $modname) {
            $cms = $this->modinfo->get_instances_of($modname);
            if (!$cms || !$dbman->table_exists($modname)) {
                continue;
            }
            $urls = $DB->get_records_list($modname, 'id', array_map(fn($cm) => $cm->instance, $cms), '', 'id, externalurl');
            foreach ($cms as $cm) {
                $embed = content::extract_youtube_embed_url((string) ($urls[$cm->instance]->externalurl ?? ''));
                if ($embed !== '') {
                    $this->youtube[$cm->id] = $embed;
                }
            }
        }
        return $this->youtube;
    }
}
