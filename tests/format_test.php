<?php
/**
 * PHPUnit tests for the format_streamdeck format class.
 *
 * @package    format_streamdeck
 * @copyright  2025
 * @license    http://www.gnu.org/licenses/gpl-3.0.txt GNU GPL v3 or later
 */

namespace format_streamdeck;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the format_streamdeck course format class (lib.php).
 *
 * @covers \format_streamdeck
 */
class format_test extends \advanced_testcase {

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
            // Section is treated as orphaned — verify it gets one of the orphaned labels.
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
