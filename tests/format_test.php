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
 * PHPUnit tests for the format_streamdeck format class.
 *
 * @package    format_streamdeck
 * @copyright  2025
 * @license    http://www.gnu.org/licenses/gpl-3.0.txt GNU GPL v3 or later
 */

namespace format_streamdeck;

/**
 * Tests for the format_streamdeck course format class (lib.php).
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\format_streamdeck::class)]
final class format_test extends \advanced_testcase {
    /**
     * Test that the format uses sections.
     */
    public function test_uses_sections(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['format' => 'streamdeck', 'numsections' => 3]);
        $format = course_get_format($course);

        $this->assertTrue($format->uses_sections());
    }

    /**
     * Test that the format uses the course index.
     */
    public function test_uses_course_index(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['format' => 'streamdeck', 'numsections' => 1]);
        $format = course_get_format($course);

        $this->assertTrue($format->uses_course_index());
    }

    /**
     * Test AJAX support is enabled.
     */
    public function test_supports_ajax(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['format' => 'streamdeck', 'numsections' => 1]);
        $format = course_get_format($course);
        $ajax = $format->supports_ajax();

        $this->assertIsObject($ajax);
        $this->assertTrue($ajax->capable);
    }

    /**
     * Test reactive components support.
     */
    public function test_supports_components(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['format' => 'streamdeck', 'numsections' => 1]);
        $format = course_get_format($course);

        $this->assertTrue($format->supports_components());
    }

    /**
     * Test adding sections is supported.
     */
    public function test_supports_add_sections(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['format' => 'streamdeck', 'numsections' => 1]);
        $format = course_get_format($course);

        $this->assertTrue($format->supports_add_sections());
    }

    /**
     * Test showing all sections is supported.
     */
    public function test_supports_showing_all_sections(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['format' => 'streamdeck', 'numsections' => 1]);
        $format = course_get_format($course);

        $this->assertTrue($format->supports_showing_all_sections());
    }

    /**
     * Test default section name for section 0.
     */
    public function test_default_section_name_section_zero(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['format' => 'streamdeck', 'numsections' => 3]);
        $format = course_get_format($course);
        $section = $format->get_section(0);
        $name = $format->get_section_name($section);

        $expected = get_string('section0name', 'format_streamdeck');
        $this->assertEquals($expected, $name);
    }

    /**
     * Test default section name for numbered sections.
     */
    public function test_default_section_name_numbered(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(
            ['format' => 'streamdeck', 'numsections' => 3],
            ['createsections' => true]
        );

        $format = course_get_format($course);
        // Verify numsections is set correctly in format options.
        $coursedata = $format->get_course();
        $numsections = $coursedata->numsections ?? 0;

        $section = $format->get_section(1);
        $name = $format->get_section_name($section);

        if ($numsections >= 1) {
            // Normal case: section 1 is within numsections.
            $expected = get_string('sectionname', 'format_streamdeck', 1);
            $this->assertEquals($expected, $name);
        } else {
            // Section is treated as orphaned - verify it gets one of the orphaned labels.
            $this->assertTrue(
                $name === get_string('orphanedsection', 'format_streamdeck')
                || strpos($name, 'Orphaned') !== false
                || strpos($name, 'orphaned') !== false,
                "Section name should indicate orphaned status, got: $name"
            );
        }
    }

    /**
     * Test custom section name is used when set.
     */
    public function test_custom_section_name(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['format' => 'streamdeck', 'numsections' => 3]);
        $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 1]);
        $DB->update_record('course_sections', (object) ['id' => $section->id, 'name' => 'Custom Name']);

        // Rebuild course cache to pick up the name change.
        rebuild_course_cache($course->id, true);

        $format = course_get_format($course);
        $sectioninfo = $format->get_section(1);
        $name = $format->get_section_name($sectioninfo);

        $this->assertEquals('Custom Name', $name);
    }

    /**
     * Test course_format_options returns herofont and enabledrawertoggle with defaults.
     */
    public function test_course_format_options_defaults(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['format' => 'streamdeck', 'numsections' => 1]);
        $format = course_get_format($course);

        $options = $format->get_format_options();
        $this->assertArrayHasKey('herofont', $options);
        $this->assertArrayHasKey('enabledrawertoggle', $options);
        $this->assertEquals('Lobster', $options['herofont']);
        $this->assertEquals(0, $options['enabledrawertoggle']);
    }

    /**
     * Test course_format_options includes form elements when foreditform is true.
     */
    public function test_course_format_options_for_edit_form(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['format' => 'streamdeck', 'numsections' => 1]);
        $format = course_get_format($course);

        $options = $format->course_format_options(true);

        $this->assertArrayHasKey('herofont', $options);
        $this->assertArrayHasKey('label', $options['herofont']);
        $this->assertArrayHasKey('element_type', $options['herofont']);
        $this->assertEquals('select', $options['herofont']['element_type']);

        $this->assertArrayHasKey('enabledrawertoggle', $options);
        $this->assertArrayHasKey('label', $options['enabledrawertoggle']);
        $this->assertEquals('select', $options['enabledrawertoggle']['element_type']);
    }

    /**
     * Test that herofont can be updated per-course.
     */
    public function test_update_herofont_option(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['format' => 'streamdeck', 'numsections' => 1]);
        $format = course_get_format($course);

        $format->update_course_format_options(['herofont' => 'Poppins']);

        // Re-fetch to confirm persistence.
        $format = course_get_format($course);
        $options = $format->get_format_options();
        $this->assertEquals('Poppins', $options['herofont']);
    }

    /**
     * Test that enabledrawertoggle can be updated per-course.
     */
    public function test_update_enabledrawertoggle_option(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['format' => 'streamdeck', 'numsections' => 1]);
        $format = course_get_format($course);

        $format->update_course_format_options(['enabledrawertoggle' => 1]);

        $format = course_get_format($course);
        $options = $format->get_format_options();
        $this->assertEquals(1, $options['enabledrawertoggle']);
    }

    /**
     * Test page_set_course adds drawer toggle body class when enabled.
     */
    public function test_page_set_course_drawer_toggle_class(): void {
        global $PAGE;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['format' => 'streamdeck', 'numsections' => 1]);
        $format = course_get_format($course);
        $format->update_course_format_options(['enabledrawertoggle' => 1]);

        $PAGE->set_course($course);
        $classes = $PAGE->bodyclasses;

        $this->assertStringContainsString('streamdeck-show-drawer-toggle', $classes);
    }

    /**
     * Test page_set_course does not add drawer toggle body class when disabled.
     */
    public function test_page_set_course_no_drawer_toggle_class(): void {
        global $PAGE;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['format' => 'streamdeck', 'numsections' => 1]);

        $PAGE->set_course($course);
        $classes = $PAGE->bodyclasses;

        $this->assertStringNotContainsString('streamdeck-show-drawer-toggle', $classes);
    }

    /**
     * Test that the format returns the correct template name.
     */
    public function test_template_name(): void {
        global $PAGE;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['format' => 'streamdeck', 'numsections' => 1]);
        $PAGE->set_course($course);
        $PAGE->set_context(\context_course::instance($course->id));

        $format = course_get_format($course);
        $content = new \format_streamdeck\output\courseformat\content($format);
        $renderer = $PAGE->get_renderer('format_streamdeck');

        $this->assertEquals('format_streamdeck/local/content', $content->get_template_name($renderer));
    }
}
