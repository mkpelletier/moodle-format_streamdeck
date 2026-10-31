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
 * PHPUnit tests for the format_streamdeck content output class.
 *
 * @package    format_streamdeck
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_streamdeck;

/**
 * Tests for the content output class.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\format_streamdeck\output\courseformat\content::class)]
final class content_test extends \advanced_testcase {
    /**
     * Test that extract_youtube_embed_url works for standard watch URLs.
     */
    public function test_extract_youtube_watch_url(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['format' => 'streamdeck', 'numsections' => 1]);
        $format = course_get_format($course);
        $content = new \format_streamdeck\output\courseformat\content($format);

        // Use reflection to access private method.
        $method = new \ReflectionMethod($content, 'extract_youtube_embed_url');

        $result = $method->invoke($content, 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');
        $this->assertEquals('https://www.youtube.com/embed/dQw4w9WgXcQ', $result);
    }

    /**
     * Test that extract_youtube_embed_url works for short URLs.
     */
    public function test_extract_youtube_short_url(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['format' => 'streamdeck', 'numsections' => 1]);
        $format = course_get_format($course);
        $content = new \format_streamdeck\output\courseformat\content($format);

        $method = new \ReflectionMethod($content, 'extract_youtube_embed_url');

        $result = $method->invoke($content, 'https://youtu.be/dQw4w9WgXcQ');
        $this->assertEquals('https://www.youtube.com/embed/dQw4w9WgXcQ', $result);
    }

    /**
     * Test that extract_youtube_embed_url works for embed URLs.
     */
    public function test_extract_youtube_embed_url(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['format' => 'streamdeck', 'numsections' => 1]);
        $format = course_get_format($course);
        $content = new \format_streamdeck\output\courseformat\content($format);

        $method = new \ReflectionMethod($content, 'extract_youtube_embed_url');

        $result = $method->invoke($content, 'https://www.youtube.com/embed/dQw4w9WgXcQ');
        $this->assertEquals('https://www.youtube.com/embed/dQw4w9WgXcQ', $result);
    }

    /**
     * Test that extract_youtube_embed_url returns empty for non-YouTube URLs.
     */
    public function test_extract_youtube_non_youtube(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['format' => 'streamdeck', 'numsections' => 1]);
        $format = course_get_format($course);
        $content = new \format_streamdeck\output\courseformat\content($format);

        $method = new \ReflectionMethod($content, 'extract_youtube_embed_url');

        $result = $method->invoke($content, 'https://vimeo.com/123456');
        $this->assertEquals('', $result);
    }

    /**
     * Test that extract_youtube_embed_url handles YouTube URL embedded in HTML text.
     */
    public function test_extract_youtube_in_html(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['format' => 'streamdeck', 'numsections' => 1]);
        $format = course_get_format($course);
        $content = new \format_streamdeck\output\courseformat\content($format);

        $method = new \ReflectionMethod($content, 'extract_youtube_embed_url');

        $html = '<p>Watch this: <a href="https://www.youtube.com/watch?v=dQw4w9WgXcQ">video</a></p>';
        $result = $method->invoke($content, $html);
        $this->assertEquals('https://www.youtube.com/embed/dQw4w9WgXcQ', $result);
    }

    /**
     * Test that export_for_template produces valid data for the overview page.
     */
    public function test_export_for_template_overview(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course([
            'format' => 'streamdeck',
            'numsections' => 3,
            'enablecompletion' => 1,
        ]);

        $PAGE->set_course($course);
        $PAGE->set_url(new \moodle_url('/course/view.php', ['id' => $course->id]));
        $PAGE->set_context(\context_course::instance($course->id));

        $format = course_get_format($course);
        $content = new \format_streamdeck\output\courseformat\content($format);
        $renderer = $PAGE->get_renderer('format_streamdeck');

        $data = $content->export_for_template($renderer);

        // Overview page: hero should show, section view should not.
        $this->assertTrue($data->showhero);
        $this->assertFalse($data->issectionview);
        $this->assertObjectHasProperty('courseurl', $data);
    }

    /**
     * Build the section view for a section, as a learner would see it.
     *
     * @param \stdClass $course
     * @param int $section
     * @return \stdClass Template data.
     */
    private function export_section(\stdClass $course, int $section): \stdClass {
        global $PAGE;

        // A page's course can only be set once; start a new one for each export.
        $PAGE = new \moodle_page();
        $PAGE->set_course($course);
        $PAGE->set_url(new \moodle_url('/course/view.php', ['id' => $course->id, 'section' => $section]));
        $PAGE->set_context(\context_course::instance($course->id));

        $_GET['section'] = $section;
        try {
            $content = new \format_streamdeck\output\courseformat\content(course_get_format($course));
            $data = $content->export_for_template($PAGE->get_renderer('format_streamdeck'));
        } finally {
            unset($_GET['section']);
        }

        // The Streamdeck export, not the core fallback used when it throws.
        $this->assertObjectHasProperty('issectionview', $data);
        return $data;
    }

    /**
     * A unit with only an assignment puts the assignment on stage, and still has episode navigation.
     */
    public function test_assessment_only_section_takes_stage(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course([
            'format' => 'streamdeck',
            'numsections' => 2,
            'enablecompletion' => 1,
        ]);
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'section' => 1,
            'name' => 'Final assignment',
            'intro' => 'Hand in your final portfolio.',
        ]);

        $data = $this->export_section($course, 1);

        $this->assertTrue($data->hasepisode);
        $this->assertFalse($data->noepisode);
        $this->assertEquals($assign->cmid, $data->episode->cmid);
        $this->assertTrue($data->episode->isstage);
        $this->assertTrue($data->episode->stage['isassessment']);
        $this->assertStringContainsString('Hand in your final portfolio.', $data->episode->introhtml);
        $this->assertFalse($data->hasrelatedactivities);
        $this->assertTrue($data->hasnextsection);
    }

    /**
     * A quiz on stage shows its settings and offers to start.
     */
    public function test_quiz_stage_facts(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['format' => 'streamdeck', 'numsections' => 1]);
        $this->getDataGenerator()->create_module('quiz', [
            'course' => $course->id,
            'section' => 1,
            'timelimit' => HOURSECS,
            'attempts' => 2,
        ]);

        $data = $this->export_section($course, 1);
        $stage = $data->episode->stage;

        $this->assertEquals(get_string('stageaction:start', 'format_streamdeck'), $stage['actionlabel']);
        $values = array_column($stage['facts'], 'value', 'label');
        $this->assertEquals(format_time(HOURSECS), $values[get_string('timelimit', 'quiz')]);
        $this->assertEquals(2, $values[get_string('attemptsallowed', 'quiz')]);
    }

    /**
     * Learning content keeps the stage; assessments move to the sidebar.
     */
    public function test_learning_activity_takes_stage_over_assessment(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['format' => 'streamdeck', 'numsections' => 1]);
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id, 'section' => 1]);
        $url = $this->getDataGenerator()->create_module('url', [
            'course' => $course->id,
            'section' => 1,
            'externalurl' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        $data = $this->export_section($course, 1);

        $this->assertEquals($url->cmid, $data->episode->cmid);
        $this->assertFalse($data->episode->isstage);
        $this->assertEquals([$assign->cmid], array_column($data->relatedactivities, 'cmid'));
    }

    /**
     * A teacher can pin any activity to the stage.
     */
    public function test_pinned_activity_takes_stage(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['format' => 'streamdeck', 'numsections' => 1]);
        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $course->id, 'section' => 1]);
        $this->getDataGenerator()->create_module('url', [
            'course' => $course->id,
            'section' => 1,
            'externalurl' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);
        $section = get_fast_modinfo($course)->get_section_info(1);
        course_get_format($course)->update_section_format_options(['id' => $section->id, 'featuredcmid' => $forum->cmid]);

        $data = $this->export_section($course, 1);

        $this->assertEquals($forum->cmid, $data->episode->cmid);
        $this->assertTrue($data->episode->isstage);
        $this->assertFalse($data->episode->stage['isassessment']);
    }

    /**
     * Precedence of the automatic choice.
     */
    public function test_select_featured_activity(): void {
        $item = fn(int $cmid, string $modname, string $youtube = '') =>
            (object) ['cmid' => $cmid, 'modname' => $modname, 'youtubeembedurl' => $youtube];
        $select = fn(array $items, int $pinned = 0) =>
            \format_streamdeck\output\courseformat\content::select_featured_activity($items, $pinned)?->cmid;

        $this->assertNull($select([]));
        $this->assertNull($select([$item(1, 'label'), $item(2, 'resource')]));
        $this->assertEquals(2, $select([$item(1, 'label'), $item(2, 'quiz')]));
        $this->assertEquals(3, $select([$item(2, 'quiz'), $item(3, 'lesson')]));
        $this->assertEquals(4, $select([$item(3, 'lesson'), $item(4, 'url', 'https://www.youtube.com/embed/x')]));
        $this->assertEquals(2, $select([$item(2, 'quiz'), $item(3, 'lesson')], 2));
        $this->assertEquals(3, $select([$item(3, 'lesson'), $item(5, 'forum')], 99));
        $this->assertEquals(5, $select([$item(1, 'label'), $item(5, 'forum')]));
    }

    /**
     * Build the course overview page, as a learner would see it.
     *
     * @param \stdClass $course
     * @return \stdClass Template data.
     */
    private function export_overview(\stdClass $course): \stdClass {
        global $PAGE;

        $PAGE->set_course($course);
        $PAGE->set_url(new \moodle_url('/course/view.php', ['id' => $course->id]));
        $PAGE->set_context(\context_course::instance($course->id));

        $content = new \format_streamdeck\output\courseformat\content(course_get_format($course));
        $data = $content->export_for_template($PAGE->get_renderer('format_streamdeck'));
        $this->assertObjectHasProperty('issectionview', $data);
        return $data;
    }

    /**
     * Name a section's season and running time.
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
     * Seasons group the cards, open on the season the learner is working through, and carry the course's label.
     */
    public function test_seasons_on_overview(): void {
        $this->resetAfterTest();
        $student = $this->getDataGenerator()->create_user();

        $course = $this->getDataGenerator()->create_course([
            'format' => 'streamdeck',
            'numsections' => 4,
            'enablecompletion' => 1,
        ]);
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        course_get_format($course)->update_course_format_options(['seasonlabel' => 'Part']);
        $this->set_section_options($course, 2, ['seasonname' => 'Nouns']);
        $this->set_section_options($course, 4, ['seasonname' => 'Verbs']);
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'section' => 4,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);

        $this->setUser($student);
        $data = $this->export_overview($course);

        $this->assertTrue($data->hasseasons);
        $this->assertSame('Part', $data->seasonlabel);
        $this->assertSame(
            [get_string('seasonopening', 'format_streamdeck'), 'Nouns', 'Verbs'],
            array_column($data->seasons, 'name')
        );
        $this->assertSame(['Part 1', 'Part 2', 'Part 3'], array_column($data->seasons, 'kicker'));
        $this->assertSame([1, 2, 1], array_map(fn($season) => count($season->sections), $data->seasons));
        $this->assertSame(
            [
                get_string('seasonepisode', 'format_streamdeck'),
                get_string('seasonepisodes', 'format_streamdeck', 2),
                get_string('seasonepisode', 'format_streamdeck'),
            ],
            array_column($data->seasons, 'episodecount')
        );
        // The only unfinished activity is in "Verbs", so that tab opens.
        $this->assertSame([false, false, true], array_column($data->seasons, 'selected'));
        $this->assertCount(4, $data->sections);
        $this->assertStringContainsString('id=' . $page->cmid, $data->playurl);
    }

    /**
     * Courses that name no season keep a single shelf.
     */
    public function test_no_seasons(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['format' => 'streamdeck', 'numsections' => 2]);
        $data = $this->export_overview($course);

        $this->assertFalse($data->hasseasons);
        $this->assertCount(2, $data->sections);
    }

    /**
     * Cards show the running time, a "New" badge and an upcoming deadline.
     */
    public function test_episode_metadata(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['format' => 'streamdeck', 'numsections' => 2]);
        $this->set_section_options($course, 1, ['duration' => 12]);
        $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'section' => 1,
            'duedate' => time() + 2 * DAYSECS,
        ]);
        $old = $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'section' => 2]);
        $DB->set_field('course_modules', 'added', time() - 30 * DAYSECS, ['id' => $old->cmid]);
        rebuild_course_cache($course->id, true);

        $cards = $this->export_overview($course)->sections;

        $this->assertSame(format_time(12 * MINSECS), $cards[0]->durationtext);
        $this->assertTrue($cards[0]->isnew);
        $this->assertTrue($cards[0]->hasdue);
        $this->assertStringStartsWith(get_string('duesoon', 'format_streamdeck', ''), $cards[0]->duetext);

        $this->assertSame('', $cards[1]->durationtext);
        $this->assertFalse($cards[1]->isnew);
        $this->assertFalse($cards[1]->hasdue);
    }

    /**
     * The episode page names its season and running time.
     */
    public function test_episode_page_season_and_duration(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['format' => 'streamdeck', 'numsections' => 2]);
        $this->set_section_options($course, 1, ['seasonname' => 'Nouns', 'duration' => 90]);

        $data = $this->export_section($course, 1);

        $this->assertSame(get_string('season', 'format_streamdeck') . ' 1: Nouns', $data->episodeseasonname);
        $this->assertSame(format_time(90 * MINSECS), $data->episodedurationtext);
    }

    /**
     * "About this episode" shows only when switched on and filled in, and gives the page a left column.
     */
    public function test_about_this_episode(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['format' => 'streamdeck', 'numsections' => 3]);
        $this->getDataGenerator()->create_module('url', [
            'course' => $course->id,
            'section' => 1,
            'externalurl' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);
        $objectives = '<ul><li>Name the parts of speech</li></ul>';
        $this->set_section_options($course, 1, [
            'showabout' => 1,
            'about' => $objectives,
            'aboutformat' => FORMAT_HTML,
            'abouttitle' => 'Learning objectives',
            'abouticon' => 'bullseye',
        ]);
        $this->set_section_options($course, 2, ['showabout' => 0, 'about' => $objectives, 'aboutformat' => FORMAT_HTML]);
        $this->set_section_options($course, 3, ['showabout' => 1, 'about' => '<p> </p>', 'aboutformat' => FORMAT_HTML]);

        $data = $this->export_section($course, 1);
        $this->assertStringContainsString('Name the parts of speech', $data->about);
        $this->assertSame('Learning objectives', $data->abouttitle);
        $this->assertSame('bullseye', $data->abouticon);
        $this->assertFalse($data->hasrelatedsidebar);
        $this->assertTrue($data->hasleftcolumn);

        $this->assertSame('', $this->export_section($course, 2)->about);
        $this->assertSame('', $this->export_section($course, 3)->about);
        $this->assertFalse($this->export_section($course, 3)->hasleftcolumn);
    }

    /**
     * The card falls back to "About this episode" and no icon.
     */
    public function test_about_this_episode_defaults(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['format' => 'streamdeck', 'numsections' => 1]);
        $this->set_section_options($course, 1, [
            'showabout' => 1,
            'about' => '<p>Read the chapter.</p>',
            'aboutformat' => FORMAT_HTML,
            'abouticon' => 'not-an-offered-icon',
        ]);

        $data = $this->export_section($course, 1);
        $this->assertSame(get_string('about', 'format_streamdeck'), $data->abouttitle);
        $this->assertSame('', $data->abouticon);
    }
}
