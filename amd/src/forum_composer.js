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
 * The composer in an episode's discussion: mod_forum's post form, reshaped when it opens inside the course page.
 *
 * The message box is a plain text box (the editor's toolbar shows on request) above a single action bar, like a
 * player's controls: on/off chips for the posting options (follow, private, send now, pin), a chip for the
 * formatting toolbar, chips that open small drawers for attachments, tags and anything else, and Cancel and Post.
 * Every control drives the forum's own form field, so everything saves exactly as in the forum.
 *
 * @module     format_streamdeck/forum_composer
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getStrings} from 'core/str';

/** Checkboxes shown as on/off chips. */
const CHIPS = [
    {name: 'discussionsubscribe', icon: 'fa-bell', key: 'composerfollow'},
    {name: 'isprivatereply', icon: 'fa-lock', key: 'composerprivate'},
    {name: 'mailnow', icon: 'fa-bolt', key: 'composersendnow'},
    {name: 'pinned', icon: 'fa-thumbtack', key: 'composerpin'},
    {name: 'posttomygroups', icon: 'fa-users', key: 'composerallgroups'},
];

/** Parts of the form behind chips that open a drawer below the bar. */
const DRAWERS = [
    {id: 'attach', icon: 'fa-paperclip', key: 'composerattach', find: (form) => [form.querySelector('#fitem_id_attachments')]},
    {id: 'tags', icon: 'fa-hashtag', key: 'composertags', find: (form) => [form.querySelector('#id_tagshdr')]},
    {
        id: 'more',
        icon: 'fa-ellipsis',
        key: 'composermore',
        find: (form) => [form.querySelector('#id_displayperiod'), form.querySelector('[name="groupinfo"]')?.closest('.fitem')],
    },
];

/** How long to watch the file picker for files it lists after the page has loaded. */
const FILE_WATCH_MS = 3000;

/** Remembers, on this device, whether the learner wants the formatting toolbar. */
const TOOLBAR_KEY = 'format_streamdeck_composer_toolbar';

/**
 * Whether a drawer holds something to see straight away: an error, a chosen tag or an attached file.
 *
 * @param {HTMLElement[]} elements
 * @returns {Boolean}
 */
const needsAttention = (elements) => elements.some((element) =>
    element.querySelector('.is-invalid') ||
    // Moodle leaves an empty (whitespace) error placeholder after every field; only a message counts.
    Array.from(element.querySelectorAll('.invalid-feedback, .error')).some((error) => error.textContent.trim() !== '') ||
    element.querySelector('.form-autocomplete-selection [data-value]') ||
    element.querySelector('.fp-content .fp-file'));

/**
 * A chip button.
 *
 * @param {String} icon Font Awesome icon class.
 * @param {String} label
 * @returns {HTMLButtonElement}
 */
const chip = (icon, label) => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'sd-chip';
    button.innerHTML = `<i class="fa-solid ${icon}" aria-hidden="true"></i> `;
    button.append(label);
    return button;
};

/**
 * Reshape the form, when the page is the composer inside the course page.
 */
