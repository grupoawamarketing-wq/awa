(function () {
    'use strict';

    if (window.__awaHeaderA11yPerformanceInit) {
        return;
    }
    window.__awaHeaderA11yPerformanceInit = true;

    var raf = window.requestAnimationFrame || function (cb) { return window.setTimeout(cb, 16); };
    var supportsPassive = false;

    function pushDataLayer(eventName, payload) {
        if (!window.dataLayer || !Array.isArray(window.dataLayer)) {
            return;
        }
        var eventPayload = payload || {};
        eventPayload.event = eventName;
        window.dataLayer.push(eventPayload);
    }

    try {
        var optionsProbe = Object.defineProperty({}, 'passive', {
            get: function () {
                supportsPassive = true;
                return true;
            }
        });
        window.addEventListener('testPassive', null, optionsProbe);
        window.removeEventListener('testPassive', null, optionsProbe);
    } catch (e) {}

    function addListener(target, eventName, handler, options) {
        if (!target || !target.addEventListener) {
            return;
        }
        if (supportsPassive && typeof options !== 'undefined') {
            target.addEventListener(eventName, handler, options);
            return;
        }
        target.addEventListener(eventName, handler, !!(options && options.capture));
    }

    function onReady(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
            return;
        }
        callback();
    }

    function setNavState() {
        var toggle = document.querySelector('[data-awa-nav-toggle="true"]');
        if (!toggle) {
            return;
        }
        var body = document.body;
        var expanded = body.classList.contains('nav-open') || body.classList.contains('nav-before-open');
        toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    }

    function getExperimentConfig() {
        var header = document.querySelector('[data-awa-component="site-header"]');
        if (!header) {
            return { enabled: false, rollout: 0, seed: 'home5_header_v1', bucket: 0, active: false, variant: 'A', variantCode: 'control' };
        }

        var enabled = header.getAttribute('data-awa-header-exp-enabled') === '1';
        var rollout = parseInt(header.getAttribute('data-awa-header-exp-rollout') || '0', 10);
        var seed = header.getAttribute('data-awa-header-exp-seed') || 'home5_header_v1';
        var bucket = parseInt(header.getAttribute('data-awa-header-exp-bucket') || '0', 10);
        var active = header.getAttribute('data-awa-header-exp-active') === '1';
        var variantCode = header.getAttribute('data-awa-header-exp-variant') || (active ? 'v2' : 'control');
        if (isNaN(rollout)) {
            rollout = 0;
        }
        if (isNaN(bucket)) {
            bucket = 0;
        }
        rollout = Math.max(0, Math.min(100, rollout));
        bucket = Math.max(0, Math.min(99, bucket));

        var variant = enabled && active ? 'B' : 'A';
        header.setAttribute('data-awa-header-exp-group', variant);
        if (variant === 'B') {
            header.classList.add('awa-header-exp-b');
        } else {
            header.classList.remove('awa-header-exp-b');
        }

        pushDataLayer('awa_header_experiment_exposure', {
            experiment_name: 'header_progressive',
            experiment_seed: seed,
            experiment_variant: variant,
            experiment_rollout: rollout,
            experiment_bucket: bucket,
            experiment_variant_code: variantCode
        });

        return {
            enabled: enabled,
            rollout: rollout,
            seed: seed,
            bucket: bucket,
            active: active,
            variant: variant,
            variantCode: variantCode
        };
    }

    function wireNavA11y(experiment) {
        var toggle = document.querySelector('[data-awa-nav-toggle="true"]');
        var navShell = document.getElementById('awa-primary-navigation');
        var useEnhancedDrawer = !!experiment && experiment.variant === 'B';
        var overlay = null;
        var lastFocusedElement = null;
        var focusableSelector = [
            'a[href]',
            'button:not([disabled])',
            'textarea:not([disabled])',
            'input:not([disabled]):not([type="hidden"])',
            'select:not([disabled])',
            '[tabindex]:not([tabindex="-1"])'
        ].join(',');

        function isDrawerOpen() {
            return useEnhancedDrawer && document.body.classList.contains('nav-before-open');
        }

        function getFocusableItems() {
            if (!navShell) {
                return [];
            }
            return Array.prototype.slice.call(navShell.querySelectorAll(focusableSelector)).filter(function (item) {
                return item.offsetParent !== null;
            });
        }

        function ensureOverlay() {
            if (overlay) {
                return overlay;
            }
            overlay = document.querySelector('.awa-mobile-drawer-overlay');
            if (overlay) {
                return overlay;
            }

            overlay = document.createElement('button');
            overlay.type = 'button';
            overlay.className = 'awa-mobile-drawer-overlay';
            overlay.setAttribute('aria-label', 'Fechar menu');
            overlay.setAttribute('aria-hidden', 'true');
            overlay.setAttribute('tabindex', '-1');
            document.body.appendChild(overlay);
            return overlay;
        }

        function syncDrawerState() {
            if (!useEnhancedDrawer || !navShell) {
                return;
            }

            var open = isDrawerOpen();
            var drawerOverlay = ensureOverlay();

            navShell.setAttribute('aria-hidden', open ? 'false' : 'true');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');

            if (open) {
                document.body.classList.add('awa-mobile-drawer-open');
                drawerOverlay.classList.add('is-active');
                drawerOverlay.setAttribute('aria-hidden', 'false');
                drawerOverlay.removeAttribute('tabindex');
                return;
            }

            document.body.classList.remove('awa-mobile-drawer-open');
            drawerOverlay.classList.remove('is-active');
            drawerOverlay.setAttribute('aria-hidden', 'true');
            drawerOverlay.setAttribute('tabindex', '-1');
        }

        function openDrawer() {
            if (!useEnhancedDrawer || !navShell) {
                return;
            }

            lastFocusedElement = document.activeElement;
            document.body.classList.add('nav-before-open');
            navShell.classList.add('is-awa-mobile-open');
            syncDrawerState();

            raf(function () {
                var focusables = getFocusableItems();
                if (focusables.length) {
                    focusables[0].focus();
                }
            });
        }

        function closeDrawer() {
            if (!useEnhancedDrawer || !navShell) {
                return;
            }

            document.body.classList.remove('nav-before-open');
            navShell.classList.remove('is-awa-mobile-open');
            syncDrawerState();

            if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
                lastFocusedElement.focus();
            } else {
                toggle.focus();
            }
            lastFocusedElement = null;
        }

        if (!toggle) {
            return;
        }

        if (useEnhancedDrawer && navShell) {
            navShell.setAttribute('role', 'dialog');
            navShell.setAttribute('aria-modal', 'true');
            navShell.setAttribute('aria-hidden', 'true');
            ensureOverlay();
        }

        setNavState();
        syncDrawerState();

        addListener(toggle, 'click', function (event) {
            if (useEnhancedDrawer && navShell) {
                event.preventDefault();
                if (isDrawerOpen()) {
                    closeDrawer();
                } else {
                    openDrawer();
                }
            }
            raf(setNavState);
            raf(syncDrawerState);

            pushDataLayer('awa_header_nav_toggle_click', {
                experiment_name: 'header_progressive',
                experiment_variant: experiment ? experiment.variant : 'A'
            });
        });

        addListener(document, 'keydown', function (event) {
            if (!useEnhancedDrawer || !navShell || !isDrawerOpen()) {
                return;
            }

            if (event.key === 'Escape') {
                closeDrawer();
                raf(setNavState);
                return;
            }

            if (event.key === 'Tab') {
                var focusables = getFocusableItems();
                if (!focusables.length) {
                    event.preventDefault();
                    return;
                }

                var first = focusables[0];
                var last = focusables[focusables.length - 1];
                var active = document.activeElement;

                if (event.shiftKey && active === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && active === last) {
                    event.preventDefault();
                    first.focus();
                }
            }
        }, { capture: true });

        addListener(document, 'click', function (event) {
            if (!useEnhancedDrawer || !navShell || !document.body.classList.contains('nav-before-open')) {
                return;
            }

            if (overlay && event.target === overlay) {
                closeDrawer();
                raf(setNavState);
                return;
            }

            if (toggle.contains(event.target) || navShell.contains(event.target)) {
                return;
            }
            closeDrawer();
            raf(setNavState);
        }, { capture: true });

        if (window.MutationObserver) {
            var bodyObserver = new MutationObserver(function () {
                setNavState();
                syncDrawerState();
            });
            bodyObserver.observe(document.body, { attributes: true, attributeFilter: ['class'] });
        }
    }

    function wireSearchA11y() {
        var root = document.querySelector('[data-awa-search-root="true"]');
        if (!root) {
            return;
        }

        var input = root.querySelector('[data-awa-search-input="true"]');
        var panel = root.querySelector('[data-awa-search-panel="true"]');
        var status = root.querySelector('[data-awa-search-status="true"]');

        if (!input || !panel) {
            return;
        }

        var debounceTimer;
        var busyTimer;

        function getSuggestionCount() {
            return panel.querySelectorAll('li, [role="option"], a').length;
        }

        function syncExpanded() {
            var hidden = panel.hasAttribute('hidden') || panel.getAttribute('aria-hidden') === 'true';
            var hasItems = getSuggestionCount() > 0;
            var expanded = !hidden && hasItems;
            input.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            panel.setAttribute('aria-hidden', expanded ? 'false' : 'true');
            if (!expanded && !panel.hasAttribute('hidden')) {
                panel.setAttribute('hidden', '');
            }
            if (status) {
                status.textContent = expanded ? String(getSuggestionCount()) + ' sugestões disponíveis' : '';
            }
            root.setAttribute('aria-busy', 'false');
            root.classList.remove('is-searching');
        }

        function markSearching() {
            root.setAttribute('aria-busy', 'true');
            root.classList.add('is-searching');
            if (busyTimer) {
                window.clearTimeout(busyTimer);
            }
            busyTimer = window.setTimeout(function () {
                syncExpanded();
            }, 900);
        }

        addListener(input, 'input', function () {
            markSearching();
            if (debounceTimer) {
                window.clearTimeout(debounceTimer);
            }
            debounceTimer = window.setTimeout(function () {
                raf(syncExpanded);
            }, 220);
        }, { passive: true });

        addListener(input, 'focus', function () {
            pushDataLayer('awa_header_search_focus', {
                experiment_name: 'header_progressive'
            });
            raf(syncExpanded);
        }, { passive: true });

        var form = root.querySelector('[data-awa-search-form="true"]');
        if (form) {
            addListener(form, 'submit', function () {
                pushDataLayer('awa_header_search_submit', {
                    experiment_name: 'header_progressive'
                });
            });
        }

        addListener(document, 'click', function (event) {
            if (!root.contains(event.target)) {
                input.setAttribute('aria-expanded', 'false');
                panel.setAttribute('aria-hidden', 'true');
            }
        }, { capture: true });

        addListener(document, 'keyup', function (event) {
            if (event.key === 'Escape') {
                input.setAttribute('aria-expanded', 'false');
                panel.setAttribute('aria-hidden', 'true');
                panel.setAttribute('hidden', '');
                root.setAttribute('aria-busy', 'false');
                root.classList.remove('is-searching');
            }
        }, { capture: true });

        if (window.MutationObserver) {
            var observer = new MutationObserver(function () {
                raf(syncExpanded);
            });
            observer.observe(panel, {
                attributes: true,
                attributeFilter: ['class', 'style', 'hidden', 'aria-hidden'],
                childList: true,
                subtree: true
            });
        }

        syncExpanded();
    }

    function wireDeferredBadges() {
        var badges = document.querySelector('[data-awa-deferred-badges="true"]');
        if (!badges) {
            return;
        }

        function reveal() {
            badges.setAttribute('aria-hidden', 'false');
            badges.classList.add('is-visible');
        }

        if (!('IntersectionObserver' in window)) {
            reveal();
            return;
        }

        var observer = new IntersectionObserver(function (entries, obs) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    reveal();
                    obs.disconnect();
                }
            });
        }, { rootMargin: '80px 0px' });

        observer.observe(badges);
    }

    onReady(function () {
        var experiment = getExperimentConfig();
        wireNavA11y(experiment);
        wireSearchA11y();
        wireDeferredBadges();

        addListener(document, 'click', function (event) {
            if (!event.target || !event.target.closest) {
                return;
            }
            var showcart = event.target.closest('.minicart-wrapper .showcart');
            if (!showcart) {
                return;
            }
            pushDataLayer('awa_header_minicart_click', {
                experiment_name: 'header_progressive',
                experiment_variant: experiment ? experiment.variant : 'A'
            });
        }, { capture: true });
    });
})();
