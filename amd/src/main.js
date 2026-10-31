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
 * Streamdeck course format main JavaScript.
 *
 * @module     format_streamdeck/main
 * @copyright  2025 Mathieu Pelletier <mathieu@sats.ac.za>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';
import Fragment from 'core/fragment';
import Modal from 'core/modal';
import Notification from 'core/notification';
import Templates from 'core/templates';
import {getString, getStrings} from 'core/str';
import {init as initKeyboardNavigation} from 'format_streamdeck/spatial_nav';

const TIMER_SIZE = 90;
const TIMER_STROKE = 4;
const TIMER_WARNING_FRACTION = 0.10;
const TIMER_CRITICAL_FRACTION = 0.05;
const TIMER_URGENT_SECONDS = 60;
const TIMER_WAIT_ATTEMPTS = 50;
const TIMER_WAIT_INTERVAL = 200;

let initialised = false;

/**
 * Escape a value for use inside an HTML attribute.
 *
 * @param {String} value
 * @returns {String}
 */
const escapeAttr = (value) => String(value)
    .replace(/&/g, '&amp;')
    .replace(/"/g, '&quot;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');

/**
 * Add a class to a same-origin iframe's body, for styles specific to one kind of embed.
 *
 * Every framed course page already drops its chrome (see hook_listener); this is only for the extras.
 *
 * @param {HTMLIFrameElement} iframe
 * @param {String} bodyClass An extra class for styles specific to this embed.
 * @param {Function} [callback] Called with the iframe document after marking.
 */
const markEmbeddedOnLoad = (iframe, bodyClass, callback) => {
    const mark = () => {
        try {
            const doc = iframe.contentDocument || iframe.contentWindow.document;
            if (!doc || !doc.body) {
                return;
            }
            doc.body.classList.add(bodyClass);
            if (callback) {
                callback(doc);
            }
        } catch (err) {
            // Cross-origin content cannot be restyled; it is shown as it is.
        }
    };
    iframe.addEventListener('load', mark);
    if (iframe.contentDocument && iframe.contentDocument.readyState === 'complete') {
        mark();
    }
};

/**
 * Add a floating close button to a modal whose header is hidden by styles.css.
 *
 * @param {Modal} modal
 * @param {String} containerSelector The element in the body to put the button in.
 */
const addFloatingClose = async(modal, containerSelector) => {
    const container = modal.getBody()[0].querySelector(containerSelector);
    if (!container || container.querySelector('.streamdeck-modal-close')) {
        return;
    }
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'streamdeck-modal-close';
    button.setAttribute('aria-label', await getString('closebuttontitle', 'core'));
    button.innerHTML = '&times;';
    button.addEventListener('click', () => modal.hide());
    container.prepend(button);
};

/**
 * Open a large modal holding a Moodle page in an iframe.
 *
 * @param {Object} options
 * @param {String} options.title Modal title.
 * @param {String} options.url Page to show.
 * @param {String} options.bodyClass Class added to the embedded page's body.
 * @param {Boolean} [options.tall] Use a tall frame instead of 16:9.
 * @returns {Promise<Modal>}
 */
const openIframeModal = async({title, url, bodyClass, tall = false}) => {
    const wrapperClass = 'streamdeck-hero-iframe-wrapper' + (tall ? ' streamdeck-hero-iframe-wrapper--tall' : '');
    const modal = await Modal.create({
        title,
        body: `<div class="${wrapperClass}">
                   <iframe src="${escapeAttr(url)}" title="${escapeAttr(title)}"
                           class="streamdeck-hero-iframe" allowfullscreen></iframe>
               </div>`,
        large: true,
        removeOnClose: true,
    });
    modal.getRoot().addClass('streamdeck-iframe-modal');
    markEmbeddedOnLoad(modal.getBody()[0].querySelector('iframe'), bodyClass);
    modal.show();
    return modal;
};

/**
 * Open a modal whose body is copied from a hidden element on the page.
 *
 * @param {Object} options
 * @param {String} options.title Modal title.
 * @param {String} options.body HTML for the body.
 * @param {String} [options.chromeless] Selector of the card to float a close button on; hides the modal header.
 * @returns {Promise<Modal>}
 */
const openContentModal = async({title, body, chromeless}) => {
    const modal = await Modal.create({title, body, large: true, removeOnClose: true});
    if (chromeless) {
        modal.getRoot().addClass('streamdeck-forum-styled');
        await addFloatingClose(modal, chromeless);
    }
    modal.show();
    return modal;
};

/**
 * Hide Moodle chrome around SCORM packages playing on the episode stage.
 */
const initScormEmbeds = () => {
    document.querySelectorAll('.streamdeck-episode-card iframe[src*="/mod/scorm/"]').forEach((iframe) => {
        markEmbeddedOnLoad(iframe, 'streamdeck-scorm-embedded');
    });
};

/**
 * Tabs (syllabus sections, seasons): click and arrow-key switching with the matching ARIA state.
 *
 * Each tab names its panel with aria-controls; panels are found inside root, because the syllabus is copied
 * into a modal and its ids are not unique on the page.
 *
 * @param {HTMLElement} root The element containing the tabs and their panels.
 * @param {String} tabSelector Selector for the tabs.
 */
const initTabs = (root, tabSelector) => {
    const tabs = Array.from(root.querySelectorAll(tabSelector));
    if (!tabs.length) {
        return;
    }

    const select = (tab) => {
        tabs.forEach((other) => {
            const selected = other === tab;
            other.classList.toggle('is-active', selected);
            other.setAttribute('aria-selected', selected ? 'true' : 'false');
            other.tabIndex = selected ? 0 : -1;
            const panel = root.querySelector('#' + CSS.escape(other.getAttribute('aria-controls')));
            if (panel) {
                panel.classList.toggle('is-active', selected);
            }
        });
        tab.focus();
    };

    tabs.forEach((tab, index) => {
        tab.addEventListener('click', (e) => {
            e.preventDefault();
            select(tab);
        });
        // Tab lists are horizontal: left and right move between tabs, down is left for the content below.
        tab.addEventListener('keydown', (e) => {
            const moves = {ArrowRight: 1, ArrowLeft: -1};
            if (e.key in moves) {
                e.preventDefault();
                select(tabs[(index + moves[e.key] + tabs.length) % tabs.length]);
            } else if (e.key === 'Home' || e.key === 'End') {
                e.preventDefault();
                select(e.key === 'Home' ? tabs[0] : tabs[tabs.length - 1]);
            }
        });
    });
};

/**
 * Season tabs on the course page. Without JavaScript every season is listed with its heading.
 */
const initSeasons = () => {
    const seasons = document.querySelector('[data-region="streamdeck-seasons"]');
    if (seasons) {
        initTabs(seasons, '.streamdeck-season-tab');
        seasons.classList.add('is-enhanced');
    }
};

/**
 * Hero buttons, related-activity previews and inline frames on the course page.
 */
const registerCoursePageActions = () => {
    const body = $('body');

    body.on('click', '.streamdeck-more-info-btn', async(e) => {
        e.preventDefault();
        const source = document.getElementById('streamdeck-syllabus');
        if (!source) {
            return;
        }
        const modal = await openContentModal({
            title: source.dataset.title || await getString('syllabus', 'format_streamdeck'),
            body: source.innerHTML,
            chromeless: '.streamdeck-syllabus-modal',
        });
        initTabs(modal.getRoot()[0], '.streamdeck-pill');
    });

    // Announcements open as a "What's new" feed; showing it marks them read, so the badge goes.
    body.on('click', '.streamdeck-hero-announcements-btn', (e) => {
        e.preventDefault();
        const button = e.currentTarget;
        openForumPanel(button, button.dataset.announcementsContextid, {view: 'news'}).then(() => {
            button.classList.remove('has-unread');
            button.querySelector('.streamdeck-hero-icon-badge')?.remove();
            return;
        }).catch(Notification.exception);
    });

    const iframeButtons = [
        ['.streamdeck-hero-participants-btn', 'participantsUrl', 'streamdeck-participants-embedded'],
        ['.streamdeck-hero-liveclass-btn', 'liveclassUrl', 'streamdeck-liveclass-embedded'],
    ];
    iframeButtons.forEach(([selector, urlKey, bodyClass]) => {
        body.on('click', selector, async(e) => {
            e.preventDefault();
            const button = e.currentTarget;
            if (!button.dataset[urlKey]) {
                return;
            }
            await openIframeModal({title: button.getAttribute('aria-label'), url: button.dataset[urlKey], bodyClass});
        });
    });

    // The course's discussion forum opens as a discussion panel, listing its discussions.
    body.on('click', '.streamdeck-hero-generalforum-btn', (e) => {
        e.preventDefault();
        const button = e.currentTarget;
        openForumPanel(button, button.dataset.generalforumContextid, {alwayslist: 1}).catch(Notification.exception);
    });

    // An announcement opens and closes in place.
    body.on('click', '[data-action="sd-news-toggle"]', (e) => {
        e.preventDefault();
        const toggle = e.currentTarget;
        const open = toggle.getAttribute('aria-expanded') !== 'true';
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.closest('.sd-news-item').classList.toggle('is-expanded', open);
        document.getElementById(toggle.getAttribute('aria-controls')).hidden = !open;
    });

    body.on('click', '.streamdeck-hero-grades-btn', async(e) => {
        e.preventDefault();
        const url = e.currentTarget.dataset.gradesUrl;
        if (!url) {
            return;
        }
        await openIframeModal({
            title: await getString('gradesmodal', 'format_streamdeck'),
            url,
            bodyClass: 'streamdeck-grades-embedded',
            tall: true,
        });
    });

    body.on('click', '.js-streamdeck-forum-trigger', async(e) => {
        e.preventDefault();
        const trigger = e.currentTarget;
        const content = document.querySelector(`.js-streamdeck-forum-content[data-forum-cmid="${trigger.dataset.forumCmid}"]`);
        if (!content) {
            return;
        }
        await openContentModal({
            title: trigger.querySelector('.streamdeck-related-episode-title')?.textContent.trim() || '',
            body: content.innerHTML,
            chromeless: '.streamdeck-forum-preview, .streamdeck-forum-single',
        });
    });

    body.on('click', '.js-streamdeck-assign-trigger', async(e) => {
        e.preventDefault();
        const trigger = e.currentTarget;
        const content = document.querySelector(`.js-streamdeck-assign-content[data-assign-cmid="${trigger.dataset.assignCmid}"]`);
        if (!content) {
            return;
        }
        await openContentModal({
            title: trigger.querySelector('.streamdeck-related-episode-title')?.textContent.trim() || '',
            body: content.innerHTML,
        });
    });

    body.on('click', '[data-action="sd-discussion-open"]', (e) => {
        e.preventDefault();
        const card = e.currentTarget.closest(DISCUSSION.card);
        listViews.set(card, card.querySelector(DISCUSSION.view));
        loadThread(card, e.currentTarget.dataset.discussionid, true);
    });

    body.on('click', '[data-action="sd-discussion-back"]', (e) => {
        e.preventDefault();
        const card = e.currentTarget.closest(DISCUSSION.card);
        const list = listViews.get(card);
        if (list) {
            card.querySelector(DISCUSSION.view).replaceWith(list);
            list.querySelector('[data-action="sd-discussion-open"]')?.focus();
        }
    });

    body.on('click', '[data-action="sd-compose"]', (e) => {
        e.preventDefault();
        openComposer(e.currentTarget);
    });

    body.on('click', '.streamdeck-assign-view-btn', (e) => {
        e.preventDefault();
        const button = e.currentTarget;
        openInlineFrame(button, '.streamdeck-assign-preview', '.streamdeck-assign-frame-wrapper',
            button.dataset.assignUrl, 'streamdeck-assign-embedded', (doc) => {
                // Send links out of the frame so graders and other full pages do not load inside it.
                const base = doc.createElement('base');
                base.target = '_blank';
                doc.head.appendChild(base);
            });
    });
};

/**
 * Reveal an iframe inside a preview (forum reply, assignment), loading it on first use.
 *
 * @param {HTMLElement} button The button that opens it.
 * @param {String} previewSelector The preview containing the frame.
 * @param {String} wrapperSelector The frame's wrapper.
 * @param {String} url Page to load.
 * @param {String} bodyClass Class for the embedded page's body.
 * @param {Function} [onLoad] Called with the frame document once loaded.
 */
const openInlineFrame = (button, previewSelector, wrapperSelector, url, bodyClass, onLoad) => {
    const wrapper = button.closest(previewSelector)?.querySelector(wrapperSelector);
    const iframe = wrapper?.querySelector('iframe');
    if (!iframe || !url) {
        return;
    }
    if (!iframe.getAttribute('src')) {
        wrapper.classList.add('is-loading');
        iframe.addEventListener('load', () => {
            wrapper.classList.remove('is-loading');
            iframe.focus();
        }, {once: true});
        markEmbeddedOnLoad(iframe, bodyClass, onLoad);
        iframe.setAttribute('src', url);
    }
    wrapper.hidden = false;
    wrapper.classList.add('is-visible');
    button.classList.add('is-active');
    button.setAttribute('aria-expanded', 'true');
};

/**
 * Open a forum's panel (a list of discussions, or the announcements feed) in a modal, from a hero button.
 *
 * @param {HTMLElement} button The hero button; its label is the modal's title.
 * @param {String} contextid The forum's context.
 * @param {Object} args alwayslist, or view: 'news'. Kept on the panel to refresh it after a post.
 * @returns {Promise}
 */
const openForumPanel = (button, contextid, args) => {
    if (!contextid) {
        return Promise.resolve();
    }
    let pendingJs = '';
    return new Promise((resolve, reject) => {
        // The fragment resolves with two values, which a plain then() chain would drop.
        Fragment.loadFragment('format_streamdeck', 'forum', contextid, args).then((html, js) => {
            resolve([html, js]);
            return;
        }).catch(reject);
    }).then(([html, js]) => {
        pendingJs = js;
        const card = document.createElement('div');
        card.className = 'streamdeck-forum-preview sd-discussion';
        card.dataset.region = 'sd-discussion';
        card.dataset.contextid = contextid;
        Object.entries(args).forEach(([key, value]) => {
            card.dataset[key] = value;
        });
        card.innerHTML = html;
        return openContentModal({
            title: button.getAttribute('aria-label'),
            body: card.outerHTML,
            chromeless: '.streamdeck-forum-preview',
        });
    }).then(() => {
        Templates.runTemplateJS(pendingJs);
        return;
    });
};

const DISCUSSION = {
    card: '[data-region="sd-discussion"]',
    view: '[data-region="sd-thread"], [data-region="sd-discussion-list"], [data-region="sd-news"]',
    thread: '[data-region="sd-thread"]',
    slot: '[data-region="sd-composer-slot"]',
    post: '[data-region="sd-post"]',
};

/** @type {WeakMap<HTMLElement, HTMLElement>} The list a thread was opened from, per discussion panel. */
const listViews = new WeakMap();

/**
 * Show a discussion's thread in its panel, fetched fresh from the server.
 *
 * @param {HTMLElement} card The discussion panel.
 * @param {Number|String} discussionid
 * @param {Boolean} backtolist Whether the thread was opened from a list of discussions.
 * @param {Number} [highlight] A post to bring into view, such as one just written.
 * @returns {Promise}
 */
const loadThread = (card, discussionid, backtolist, highlight) => new Promise((resolve) => {
    Fragment.loadFragment('format_streamdeck', 'discussion', card.dataset.contextid, {
        discussionid,
        backtolist: backtolist ? 1 : 0,
    }).then((html, js) => {
        const view = card.querySelector(DISCUSSION.view);
        Templates.replaceNode(view, html, js);
        const post = highlight && card.querySelector(`${DISCUSSION.post}[data-postid="${highlight}"]`);
        if (post) {
            post.classList.add('sd-post--new');
            post.scrollIntoView({block: 'nearest', behavior: 'smooth'});
        } else {
            card.querySelector('.sd-thread-title')?.setAttribute('tabindex', '-1');
            card.querySelector('.sd-thread-title')?.focus();
        }
        resolve();
        return;
    }).catch(Notification.exception);
});

/**
 * Show a forum's list of discussions, or its announcements, in its panel again, fetched fresh (after a post).
 *
 * @param {HTMLElement} card The discussion panel.
 */
const loadList = (card) => {
    Fragment.loadFragment('format_streamdeck', 'forum', card.dataset.contextid, {
        alwayslist: card.dataset.alwayslist || 0,
        view: card.dataset.view || '',
    }).then((html, js) => {
        Templates.replaceNode(card.querySelector(DISCUSSION.view), html, js);
        card.querySelector('[data-action="sd-discussion-open"], [data-action="sd-news-toggle"]')?.focus();
        return;
    }).catch(Notification.exception);
};

/**
 * Open mod_forum's form (reply, edit or delete; or a new discussion, from a list) in a composer under the post it is
 * about.
 *
 * There is one composer per panel; opening another moves it. When the form is done Moodle shows the discussion
 * (or forum) page, which is the signal to close the composer and refresh the thread or list.
 *
 * @param {HTMLElement} trigger The Reply, Edit or Delete control, or the reply bar.
 */
const openComposer = (trigger) => {
    const card = trigger.closest(DISCUSSION.card);
    const thread = trigger.closest(DISCUSSION.view);
    const slot = trigger.closest(DISCUSSION.slot) ||
        trigger.closest(DISCUSSION.post)?.querySelector(DISCUSSION.slot);
    if (!card || !thread || !slot) {
        return;
    }

    let composer = card.querySelector('.sd-composer');
    if (!composer) {
        composer = document.createElement('div');
        composer.className = 'sd-composer is-loading';
        composer.innerHTML = '<iframe class="sd-composer-frame" name="sdreplyform"></iframe>';
    }
    const iframe = composer.querySelector('iframe');
    iframe.title = trigger.textContent.trim();
    // The reply bar becomes the composer; a post's own slot holds it under the post.
    if (slot.classList.contains('sd-reply-bar')) {
        slot.hidden = true;
        slot.after(composer);
    } else {
        slot.append(composer);
    }
    card.querySelectorAll('.sd-reply-bar').forEach((bar) => {
        bar.hidden = bar === slot;
    });
    composer.classList.add('is-loading');

    const close = () => {
        composer.remove();
        card.querySelectorAll('.sd-reply-bar').forEach((bar) => {
            bar.hidden = false;
        });
    };

    iframe.onload = () => {
        composer.classList.remove('is-loading');
        let url;
        let doc;
        try {
            url = new URL(iframe.contentWindow.location.href);
            doc = iframe.contentDocument;
        } catch (err) {
            return;
        }
        // Posting, saving, deleting or cancelling ends on the discussion (or, when the discussion was deleted, the
        // forum). Refresh the thread, bringing a new or edited post (#p<id>) into view.
        if (/\/mod\/forum\/(discuss|view)\.php$/.test(url.pathname)) {
            close();
            if (!thread.matches(DISCUSSION.thread)) {
                loadList(card);
                return;
            }
            const postid = url.hash.startsWith('#p') ? Number(url.hash.slice(2)) : 0;
            loadThread(card, thread.dataset.discussionid, !!thread.querySelector('[data-action="sd-discussion-back"]'),
                postid);
            return;
        }
        // Size the frame to where the form ends, growing and shrinking with it (drawers, validation), so only the
        // modal scrolls. Wrappers that stretch to fill the window (Boost Union) are not a measure of the content.
        const content = doc.getElementById('region-main') || doc.body;
        const fit = () => {
            iframe.style.height = Math.ceil(content.getBoundingClientRect().bottom + doc.defaultView.scrollY) + 'px';
        };
        fit();
        new ResizeObserver(fit).observe(content);
    };
    iframe.src = trigger.dataset.url;
};

/**
 * Take over Boost's right drawer toggle, so its saved preference cannot open the drawer on load.
 */
const initRightDrawerToggle = () => {
    if (document.body.id.startsWith('page-mod-quiz') ||
            !document.body.classList.contains('streamdeck-show-drawer-toggle')) {
        return;
    }
    const drawer = document.getElementById('theme_boost-drawers-blocks');
    const toggle = document.querySelector('.drawer-right-toggle [data-toggler="drawers"]');
    const closeButton = drawer?.querySelector('[data-action="closedrawer"]');
    if (!drawer) {
        return;
    }

    drawer.classList.remove('show');
    document.getElementById('page')?.classList.remove('show-drawer-right');
    document.body.classList.remove('drawer-open-right');
    [toggle, closeButton].forEach((button) => {
        button?.removeAttribute('data-toggler');
        button?.removeAttribute('data-action');
        button?.removeAttribute('data-target');
    });

    const setOpen = (open) => {
        drawer.classList.toggle('streamdeck-drawer-open', open);
        document.body.classList.toggle('streamdeck-blocks-open', open);
        drawer.setAttribute('aria-hidden', open ? 'false' : 'true');
        toggle?.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) {
            closeButton?.focus();
        }
    };
    setOpen(false);

    // Capture phase, so Boost's own drawer handler never sees the click.
    document.addEventListener('click', (e) => {
        const isOpen = drawer.classList.contains('streamdeck-drawer-open');
        if (e.target.closest('.drawer-right-toggle')) {
            e.preventDefault();
            e.stopPropagation();
            setOpen(!isOpen);
        } else if (closeButton && e.target.closest('button') === closeButton) {
            e.preventDefault();
            e.stopPropagation();
            setOpen(false);
            toggle?.focus();
        } else if (isOpen && !e.target.closest('.drawer.drawer-right')) {
            setOpen(false);
        }
    }, true);

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && drawer.classList.contains('streamdeck-drawer-open')) {
            setOpen(false);
            toggle?.focus();
        }
    });
};

