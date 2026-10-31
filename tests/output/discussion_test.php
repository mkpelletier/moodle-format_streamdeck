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
 * Tests for an episode's discussion thread.
 *
 * @package    format_streamdeck
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(discussion::class)]
final class discussion_test extends \advanced_testcase {
    /**
     * Replies nest under what they answer, at every level, and render in the thread.
     */
    public function test_nested_replies(): void {
        global $PAGE;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'streamdeck', 'numsections' => 1]);
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');
        $forum = $generator->create_module('forum', ['course' => $course->id, 'section' => 1]);
        $forumgen = $generator->get_plugin_generator('mod_forum');
        $discussion = $forumgen->create_discussion([
            'course' => $course->id, 'forum' => $forum->id, 'userid' => $student->id, 'message' => 'Opening',
        ]);
        $first = $forumgen->create_post([
            'discussion' => $discussion->id, 'parent' => $discussion->firstpost, 'userid' => $student->id,
            'message' => 'First level',
        ]);
        $second = $forumgen->create_post([
            'discussion' => $discussion->id, 'parent' => $first->id, 'userid' => $student->id,
            'message' => 'Second level',
        ]);
        $third = $forumgen->create_post([
            'discussion' => $discussion->id, 'parent' => $second->id, 'userid' => $student->id,
            'message' => 'Third level',
        ]);

        $this->setUser($student);
        $PAGE->set_url('/course/view.php', ['id' => $course->id]);
        $cm = get_fast_modinfo($course)->get_cm($forum->cmid);
        $data = discussion::export_thread($cm, $discussion->id);

        $this->assertCount(1, $data['replies']);
        $this->assertSame($first->id, $data['replies'][0]['id']);
        $this->assertCount(1, $data['replies'][0]['children']);
        $this->assertSame($second->id, $data['replies'][0]['children'][0]['id']);
        $this->assertSame($third->id, $data['replies'][0]['children'][0]['children'][0]['id']);

        $html = $PAGE->get_renderer('core')->render_from_template('format_streamdeck/local/discussion', $data);
        foreach (['First level', 'Second level', 'Third level'] as $message) {
            $this->assertStringContainsString($message, $html);
        }
    }

    /**
     * An episode's forum with one discussion opens on it; the course's discussion forum always lists, even when empty.
     */
    public function test_alwayslist(): void {
        global $PAGE;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'streamdeck', 'numsections' => 1]);
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');
        $episode = $generator->create_module('forum', ['course' => $course->id, 'section' => 1]);
        $community = $generator->create_module('forum', ['course' => $course->id, 'section' => 0]);
        $generator->get_plugin_generator('mod_forum')->create_discussion([
            'course' => $course->id, 'forum' => $episode->id, 'userid' => $student->id, 'name' => 'Only one',
        ]);

        $this->setUser($student);
        $PAGE->set_url('/course/view.php', ['id' => $course->id]);
        $modinfo = get_fast_modinfo($course);

        $thread = discussion::render_preview($modinfo->get_cm($episode->cmid));
        $this->assertStringContainsString('data-region="sd-thread"', $thread);
        $list = discussion::render_preview($modinfo->get_cm($episode->cmid), true);
        $this->assertStringContainsString('data-region="sd-discussion-list"', $list);
        $this->assertStringContainsString('Only one', $list);

        $this->assertSame('', discussion::render_preview($modinfo->get_cm($community->cmid)));
        $empty = discussion::render_view($modinfo->get_cm($community->cmid), true);
        $this->assertStringContainsString(get_string('nodiscussions', 'format_streamdeck'), $empty);
        $this->assertStringContainsString('post.php?forum=' . $community->id, $empty);
    }

    /**
     * Announcements come newest first, show as new until seen, and only those who can post get the post bar.
     */
    public function test_news(): void {
        global $PAGE;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'streamdeck', 'numsections' => 1]);
        $student = $generator->create_user(['trackforums' => 1]);
        $teacher = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $news = $generator->create_module('forum', ['course' => $course->id, 'section' => 0, 'type' => 'news']);
        $forumgen = $generator->get_plugin_generator('mod_forum');
        foreach (['Welcome' => time() - HOURSECS, 'Exam dates' => time()] as $name => $time) {
            $forumgen->create_discussion([
                'course' => $course->id, 'forum' => $news->id, 'userid' => $teacher->id, 'name' => $name,
                'timecreated' => $time,
            ]);
        }
        $PAGE->set_url('/course/view.php', ['id' => $course->id]);
        $cm = get_fast_modinfo($course)->get_cm($news->cmid);

        $this->setUser($student);
        $html = discussion::render_news($cm);
        $this->assertLessThan(strpos($html, 'Welcome'), strpos($html, 'Exam dates'));
        $this->assertStringContainsString('sd-news-badge', $html);
        $this->assertStringNotContainsString('post.php?forum=', $html);
        // Shown once, they are read.
        $this->assertStringNotContainsString('sd-news-badge', discussion::render_news($cm));

        $this->setUser($teacher);
        $this->assertStringContainsString('post.php?forum=' . $news->id, discussion::render_news($cm));
    }
}
