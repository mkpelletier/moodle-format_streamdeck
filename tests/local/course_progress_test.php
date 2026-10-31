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
 * Tests for the per-page completion snapshot.
 *
 * @package    format_streamdeck
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(course_progress::class)]
final class course_progress_test extends \advanced_testcase {
    /**
     * Counts per section and for the course, and the next activity to resume.
     */
    public function test_progress(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $student = $generator->create_user();
        $course = $generator->create_course(['format' => 'streamdeck', 'numsections' => 2, 'enablecompletion' => 1]);
        $generator->enrol_user($student->id, $course->id, 'student');

        $manual = ['course' => $course->id, 'completion' => COMPLETION_TRACKING_MANUAL];
        $first = $generator->create_module('page', $manual + ['section' => 1]);
        $second = $generator->create_module('page', $manual + ['section' => 1]);
        $third = $generator->create_module('page', $manual + ['section' => 2]);
        $untracked = $generator->create_module('page', ['course' => $course->id, 'section' => 2]);

        $completion = new \completion_info($course);
        $modinfo = get_fast_modinfo($course, $student->id);
        $completion->update_state($modinfo->get_cm($first->cmid), COMPLETION_COMPLETE, $student->id);

        $progress = new course_progress(get_fast_modinfo($course, $student->id), $completion, $student->id);

        $this->assertTrue($progress->is_complete($first->cmid));
        $this->assertFalse($progress->is_complete($second->cmid));
        $this->assertFalse($progress->is_tracked($untracked->cmid));
        $this->assertSame(['complete' => 1, 'total' => 2], $progress->get_section_counts(1));
        $this->assertSame(['complete' => 0, 'total' => 1], $progress->get_section_counts(2));
        $this->assertSame(['complete' => 1, 'total' => 3], $progress->get_course_counts());
        $this->assertSame(33, course_progress::percent($progress->get_course_counts()));
        $this->assertEquals($second->cmid, $progress->get_first_incomplete()->id);
        $this->assertNotEquals($third->cmid, $progress->get_first_incomplete()->id);
    }
}
