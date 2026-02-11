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
                modal.show();
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
    var init = function() {
        // Hide SCORM chrome in episode cards
        hideScormChromeInEpisodeCard();

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

                // After modal is shown, initialize pill switching.
                modal.getRoot().on(ModalEvents.shown, function() {
                    try {
                        initPillSwitching(modal.getRoot());
                    } catch (err) {
                        // Silent fail; modal still usable.
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