export const init = async() => {
    const root = document.documentElement;
    if (!root.classList.contains('streamdeck-in-frame')) {
        return;
    }
    root.classList.add('sd-composer-page');
    const form = document.querySelector('#region-main form.mform');
    const message = form?.querySelector('#fitem_id_message');
    if (!message) {
        // The delete confirmation: styled by styles.css, nothing to reshape.
        return;
    }

    // Replies take their subject from the post they answer; editing keeps it. A new discussion's subject is its
    // title, labelled by its placeholder (the label stays for screen readers).
    if (Number(form.querySelector('input[name="reply"]')?.value)) {
        root.classList.add('sd-composer-reply');
    }
    const subject = form.querySelector('#id_subject');
    if (subject) {
        subject.placeholder = form.querySelector('#id_subject_label')?.textContent.trim() || '';
    }

    const keys = [...CHIPS, ...DRAWERS].map(({key}) => key).concat('composerformatting');
    const strings = await getStrings(keys.map((key) => ({key, component: 'format_streamdeck'})));
    const text = Object.fromEntries(keys.map((key, index) => [key, strings[index]]));

    const bar = document.createElement('div');
    bar.className = 'sd-composer-bar';
    const chips = document.createElement('div');
    chips.className = 'sd-composer-chips';
    const submit = document.createElement('div');
    submit.className = 'sd-composer-submit';
    bar.append(chips, submit);
    message.after(bar);

    // Option checkboxes become chips; the checkbox stays in the form (hidden) and is what gets saved.
    CHIPS.forEach(({name, icon, key}) => {
        const checkbox = form.querySelector(`input[type="checkbox"][name="${name}"]`);
        const row = checkbox?.closest('.fitem');
        if (!row) {
            return;
        }
        row.classList.add('sd-composer-moved');
        const button = chip(icon, text[key]);
        button.title = row.querySelector('label')?.textContent.trim() || text[key];
        const sync = () => {
            button.setAttribute('aria-pressed', checkbox.checked ? 'true' : 'false');
            button.disabled = checkbox.disabled;
        };
        sync();
        checkbox.addEventListener('change', sync);
        button.addEventListener('click', () => {
            checkbox.click();
            sync();
        });
        chips.append(button);
    });

    // The editor starts as a plain text box; this chip brings back its formatting toolbar.
    const formatting = chip('fa-font', text.composerformatting);
    const showToolbar = (show) => {
        root.classList.toggle('sd-composer-show-toolbar', show);
        formatting.setAttribute('aria-pressed', show ? 'true' : 'false');
    };
    let remembered = false;
    try {
        remembered = window.localStorage.getItem(TOOLBAR_KEY) === '1';
    } catch (err) {
        // Storage unavailable: start plain.
    }
    showToolbar(remembered);
    formatting.addEventListener('click', () => {
        const show = formatting.getAttribute('aria-pressed') !== 'true';
        showToolbar(show);
        try {
            window.localStorage.setItem(TOOLBAR_KEY, show ? '1' : '0');
        } catch (err) {
            // Storage unavailable: the choice lasts for this composer only.
        }
    });
    chips.append(formatting);

    // Attachments, tags and the rest open as drawers under the bar, only when asked for (or when they need attention).
    let drawerAfter = bar;
    DRAWERS.forEach(({id, icon, key, find}) => {
        const elements = find(form).filter(Boolean);
        if (!elements.length) {
            return;
        }
        const button = chip(icon, text[key]);
        const ids = elements.map((element, index) => {
            element.id = element.id || `sd-composer-${id}-${index}`;
            element.classList.add('sd-composer-drawer', 'sd-composer-drawer--' + id);
            // Sections in a drawer are always expanded; the drawer does the hiding.
            element.querySelectorAll('.fcontainer.collapse').forEach((container) => container.classList.add('show'));
            drawerAfter.after(element);
            drawerAfter = element;
            return element.id;
        });
        button.setAttribute('aria-controls', ids.join(' '));
        const set = (open) => {
            root.classList.toggle('sd-composer-open-' + id, open);
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
        };
        set(needsAttention(elements));
        button.addEventListener('click', () => set(button.getAttribute('aria-expanded') !== 'true'));
        chips.append(button);

        // The file picker lists a post's existing attachments after the page loads: open the drawer if it does.
        const files = id === 'attach' && elements[0].querySelector('.filemanager');
        if (files) {
            const observer = new MutationObserver(() => {
                if (needsAttention(elements)) {
                    set(true);
                    observer.disconnect();
                }
            });
            observer.observe(files, {childList: true, subtree: true});
            setTimeout(() => observer.disconnect(), FILE_WATCH_MS);
        }
    });

    // Cancel and Post join the bar; their original row is hidden.
    const cancel = form.querySelector('#id_cancel');
    const post = form.querySelector('#id_submitbutton');
    if (cancel) {
        submit.append(cancel);
    }
    if (post) {
        submit.append(post);
    }
    form.querySelector('#fgroup_id_buttonar')?.classList.add('sd-composer-moved');

    const startWithSubject = subject && !subject.value && !root.classList.contains('sd-composer-reply');
    (startWithSubject ? subject : form.querySelector('#id_message, [name="message[text]"]'))?.focus();
};
