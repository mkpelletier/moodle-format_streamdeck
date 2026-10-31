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
 * Tests for the Streamdeck colour mode.
 *
 * @package    format_streamdeck
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(colour_mode::class)]
final class colour_mode_test extends \advanced_testcase {
    /**
     * Dark until the site or the learner says otherwise; a learner's choice wins.
     */
    public function test_get_current_mode(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        $this->assertSame(colour_mode::DARK, colour_mode::get_current_mode());

        set_config('defaultcolourmode', colour_mode::LIGHT, 'format_streamdeck');
        $this->assertSame(colour_mode::LIGHT, colour_mode::get_current_mode());

        set_user_preference(colour_mode::PREFERENCE, colour_mode::AUTO);
        $this->assertSame(colour_mode::AUTO, colour_mode::get_current_mode());

        set_user_preference(colour_mode::PREFERENCE, 'sepia');
        $this->assertSame(colour_mode::LIGHT, colour_mode::get_current_mode());
    }

    /**
     * Guests always get the site default and cannot choose.
     */
    public function test_guest(): void {
        $this->resetAfterTest();
        $this->setGuestUser();

        $this->assertSame(colour_mode::DARK, colour_mode::get_current_mode());
        $this->assertFalse(colour_mode::can_choose_mode());
    }

    /**
     * Only viewing pages in Streamdeck courses are themed, minus the excluded page types.
     */
    public function test_applies_to_page(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        $streamdeck = $this->getDataGenerator()->create_course(['format' => 'streamdeck']);
        $topics = $this->getDataGenerator()->create_course(['format' => 'topics']);

        $page = new \moodle_page();
        $page->set_course($streamdeck);
        $page->set_pagetype('mod-quiz-attempt');
        $this->assertTrue(colour_mode::applies_to_page($page));

        $page = new \moodle_page();
        $page->set_course($streamdeck);
        $page->set_pagetype('local-unifiedgrader-grade');
        $this->assertFalse(colour_mode::applies_to_page($page));

        $page = new \moodle_page();
        $page->set_course($topics);
        $page->set_pagetype('course-view-topics');
        $this->assertFalse(colour_mode::applies_to_page($page));

        $USER->editing = 1;
        $page = new \moodle_page();
        $page->set_course($streamdeck);
        $page->set_pagetype('course-view-streamdeck');
        $this->assertFalse(colour_mode::applies_to_page($page));
    }

    /**
     * Boost's colour modes take over when a site turns them on.
     */
    public function test_is_core_managed(): void {
        if (!class_exists(\theme_boost\colour_mode::class)) {
            $this->markTestSkipped('Boost has no colour modes before Moodle 5.3.');
        }
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        set_config('enablecolourmodes', 0, 'theme_boost');
        $this->assertFalse(colour_mode::is_core_managed());
        $this->assertTrue(colour_mode::can_choose_mode());

        set_config('enablecolourmodes', 1, 'theme_boost');
        $this->assertTrue(colour_mode::is_core_managed());
        $this->assertFalse(colour_mode::can_choose_mode());
    }
}
