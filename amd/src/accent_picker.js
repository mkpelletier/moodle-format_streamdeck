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
 * The accent colour course setting: a colour swatch beside the field, kept in step with it, and a reset to the
 * default. The field stays, for typing or pasting a brand colour; empty means the default.
 *
 * @module     format_streamdeck/accent_picker
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getString} from 'core/str';

const VALID = /^#[0-9a-f]{6}$/i;

/**
 * Add the swatch and reset button.
 *
 * @param {String} fieldId The text field.
 * @param {String} defaultColour The default accent, #rrggbb.
 */
export const init = async(fieldId, defaultColour) => {
    const field = document.getElementById(fieldId);
    if (!field) {
        return;
    }
    const [label, resetLabel] = await Promise.all([
        getString('accentcolour', 'format_streamdeck'),
        getString('accentcolourreset', 'format_streamdeck'),
    ]);

    const swatch = document.createElement('input');
    swatch.type = 'color';
    swatch.className = 'form-control form-control-color me-2';
    swatch.setAttribute('aria-label', label);
    const reset = document.createElement('button');
    reset.type = 'button';
    reset.className = 'btn btn-link ms-1';
    reset.textContent = resetLabel;

    const fromField = () => {
        const value = field.value.trim();
        swatch.value = VALID.test(value) ? value.toLowerCase() : defaultColour;
        reset.hidden = value === '';
    };
    fromField();

    swatch.addEventListener('input', () => {
        field.value = swatch.value;
        reset.hidden = false;
    });
    field.addEventListener('input', fromField);
    reset.addEventListener('click', () => {
        field.value = '';
        fromField();
        field.focus();
    });

    field.before(swatch);
    field.after(reset);
    field.parentElement.classList.add('d-flex', 'align-items-center');
};
