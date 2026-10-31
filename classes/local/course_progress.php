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

/**
 * One learner's completion across a course, read once per page.
 *
 * The states come from completion_info::get_data() for the whole course, which core keeps in the core/completion
 * cache, so building this costs one cached read. The hero, the episode cards and the episode page all ask this
 * object instead of walking the course modules again.
 *
 * @package    format_streamdeck
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_progress {
    /** @var bool[] cmid => complete, for visible activities that track completion. */
    private array $states = [];

    /** @var array[] section number => ['complete' => int, 'total' => int]. */
    private array $sections = [];

    /** @var \cm_info|null The first tracked activity not yet complete, in course order. */
    private ?\cm_info $firstincomplete = null;

    /**
     * Read the learner's completion states.
     *
     * @param \course_modinfo $modinfo
     * @param \completion_info $completion
     * @param int $userid
     */
    public function __construct(\course_modinfo $modinfo, \completion_info $completion, int $userid) {
        foreach ($modinfo->get_section_info_all() as $sectioninfo) {
            $counts = ['complete' => 0, 'total' => 0];
            foreach ($modinfo->sections[$sectioninfo->section] ?? [] as $cmid) {
                $cm = $modinfo->get_cm($cmid);
                if (!$cm->uservisible || $cm->completion == COMPLETION_TRACKING_NONE) {
                    continue;
                }
                // Cast: the state can come back from the cache as a string.
                $state = (int) $completion->get_data($cm, true, $userid)->completionstate;
                $complete = in_array($state, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS], true);
                $this->states[$cm->id] = $complete;
                $counts['total']++;
                $counts['complete'] += (int) $complete;
                if (!$complete && $this->firstincomplete === null && $sectioninfo->uservisible) {
                    $this->firstincomplete = $cm;
                }
            }
            $this->sections[$sectioninfo->section] = $counts;
        }
    }

    /**
     * Whether an activity tracks completion for this learner.
     *
     * @param int $cmid
     * @return bool
     */
    public function is_tracked(int $cmid): bool {
        return array_key_exists($cmid, $this->states);
    }

    /**
     * Whether a tracked activity is complete.
     *
     * @param int $cmid
     * @return bool
     */
    public function is_complete(int $cmid): bool {
        return $this->states[$cmid] ?? false;
    }

    /**
     * Completed and total tracked activities in one section.
     *
     * @param int $sectionnum
     * @return array ['complete' => int, 'total' => int]
     */
    public function get_section_counts(int $sectionnum): array {
        return $this->sections[$sectionnum] ?? ['complete' => 0, 'total' => 0];
    }

    /**
     * Completed and total tracked activities in the course.
     *
     * @return array ['complete' => int, 'total' => int]
     */
    public function get_course_counts(): array {
        return [
            'complete' => (int) array_sum(array_column($this->sections, 'complete')),
            'total' => (int) array_sum(array_column($this->sections, 'total')),
        ];
    }

    /**
     * Percentage complete for counts from this class.
     *
     * @param array $counts
     * @return int
     */
    public static function percent(array $counts): int {
        return $counts['total'] ? (int) round($counts['complete'] / $counts['total'] * 100) : 0;
    }

    /**
     * The first activity the learner has still to complete, in course order.
     *
     * @return \cm_info|null
     */
    public function get_first_incomplete(): ?\cm_info {
        return $this->firstincomplete;
    }
}
