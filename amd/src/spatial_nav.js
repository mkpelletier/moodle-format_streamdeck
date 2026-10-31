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
 * Arrow-key and remote navigation for Streamdeck courses.
 *
 * Standard display: the arrow keys move between episode cards, and down from the season tabs into them; elsewhere
 * they scroll the page as usual. TV display (html[data-streamdeck-display="tv"]): the arrow keys move focus to the
 * nearest control in that direction anywhere on the page, Back closes what is open or leaves the episode, and the
 * stage plays full screen so the remote is never trapped inside an embedded player.
 *
 * @module     format_streamdeck/spatial_nav
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getString} from 'core/str';

const DIRECTIONS = {ArrowLeft: 'left', ArrowRight: 'right', ArrowUp: 'up', ArrowDown: 'down'};

/** Samsung (Tizen) and LG (webOS) remote Back keys, which browsers report by keyCode only. */
const BACK_KEYCODES = [10009, 461];
const BACK_KEYS = ['GoBack', 'BrowserBack'];

const FOCUSABLE = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled]):not([type="hidden"])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    'summary',
    '[tabindex]:not([tabindex="-1"])',
].join(',');

const SELECTORS = {
    grid: '[data-region="streamdeck-cards"]',
    card: '.streamdeck-card-link',
    seasons: '[data-region="streamdeck-seasons"]',
    seasonTab: '.streamdeck-season-tab',
    stage: '.streamdeck-episode-video, .streamdeck-h5p-embed-wrapper, .streamdeck-h5p-iframe-wrapper',
    openModal: '.modal.show',
    openMenu: '.dropdown-menu.show',
    openDrawer: '.drawer.streamdeck-drawer-open',
    courseLink: '.streamdeck-episode-header-actions a[href]',
};

let initialised = false;

/**
 * Whether the TV display is on.
 *
 * @returns {Boolean}
 */
const isTv = () => document.documentElement.dataset.streamdeckDisplay === 'tv';

/**
 * Whether the arrow keys already mean something for this element.
 *
 * @param {Element|null} element
 * @returns {Boolean}
 */
const ownsArrows = (element) => {
    if (!element) {
        return false;
    }
    if (element.isContentEditable || element.matches('textarea, select, input:not([type="button"], [type="submit"], ' +
            '[type="reset"], [type="checkbox"])')) {
        return true;
    }
    return !!element.closest(SELECTORS.openMenu + ', [role="listbox"], [role="radiogroup"], [role="grid"], [role="tree"]');
};

/**
 * Whether an element can take focus and is on screen.
 *
 * @param {Element} element
 * @returns {Boolean}
 */
const isReachable = (element) => {
    if (element.closest('[inert], [aria-hidden="true"], [hidden]') || element.matches('iframe')) {
        return false;
    }
    const rect = element.getBoundingClientRect();
    if (rect.width === 0 || rect.height === 0) {
        return false;
    }
    const style = window.getComputedStyle(element);
    return style.visibility !== 'hidden' && style.display !== 'none';
};

/**
 * The closest candidate in a direction, by distance along it plus a penalty for drifting across it.
 *
 * @param {Element} from
 * @param {String} direction left, right, up or down
 * @param {Element[]} candidates
 * @returns {Element|null}
 */
const nearest = (from, direction, candidates) => {
    const a = from.getBoundingClientRect();
    const ax = a.left + a.width / 2;
    const ay = a.top + a.height / 2;
    let best = null;
    let bestScore = Infinity;

    candidates.forEach((candidate) => {
        if (candidate === from || from.contains(candidate)) {
            return;
        }
        const b = candidate.getBoundingClientRect();
        const bx = b.left + b.width / 2;
        const by = b.top + b.height / 2;
        let along;
        let across;
        let overlaps;
        if (direction === 'left' || direction === 'right') {
            along = direction === 'right' ? b.left - a.right : a.left - b.right;
            if ((direction === 'right' ? bx - ax : ax - bx) <= 1) {
                return;
            }
            overlaps = b.top < a.bottom && b.bottom > a.top;
            across = Math.abs(by - ay);
        } else {
            along = direction === 'down' ? b.top - a.bottom : a.top - b.bottom;
            if ((direction === 'down' ? by - ay : ay - by) <= 1) {
                return;
            }
            overlaps = b.left < a.right && b.right > a.left;
            across = Math.abs(bx - ax);
        }
        // Prefer controls in line with the current one; then the nearest one along the direction.
        const score = Math.max(along, 0) + (overlaps ? 0 : across * 2);
        if (score < bestScore) {
            bestScore = score;
            best = candidate;
        }
    });
    return best;
};

