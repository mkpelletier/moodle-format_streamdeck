/**
 * Streamdeck course format main JavaScript.
 *
 * @module     format_streamdeck/main
 * @copyright  2025 Mathieu Pelletier <mathieu@sats.ac.za>
 * @license    http://www.gnu.org/licenses/gpl-3.0.txt GNU GPL v3 or later
 */

define(['jquery', 'core/modal_factory', 'core/modal_events'], function($, ModalFactory, ModalEvents) {
    'use strict';

    /**
     * Hide Moodle chrome elements in SCORM iframes within episode cards.
     */
    function hideScormChromeInEpisodeCard() {
        var episodeCards = document.querySelectorAll('.streamdeck-episode-card');

        episodeCards.forEach(function(card) {
            var iframe = card.querySelector('iframe');
            if (!iframe) {
                return;
            }

            // Only target SCORM iframes
            if (!iframe.src.includes('/mod/scorm/')) {
                return;
            }

            /**
             * Inject CSS to hide chrome elements inside the iframe.
             * @param {HTMLIFrameElement} targetIframe The iframe to modify.
             */
            function injectChromeHidingStyles(targetIframe) {
                try {
                    var iframeDoc = targetIframe.contentDocument || targetIframe.contentWindow.document;
                    var style = iframeDoc.createElement('style');
                    style.type = 'text/css';
                    style.innerHTML = `
                        #page-header,
                        #page-footer,
                        .activity-navigation, .navbar, #region-main > div > .d-flex, #scorm_toc_toggle,
                        #contentframe .content-actions-lti {
                            display: none !important;
                        }
                    `;
                    iframeDoc.head.appendChild(style);
                } catch (e) {
                    // Silently fail due to cross-origin or other issues
                }
            }

            iframe.addEventListener('load', function() {
                injectChromeHidingStyles(iframe);
            });

            // If iframe already loaded
            if (iframe.contentDocument && iframe.contentDocument.readyState === 'complete') {
                injectChromeHidingStyles(iframe);
            }
        });
    }

    /**
     * Initialize pill-based tab switching inside the modal.
     *
     * @param {jQuery} modalRoot The modal root element.
     */
    function initPillSwitching(modalRoot) {
        // Use the same classes/structure as in content.mustache.
        var pills = modalRoot.find('.streamdeck-pill');
        var panels = modalRoot.find('.streamdeck-syllabus-panel');

        if (pills.length === 0 || panels.length === 0) {
            return;
        }

        // Ensure one active pill/panel (in case markup changes).
        if (!pills.filter('.is-active').length) {
            pills.first().addClass('is-active');
        }
        if (!panels.filter('.is-active').length) {
            panels.first().addClass('is-active');
        }

        pills.on('click', function(e) {
            e.preventDefault();

            var $pill = $(this);
            var targetSelector = $pill.data('target'); // E.g. "#sd-pill-intro".

            if (!targetSelector) {
                return;
            }

            // Remove active classes.
            pills.removeClass('is-active');
            panels.removeClass('is-active');

            // Activate clicked pill and corresponding panel.
            $pill.addClass('is-active');
            modalRoot.find(targetSelector).addClass('is-active');
        });
    }

    /**
     * Initialize forum modals using the existing Bootstrap modal shell.
     */
    function initForumModals() {
        $(document).on('click', '.js-streamdeck-forum-trigger', function(e) {
            e.preventDefault();

            var $trigger = $(this);
            var cmid = $trigger.data('forum-cmid');

            if (!cmid) {
                return;
            }

            var $contentNode = $('.js-streamdeck-forum-content[data-forum-cmid="' + cmid + '"]');
            if (!$contentNode.length) {
                return;
            }

            var bodyContent = $contentNode.html();
            var title = $trigger.find('.streamdeck-related-episode-title').text().trim() ||
                        $trigger.text().trim();

            ModalFactory.create({
                type: ModalFactory.types.DEFAULT,
                title: title,
                body: bodyContent,
                large: true
            }).then(function(modal) {
                modal.getRoot().addClass('streamdeck-forum-styled');
                modal.show();

                modal.getRoot().on(ModalEvents.shown, function() {
                    var container = modal.getRoot()
                        .find('.streamdeck-forum-preview, ' +
                            '.streamdeck-forum-single').first();
                    if (container.length &&
                        !container.find('.streamdeck-forum-close').length
                    ) {
                        var btn = $(
                            '<button type="button"' +
                            ' class="streamdeck-forum-close"' +
                            ' aria-label="Close">' +
                            '&times;</button>'
                        );
                        container.prepend(btn);
                        btn.on('click', function() {
                            modal.hide();
                        });
                    }
                });

                return modal;
            }).catch(function() {
                // Silent fail if modal creation fails.
            });
        });
    }

    /**
     * Initialize assignment modals using ModalFactory.
     */
    function initAssignModals() {
        $(document).on('click', '.js-streamdeck-assign-trigger', function(e) {
            e.preventDefault();

            var $trigger = $(this);
            var cmid = $trigger.data('assign-cmid');

            if (!cmid) {
                return;
            }

            var $contentNode = $('.js-streamdeck-assign-content[data-assign-cmid="' + cmid + '"]');
            if (!$contentNode.length) {
                return;
            }

            var bodyContent = $contentNode.html();
            var title = $trigger.find('.streamdeck-related-episode-title').text().trim() ||
                        $trigger.text().trim();

            ModalFactory.create({
                type: ModalFactory.types.DEFAULT,
                title: title,
                body: bodyContent,
                large: true
            }).then(function(modal) {
                modal.show();
                return modal;
            }).catch(function() {
                // Silent fail if modal creation fails.
            });
        });
    }

    /**
     * Initialize the Streamdeck format.
     */
    /**
     * Set up the auto-hiding navbar with a toggle tab.
     *
     * Scoped to format-streamdeck not editing.
     */
    /**
     * Manually handle the right drawer toggle since Boost's drawer JS
     * may not initialise correctly when we hide drawers with CSS at load time.
     * Uses document-level event delegation for reliability.
     */
    function initRightDrawerToggle() {
        // Skip quiz pages — leave Boost's native drawer handling intact there.
        if (document.body.id && document.body.id.indexOf('page-mod-quiz') === 0) {
            return;
        }
        if (!document.body.classList.contains('streamdeck-show-drawer-toggle') ||
            document.body.classList.contains('editing')) {
            return;
        }

        // We use our own class .streamdeck-drawer-open instead of Boost's .show
        // so that Boost's server-side preference cannot force the drawer open on load.
        var drawer = document.getElementById('theme_boost-drawers-blocks');
        if (!drawer) {
            return;
        }

        // Strip Boost's .show on load so it cannot interfere.
        drawer.classList.remove('show');
        drawer.setAttribute('aria-hidden', 'true');
        var page = document.getElementById('page');
        if (page) {
            page.classList.remove('show-drawer-right');
        }

        // Also strip body class that Boost may have set.
        document.body.classList.remove('drawer-open-right');

        /**
         * Close the drawer.
         */
        function closeDrawer() {
            drawer.classList.remove('streamdeck-drawer-open');
            drawer.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('drawer-open-right');
        }

        /**
         * Open the drawer.
         */
        function openDrawer() {
            drawer.classList.add('streamdeck-drawer-open');
            drawer.removeAttribute('aria-hidden');
        }

        // Remove Boost's data-toggler attribute to prevent Boost JS from interfering.
        var boostBtn = document.querySelector('.drawer-right-toggle [data-toggler="drawers"]');
        if (boostBtn) {
            boostBtn.removeAttribute('data-toggler');
            boostBtn.removeAttribute('data-action');
            boostBtn.removeAttribute('data-target');
        }

        // Toggle on button click (match the toggle container or anything inside it).
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.drawer-right-toggle')) {
                // Not a toggle click — check if we should auto-hide.
                if (drawer.classList.contains('streamdeck-drawer-open') &&
                    !e.target.closest('.drawer.drawer-right')) {
                    closeDrawer();
                }
                return;
            }

            e.preventDefault();
            e.stopPropagation();

            if (drawer.classList.contains('streamdeck-drawer-open')) {
                closeDrawer();
            } else {
                openDrawer();
            }
        }, true);
    }

    /**
     * Auto-hide the navbar after 1s and provide a toggle to show/hide it.
     */
    function initNavbarAutoHide() {
        // Only run in non-editing streamdeck view.
        if (!document.body.classList.contains('format-streamdeck') ||
            document.body.classList.contains('editing')) {
            return;
        }

        var navbar = document.querySelector('.navbar');
        if (!navbar) {
            return;
        }

        // Create the toggle tab.
        var toggle = document.createElement('button');
        toggle.className = 'streamdeck-navbar-toggle';
        toggle.setAttribute('aria-label', 'Toggle navigation bar');
        toggle.setAttribute('type', 'button');
        document.body.appendChild(toggle);

        var isHidden = false;

        // After the CSS animation finishes (1s delay + 0.4s animation), mark as hidden.
        navbar.addEventListener('animationend', function() {
            isHidden = true;
        });

        toggle.addEventListener('click', function() {
            if (isHidden) {
                navbar.classList.remove('streamdeck-navbar-hidden');
                navbar.classList.add('streamdeck-navbar-visible');
                isHidden = false;
            } else {
                navbar.classList.remove('streamdeck-navbar-visible');
                navbar.classList.add('streamdeck-navbar-hidden');
                isHidden = true;
            }
        });
    }

    /**
     * Transform the Moodle quiz timer into a circular countdown ring.
     */
    function initCircularQuizTimer() {
        var wrapper = document.getElementById('quiz-timer-wrapper');
        var timerEl = document.getElementById('quiz-timer');
        var timeLeft = document.getElementById('quiz-time-left');
        if (!wrapper || !timerEl || !timeLeft) {
            return;
        }

        // Only on quiz attempt pages within streamdeck format.
        if (!document.body.id || document.body.id.indexOf('page-mod-quiz') !== 0) {
            return;
        }
        if (!document.body.classList.contains('format-streamdeck')) {
            return;
        }

        // Move the timer into the tertiary-navigation bar (right side).
        var tertiaryNav = document.querySelector('.tertiary-navigation');
        if (tertiaryNav) {
            tertiaryNav.appendChild(wrapper);
        }

        // Use the quiz timelimit from settings (injected by PHP) as the total duration.
        var totalSeconds = window.streamdeckQuizTimelimit || 0;
        var ring = null;
        var circumference = 0;

        /**
         * Build the circular timer UI.
         */
        function buildCircularTimer() {
            // Hide the original toggle button.
            var toggleBtn = document.getElementById('toggle-timer');
            if (toggleBtn) {
                toggleBtn.style.display = 'none';
            }

            // Create circular SVG wrapper.
            var size = 90;
            var strokeWidth = 4;
            var radius = (size - strokeWidth) / 2;
            circumference = 2 * Math.PI * radius;

            wrapper.classList.add('streamdeck-timer-circular');
            // Move #quiz-time-left out of the timer circle but keep it in the DOM
            // so Moodle's YUI timer can still find and update it by ID.
            timeLeft.style.cssText = 'position:absolute;width:0;height:0;overflow:hidden;opacity:0;pointer-events:none';
            wrapper.parentNode.insertBefore(timeLeft, wrapper);
            timerEl.innerHTML = '';

            var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            svg.setAttribute('width', size);
            svg.setAttribute('height', size);
            svg.setAttribute('viewBox', '0 0 ' + size + ' ' + size);
            svg.classList.add('streamdeck-timer-svg');

            // Background track.
            var bgCircle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
            bgCircle.setAttribute('cx', size / 2);
            bgCircle.setAttribute('cy', size / 2);
            bgCircle.setAttribute('r', radius);
            bgCircle.setAttribute('fill', 'none');
            bgCircle.setAttribute('stroke', 'rgba(255,255,255,0.08)');
            bgCircle.setAttribute('stroke-width', strokeWidth);
            svg.appendChild(bgCircle);

            // Countdown ring.
            ring = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
            ring.setAttribute('cx', size / 2);
            ring.setAttribute('cy', size / 2);
            ring.setAttribute('r', radius);
            ring.setAttribute('fill', 'none');
            ring.setAttribute('stroke', '#e50914');
            ring.setAttribute('stroke-width', strokeWidth);
            ring.setAttribute('stroke-linecap', 'round');
            ring.setAttribute('stroke-dasharray', circumference);
            ring.setAttribute('stroke-dashoffset', '0');
            ring.classList.add('streamdeck-timer-ring');
            svg.appendChild(ring);

            timerEl.appendChild(svg);

            // Time text overlay.
            var timeDisplay = document.createElement('div');
            timeDisplay.className = 'streamdeck-timer-text';
            timeDisplay.id = 'streamdeck-timer-display';
            timerEl.appendChild(timeDisplay);

            // Label.
            var label = document.createElement('div');
            label.className = 'streamdeck-timer-label';
            label.textContent = 'TIME';
            timerEl.appendChild(label);
        }

        /**
         * Parse H:MM:SS or MM:SS to total seconds.
         * @param {string} text Time string.
         * @returns {number} Total seconds.
         */
        function parseTime(text) {
            var parts = text.split(':');
            if (parts.length === 3) {
                return parseInt(parts[0], 10) * 3600 + parseInt(parts[1], 10) * 60 + parseInt(parts[2], 10);
            } else if (parts.length === 2) {
                return parseInt(parts[0], 10) * 60 + parseInt(parts[1], 10);
            }
            return 0;
        }

        /**
         * Format seconds as MM:SS or H:MM:SS.
         * @param {number} secs Seconds remaining.
         * @returns {string} Formatted time.
         */
        function formatTime(secs) {
            if (secs < 0) {
                secs = 0;
            }
            var h = Math.floor(secs / 3600);
            var m = Math.floor((secs % 3600) / 60);
            var s = secs % 60;
            var pad = function(n) {
                return n < 10 ? '0' + n : '' + n;
            };
            // Show hours only if the total quiz duration is 1 hour or more.
            if (totalSeconds >= 3600) {
                return h + ':' + pad(m) + ':' + pad(s);
            }
            return pad(m) + ':' + pad(s);
        }

        /**
         * Update the circular display.
         */
        function updateDisplay() {
            var display = document.getElementById('streamdeck-timer-display');
            if (!display || !ring) {
                return;
            }

            // Read from Moodle's hidden original span (it keeps updating via module.js).
            var currentText = timeLeft.textContent || '';
            var currentSecs = parseTime(currentText);

            // Fallback: if PHP didn't inject the timelimit, use current remaining on first read.
            if (totalSeconds === 0 && currentSecs > 0) {
                totalSeconds = currentSecs;
            }

            display.textContent = formatTime(currentSecs);

            // Update ring progress.
            var fraction = totalSeconds > 0 ? currentSecs / totalSeconds : 1;
            var offset = circumference * (1 - fraction);
            ring.setAttribute('stroke-dashoffset', offset);

            // Color transitions: green (>10%) > yellow (>5%) > red (<=5%).
            if (fraction > 0.10) {
                ring.setAttribute('stroke', '#27c24a');
            } else if (fraction > 0.05) {
                ring.setAttribute('stroke', '#f0a500');
            } else {
                ring.setAttribute('stroke', '#e50914');
            }

            // Pulse effect in final 60 seconds.
            if (currentSecs <= 60 && currentSecs > 0) {
                wrapper.classList.add('streamdeck-timer-urgent');
            } else {
                wrapper.classList.remove('streamdeck-timer-urgent');
            }

            requestAnimationFrame(updateDisplay);
        }

        // Wait for Moodle's timer to start populating #quiz-time-left.
        var checkInterval = setInterval(function() {
            var text = timeLeft.textContent || '';
            if (text.indexOf(':') !== -1) {
                clearInterval(checkInterval);
                // Only use remaining time as total if PHP didn't inject the timelimit.
                if (totalSeconds === 0) {
                    totalSeconds = parseTime(text);
                }
                buildCircularTimer();
                requestAnimationFrame(updateDisplay);
            }
        }, 200);
    }

    var init = function() {
        // Hide SCORM chrome in episode cards
        hideScormChromeInEpisodeCard();

        // Right drawer toggle handler.
        initRightDrawerToggle();

        // Circular quiz timer.
        initCircularQuizTimer();

        // Auto-hide navbar with toggle.
        initNavbarAutoHide();

        // Initialize modals
        initForumModals();
        initAssignModals();
        registerForumReplyIframe();
        registerAssignViewIframe();
        registerAnnouncementsIcon();
        registerGeneralForumIcon();
        registerParticipantsIcon();
        registerLiveClassIcon();
        applyLocalAnnouncementsClearState();
        registerGradesIcon();

        // More Info modal trigger.
        $('.streamdeck-more-info-btn').on('click', function(e) {
            e.preventDefault();

            // Build modal body from the hidden #streamdeck-syllabus div.
            var $source = $('#streamdeck-syllabus');
            var bodyContent = $source.html();
            var title = $source.data('title') || 'Syllabus';

            ModalFactory.create({
                type: ModalFactory.types.DEFAULT,
                title: title,
                body: bodyContent,
                large: true
            }).then(function(modal) {
                modal.show();

                // After modal is shown, initialize pill switching and inject close button.
                modal.getRoot().on(ModalEvents.shown, function() {
                    try {
                        initPillSwitching(modal.getRoot());
                    } catch (err) {
                        // Silent fail; modal still usable.
                    }

                    // Inject a floating close button into the syllabus modal.
                    var syllabusEl = modal.getRoot().find('.streamdeck-syllabus-modal').first();
                    if (syllabusEl.length && !syllabusEl.find('.streamdeck-syllabus-close').length) {
                        var closeBtn = $('<button type="button" class="streamdeck-syllabus-close"' +
                            ' aria-label="Close">&times;</button>');
                        syllabusEl.prepend(closeBtn);
                        closeBtn.on('click', function() {
                            modal.hide();
                        });
                    }
                });

                return modal;
            }).catch(function() {
                // Silent fail if modal creation fails.
            });
        });
    };

    /**
     * Register behaviour for hero Announcements icon.
     * Opens the News forum in an inline iframe modal
     * and clears the unread badge/wiggle locally.
     */
    const registerAnnouncementsIcon = () => {
        $('body').on('click', '.streamdeck-hero-announcements-btn', function(e) {
            e.preventDefault();

            const $btn = $(this);
            const announcementsUrl = $btn.data('announcements-url');
            const forumId = $btn.data('announcements-forumid');

            if (!announcementsUrl) {
                return;
            }

            // Immediately clear unread indicator (badge + wiggle) and persist that locally.
            try {
                $btn.removeClass('has-unread');
                const $badge = $btn.find('.streamdeck-hero-icon-badge');
                if ($badge.length) {
                    $badge.remove();
                }

                if (forumId && window.localStorage) {
                    const key = 'streamdeck_announcements_cleared_' + forumId;
                    window.localStorage.setItem(key, '1');
                }
            } catch (err) {
                // Silent; not critical if this fails.
            }

            const title = $btn.attr('aria-label') || 'Announcements';

            // Build iframe body content.
            const bodyContent = `
                <div class="streamdeck-hero-iframe-wrapper">
                    <iframe src="${announcementsUrl}"
                            title="${title}"
                            class="streamdeck-hero-iframe"
                            frameborder="0"
                            allowfullscreen></iframe>
                </div>
            `;

            ModalFactory.create({
                type: ModalFactory.types.DEFAULT,
                title: title,
                body: bodyContent,
                large: true
            }).then(function(modal) {
                modal.getRoot().addClass(
                    'streamdeck-announcements-modal'
                );
                modal.show();

                // After modal is shown, strip chrome from the iframe.
                modal.getRoot().on(ModalEvents.shown, function() {
                    try {
                        const $iframe = modal.getRoot().find('iframe.streamdeck-hero-iframe').first();
                        if (!$iframe.length) {
                            return;
                        }

                        $iframe.on('load', function() {
                            try {
                                const win = this.contentWindow;
                                const doc = win.document;

                                $(doc).find(
                                    '#page-header, ' +
                                    '#page-footer, ' +
                                    'header[role="banner"], ' +
                                    'nav.navbar, ' +
                                    '.navbar, ' +
                                    '.breadcrumb, ' +
                                    '.secondary-navigation, ' +
                                    '.page-context-header, ' +
                                    '.drawer-toggle, ' +
                                    '.drawer, ' +
                                    '.activity-navigation, ' +
                                    'ul.content-actions-lti'
                                ).hide();

                                $(doc.body).addClass('streamdeck-announcements-embedded');
                            } catch (err) {
                                // Silent failure (cross-origin, etc.).
                            }
                        });
                    } catch (err) {
                        // Silent failure.
                    }
                });

                return modal;
            }).catch(function() {
                // Silent fail if modal creation fails.
            });
        });
    };

    /**
     * On load, suppress unread wiggle/badge for announcements if user
     * has previously opened this forum via the hero icon on this device.
     */
    const applyLocalAnnouncementsClearState = () => {
        $('.streamdeck-hero-announcements-btn').each(function() {
            const $btn = $(this);
            const forumId = $btn.data('announcements-forumid');
            if (!forumId) {
                return;
            }

            const key = 'streamdeck_announcements_cleared_' + forumId;
            try {
                const cleared = window.localStorage && window.localStorage.getItem(key);
                if (cleared === '1') {
                    $btn.removeClass('has-unread');
                    $btn.find('.streamdeck-hero-icon-badge').remove();
                }
            } catch (err) {
                // Silent; localStorage not critical.
            }
        });
    };

    /**
     * Register behaviour for hero Live Class icon.
     * Opens BigBlueButton in an inline iframe modal.
     */
    const registerLiveClassIcon = () => {
        $('body').on('click', '.streamdeck-hero-liveclass-btn', function(e) {
            e.preventDefault();

            const $btn = $(this);
            const liveClassUrl = $btn.data('liveclass-url');
            if (!liveClassUrl) {
                return;
            }

            const title = $btn.attr('aria-label') || 'Live Class';

            // Build iframe body content.
            const bodyContent = `
                <div class="streamdeck-hero-iframe-wrapper">
                    <iframe src="${liveClassUrl}"
                            title="${title}"
                            class="streamdeck-hero-iframe"
                            frameborder="0"
                            allowfullscreen></iframe>
                </div>
            `;

            ModalFactory.create({
                type: ModalFactory.types.DEFAULT,
                title: title,
                body: bodyContent,
                large: true
            }).then(function(modal) {
                modal.show();

                // After modal shown, strip chrome from the iframe.
                modal.getRoot().on(ModalEvents.shown, function() {
                    try {
                        const $iframe = modal.getRoot().find('iframe.streamdeck-hero-iframe').first();
                        if (!$iframe.length) {
                            return;
                        }

                        $iframe.on('load', function() {
                            try {
                                const win = this.contentWindow;
                                const doc = win.document;

                                // Hide Moodle chrome.
                                $(doc).find(
                                    '#page-header, ' +
                                    '#page-footer, ' +
                                    'header[role="banner"], ' +
                                    'nav.navbar, ' +
                                    '.navbar, ' +
                                    '.breadcrumb, ' +
                                    '.secondary-navigation, ' +
                                    '.page-context-header, ' +
                                    '.drawer-toggle, ' +
                                    '.drawer, ' +
                                    '.activity-navigation'
                                ).hide();

                                // Add hook class for custom styling.
                                $(doc.body).addClass('streamdeck-liveclass-embedded');
                            } catch (err) {
                                // Silent failure (cross-origin, etc.).
                            }
                        });
                    } catch (err) {
                        // Silent failure.
                    }
                });

                return modal;
            }).catch(function() {
                // Silent fail if modal creation fails.
            });
        });
    };

    /**
     * Register behaviour for hero General Forum icon.
     * Opens the general discussion forum in an inline iframe modal.
     */
    const registerGeneralForumIcon = () => {
        $('body').on('click', '.streamdeck-hero-generalforum-btn', function(e) {
            e.preventDefault();

            const $btn = $(this);
            const forumUrl = $btn.data('generalforum-url');
            if (!forumUrl) {
                return;
            }

            const title = $btn.attr('aria-label') || 'Discussion forum';

            const bodyContent = `
                <div class="streamdeck-hero-iframe-wrapper">
                    <iframe src="${forumUrl}"
                            title="${title}"
                            class="streamdeck-hero-iframe"
                            frameborder="0"
                            allowfullscreen></iframe>
                </div>
            `;

            ModalFactory.create({
                type: ModalFactory.types.DEFAULT,
                title: title,
                body: bodyContent,
                large: true
            }).then(function(modal) {
                modal.getRoot().addClass(
                    'streamdeck-generalforum-modal'
                );
                modal.show();

                modal.getRoot().on(ModalEvents.shown, function() {
                    try {
                        const $iframe = modal.getRoot().find('iframe.streamdeck-hero-iframe').first();
                        if (!$iframe.length) {
                            return;
                        }

                        $iframe.on('load', function() {
                            try {
                                const win = this.contentWindow;
                                const doc = win.document;

                                $(doc).find(
                                    '#page-header, ' +
                                    '#page-footer, ' +
                                    'header[role="banner"], ' +
                                    'nav.navbar, ' +
                                    '.navbar, ' +
                                    '.breadcrumb, ' +
                                    '.secondary-navigation, ' +
                                    '.page-context-header, ' +
                                    '.drawer-toggle, ' +
                                    '.drawer, ' +
                                    '.activity-navigation, ' +
                                    'ul.content-actions-lti'
                                ).hide();

                                $(doc.body).addClass('streamdeck-generalforum-embedded');
                            } catch (err) {
                                // Silent failure (cross-origin, etc.).
                            }
                        });
                    } catch (err) {
                        // Silent failure.
                    }
                });

                return modal;
            }).catch(function() {
                // Silent fail if modal creation fails.
            });
        });
    };

    /**
     * Register behaviour for hero Participants icon.
     * Opens the course participants list in an inline iframe modal.
     */
    const registerParticipantsIcon = () => {
        $('body').on('click', '.streamdeck-hero-participants-btn', function(e) {
            e.preventDefault();

            const $btn = $(this);
            const participantsUrl = $btn.data('participants-url');
            if (!participantsUrl) {
                return;
            }

            const title = $btn.attr('aria-label') || 'Participants';

            const bodyContent = `
                <div class="streamdeck-hero-iframe-wrapper">
                    <iframe src="${participantsUrl}"
                            title="${title}"
                            class="streamdeck-hero-iframe"
                            frameborder="0"
                            allowfullscreen></iframe>
                </div>
            `;

            ModalFactory.create({
                type: ModalFactory.types.DEFAULT,
                title: title,
                body: bodyContent,
                large: true
            }).then(function(modal) {
                modal.show();

                modal.getRoot().on(ModalEvents.shown, function() {
                    try {
                        const $iframe = modal.getRoot().find('iframe.streamdeck-hero-iframe').first();
                        if (!$iframe.length) {
                            return;
                        }

                        $iframe.on('load', function() {
                            try {
                                const win = this.contentWindow;
                                const doc = win.document;

                                $(doc).find(
                                    '#page-header, ' +
                                    '#page-footer, ' +
                                    'header[role="banner"], ' +
                                    'nav.navbar, ' +
                                    '.navbar, ' +
                                    '.breadcrumb, ' +
                                    '.secondary-navigation, ' +
                                    '.page-context-header, ' +
                                    '.drawer-toggle, ' +
                                    '.drawer, ' +
                                    '.activity-navigation'
                                ).hide();

                                $(doc.body).addClass('streamdeck-participants-embedded');
                            } catch (err) {
                                // Silent failure (cross-origin, etc.).
                            }
                        });
                    } catch (err) {
                        // Silent failure.
                    }
                });

                return modal;
            }).catch(function() {
                // Silent fail if modal creation fails.
            });
        });
    };

    const registerForumReplyIframe = () => {
        $('body').on('click', '.streamdeck-forum-reply-btn', function(e) {
            e.preventDefault();

            const $btn = $(this);
            const $preview = $btn.closest('.streamdeck-forum-preview');
            if (!$preview.length) {
                return;
            }

            const $wrapper = $preview.find('.streamdeck-forum-reply-frame-wrapper').first();
            if (!$wrapper.length) {
                return;
            }

            const $iframe = $wrapper.find('iframe.streamdeck-forum-reply-frame').first();
            if (!$iframe.length) {
                return;
            }

            const discussionUrl = $btn.data('discussionUrl');
            if (!discussionUrl) {
                return;
            }

            if (!$iframe.attr('src')) {
                $wrapper.addClass('is-loading');

                $iframe
                    .on('load', function() {
                        $wrapper.removeClass('is-loading');

                        // ── NEW: chrome stripping + dark theme inside iframe ──
                        try {
                            const win = this.contentWindow;
                            const doc = win.document;

                            // Hide top‑level chrome we never want in the embedded view.
                            $(doc).find(
                                '#page-header, ' +
                                '#page-footer, ' +
                                'header[role="banner"], ' +
                                'nav.navbar, ' +
                                '.navbar, ' +
                                '.breadcrumb, ' +
                                '.secondary-navigation, ' +
                                '.page-context-header, ' +
                                '.drawer-toggle, ' +
                                '.drawer, ' +
                                '.activity-navigation'
                            ).hide();

                            // Add a single hook class. All visual theming done in CSS.
                            $(doc.body).addClass('streamdeck-forum-embedded');
                        } catch (err) {
                            // Silent failure.
                        }
                    })
                    .attr('src', discussionUrl);
            }

            $wrapper.removeAttr('hidden').addClass('is-visible');
            $btn.addClass('is-active');
        });
    };

