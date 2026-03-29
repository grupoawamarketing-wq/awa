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

    function pushHeaderTelemetry(eventName, experiment, payload) {
        var basePayload = {
            experiment_name: 'header_progressive',
            experiment_variant: experiment ? experiment.variant : 'A'
        };
        var extraPayload = payload || {};
        var key;

        for (key in extraPayload) {
            if (Object.prototype.hasOwnProperty.call(extraPayload, key)) {
                basePayload[key] = extraPayload[key];
            }
        }

        pushDataLayer(eventName, basePayload);
    }

    function normalizeText(value) {
        return String(value || '').replace(/\s+/g, ' ').trim();
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

    function ensureToggleRole(el) {
        if (!el) { return; }
        if (!el.getAttribute('role')) {
            el.setAttribute('role', 'button');
        }
        if (!el.getAttribute('tabindex')) {
            el.setAttribute('tabindex', '0');
        }
    }

    function setNavState() {
        var toggle = document.querySelector('[data-awa-nav-toggle="true"]');
        if (!toggle) {
            return;
        }
        ensureToggleRole(toggle);
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

        pushHeaderTelemetry('awa_header_experiment_exposure', { variant: variant }, {
            experiment_seed: seed,
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
        if (toggle) {
            ensureToggleRole(toggle);
        }
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

            pushHeaderTelemetry('awa_header_nav_toggle_click', experiment);
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

    function findSearchRoot() {
        return document.querySelector('[data-awa-search-root="true"]') ||
            document.querySelector('.block-search[data-awa-component="search-autocomplete"]') ||
            document.querySelector('.top-search .block-search') ||
            document.querySelector('.block-search');
    }

    function ensureSearchStatus(root, input) {
        if (!root) {
            return null;
        }

        var status = root.querySelector('[data-awa-search-status="true"]');
        var describedBy;

        if (!status) {
            status = document.createElement('span');
            status.className = 'awa-sr-only';
            status.setAttribute('data-awa-search-status', 'true');
            status.setAttribute('aria-live', 'polite');
            status.setAttribute('aria-atomic', 'true');
            status.id = 'awa-search-status';

            (root.querySelector('.block-content') || root).appendChild(status);
        } else if (!status.id) {
            status.id = 'awa-search-status';
        }

        if (input) {
            describedBy = normalizeText(input.getAttribute('aria-describedby'))
                .split(' ')
                .filter(Boolean);

            if (describedBy.indexOf(status.id) === -1) {
                describedBy.push(status.id);
                input.setAttribute('aria-describedby', describedBy.join(' '));
            }
        }

        return status;
    }

    function wireSearchA11y() {
        var root = findSearchRoot();
        if (!root) {
            return;
        }

        root.setAttribute('data-awa-search-root', 'true');

        var input = root.querySelector('[data-awa-search-input="true"], #search, input[name="q"]');
        var panel = root.querySelector('[data-awa-search-panel="true"], #search_autocomplete, .searchsuite-autocomplete, .mst-searchautocomplete__autocomplete');
        var status = ensureSearchStatus(root, input);

        if (!input || !panel) {
            return;
        }

        panel.setAttribute('data-awa-search-panel', 'true');
        if (!panel.id) {
            panel.id = 'search_autocomplete';
        }
        if (!input.getAttribute('aria-controls')) {
            input.setAttribute('aria-controls', panel.id);
        }
        if (!panel.getAttribute('role')) {
            panel.setAttribute('role', 'listbox');
        }
        if (!panel.getAttribute('aria-label')) {
            panel.setAttribute('aria-label', 'Sugestões de busca');
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
            var query = normalizeText(input.value || '');
            input.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            panel.setAttribute('aria-hidden', expanded ? 'false' : 'true');
            if (!expanded && !panel.hasAttribute('hidden')) {
                panel.setAttribute('hidden', '');
            }
            if (status) {
                if (expanded) {
                    status.textContent = String(getSuggestionCount()) + ' sugestões disponíveis';
                } else if (query.length >= 2) {
                    status.textContent = 'Nenhuma sugestão encontrada';
                } else {
                    status.textContent = '';
                }
            }
            root.setAttribute('aria-busy', 'false');
            root.classList.remove('is-searching');
        }

        function markSearching() {
            root.setAttribute('aria-busy', 'true');
            root.classList.add('is-searching');
            if (status) {
                status.textContent = 'Buscando sugestões...';
            }
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

    function wireHeaderClickTelemetry(experiment) {
        var root = document.querySelector('[data-awa-component="site-header"]');
        var stickyClass = 'awa-header-sticky';
        var stickyTrackedState = null;

        if (!root) {
            return;
        }

        addListener(document, 'click', function (event) {
            if (!event.target || !event.target.closest) {
                return;
            }

            var accountLink = event.target.closest('.top-account a, [data-awa-top-account="true"] a');
            if (accountLink) {
                pushHeaderTelemetry('awa_header_account_click', experiment, {
                    link_text: normalizeText(accountLink.textContent || accountLink.getAttribute('aria-label') || accountLink.getAttribute('title')),
                    link_href: accountLink.getAttribute('href') || ''
                });
                return;
            }

            var categoryLink = event.target.closest('.menu_left_home1 .navigation.verticalmenu a, .menu_left_home1 .title-category-dropdown');
            if (categoryLink) {
                pushHeaderTelemetry('awa_header_category_click', experiment, {
                    link_text: normalizeText(categoryLink.textContent || categoryLink.getAttribute('aria-label') || categoryLink.getAttribute('title')),
                    link_href: categoryLink.getAttribute('href') || ''
                });
            }
        }, { capture: true });

        function syncStickyTelemetry() {
            var isSticky = root.classList.contains(stickyClass) || document.body.classList.contains(stickyClass);

            if (stickyTrackedState === isSticky) {
                return;
            }

            stickyTrackedState = isSticky;
            pushHeaderTelemetry('awa_header_sticky_state', experiment, {
                sticky_active: isSticky ? 1 : 0
            });
        }

        syncStickyTelemetry();
        addListener(window, 'scroll', syncStickyTelemetry, { passive: true });
        addListener(window, 'resize', syncStickyTelemetry, { passive: true });

        if (window.MutationObserver) {
            new MutationObserver(syncStickyTelemetry).observe(root, {
                attributes: true,
                attributeFilter: ['class']
            });
        }
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

    function setImportantStyle(element, property, value) {
        if (!element) {
            return;
        }

        element.style.setProperty(property, value, 'important');
    }

    function clearStyleProperties(element, properties) {
        if (!element || !Array.isArray(properties)) {
            return;
        }

        properties.forEach(function (property) {
            element.style.removeProperty(property);
        });
    }

    function isHomeHeaderPage() {
        var body = document.body;

        return !!body && (
            body.classList.contains('cms-index-index')
            || body.classList.contains('cms-home')
            || body.classList.contains('cms-homepage_ayo_home5')
        );
    }

    function isMobileHeaderViewport() {
        return !!window.matchMedia && window.matchMedia('(max-width: 991px)').matches;
    }

    function isNavDrawerOpen() {
        return document.documentElement.classList.contains('nav-open')
            || document.body.classList.contains('nav-open')
            || document.body.classList.contains('nav-before-open');
    }

    function collapseHomeSearchLayout() {
        var topSearch = document.querySelector('.header .top-search');
        var nestedCart = topSearch
            ? topSearch.querySelector(':scope > .mini-cart-wrapper, :scope > .shadowcart')
            : null;
        var blockSearch = topSearch
            ? topSearch.querySelector(':scope > .block-search')
            : null;

        if (!topSearch || !isHomeHeaderPage() || !isMobileHeaderViewport()) {
            return;
        }

        setImportantStyle(topSearch, 'display', 'block');
        setImportantStyle(topSearch, 'grid-template-columns', 'minmax(0, 1fr)');
        setImportantStyle(topSearch, 'grid-template-areas', '"search"');
        setImportantStyle(topSearch, 'grid-template-rows', 'auto');
        setImportantStyle(topSearch, 'gap', '0');
        setImportantStyle(topSearch, 'min-height', '0');

        if (blockSearch) {
            setImportantStyle(blockSearch, 'grid-area', 'search');
            setImportantStyle(blockSearch, 'grid-column', '1');
            setImportantStyle(blockSearch, 'width', '100%');
            setImportantStyle(blockSearch, 'max-width', '100%');
            setImportantStyle(blockSearch, 'min-width', '0');
        }

        if (nestedCart) {
            setImportantStyle(nestedCart, 'display', 'none');
            setImportantStyle(nestedCart, 'width', '0');
            setImportantStyle(nestedCart, 'min-width', '0');
            setImportantStyle(nestedCart, 'max-width', '0');
            setImportantStyle(nestedCart, 'height', '0');
            setImportantStyle(nestedCart, 'min-height', '0');
            setImportantStyle(nestedCart, 'margin', '0');
            setImportantStyle(nestedCart, 'padding', '0');
            setImportantStyle(nestedCart, 'overflow', 'hidden');
            setImportantStyle(nestedCart, 'visibility', 'hidden');
            setImportantStyle(nestedCart, 'opacity', '0');
            setImportantStyle(nestedCart, 'pointer-events', 'none');
        }
    }

    function resetHomeHeaderCollapseGuard() {
        var topSearch = document.querySelector('.header .top-search');
        var nestedCart = topSearch
            ? topSearch.querySelector(':scope > .mini-cart-wrapper, :scope > .shadowcart')
            : null;
        var blockSearch = topSearch
            ? topSearch.querySelector(':scope > .block-search')
            : null;
        var nav = document.querySelector('.header-control.header-nav-global.cms_home_1');
        var container = nav ? nav.querySelector(':scope > .container') : null;
        var row = container ? container.querySelector(':scope > .row') : null;
        var menu = nav ? nav.querySelector('.menu_left_home1') : null;
        var dropdown = menu ? menu.querySelector('.list-category-dropdown') : null;

        clearStyleProperties(topSearch, [
            'display',
            'grid-template-columns',
            'grid-template-areas',
            'grid-template-rows',
            'gap',
            'min-height'
        ]);

        clearStyleProperties(blockSearch, [
            'grid-area',
            'grid-column',
            'width',
            'max-width',
            'min-width'
        ]);

        clearStyleProperties(nestedCart, [
            'display',
            'width',
            'min-width',
            'max-width',
            'height',
            'min-height',
            'margin',
            'padding',
            'overflow',
            'visibility',
            'opacity',
            'pointer-events'
        ]);

        [nav, container, row].forEach(function (element) {
            clearStyleProperties(element, [
                'height',
                'min-height',
                'margin-top',
                'margin-bottom',
                'padding-top',
                'padding-bottom',
                'border',
                'overflow'
            ]);
        });

        clearStyleProperties(menu, [
            'display',
            'height',
            'min-height',
            'margin',
            'padding',
            'overflow',
            'visibility',
            'opacity',
            'pointer-events',
            'max-height'
        ]);

        clearStyleProperties(dropdown, [
            'max-height',
            'overflow'
        ]);
    }

    function guardHomeMobileHeaderCollapse() {
        var nav = document.querySelector('.header-control.header-nav-global.cms_home_1');
        var container = nav ? nav.querySelector(':scope > .container') : null;
        var row = container ? container.querySelector(':scope > .row') : null;
        var menu = nav ? nav.querySelector('.menu_left_home1') : null;
        var dropdown = menu ? menu.querySelector('.list-category-dropdown') : null;

        if (!isHomeHeaderPage()) {
            return;
        }

        if (!isMobileHeaderViewport()) {
            resetHomeHeaderCollapseGuard();
            return;
        }

        collapseHomeSearchLayout();

        if (!nav || !container || !row || !menu) {
            return;
        }

        if (isNavDrawerOpen()) {
            setImportantStyle(menu, 'display', 'block');
            setImportantStyle(menu, 'height', 'auto');
            setImportantStyle(menu, 'min-height', '0');
            setImportantStyle(menu, 'overflow', 'visible');
            setImportantStyle(menu, 'visibility', 'visible');
            setImportantStyle(menu, 'opacity', '1');
            setImportantStyle(menu, 'pointer-events', 'auto');

            if (dropdown) {
                setImportantStyle(dropdown, 'max-height', 'none');
                setImportantStyle(dropdown, 'overflow', 'visible');
            }

            return;
        }

        [nav, container, row].forEach(function (element) {
            setImportantStyle(element, 'height', '0');
            setImportantStyle(element, 'min-height', '0');
            setImportantStyle(element, 'margin-top', '0');
            setImportantStyle(element, 'margin-bottom', '0');
            setImportantStyle(element, 'padding-top', '0');
            setImportantStyle(element, 'padding-bottom', '0');
            setImportantStyle(element, 'border', '0');
            setImportantStyle(element, 'overflow', 'hidden');
        });

        setImportantStyle(menu, 'display', 'none');
        setImportantStyle(menu, 'height', '0');
        setImportantStyle(menu, 'min-height', '0');
        setImportantStyle(menu, 'margin', '0');
        setImportantStyle(menu, 'padding', '0');
        setImportantStyle(menu, 'overflow', 'hidden');
        setImportantStyle(menu, 'visibility', 'hidden');
        setImportantStyle(menu, 'opacity', '0');
        setImportantStyle(menu, 'pointer-events', 'none');

        if (dropdown) {
            setImportantStyle(dropdown, 'max-height', '0');
            setImportantStyle(dropdown, 'overflow', 'hidden');
        }
    }

    onReady(function () {
        var experiment = getExperimentConfig();
        wireNavA11y(experiment);
        wireSearchA11y();
        wireDeferredBadges();
        wireHeaderClickTelemetry(experiment);
        guardHomeMobileHeaderCollapse();

        addListener(window, 'resize', function () {
            raf(guardHomeMobileHeaderCollapse);
        }, { passive: true });

        addListener(document, 'click', function () {
            raf(guardHomeMobileHeaderCollapse);
        }, { capture: true });

        if (window.MutationObserver) {
            new MutationObserver(function () {
                raf(guardHomeMobileHeaderCollapse);
            }).observe(document.documentElement, {
                attributes: true,
                attributeFilter: ['class']
            });

            new MutationObserver(function () {
                raf(guardHomeMobileHeaderCollapse);
            }).observe(document.body, {
                attributes: true,
                attributeFilter: ['class']
            });
        }

        addListener(document, 'click', function (event) {
            if (!event.target || !event.target.closest) {
                return;
            }
            var showcart = event.target.closest('.minicart-wrapper .showcart');
            if (!showcart) {
                return;
            }
            pushHeaderTelemetry('awa_header_minicart_click', experiment);
        }, { capture: true });
    });
})();
