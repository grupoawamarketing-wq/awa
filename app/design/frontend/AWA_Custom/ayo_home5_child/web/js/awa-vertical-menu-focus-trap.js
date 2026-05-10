/**
 * AWA Vertical Menu — Focus Trap (WCAG 2.4.3 / FASE E)
 *
 * When the mobile nav drawer opens, traps keyboard focus inside it.
 * Pressing Escape closes the drawer via the existing Magento nav-toggle mechanism.
 *
 * Loaded via RequireJS: require(['awa-vertical-menu-focus-trap'], fn)
 */
define([], function () {
    'use strict';

    let FOCUSABLE = [
        'a[href]:not([tabindex="-1"])',
        'button:not([disabled]):not([tabindex="-1"])',
        '[role="button"]:not([disabled]):not([tabindex="-1"])',
        '[tabindex="0"]',
        'input:not([disabled]):not([tabindex="-1"])',
        'select:not([disabled]):not([tabindex="-1"])',
        'textarea:not([disabled]):not([tabindex="-1"])'
    ].join(', ');

    /**
     * Returns all keyboard-reachable elements inside a container.
     *
     * @param {HTMLElement} container
     * @returns {HTMLElement[]}
     */
    function getFocusable(container) {
        return Array.prototype.filter.call(
            container.querySelectorAll(FOCUSABLE),
            function (el) {
                return !el.closest('[hidden]') && !el.closest('[aria-hidden="true"]');
            }
        );
    }

    /** @type {Function|null} active keydown handler */
    let _activeHandler = null;

    /** @type {HTMLElement|null} element focused before drawer opened */
    let _previouslyFocused = null;

    /**
     * Activate focus trap inside nav container.
     *
     * @param {HTMLElement} nav
     */
    function activateTrap(nav) {
        _previouslyFocused = document.activeElement || null;

        let focusable = getFocusable(nav);
        if (focusable.length) {
            focusable[0].focus();
        }

        _activeHandler = function (event) {
            // Escape → close drawer
            if (event.key === 'Escape' || event.keyCode === 27) {
                event.preventDefault();
                triggerClose();
                return;
            }

            if (event.key !== 'Tab' && event.keyCode !== 9) {
                return;
            }

            let focusableNow = getFocusable(nav);
            if (!focusableNow.length) {
                event.preventDefault();
                return;
            }

            let first = focusableNow[0];
            let last  = focusableNow[focusableNow.length - 1];

            if (event.shiftKey) {
                // Shift+Tab wraps to last
                if (document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                }
            } else {
                // Tab wraps to first
                if (document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            }
        };

        document.addEventListener('keydown', _activeHandler);
    }

    /**
     * Deactivate focus trap and restore previous focus.
     */
    function deactivateTrap() {
        if (_activeHandler) {
            document.removeEventListener('keydown', _activeHandler);
            _activeHandler = null;
        }

        if (_previouslyFocused && typeof _previouslyFocused.focus === 'function') {
            try {
                _previouslyFocused.focus();
            } catch (e) { /* element may have been removed */ }
            _previouslyFocused = null;
        }
    }

    /**
     * Trigger Magento's existing nav-toggle close mechanism.
     */
    function triggerClose() {
        let toggle = document.querySelector('[data-awa-nav-toggle="true"]');
        if (toggle) {
            toggle.click();
        } else {
            // Fallback: remove nav-open class directly
            document.body.classList.remove('nav-open');
        }
    }

    /* ── Close button + overlay (injected once) ── */

    /** @type {HTMLButtonElement|null} */
    let _closeBtn = null;

    /** @type {HTMLDivElement|null} */
    let _overlay = null;

    /**
     * Lazily create and inject close button inside nav drawer
     * and backdrop overlay on <body>.
     *
     * @param {HTMLElement} nav
     */
    function ensureDrawerControls(nav) {
        if (!_closeBtn) {
            _closeBtn = document.createElement('button');
            _closeBtn.type = 'button';
            _closeBtn.className = 'awa-nav-close';
            _closeBtn.setAttribute('aria-label', 'Fechar menu');
            _closeBtn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><line x1="6" y1="6" x2="18" y2="18"/><line x1="18" y1="6" x2="6" y2="18"/></svg>';
            _closeBtn.addEventListener('click', triggerClose);

            // Insert as first child of nav-sections wrapper
            let sections = nav.closest('.sections.nav-sections') || nav;
            sections.insertBefore(_closeBtn, sections.firstChild);
        }

        if (!_overlay) {
            _overlay = document.createElement('div');
            _overlay.className = 'awa-nav-overlay';
            _overlay.setAttribute('aria-hidden', 'true');
            _overlay.addEventListener('click', triggerClose);
            document.body.appendChild(_overlay);
        }
    }

    /**
     * Show overlay backdrop.
     */
    function showOverlay() {
        if (_overlay) {
            _overlay.classList.add('is-visible');
        }
    }

    /**
     * Hide overlay backdrop.
     */
    function hideOverlay() {
        if (_overlay) {
            _overlay.classList.remove('is-visible');
        }
    }

    function isDesktopViewport() {
        return window.matchMedia && window.matchMedia('(min-width: 992px)').matches;
    }

    function removeDrawerControls() {
        if (_closeBtn && _closeBtn.parentNode) {
            _closeBtn.parentNode.removeChild(_closeBtn);
        }

        if (_overlay && _overlay.parentNode) {
            _overlay.parentNode.removeChild(_overlay);
        }

        _closeBtn = null;
        _overlay = null;
    }

    /**
     * Resolve the mobile drawer shell used by the vertical menu.
     * Prefers the explicit data attribute and supports legacy fallback IDs.
     *
     * @returns {HTMLElement|null}
     */
    function resolveDrawerShell() {
        return document.querySelector('[data-awa-nav-shell="true"]') ||
            document.getElementById('awa-category-navigation') ||
            document.querySelector('#awa-primary-navigation.section-items');
    }

    /**
     * Module entry point.
     * Observes body.nav-open via MutationObserver.
     */
    return function init() {
        let nav = resolveDrawerShell();
        if (!nav) {
            return;
        }

        if (!window.MutationObserver) {
            return; // Graceful degradation for old browsers
        }

        if (isDesktopViewport()) {
            removeDrawerControls();
            deactivateTrap();
            return;
        }

        ensureDrawerControls(nav);

        let wasOpen = false;

        let observer = new MutationObserver(function () {
            let isOpen = document.body.classList.contains('nav-open');

            if (isOpen === wasOpen) {
                return;
            }

            wasOpen = isOpen;

            if (isOpen) {
                showOverlay();
                activateTrap(nav);
            } else {
                hideOverlay();
                deactivateTrap();
            }
        });

        observer.observe(document.body, {
            attributes: true,
            attributeFilter: ['class']
        });

        // Sync initial state (in case drawer is already open on load)
        if (document.body.classList.contains('nav-open')) {
            wasOpen = true;
            showOverlay();
            activateTrap(nav);
        }
    };
});
