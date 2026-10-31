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
 * The view menu for Streamdeck courses: Appearance (light / dark / auto) and Display (standard / TV).
 *
 * The colour mode is stored as a user preference; the display is remembered per device in a cookie, because the
 * same learner uses a laptop and a TV. Both apply to the page straight away.
 *
 * @module     format_streamdeck/colour_mode
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {setUserPreference} from 'core_user/repository';
import Notification from 'core/notification';

const PREFERENCE = 'format_streamdeck_colourmode';
const DISPLAY_COOKIE = 'streamdeck_display';
const SELECTORS = {
    menu: '[data-region="streamdeck-colourmode"]',
    colour: '[data-action="streamdeck-set-colourmode"]',
    display: '[data-action="streamdeck-set-display"]',
};

const darkQuery = window.matchMedia('(prefers-color-scheme: dark)');
let initialised = false;

/**
 * Resolve a colour mode to the Bootstrap theme it shows.
 *
 * @param {String} mode light, dark or auto
 * @returns {String} light or dark
 */
const resolve = (mode) => {
    if (mode === 'auto') {
        return darkQuery.matches ? 'dark' : 'light';
    }
    return mode;
};

/**
 * Tick the chosen item of one group in every view menu on the page.
 *
 * @param {String} selector The group's items.
 * @param {String} mode The chosen mode.
 */
const check = (selector, mode) => {
    document.querySelectorAll(SELECTORS.menu + ' ' + selector).forEach((item) => {
        item.setAttribute('aria-checked', item.dataset.mode === mode ? 'true' : 'false');
    });
};

/**
 * Apply a colour mode to the page.
 *
 * @param {String} mode light, dark or auto
 */
const applyColour = (mode) => {
    const root = document.documentElement;
    root.setAttribute('data-streamdeck-mode', mode);
    root.setAttribute('data-bs-theme', resolve(mode));
    check(SELECTORS.colour, mode);
};

/**
 * Apply a display to the page and remember it on this device.
 *
 * @param {String} mode standard or tv
 * @param {String} cookieattributes Cookie attributes from the server, following the site's cookie settings.
 */
const applyDisplay = (mode, cookieattributes) => {
    document.documentElement.setAttribute('data-streamdeck-display', mode);
    document.cookie = DISPLAY_COOKIE + '=' + mode + (cookieattributes || '');
    check(SELECTORS.display, mode);
};

/**
 * Set up the view menu.
 */
export const init = () => {
    if (initialised) {
        return;
    }
    initialised = true;

    document.addEventListener('click', (e) => {
        const colour = e.target.closest(SELECTORS.colour);
        const display = e.target.closest(SELECTORS.display);
        if (colour) {
            e.preventDefault();
            applyColour(colour.dataset.mode);
            setUserPreference(PREFERENCE, colour.dataset.mode).catch(Notification.exception);
        } else if (display) {
            e.preventDefault();
            applyDisplay(display.dataset.mode, display.closest(SELECTORS.menu)?.dataset.cookieattributes);
        }
    });
};
