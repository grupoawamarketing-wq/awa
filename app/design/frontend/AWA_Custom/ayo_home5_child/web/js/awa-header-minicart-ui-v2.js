/**
 * AWA Motos — Header / busca / minicart UI (v2 performance-safe)
 * Evita MutationObserver em attributes/style (loop com setProperty).
 */
(function () {
    'use strict';

    if (window.__awaRound2HeaderMinicartUiInit) {
        return;
    }
    window.__awaRound2HeaderMinicartUiInit = true;

    var HEADER_ROW_SEL = '.header .header_main .awa-main-header__inner[data-awa-header-row], .header .header_main .wp-header[data-awa-header-row], .header .header-main .awa-main-header__inner[data-awa-header-row], .header .header-main .wp-header[data-awa-header-row]';
    var layoutFingerprint = '';
    var searchOptionSeq = 0;
    var searchCompatLoading = false;
    var searchCompatReady = false;
    var resizeScheduled = false;
    var scrollScheduled = false;
    var searchSyncScheduled = false;
    var layoutSyncScheduled = false;
    var searchSyncRunning = false;
    var layoutSyncRunning = false;
    var stickyScrollScheduled = false;

    function bodyEl() {
        return document.body;
    }

    function stickyWrapper() {
        return document.querySelector('.header-wrapper-sticky');
    }

    function searchBlock() {
        return document.querySelector('.header .top-search .block-search, .awa-site-header .block-search');
    }

    function hasModernHeader() {
        return !!document.querySelector('.awa-site-header');
    }

    function isVisible(el) {
        return !!el && !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
    }

    function isHomePage() {
        var b = bodyEl();
        return !!b && (b.classList.contains('cms-index-index') || b.classList.contains('cms-home') || b.classList.contains('cms-homepage_ayo_home5'));
    }

    function isCategoryPage() {
        var b = bodyEl();
        return !!b && (b.classList.contains('catalog-category-view') || b.classList.contains('catalogsearch-result-index'));
    }

    function setStyleImportant(el, prop, value) {
        if (!el || el.getAttribute('data-awa-js-layout') === 'css') {
            return;
        }
        if (el.style.getPropertyValue(prop) === value && el.style.getPropertyPriority(prop) === 'important') {
            return;
        }
        el.style.setProperty(prop, value, 'important');
    }

    function schedule(fn, flagName, holder) {
        if (holder[flagName]) {
            return;
        }
        holder[flagName] = true;
        window.requestAnimationFrame(function () {
            holder[flagName] = false;
            fn();
        });
    }

    var syncFlags = { search: false, layout: false, scroll: false, resize: false };

    function scheduleSearchSync() {
        schedule(function searchSyncJob() {
            if (searchSyncRunning) {
                return;
            }
            searchSyncRunning = true;
            try {
                ensureSearchCompat();
                syncSearchPanelState();
            } finally {
                searchSyncRunning = false;
            }
        }, 'search', syncFlags);
    }

    function scheduleLayoutSync() {
        schedule(function layoutSyncJob() {
            if (layoutSyncRunning) {
                return;
            }
            layoutSyncRunning = true;
            try {
                syncLayout();
            } finally {
                layoutSyncRunning = false;
            }
        }, 'layout', syncFlags);
    }

    function getSearchContext() {
        var block = searchBlock();
        var form = block ? block.querySelector('#search_mini_form, form.form.minisearch') : null;
        if (!block || !form) {
            return null;
        }
        return {
            scope: block,
            form: form,
            input: form.querySelector('#search, #search-input-autocomplate, input[name="q"]'),
            actions: form.querySelector('.actions'),
            button: form.querySelector('.action.search, button[type="submit"]'),
            panel: block.querySelector('#search_autocomplete'),
            resultsRoot: block.querySelector('.mst-searchautocomplete__autocomplete, .searchsuite-autocomplete')
        };
    }

    function ensureSearchCompat() {
        var req = typeof window.require === 'function' ? window.require : (typeof window.requirejs === 'function' ? window.requirejs : null);
        var ctx = getSearchContext();
        var root = ctx ? ctx.scope : document;
        var hasAutocomplete = document.getElementById('searchAutocompletePlaceholder') ||
            (root && root.querySelector('.mst-searchautocomplete__autocomplete'));

        if (hasAutocomplete) {
            searchCompatReady = true;
            return;
        }
        if (!req || !ctx || !ctx.form || searchCompatReady || searchCompatLoading) {
            return;
        }
        searchCompatLoading = true;
        req(['js/awa-search-autocomplete-compat'], function (initCompat) {
            var fresh = getSearchContext();
            searchCompatLoading = false;
            searchCompatReady = true;
            if (fresh && fresh.form && typeof initCompat === 'function') {
                initCompat({}, fresh.form);
                fresh.form.setAttribute('data-awa-search-compat-bound', 'true');
                scheduleSearchSync();
            }
        }, function () {
            searchCompatLoading = false;
        });
    }

    function getSearchOptions(ctx) {
        var root = ctx ? (ctx.resultsRoot || ctx.panel) : null;
        var nodes;
        var i;
        var out = [];
        var item;
        var action;
        var dup;

        if (!root) {
            return out;
        }
        nodes = root.querySelectorAll('.suggest ul li, .product ul li, [role="option"], li');
        for (i = 0; i < nodes.length; i += 1) {
            item = nodes[i];
            if (!item) {
                continue;
            }
            if (!item.matches || !item.matches('[role="option"], li')) {
                item = item.closest('[role="option"], li');
            }
            if (!item || !isVisible(item)) {
                continue;
            }
            dup = false;
            out.forEach(function (row) {
                if (row.option === item) {
                    dup = true;
                }
            });
            if (dup) {
                continue;
            }
            item.setAttribute('role', 'option');
            if (!item.id) {
                searchOptionSeq += 1;
                item.id = 'awa-search-option-' + searchOptionSeq;
            }
            action = item.matches && item.matches('a[href], button, [tabindex]') ?
                item : item.querySelector('a[href], button, [tabindex]');
            out.push({ option: item, action: action || item });
        }
        return out;
    }

    function clearSearchActive(ctx, options) {
        var i;
        if (!ctx) {
            return;
        }
        (options || []).forEach(function (row) {
            row.option.classList.remove('selected', 'awa-option-active');
            row.option.setAttribute('aria-selected', 'false');
        });
        if (ctx.input) {
            ctx.input.removeAttribute('aria-activedescendant');
        }
        ctx.form.setAttribute('data-awa-active-index', '-1');
    }

    function setSearchActive(ctx, options, index, announce) {
        var row;
        var region;
        var label;
        var i;

        if (!ctx || !options.length) {
            clearSearchActive(ctx, options);
            return;
        }
        if (index >= options.length) {
            index = 0;
        }
        if (index < 0) {
            index = options.length - 1;
        }
        clearSearchActive(ctx, options);
        row = options[index];
        row.option.classList.add('selected', 'awa-option-active');
        row.option.setAttribute('aria-selected', 'true');
        ctx.form.setAttribute('data-awa-active-index', String(index));
        if (ctx.input) {
            ctx.input.setAttribute('aria-activedescendant', row.option.id);
        }
        if (row.option.scrollIntoView) {
            row.option.scrollIntoView({ block: 'nearest', inline: 'nearest' });
        }
        if (announce) {
            region = ensureSearchLiveRegion(ctx);
            if (region) {
                label = (row.option.textContent || '').replace(/\s+/g, ' ').trim();
                region.textContent = 'Sugestão ' + (index + 1) + ' de ' + options.length + ': ' + label;
            }
        }
    }

    function ensureSearchLiveRegion(ctx) {
        var region;
        var ids;
        if (!ctx || !ctx.scope) {
            return null;
        }
        region = ctx.scope.querySelector('#awa-search-live-region');
        if (!region) {
            region = document.createElement('div');
            region.id = 'awa-search-live-region';
            region.className = 'awa-sr-only';
            region.setAttribute('aria-live', 'polite');
            region.setAttribute('aria-atomic', 'true');
            region.setAttribute('data-awa-search-live', 'true');
            region.style.cssText = 'position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0 0 0 0);clip-path:inset(50%);white-space:nowrap;';
            ctx.scope.appendChild(region);
        }
        if (ctx.input) {
            ids = (ctx.input.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
            if (ids.indexOf(region.id) === -1) {
                ids.push(region.id);
                ctx.input.setAttribute('aria-describedby', ids.join(' '));
            }
        }
        return region;
    }

    function syncSearchPanelState() {
        var ctx = getSearchContext();
        var panelOpen;
        var options;
        var hasResults;
        var text;
        var activeIndex;
        var region;
        var query;

        if (!ctx) {
            return;
        }

        ctx.form.classList.add('is-ready');
        ensureSearchLiveRegion(ctx);
        query = ctx.input ? (ctx.input.value || '') : '';
        if (query !== (ctx.form.getAttribute('data-awa-last-query') || '')) {
            ctx.form.setAttribute('data-awa-last-query', query);
            ctx.form.removeAttribute('data-awa-panel-closed');
            if (ctx.panel) {
                ctx.panel.style.removeProperty('display');
            }
        }

        panelOpen = !!(ctx.panel && isVisible(ctx.panel) &&
            window.getComputedStyle(ctx.panel).display !== 'none');
        options = getSearchOptions(ctx);

        if (ctx.resultsRoot && isVisible(ctx.resultsRoot)) {
            hasResults = options.length > 0 || ctx.resultsRoot.querySelectorAll('li').length > 0;
            text = (ctx.resultsRoot.textContent || '').trim();
        } else if (ctx.panel) {
            text = (ctx.panel.textContent || '').trim();
            hasResults = options.length > 0 || text !== '' || ctx.panel.children.length > 0;
        } else {
            hasResults = false;
            text = '';
        }

        if (ctx.form.getAttribute('data-awa-panel-closed') === 'true') {
            panelOpen = false;
            if (ctx.panel) {
                setStyleImportant(ctx.panel, 'display', 'none');
            }
        }

        ctx.form.classList.toggle('is-open', panelOpen);
        ctx.form.classList.toggle('has-results', hasResults);
        ctx.form.classList.toggle('is-empty', !hasResults);
        if (ctx.input) {
            ctx.input.setAttribute('aria-expanded', panelOpen ? 'true' : 'false');
            ctx.input.setAttribute('aria-haspopup', 'listbox');
        }
        if (ctx.panel) {
            ctx.panel.setAttribute('aria-hidden', panelOpen ? 'false' : 'true');
            ctx.panel.classList.toggle('is-open', panelOpen);
            ctx.panel.classList.toggle('has-results', hasResults);
            if (panelOpen) {
                ctx.panel.style.removeProperty('display');
            }
        }

        activeIndex = parseInt(ctx.form.getAttribute('data-awa-active-index') || '-1', 10);
        if (panelOpen && hasResults) {
            if (isNaN(activeIndex) || activeIndex < 0 || activeIndex >= options.length) {
                clearSearchActive(ctx, options);
            }
        } else {
            clearSearchActive(ctx, options);
        }

        region = ensureSearchLiveRegion(ctx);
        if (region) {
            if (panelOpen && hasResults) {
                region.textContent = options.length + ' sugestões disponíveis. Use seta para baixo e cima para navegar.';
            } else if (panelOpen && !hasResults && query.length >= 2) {
                region.textContent = 'Nenhuma sugestão encontrada.';
            } else {
                region.textContent = '';
            }
        }
    }

    function fixSearchFormAction() {
        var ctx = getSearchContext();
        var action;
        var url;
        var fixed;
        if (!ctx || !ctx.form || typeof window.URL !== 'function') {
            return;
        }
        action = ctx.form.getAttribute('action') || ctx.form.action || '';
        try {
            url = new window.URL(action, window.location.origin);
        } catch (e) {
            return;
        }
        if (/\/catalogsearch\/result\/?/i.test(url.pathname) && url.origin !== window.location.origin) {
            fixed = window.location.origin + url.pathname;
            if (ctx.form.getAttribute('action') !== fixed) {
                ctx.form.setAttribute('action', fixed);
            }
        }
    }

    function syncStickyCondensed() {
        var wrap = stickyWrapper();
        var body = bodyEl();
        var condensed;
        if (!body || !wrap || (!wrap.classList.contains('enabled-header-sticky') && !wrap.classList.contains('enable-sticky'))) {
            return;
        }
        condensed = window.scrollY >= 48;
        wrap.classList.toggle('awa-header-condensed', condensed);
        body.classList.toggle('awa-header-condensed', condensed);
    }

    function syncPlpToolbarOffset() {
        var html = document.documentElement;
        var body = bodyEl();
        var toolbar;
        var mobile;
        var selectors;
        var nodes;
        var maxBottom;
        var offset;
        var i;
        var j;
        var el;
        var style;
        var rect;

        if (!html || !body) {
            return;
        }
        mobile = window.matchMedia && window.matchMedia('(max-width: 767px)').matches;
        toolbar = document.querySelector('.shop-tab-select .toolbar.toolbar-products');
        if (!body || !isCategoryPage() || !mobile || !toolbar) {
            html.style.removeProperty('--awa-plp-toolbar-top-offset');
            return;
        }
        selectors = [
            '.header-wrapper-sticky',
            '.page-wrapper .header-wrapper-sticky',
            '.page-wrapper .top-header',
            '.page-wrapper .header-control',
            '.page-wrapper .header .header_main',
            '.page-wrapper .header .header-main'
        ];
        maxBottom = 0;
        selectors.forEach(function (sel) {
            nodes = document.querySelectorAll(sel);
            for (i = 0; i < nodes.length; i += 1) {
                el = nodes[i];
                if (!isVisible(el)) {
                    continue;
                }
                style = window.getComputedStyle ? window.getComputedStyle(el) : null;
                if (!style || (style.position !== 'fixed' && style.position !== 'sticky')) {
                    continue;
                }
                rect = el.getBoundingClientRect();
                if (!rect || rect.height < 20 || rect.bottom <= 0) {
                    continue;
                }
                if (style.position === 'sticky' && rect.top > 2) {
                    continue;
                }
                if (rect.bottom > maxBottom) {
                    maxBottom = rect.bottom;
                }
            }
        });
        offset = Math.round(Math.min(220, Math.max(6, maxBottom + 8)));
        html.style.setProperty('--awa-plp-toolbar-top-offset', offset + 'px');
    }

    function classifyTopLinks() {
        var links = document.querySelectorAll('.top-account ul.header.links > li > a');
        var i;
        var anchor;
        var li;
        var href;
        var text;
        links.forEach(function (anchorEl) {
            anchor = anchorEl;
            li = anchor ? anchor.closest('li') : null;
            if (!anchor || !li) {
                return;
            }
            href = (anchor.getAttribute('href') || '').toLowerCase();
            text = (anchor.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
            li.classList.add('awa-top-link-item');
            anchor.classList.add('awa-top-link-anchor');
            li.classList.remove('awa-top-link-item--compare', 'awa-top-link-item--b2b-register', 'awa-top-link-item--login', 'awa-top-link-item--logout', 'awa-top-link-item--account');
            if (li.classList.contains('compare') || /comparar/.test(text) || /product_compare/.test(href)) {
                li.classList.add('awa-top-link-item--compare');
            } else if (/cadastro b2b|cadastre-se|cadastrar-se|cadastro/.test(text) || /\/b2b\/register|\/customer\/account\/create|\/register/.test(href)) {
                li.classList.add('awa-top-link-item--b2b-register');
            } else if (/entrar|acessar|login/.test(text) || /\/login/.test(href)) {
                li.classList.add('awa-top-link-item--login');
            } else if (/sair|logout/.test(text) || /\/logout/.test(href)) {
                li.classList.add('awa-top-link-item--logout');
            } else if (/minha conta/.test(text) || /customer\/account/.test(href)) {
                li.classList.add('awa-top-link-item--account');
            }
            if (!anchor.getAttribute('title')) {
                anchor.setAttribute('title', (anchor.textContent || '').replace(/\s+/g, ' ').trim());
            }
        });
    }

    function syncTopLinkCounters() {
        document.querySelectorAll('.top-account ul.header.links > li').forEach(function (li) {
            var counter = li.querySelector('.counter.qty');
            var raw;
            var qty;
            var hasValue;
            if (!counter) {
                li.classList.remove('awa-top-link-counter-zero', 'awa-top-link-counter-has-value');
                return;
            }
            raw = (counter.textContent || '').replace(/[^\d]/g, '');
            qty = raw ? Number(raw) : 0;
            hasValue = Number.isFinite(qty) && qty > 0;
            li.classList.toggle('awa-top-link-counter-has-value', hasValue);
            li.classList.toggle('awa-top-link-counter-zero', !hasValue);
            counter.setAttribute('aria-hidden', hasValue ? 'false' : 'true');
        });
    }

    function syncMinicartA11y() {
        var ctx = getSearchContext();
        var btn = ctx ? ctx.button : null;
        var input = ctx ? ctx.input : null;
        var panel = ctx ? ctx.panel : null;
        var showcart = document.querySelector('.header .top-search .minicart-wrapper .action.showcart, .awa-site-header .minicart-wrapper .action.showcart');
        var wrap = document.querySelector('.header .top-search .minicart-wrapper, .awa-site-header .minicart-wrapper');
        var restricted = !!(bodyEl() && bodyEl().classList.contains('b2b-restricted-mode'));
        var restrictedLabel = 'Abrir carrinho - resumo comercial protegido';
        var defaultLabel = 'Abrir carrinho';

        if (btn && !btn.getAttribute('aria-label')) {
            btn.setAttribute('aria-label', btn.getAttribute('title') || 'Buscar');
        }
        if (btn && !btn.getAttribute('title')) {
            btn.setAttribute('title', btn.getAttribute('aria-label') || 'Buscar');
        }
        if (input && !input.getAttribute('aria-label') && !input.getAttribute('aria-labelledby')) {
            input.setAttribute('aria-label', 'Buscar produtos');
        }
        if (input && panel && !input.getAttribute('aria-controls')) {
            input.setAttribute('aria-controls', panel.id || 'search_autocomplete');
        }
        if (wrap) {
            wrap.classList.toggle('awa-b2b-minicart-restricted', restricted);
        }
        if (showcart) {
            showcart.setAttribute('title', restricted ? restrictedLabel : defaultLabel);
            showcart.setAttribute('aria-label', restricted ? restrictedLabel : defaultLabel);
            showcart.setAttribute('data-b2b-cart-state', restricted ? 'restricted' : 'default');
        }
    }

    function buildLayoutFingerprint() {
        var ctx = getSearchContext();
        var mobile = window.matchMedia && window.matchMedia('(max-width: 767px)').matches;
        var desktop = window.matchMedia && window.matchMedia('(min-width: 992px)').matches;
        var body = bodyEl();
        return [
            ctx && ctx.form ? 1 : 0,
            ctx && ctx.input ? 1 : 0,
            ctx && ctx.actions ? 1 : 0,
            ctx && ctx.button ? 1 : 0,
            mobile ? 1 : 0,
            desktop ? 1 : 0,
            isHomePage() ? 1 : 0,
            isCategoryPage() ? 1 : 0,
            body && body.classList.contains('awa-plp-filters-collapsed') ? 1 : 0,
            document.querySelectorAll(HEADER_ROW_SEL).length,
            document.querySelectorAll('.top-account ul.header.links > a').length,
            hasModernHeader() ? 1 : 0
        ].join('|');
    }

    function applyLegacyMobileGrid() {
        /* Layout mobile moderno vem do CSS (awa-ui-ux-pro-max-header); não sobrescrever com JS. */
        if (hasModernHeader()) {
            return;
        }
        var mobile = window.matchMedia && window.matchMedia('(max-width: 767px)').matches;
        var rows = document.querySelectorAll(HEADER_ROW_SEL);
        var t;
        var row;
        var brandCol;
        var searchCol;
        var block;
        var cartWrap;
        var minicart;
        var showcart;
        var logo;
        var logoLink;
        var logoImg;

        if (isHomePage() || !mobile || !rows.length) {
            return;
        }

        for (t = 0; t < rows.length; t += 1) {
            row = rows[t];
            row.setAttribute('data-awa-js-layout', 'js');
            brandCol = row.querySelector(':scope > [class*="col-"]:first-child');
            searchCol = row.querySelector(':scope > .top-search');
            block = searchCol ? searchCol.querySelector(':scope > .block-search') : null;
            cartWrap = searchCol ? searchCol.querySelector(':scope > .mini-cart-wrapper') : null;
            minicart = cartWrap ? cartWrap.querySelector(':scope > .mini-carts, .minicart-wrapper') : null;
            showcart = cartWrap ? cartWrap.querySelector('.showcart, .action.showcart') : null;
            logo = (brandCol ? brandCol.querySelector('.logo') : null) || row.querySelector('.logo');
            logoLink = logo ? logo.querySelector('a') : null;
            logoImg = logo ? logo.querySelector('img') : null;

            setStyleImportant(row, 'display', 'grid');
            setStyleImportant(row, 'grid-template-columns', 'clamp(82px, 24vw, 108px) minmax(0, 1fr)');
            setStyleImportant(row, 'align-items', 'center');
            setStyleImportant(row, 'gap', '8px');
            setStyleImportant(row, 'width', '100%');
            setStyleImportant(row, 'max-width', '100%');
            setStyleImportant(row, 'min-width', '0');

            if (searchCol) {
                setStyleImportant(searchCol, 'display', 'grid');
                setStyleImportant(searchCol, 'grid-template-columns', 'minmax(0, 1fr) 44px');
                setStyleImportant(searchCol, 'grid-template-areas', '"search cart"');
                setStyleImportant(searchCol, 'grid-template-rows', '44px');
                setStyleImportant(searchCol, 'align-items', 'center');
                setStyleImportant(searchCol, 'gap', '8px');
                setStyleImportant(searchCol, 'width', '100%');
                setStyleImportant(searchCol, 'min-width', '0');
                setStyleImportant(searchCol, 'min-height', '44px');
                setStyleImportant(searchCol, 'margin', '0');
                setStyleImportant(searchCol, 'position', 'relative');
            }
            if (block) {
                setStyleImportant(block, 'grid-area', 'search');
                setStyleImportant(block, 'width', '100%');
                setStyleImportant(block, 'min-width', '0');
            }
            if (cartWrap) {
                setStyleImportant(cartWrap, 'display', 'block');
                setStyleImportant(cartWrap, 'position', 'static');
                setStyleImportant(cartWrap, 'grid-area', 'cart');
                setStyleImportant(cartWrap, 'width', '44px');
                setStyleImportant(cartWrap, 'min-width', '44px');
                setStyleImportant(cartWrap, 'max-width', '44px');
                setStyleImportant(cartWrap, 'min-height', '44px');
            }
            if (minicart) {
                setStyleImportant(minicart, 'position', 'static');
                setStyleImportant(minicart, 'width', '44px');
                setStyleImportant(minicart, 'height', '44px');
                setStyleImportant(minicart, 'display', 'flex');
                setStyleImportant(minicart, 'align-items', 'center');
                setStyleImportant(minicart, 'justify-content', 'center');
            }
            if (showcart) {
                setStyleImportant(showcart, 'position', 'static');
                setStyleImportant(showcart, 'width', '44px');
                setStyleImportant(showcart, 'height', '44px');
                setStyleImportant(showcart, 'display', 'inline-flex');
                setStyleImportant(showcart, 'align-items', 'center');
                setStyleImportant(showcart, 'justify-content', 'center');
            }
            if (logo) {
                setStyleImportant(logo, 'display', 'flex');
                setStyleImportant(logo, 'width', 'clamp(82px, 24vw, 108px)');
                setStyleImportant(logo, 'max-width', '108px');
                setStyleImportant(logo, 'overflow', 'visible');
            }
            if (logoImg) {
                setStyleImportant(logoImg, 'display', 'block');
                setStyleImportant(logoImg, 'width', '100%');
                setStyleImportant(logoImg, 'max-height', '56px');
            }
        }
    }

    function hideLegacyNavToggleOnHome() {
        var toggle = document.querySelector('.header-control .action.nav-toggle, .header-control .nav-toggle');
        if (!toggle) {
            return;
        }
        if (isHomePage()) {
            toggle.style.removeProperty('display');
            toggle.style.removeProperty('visibility');
            toggle.style.removeProperty('pointer-events');
            toggle.removeAttribute('aria-hidden');
            if (toggle.getAttribute('tabindex') === '-1') {
                toggle.removeAttribute('tabindex');
            }
            return;
        }
        setStyleImportant(toggle, 'display', 'none');
        setStyleImportant(toggle, 'visibility', 'hidden');
        setStyleImportant(toggle, 'pointer-events', 'none');
        toggle.setAttribute('aria-hidden', 'true');
        toggle.setAttribute('tabindex', '-1');
    }

    function syncLayout() {
        var fp = buildLayoutFingerprint();
        if (fp === layoutFingerprint) {
            return;
        }
        layoutFingerprint = fp;
        fixSearchFormAction();
        syncPlpToolbarOffset();
        applyLegacyMobileGrid();
        hideLegacyNavToggleOnHome();
    }

    function runHeaderPass() {
        syncStickyCondensed();
        fixSearchFormAction();
        syncPlpToolbarOffset();
        syncLayout();
        ensureSearchCompat();
        syncMinicartA11y();
        classifyTopLinks();
        syncTopLinkCounters();
        scheduleSearchSync();
    }

    function onDelegatedInteraction(evt) {
        var target = evt && evt.target ? evt.target : null;
        if (!target) {
            return;
        }
        if (target.closest('.toolbar .modes .modes-label[data-awa-filter-toggle="true"]')) {
            scheduleLayoutSync();
            return;
        }
        if (target.closest('[data-awa-search-root="true"],[data-awa-search-form="true"],[data-awa-search-input="true"],[data-awa-search-panel="true"],.header .top-search,.awa-site-header .block-search')) {
            if (evt.type === 'click') {
                scheduleLayoutSync();
            } else {
                scheduleSearchSync();
            }
            return;
        }
        if (target.closest('.header .mini-cart-wrapper,.header .mini-carts,.minicart-wrapper,.top-account ul.header.links,.awa-header-minicart')) {
            scheduleLayoutSync();
        }
    }

    function onSearchKeydown(evt) {
        var ctx = getSearchContext();
        var key = evt.key;
        var options;
        var index;
        var row;
        if (!ctx || !ctx.input || evt.target !== ctx.input) {
            return;
        }
        options = getSearchOptions(ctx);
        index = parseInt(ctx.form.getAttribute('data-awa-active-index') || '-1', 10);
        if (key === 'Escape') {
            evt.preventDefault();
            clearSearchActive(ctx, options);
            ctx.form.setAttribute('data-awa-panel-closed', 'true');
            if (ctx.panel) {
                setStyleImportant(ctx.panel, 'display', 'none');
            }
            scheduleSearchSync();
            return;
        }
        if (!options.length) {
            return;
        }
        if (key === 'ArrowDown' || key === 'Down') {
            evt.preventDefault();
            setSearchActive(ctx, options, (isNaN(index) ? -1 : index) + 1, true);
            return;
        }
        if (key === 'ArrowUp' || key === 'Up') {
            evt.preventDefault();
            setSearchActive(ctx, options, (isNaN(index) ? 0 : index) - 1, true);
            return;
        }
        if (key === 'Enter' && index >= 0) {
            row = options[index];
            if (row && row.action && row.action.click) {
                evt.preventDefault();
                row.action.click();
            }
        }
    }

    function onSearchHover(evt) {
        var ctx = getSearchContext();
        var options;
        var i;
        var row;
        if (!ctx || !ctx.panel || !ctx.panel.contains(evt.target)) {
            return;
        }
        row = evt.target.closest('[role="option"], li');
        if (!row) {
            return;
        }
        options = getSearchOptions(ctx);
        for (i = 0; i < options.length; i += 1) {
            if (options[i].option === row) {
                setSearchActive(ctx, options, i, false);
                break;
            }
        }
    }

    function initObservers() {
        var sticky = stickyWrapper();
        var search = searchBlock();
        if (sticky && window.MutationObserver) {
            new MutationObserver(function () {
                schedule(function () {
                    syncStickyCondensed();
                    syncPlpToolbarOffset();
                }, 'scroll', syncFlags);
            }).observe(sticky, { attributes: true, attributeFilter: ['class'] });
        }
        if (search && window.MutationObserver) {
            new MutationObserver(function (mutations) {
                var i;
                for (i = 0; i < mutations.length; i += 1) {
                    if (mutations[i].type === 'childList') {
                        scheduleSearchSync();
                        return;
                    }
                }
            }).observe(search, { childList: true, subtree: true });
        }
    }

    function init() {
        runHeaderPass();
        window.addEventListener('scroll', function () {
            schedule(function () {
                syncStickyCondensed();
                syncPlpToolbarOffset();
            }, 'scroll', syncFlags);
        }, { passive: true });
        window.addEventListener('resize', function () {
            layoutFingerprint = '';
            scheduleLayoutSync();
            schedule(function () {
                runHeaderPass();
            }, 'resize', syncFlags);
        }, { passive: true });
        document.addEventListener('keydown', onSearchKeydown, true);
        document.addEventListener('mouseover', onSearchHover, true);
        document.addEventListener('focusin', onDelegatedInteraction, true);
        document.addEventListener('keyup', onDelegatedInteraction, true);
        document.addEventListener('click', onDelegatedInteraction, true);
        document.addEventListener('input', onDelegatedInteraction, true);
        initObservers();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
}());

/* Auth / account shells — minicart visível sem polling agressivo */
(function () {
    'use strict';

    function isAuthShell() {
        var body = document.body;
        return !!body && (
            body.classList.contains('b2b-auth-shell') ||
            body.classList.contains('customer-account') ||
            body.classList.contains('customer-account-login') ||
            body.classList.contains('customer-account-create')
        );
    }

    function ensureShowcartVisible() {
        if (!isAuthShell()) {
            return;
        }
        document.querySelectorAll('.awa-site-header .minicart-wrapper .showcart, .awa-site-header .minicart-wrapper .action.showcart, .b2b-auth-shell .awa-site-header .minicart-wrapper .showcart').forEach(function (btn) {
            if (btn.getAttribute('data-awa-showcart-fixed') === '1') {
                return;
            }
            btn.setAttribute('data-awa-showcart-fixed', '1');
            btn.style.setProperty('opacity', '1', 'important');
            btn.style.setProperty('visibility', 'visible', 'important');
            btn.style.setProperty('display', 'inline-flex', 'important');
            btn.style.setProperty('pointer-events', 'auto', 'important');
        });
    }

    function fixOpenMinicartPanel() {
        var panel = document.querySelector('.minicart-wrapper .block-minicart._active, .minicart-wrapper.active .block-minicart');
        if (!panel) {
            return;
        }
        panel.style.setProperty('position', 'absolute', 'important');
        panel.style.setProperty('top', 'calc(100% + 8px)', 'important');
        panel.style.setProperty('right', '0', 'important');
        panel.style.setProperty('left', 'auto', 'important');
        panel.style.setProperty('z-index', '100130', 'important');
        panel.style.setProperty('max-height', '78vh', 'important');
        panel.style.setProperty('overflow', 'auto', 'important');
    }

    function onCartInteraction() {
        window.requestAnimationFrame(function () {
            ensureShowcartVisible();
            fixOpenMinicartPanel();
        });
    }

    function initAuthShell() {
        if (!isAuthShell()) {
            return;
        }
        ensureShowcartVisible();
        document.addEventListener('click', function (evt) {
            if (evt.target && evt.target.closest('.minicart-wrapper,.showcart,.action.showcart,.awa-header-minicart')) {
                onCartInteraction();
            }
        }, true);
        window.addEventListener('resize', ensureShowcartVisible, { passive: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAuthShell, { once: true });
    } else {
        initAuthShell();
    }
}());
