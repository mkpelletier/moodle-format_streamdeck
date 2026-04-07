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
 * PHPUnit tests for the format_streamdeck syllabus table.
 *
 * @package    format_streamdeck
 * @copyright  2025
 * @license    http://www.gnu.org/licenses/gpl-3.0.txt GNU GPL v3 or later
 */

namespace format_streamdeck;

/**
 * Tests for the syllabus database table and CRUD operations.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\format_streamdeck\output\renderer::class)]
final class syllabus_test extends \advanced_testcase {
    /**
     * Test inserting a syllabus record.
     */
    public function test_insert_syllabus(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['format' => 'streamdeck']);

        $record = new \stdClass();
        $record->courseid = $course->id;
        $record->introhtml = '<p>Welcome to this course.</p>';
        $record->outcomeshtml = '<p>By the end you will know things.</p>';
        $record->assessmentshtml = '<p>One big exam.</p>';
        $record->materialshtml = '<p>A textbook.</p>';
        $record->timemodified = time();

        $id = $DB->insert_record('format_streamdeck_syllabus', $record);
        $this->assertNotEmpty($id);

        $saved = $DB->get_record('format_streamdeck_syllabus', ['id' => $id]);
        $this->assertEquals($course->id, $saved->courseid);
        $this->assertStringContainsString('Welcome', $saved->introhtml);
        $this->assertStringContainsString('know things', $saved->outcomeshtml);
        $this->assertStringContainsString('big exam', $saved->assessmentshtml);
        $this->assertStringContainsString('textbook', $saved->materialshtml);
    }

    /**
     * Test updating a syllabus record.
     */
    public function test_update_syllabus(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['format' => 'streamdeck']);

        $record = new \stdClass();
        $record->courseid = $course->id;
        $record->introhtml = '<p>Original intro.</p>';
        $record->timemodified = time();
        $id = $DB->insert_record('format_streamdeck_syllabus', $record);

        $record->id = $id;
        $record->introhtml = '<p>Updated intro.</p>';
        $record->timemodified = time();
        $DB->update_record('format_streamdeck_syllabus', $record);

        $saved = $DB->get_record('format_streamdeck_syllabus', ['id' => $id]);
        $this->assertStringContainsString('Updated', $saved->introhtml);
    }

    /**
     * Test fetching syllabus by course ID.
     */
    public function test_get_syllabus_by_courseid(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['format' => 'streamdeck']);

        $record = new \stdClass();
        $record->courseid = $course->id;
        $record->introhtml = '<p>Course intro.</p>';
        $record->timemodified = time();
        $DB->insert_record('format_streamdeck_syllabus', $record);

        $saved = $DB->get_record('format_streamdeck_syllabus', ['courseid' => $course->id]);
        $this->assertNotEmpty($saved);
        $this->assertEquals($course->id, $saved->courseid);
    }

    /**
     * Test that the courseid unique index prevents duplicates.
     */
    public function test_unique_courseid_constraint(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['format' => 'streamdeck']);

        $record = new \stdClass();
        $record->courseid = $course->id;
        $record->introhtml = '<p>First.</p>';
        $record->timemodified = time();
        $DB->insert_record('format_streamdeck_syllabus', $record);

        $this->expectException(\dml_write_exception::class);

        $record2 = new \stdClass();
        $record2->courseid = $course->id;
        $record2->introhtml = '<p>Duplicate.</p>';
        $record2->timemodified = time();
        $DB->insert_record('format_streamdeck_syllabus', $record2);
    }

    /**
     * Test syllabus with nullable fields.
     */
    public function test_syllabus_nullable_fields(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['format' => 'streamdeck']);

        $record = new \stdClass();
        $record->courseid = $course->id;
        $record->introhtml = null;
        $record->outcomeshtml = null;
        $record->assessmentshtml = null;
        $record->materialshtml = null;
        $record->timemodified = null;
        $id = $DB->insert_record('format_streamdeck_syllabus', $record);

        $saved = $DB->get_record('format_streamdeck_syllabus', ['id' => $id]);
        $this->assertNull($saved->introhtml);
        $this->assertNull($saved->outcomeshtml);
    }
}
