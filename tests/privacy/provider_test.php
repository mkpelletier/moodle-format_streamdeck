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

namespace format_streamdeck\privacy;

use core_privacy\local\request\writer;
use format_streamdeck\local\colour_mode;

/**
 * Privacy provider tests.
 *
 * @package    format_streamdeck
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
final class provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * The colour mode preference is exported with a readable description.
     */
    public function test_export_user_preferences(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        set_user_preference(colour_mode::PREFERENCE, colour_mode::LIGHT, $user);

        provider::export_user_preferences($user->id);

        $prefs = writer::with_context(\context_system::instance())->get_user_preferences('format_streamdeck');
        $this->assertSame(colour_mode::LIGHT, $prefs->{colour_mode::PREFERENCE}->value);
        $this->assertSame(get_string('colourmode:light', 'format_streamdeck'), $prefs->{colour_mode::PREFERENCE}->description);
    }

    /**
     * Nothing is exported for a user who never chose a mode.
     */
    public function test_export_without_preference(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        provider::export_user_preferences($user->id);

        $this->assertFalse(writer::with_context(\context_system::instance())->has_any_data());
    }
}