/**
 * The navbar slides away after a second (styles.css); a tab at the top edge brings it back.
 */
const initNavbarAutoHide = async() => {
    const root = document.documentElement;
    const navbar = document.querySelector('.navbar.fixed-top');
    if (!navbar || root.classList.contains('streamdeck-in-frame') ||
            root.dataset.streamdeckDisplay === 'tv' ||
            document.body.classList.contains('behat-site') ||
            window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    // The pull tab sits at the top of the screen, so it is the first tab stop too.
    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'streamdeck-navbar-toggle';
    toggle.setAttribute('aria-label', await getString('togglenavbar', 'format_streamdeck'));
    toggle.setAttribute('aria-expanded', 'true');
    document.body.prepend(toggle);

    // A slid-away navbar is inert, so keyboard and remote focus cannot land on it off screen.
    const setHidden = (hidden) => {
        navbar.classList.toggle('streamdeck-navbar-hidden', hidden);
        navbar.classList.toggle('streamdeck-navbar-visible', !hidden);
        navbar.inert = hidden;
        toggle.setAttribute('aria-expanded', hidden ? 'false' : 'true');
    };

    navbar.addEventListener('animationend', () => {
        navbar.inert = true;
        toggle.setAttribute('aria-expanded', 'false');
    }, {once: true});
    toggle.addEventListener('click', () => {
        const hide = toggle.getAttribute('aria-expanded') === 'true';
        setHidden(hide);
        if (!hide) {
            navbar.querySelector('a[href], button:not([disabled])')?.focus();
        }
    });
};

/**
 * Parse the core timer's H:MM:SS or MM:SS text.
 *
 * @param {String} text
 * @returns {Number} Seconds.
 */
const parseTime = (text) => text.split(':').reduce((total, part) => total * 60 + (parseInt(part, 10) || 0), 0);

/**
 * Format seconds as MM:SS, or H:MM:SS when the quiz lasts an hour or more.
 *
 * @param {Number} seconds
 * @param {Boolean} showHours
 * @returns {String}
 */
const formatTime = (seconds, showHours) => {
    const s = Math.max(0, seconds);
    const pad = (n) => String(n).padStart(2, '0');
    const minutes = pad(Math.floor((s % 3600) / 60)) + ':' + pad(s % 60);
    return showHours ? Math.floor(s / 3600) + ':' + minutes : minutes;
};

/**
 * Turn the core quiz timer into a circular countdown ring.
 *
 * The core timer keeps running in its own (visually hidden) element; the ring reads it once a second.
 *
 * @param {Number} timelimit The quiz time limit in seconds, or 0 if unknown.
 */
const initCircularQuizTimer = async(timelimit) => {
    const wrapper = document.getElementById('quiz-timer-wrapper');
    const timer = document.getElementById('quiz-timer');
    const timeLeft = document.getElementById('quiz-time-left');
    if (!wrapper || !timer || !timeLeft) {
        return;
    }

    let attempts = 0;
    while (!timeLeft.textContent.includes(':')) {
        if (++attempts > TIMER_WAIT_ATTEMPTS) {
            return;
        }
        await new Promise((resolve) => setTimeout(resolve, TIMER_WAIT_INTERVAL));
    }

    const [label, warning] = await getStrings([
        {key: 'timerlabel', component: 'format_streamdeck'},
        {key: 'timerwarning', component: 'format_streamdeck'},
    ]);
    const total = timelimit || parseTime(timeLeft.textContent);
    const radius = (TIMER_SIZE - TIMER_STROKE) / 2;
    const circumference = 2 * Math.PI * radius;

    document.querySelector('.tertiary-navigation')?.appendChild(wrapper);
    wrapper.classList.add('streamdeck-timer-circular');
    // Keep the core element in the DOM so the quiz timer can still update it.
    timeLeft.classList.add('visually-hidden');
    wrapper.parentNode.insertBefore(timeLeft, wrapper);

    const circle = (cls) => `<circle class="${cls}" cx="${TIMER_SIZE / 2}" cy="${TIMER_SIZE / 2}" r="${radius}"
        fill="none" stroke-width="${TIMER_STROKE}" stroke-linecap="round"
        stroke-dasharray="${circumference}" stroke-dashoffset="0"></circle>`;
    timer.innerHTML = `
        <svg class="streamdeck-timer-svg" width="${TIMER_SIZE}" height="${TIMER_SIZE}"
             viewBox="0 0 ${TIMER_SIZE} ${TIMER_SIZE}" aria-hidden="true">
            ${circle('streamdeck-timer-track')}${circle('streamdeck-timer-ring')}
        </svg>
        <div class="streamdeck-timer-text" aria-hidden="true"></div>
        <div class="streamdeck-timer-label" aria-hidden="true">${escapeAttr(label)}</div>
        <div class="visually-hidden" aria-live="polite"></div>`;
    const ring = timer.querySelector('.streamdeck-timer-ring');
    const text = timer.querySelector('.streamdeck-timer-text');
    const live = timer.querySelector('[aria-live]');
    let lastState = '';

    const update = () => {
        const seconds = parseTime(timeLeft.textContent);
        const fraction = total > 0 ? seconds / total : 1;
        let state = '';
        if (fraction <= TIMER_CRITICAL_FRACTION) {
            state = 'critical';
        } else if (fraction <= TIMER_WARNING_FRACTION) {
            state = 'warning';
        }

        text.textContent = formatTime(seconds, total >= 3600);
        ring.setAttribute('stroke-dashoffset', circumference * (1 - fraction));
        wrapper.classList.toggle('streamdeck-timer-warning', state === 'warning');
        wrapper.classList.toggle('streamdeck-timer-critical', state === 'critical');
        wrapper.classList.toggle('streamdeck-timer-urgent', seconds > 0 && seconds <= TIMER_URGENT_SECONDS);

        if (state !== lastState && state !== '') {
            live.textContent = warning.replace('{$a}', timeLeft.textContent);
        }
        lastState = state;
    };
    update();
    setInterval(update, 1000);
};

/**
 * Initialise the Streamdeck format on the course page or an activity page.
 *
 * @param {Object} [config]
 * @param {Number} [config.quiztimelimit] Effective quiz time limit, on quiz pages.
 */
export const init = (config = {}) => {
    if (initialised) {
        return;
    }
    initialised = true;

    initScormEmbeds();
    initSeasons();
    initKeyboardNavigation();
    initRightDrawerToggle();
    initNavbarAutoHide();
    initCircularQuizTimer(config.quiztimelimit || 0);
    registerCoursePageActions();
};
