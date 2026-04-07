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
 * @license    http://www.gnu.org/licenses/gpl-3.0.txt GNU GPL v3 or later
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
     * Test that related activities appear independently of related resources.
     */
    public function test_related_activities_without_resources(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course([
            'format' => 'streamdeck',
            'numsections' => 1,
            'enablecompletion' => 1,
        ]);

        // Add an assignment (activity) but no resources to section 1.
        $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'section' => 1,
            'name' => 'Test Assignment',
        ]);

        $PAGE->set_course($course);
        $PAGE->set_url(new \moodle_url('/course/view.php', [
            'id' => $course->id,
            'section' => 1,
        ]));
        $PAGE->set_context(\context_course::instance($course->id));

        // Simulate the section parameter so the content class picks up section view.
        $_GET['section'] = 1;

        $format = course_get_format($course);
        $content = new \format_streamdeck\output\courseformat\content($format);
        $renderer = $PAGE->get_renderer('format_streamdeck');

        $data = $content->export_for_template($renderer);

        // Related resources should be empty.
        $this->assertFalse($data->hasrelatedresources);

        // Related activities should still show.
        $this->assertTrue($data->hasrelatedactivities);
        $this->assertTrue($data->hasrelatedsidebar);
        $this->assertNotEmpty($data->relatedactivities);

        // Clean up superglobal.
        unset($_GET['section']);
    }
}
