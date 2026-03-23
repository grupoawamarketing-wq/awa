(function () {
    'use strict';

    if (window.__awaHeaderA11yPerformanceInit) {
        return;
    }
    window.__awaHeaderA11yPerformanceInit = true;

    var raf = window.requestAnimationFrame || function (cb) { return window.setTimeout(cb, 16); };
    var supportsPassive = false;

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
            return { enabled: false, rollout: 0, seed: 'home5_header_v1', variant: 'A' };
        }

        var enabled = header.getAttribute('data-awa-header-exp-enabled') === '1';
        var rollout = parseInt(header.getAttribute('data-awa-header-exp-rollout') || '0', 10);
        var seed = header.getAttribute('data-awa-header-exp-seed') || 'home5_header_v1';
        if (isNaN(rollout)) {
            rollout = 0;
        }
        rollout = Math.max(0, Math.min(100, rollout));
        var storageKey = 'awa_header_exp_' + seed;
        var bucket = null;

        try {
            bucket = window.localStorage.getItem(storageKey);
            if (bucket === null) {
                bucket = String(Math.floor(Math.random() * 100));
                window.localStorage.setItem(storageKey, bucket);
            }
        } catch (e) {
            bucket = String(Math.floor(Math.random() * 100));
        }

        var variant = enabled && parseInt(bucket, 10) < rollout ? 'B' : 'A';
        header.setAttribute('data-awa-header-exp-variant', variant);
        if (variant === 'B') {
            header.classList.add('awa-header-exp-b');
        } else {
            header.classList.remove('awa-header-exp-b');
        }

        if (window.dataLayer && Array.isArray(window.dataLayer)) {
            window.dataLayer.push({
                event: 'awa_header_experiment_exposure',
                experiment_name: 'header_progressive',
                experiment_variant: variant,
                experiment_rollout: rollout
            });
        }

        return { enabled: enabled, rollout: rollout, seed: seed, variant: variant };
    }

    function wireNavA11y(experiment) {
        var toggle = document.querySelector('[data-awa-nav-toggle="true"]');
        var navShell = document.getElementById('awa-primary-navigation');
        var useEnhancedDrawer = !!experiment && experiment.variant === 'B';
        if (!toggle) {
            return;
        }

        setNavState();

        addListener(toggle, 'click', function () {
            if (useEnhancedDrawer && !document.body.classList.contains('nav-open') && !document.body.classList.contains('nav-before-open')) {
                document.body.classList.toggle('nav-before-open');
                if (navShell && navShell.classList) {
                    navShell.classList.toggle('is-awa-mobile-open');
                }
            }
            raf(setNavState);
        });

        addListener(document, 'keyup', function (event) {
            if (event.key === 'Escape') {
                if (useEnhancedDrawer && document.body.classList.contains('nav-before-open')) {
                    document.body.classList.remove('nav-before-open');
                }
                if (useEnhancedDrawer && navShell && navShell.classList) {
                    navShell.classList.remove('is-awa-mobile-open');
                }
                raf(setNavState);
            }
        }, { capture: true });

        addListener(document, 'click', function (event) {
            if (!useEnhancedDrawer || !navShell || !document.body.classList.contains('nav-before-open')) {
                return;
            }
            if (toggle.contains(event.target) || navShell.contains(event.target)) {
                return;
            }
            document.body.classList.remove('nav-before-open');
            navShell.classList.remove('is-awa-mobile-open');
            raf(setNavState);
        }, { capture: true });

        if (window.MutationObserver) {
            var bodyObserver = new MutationObserver(function () {
                setNavState();
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
            raf(syncExpanded);
        }, { passive: true });

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
    });
})();
