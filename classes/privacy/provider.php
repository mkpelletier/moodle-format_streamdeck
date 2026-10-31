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
 * Privacy provider for format_streamdeck.
 *
 * @package    format_streamdeck
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_streamdeck\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\writer;
use format_streamdeck\local\colour_mode;

/**
 * Privacy provider: the colour mode preference, and what the course page sends elsewhere.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\user_preference_provider {
    /**
     * Describe the data this plugin stores or sends.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_user_preference(colour_mode::PREFERENCE, 'privacy:metadata:preference:colourmode');
        $collection->add_external_location_link('googlefonts', [
            'ipaddress' => 'privacy:metadata:googlefonts:ipaddress',
        ], 'privacy:metadata:googlefonts');
        return $collection;
    }

    /**
     * Export the user's colour mode preference.
     *
     * @param int $userid
     */
    public static function export_user_preferences(int $userid): void {
        $mode = get_user_preferences(colour_mode::PREFERENCE, null, $userid);
        if ($mode === null) {
            return;
        }
        writer::export_user_preference(
            'format_streamdeck',
            colour_mode::PREFERENCE,
            $mode,
            get_string('colourmode:' . $mode, 'format_streamdeck')
        );
    }
}