/**
 * Move focus and bring the element into view.
 *
 * @param {HTMLElement} element
 */
const moveTo = (element) => {
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    element.focus({preventScroll: true});
    element.scrollIntoView({block: 'nearest', inline: 'nearest', behavior: reduce ? 'auto' : 'smooth'});
};

/**
 * Standard display: arrows inside a card grid, and between the season tabs and their cards.
 *
 * @param {Element} active
 * @param {String} direction
 * @returns {Boolean} Whether focus moved.
 */
const moveInGrid = (active, direction) => {
    const seasons = active.closest(SELECTORS.seasons);

    // Down from a season tab goes to the first card of its season.
    if (active.matches(SELECTORS.seasonTab) && direction === 'down') {
        const panel = document.getElementById(active.getAttribute('aria-controls'));
        const card = panel && Array.from(panel.querySelectorAll(SELECTORS.card)).find(isReachable);
        if (card) {
            moveTo(card);
            return true;
        }
        return false;
    }

    const grid = active.matches(SELECTORS.card) && active.closest(SELECTORS.grid);
    if (!grid) {
        return false;
    }
    const target = nearest(active, direction, Array.from(grid.querySelectorAll(SELECTORS.card)).filter(isReachable));
    if (target) {
        moveTo(target);
        return true;
    }
    // Up from the top row goes back to the selected season tab.
    if (direction === 'up' && seasons) {
        const tab = seasons.querySelector(SELECTORS.seasonTab + '[aria-selected="true"]');
        if (tab && isReachable(tab)) {
            moveTo(tab);
            return true;
        }
    }
    return false;
};

/**
 * TV display: move to the nearest control in any direction, within an open modal or drawer if there is one.
 *
 * @param {Element|null} active
 * @param {String} direction
 * @returns {Boolean} Whether focus moved.
 */
const moveSpatially = (active, direction) => {
    const scope = document.querySelector(SELECTORS.openModal) || document.querySelector(SELECTORS.openDrawer) || document;
    const candidates = Array.from(scope.querySelectorAll(FOCUSABLE)).filter(isReachable);
    if (!candidates.length) {
        return false;
    }
    if (!active || active === document.body || !scope.contains(active)) {
        moveTo(candidates[0]);
        return true;
    }
    const target = nearest(active, direction, candidates);
    if (target) {
        moveTo(target);
        return true;
    }
    return false;
};

/**
 * Back on a remote: close what is open, else leave the episode for the course page.
 *
 * @param {KeyboardEvent} e
 */
const goBack = (e) => {
    const isEscape = e.key === 'Escape';
    if (document.fullscreenElement) {
        // The browser leaves full screen on Escape itself.
        if (!isEscape) {
            document.exitFullscreen();
        }
        return;
    }
    const open = document.querySelector(SELECTORS.openModal) || document.querySelector(SELECTORS.openMenu) ||
        document.querySelector(SELECTORS.openDrawer);
    if (open) {
        // Modals, menus and the drawer close on Escape already; the remote's Back key presses their close control.
        if (!isEscape) {
            e.preventDefault();
            open.querySelector('[data-action="hide"], .btn-close, [data-action="closedrawer"], .streamdeck-modal-close')
                ?.click();
        }
        return;
    }
    const course = document.querySelector(SELECTORS.courseLink);
    if (course) {
        e.preventDefault();
        window.location.href = course.href;
    } else if (!isEscape) {
        e.preventDefault();
        window.history.back();
    }
};

/**
 * Play a stage full screen; YouTube starts playing, and other players take the keys while full screen.
 *
 * @param {HTMLElement} stage
 */
