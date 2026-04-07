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
 * Settings for the Streamdeck course format.
 *
 * @package    format_streamdeck
 * @copyright  2025
 * @license    http://www.gnu.org/licenses/gpl-3.0.txt GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    if ($ADMIN->fulltree) {
        // Hero instructor role selection.

        // Build list of all roles (roleid => readable name).
        $allroles = role_get_names(null, ROLENAME_ORIGINAL);
        $roleoptions = [];
        foreach ($allroles as $roleid => $role) {
            // The $role is a stdClass; use its localname/name property as a string.
            $label = isset($role->localname) && $role->localname !== ''
                ? $role->localname
                : (isset($role->name) ? $role->name : (string)$roleid);
            $roleoptions[$roleid] = $label;
        }

        // Default to the standard teacher roles, if they exist.
        $defaultroleids = [];

        // Look up roles by shortname.
        $editingrole = $DB->get_record('role', ['shortname' => 'editingteacher'], 'id');
        if ($editingrole) {
            $defaultroleids[] = (int)$editingrole->id;
        }

        $teacherrole = $DB->get_record('role', ['shortname' => 'teacher'], 'id');
        if ($teacherrole) {
            $defaultroleids[] = (int)$teacherrole->id;
        }

        $settings->add(new admin_setting_configmultiselect(
            'format_streamdeck/teacherroles',
            get_string('teacherroles', 'format_streamdeck'),
            get_string('teacherroles_desc', 'format_streamdeck'),
            $defaultroleids,
            $roleoptions
        ));

        // Instructor label (singular).
        $settings->add(new admin_setting_configtext(
            'format_streamdeck/instructorlabel',
            get_string('instructorlabel', 'format_streamdeck'),
            get_string('instructorlabel_desc', 'format_streamdeck'),
            get_string('instructorlabel_default', 'format_streamdeck'),
            PARAM_TEXT
        ));

        // Instructor label (plural).
        $settings->add(new admin_setting_configtext(
            'format_streamdeck/instructorlabelplural',
            get_string('instructorlabelplural', 'format_streamdeck'),
            get_string('instructorlabelplural_desc', 'format_streamdeck'),
            get_string('instructorlabelplural_default', 'format_streamdeck'),
            PARAM_TEXT
        ));
    }
}