/**
 * Register grades icon click handler in the hero.
 * Opens a modal with an iframe showing the user grade report.
 */
const registerGradesIcon = () => {
    const gradesBtn = document.querySelector('.streamdeck-hero-grades-btn');
    if (!gradesBtn) {
        return;
    }

    const gradesUrl = gradesBtn.dataset.gradesUrl;
    if (!gradesUrl) {
        return;
    }

    gradesBtn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();

        require(['core/modal_factory', 'core/modal_events'], (ModalFactory, ModalEvents) => {
            ModalFactory.create({
                type: ModalFactory.types.DEFAULT,
                title: M.util.get_string('gradesmodal', 'format_streamdeck'),
                body: '<div class="streamdeck-loader" style="text-align:center; padding:2rem;">' +
                    '<i class="fa fa-circle-o-notch fa-spin fa-3x fa-fw"></i>' +
                    '</div>' +
                    '<iframe src="' + gradesUrl + '&streamdeck_embedded=1" ' +
                    'class="streamdeck-grades-iframe" ' +
                    'style="width:100%; height:70vh; border:none; display:none;" ' +
                    'onload="this.style.display=\'block\'; this.previousElementSibling.style.display=\'none\';">' +
                    '</iframe>',
                large: true
            }).then((modal) => {
                modal.show();

                const modalElement = modal.getRoot()[0];
                if (modalElement) {
                    modalElement.style.zIndex = '1050';
                }

                const iframe = modalElement.querySelector('.streamdeck-grades-iframe');
                if (iframe) {
                    iframe.addEventListener('load', () => {
                        try {
                            const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
                            if (iframeDoc && iframeDoc.body) {
                                iframeDoc.body.classList.add('streamdeck-grades-embedded');
                            }
                        } catch (err) {
                            // Ignore cross-origin errors
                        }
                    });
                }

                modal.getRoot().on(ModalEvents.hidden, () => {
                    modal.destroy();
                });
                return modal;
            }).catch(function() {
                // Silent fail if modal creation fails.
            });
        });
    });
};

    /**
     * Register behaviour for inline assignment view iframes.
     */
    const registerAssignViewIframe = () => {
        $('body').on('click', '.streamdeck-assign-view-btn', function(e) {
            e.preventDefault();

            const $btn = $(this);
            const $preview = $btn.closest('.streamdeck-assign-preview');
            if (!$preview.length) {
                return;
            }

            const $wrapper = $preview.find('.streamdeck-assign-frame-wrapper').first();
            if (!$wrapper.length) {
                return;
            }

            const $iframe = $wrapper.find('iframe.streamdeck-assign-frame').first();
            if (!$iframe.length) {
                return;
            }

            const assignUrl = $btn.data('assignUrl');
            if (!assignUrl) {
                return;
            }

            if (!$iframe.attr('src')) {
                $wrapper.addClass('is-loading');

                $iframe
                    .on('load', function() {
                        $wrapper.removeClass('is-loading');

                    try {
                        const win = this.contentWindow;
                        const doc = win.document;

                        $(doc).find(
                            '#page-header, ' +
                            '#page-footer, ' +
                            'header[role="banner"], ' +
                            'nav.navbar, ' +
                            '.navbar, ' +
                            '.breadcrumb, ' +
                            '.secondary-navigation, ' +
                            '.page-context-header, ' +
                            '.drawer-toggle, ' +
                            '.drawer, ' +
                            '.activity-navigation'
                        ).hide();

                        $(doc.body).addClass('streamdeck-assign-embedded');

                        // Force all navigation inside the iframe to open in a new tab.
                        // This prevents the grader and other JS from loading inside the iframe.
                        var base = doc.createElement('base');
                        base.target = '_blank';
                        doc.head.appendChild(base);
                    } catch (err) {
                            // Silent failure.
                        }
                    })
                    .attr('src', assignUrl);
            }

            $wrapper.removeAttr('hidden').addClass('is-visible');
            $btn.addClass('is-active');
        });
    };

    return {
        init: init
    };
});