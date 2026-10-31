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

namespace format_streamdeck\output;

/**
 * Tests for the Moodle App course view.
 *
 * @package    format_streamdeck
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(mobile::class)]
final class mobile_test extends \advanced_testcase {
    /**
     * Name a section's options.
     *
     * @param \stdClass $course
     * @param int $sectionnum
     * @param array $options
     */
    private function set_section_options(\stdClass $course, int $sectionnum, array $options): void {
        $section = get_fast_modinfo($course)->get_section_info($sectionnum);
        course_get_format($course)->update_section_format_options(['id' => $section->id] + $options);
    }

    /**
     * The app gets a fixed template, the course data as JSON, and the parsing script.
     */
    public function test_mobile_course_view(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();

        $course = $generator->create_course([
            'format' => 'streamdeck',
            'numsections' => 3,
            'enablecompletion' => 1,
            'fullname' => 'Biblical Hebrew {{ 7 * 7 }}',
        ]);
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');

        $quiz = $generator->create_module('quiz', [
            'course' => $course->id,
            'section' => 1,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);
        $video = $generator->create_module('url', [
            'course' => $course->id,
            'section' => 2,
            'externalurl' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);
        $generator->create_module('assign', ['course' => $course->id, 'section' => 2]);
        $this->set_section_options($course, 2, [
            'seasonname' => 'Nouns',
            'duration' => 15,
            'showabout' => 1,
            'about' => '<p>Name the parts of speech</p>',
            'aboutformat' => FORMAT_HTML,
            'abouticon' => 'bullseye',
        ]);
        // Section 3 opens after section 2's video is complete.
        $section3 = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 3]);
        $DB->set_field('course_sections', 'availability', json_encode([
            'op' => '&',
            'c' => [['type' => 'completion', 'cm' => $video->cmid, 'e' => COMPLETION_COMPLETE]],
            'showc' => [true],
        ]), ['id' => $section3->id]);
        rebuild_course_cache($course->id, true);

        $this->setUser($student);
        $result = mobile::mobile_course_view(['courseid' => $course->id]);

        // Teacher and learner text never reaches the template the app compiles.
        $this->assertCount(1, $result['templates']);
        $this->assertSame('main', $result['templates'][0]['id']);
        $this->assertStringNotContainsString('Biblical Hebrew', $result['templates'][0]['html']);
        $this->assertStringContainsString('format-streamdeck-app', $result['templates'][0]['html']);
        $this->assertStringContainsString('CONTENT_OTHERDATA', $result['javascript']);
        $this->assertSame('0', $result['otherdata']['view']);

        $data = json_decode($result['otherdata']['data'], true);
        $this->assertSame('Biblical Hebrew {{ 7 * 7 }}', $data['course']['fullname']);
        $this->assertSame(1, $data['course']['resume']);

        $episodes = array_column($data['episodes'], null, 'num');
        $this->assertSame([1, 2, 3], array_keys($episodes));
        $this->assertSame((int) $quiz->cmid, $episodes[1]['featuredcmid']);
        $this->assertSame((int) $video->cmid, $episodes[2]['featuredcmid']);
        $this->assertSame(format_time(15 * MINSECS), $episodes[2]['durationtext']);
        $this->assertStringContainsString('Name the parts of speech', $episodes[2]['about']);
        $this->assertSame('fas-bullseye', $episodes[2]['abouticon']);
        $this->assertSame(get_string('season', 'format_streamdeck') . ' 2: Nouns', $episodes[2]['seasonname']);
        // Like the web page, "next" only points at episodes the learner can open; section 3 is still locked.
        $this->assertSame([1, 0], [$episodes[2]['prev'], $episodes[2]['next']]);
        $this->assertFalse($episodes[2]['canshownext']);
        $this->assertTrue($episodes[3]['locked']);
        $this->assertNotEmpty($episodes[3]['lockedinfo']);
        $this->assertSame(0, $episodes[3]['featuredcmid']);

        $this->assertTrue($data['hasseasons']);
        $this->assertSame([[1], [2, 3]], array_column($data['seasons'], 'nums'));
        $this->assertSame(get_string('seasonepisodes', 'format_streamdeck', 2), $data['seasons'][1]['episodecount']);
    }

    /**
     * A course without completion or seasons still exports cleanly.
     */
    public function test_export_course_minimal(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course(['format' => 'streamdeck', 'numsections' => 1]);

        $data = mobile::export_course($course);

        $this->assertFalse($data['hasseasons']);
        $this->assertFalse($data['course']['hascompletion']);
        $this->assertSame(0, $data['course']['resume']);
        $this->assertCount(1, $data['episodes']);
        $this->assertSame(0, $data['episodes'][0]['featuredcmid']);
    }
}