const playFullScreen = (stage) => {
    const iframe = stage.querySelector('iframe');
    stage.requestFullscreen?.().then(() => {
        if (!iframe) {
            return;
        }
        if (/youtube(-nocookie)?\.com\/embed\//.test(iframe.src) && !/[?&]autoplay=1/.test(iframe.src)) {
            iframe.allow = 'autoplay; fullscreen';
            iframe.src += (iframe.src.includes('?') ? '&' : '?') + 'autoplay=1';
        }
        iframe.tabIndex = 0;
        iframe.focus();
        return;
    }).catch(() => {
        // Full screen refused (for example inside a frame): leave the stage as it is.
    });
};

/**
 * TV display: each stage is a "Play full screen" control instead of a frame the remote can get lost in.
 * Standard display: stages are left as they are. Runs again when the display changes.
 */
const updateStages = async() => {
    const stages = Array.from(document.querySelectorAll(SELECTORS.stage));
    if (!stages.length) {
        return;
    }
    const tv = isTv();
    const [label, hint] = tv ? await Promise.all([
        getString('playfullscreen', 'format_streamdeck'),
        getString('playfullscreenhint', 'format_streamdeck'),
    ]) : ['', ''];

    stages.forEach((stage) => {
        const iframe = stage.querySelector('iframe');
        const prepared = stage.dataset.streamdeckStage === 'tv';
        if (tv && !prepared) {
            stage.dataset.streamdeckStage = 'tv';
            stage.tabIndex = 0;
            stage.setAttribute('role', 'button');
            stage.setAttribute('aria-label', label);
            if (iframe) {
                iframe.tabIndex = -1;
            }
            const note = document.createElement('p');
            note.className = 'streamdeck-stage-hint';
            note.textContent = hint;
            stage.after(note);
        } else if (!tv && prepared) {
            delete stage.dataset.streamdeckStage;
            stage.removeAttribute('tabindex');
            stage.removeAttribute('role');
            stage.removeAttribute('aria-label');
            iframe?.removeAttribute('tabindex');
            if (stage.nextElementSibling?.classList.contains('streamdeck-stage-hint')) {
                stage.nextElementSibling.remove();
            }
        }
    });
};

/**
 * Stage controls in the TV display, and returning the remote to the stage when full screen ends.
 */
const listenToStages = () => {
    const isStage = (target) => target.matches?.(SELECTORS.stage) && target.dataset.streamdeckStage === 'tv';
    document.addEventListener('click', (e) => {
        if (isStage(e.target)) {
            playFullScreen(e.target);
        }
    });
    document.addEventListener('keydown', (e) => {
        if (isStage(e.target) && (e.key === 'Enter' || e.key === ' ')) {
            e.preventDefault();
            playFullScreen(e.target);
        }
    });
    document.addEventListener('fullscreenchange', () => {
        if (document.fullscreenElement) {
            return;
        }
        const stage = document.activeElement?.closest?.(SELECTORS.stage) ||
            document.activeElement?.matches?.('iframe') && document.activeElement.closest(SELECTORS.stage);
        if (stage && stage.dataset.streamdeckStage === 'tv') {
            const iframe = stage.querySelector('iframe');
            if (iframe) {
                iframe.tabIndex = -1;
            }
            stage.focus();
        }
    });
};

/**
 * Handle the arrow and Back keys.
 *
 * @param {KeyboardEvent} e
 */
const onKeyDown = (e) => {
    if (e.defaultPrevented || e.altKey || e.ctrlKey || e.metaKey || e.shiftKey) {
        return;
    }
    if (isTv() && (e.key === 'Escape' || BACK_KEYS.includes(e.key) || BACK_KEYCODES.includes(e.keyCode))) {
        goBack(e);
        return;
    }
    const direction = DIRECTIONS[e.key];
    const active = document.activeElement;
    if (!direction || ownsArrows(active)) {
        return;
    }
    // Tab lists handle left and right themselves.
    if ((direction === 'left' || direction === 'right') && active?.closest('[role="tablist"]')) {
        return;
    }
    const moved = isTv() ? moveSpatially(active, direction) : (active ? moveInGrid(active, direction) : false);
    if (moved) {
        e.preventDefault();
    }
};

/**
 * Set up keyboard and remote navigation.
 */
export const init = () => {
    if (initialised) {
        return;
    }
    initialised = true;
    document.addEventListener('keydown', onKeyDown);
    listenToStages();
    updateStages();

    // Switching display from the view menu updates the stages without a reload.
    new MutationObserver(updateStages).observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-streamdeck-display'],
    });
};
