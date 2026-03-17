(function (window, document) {
    'use strict';

    if (window.__awaQaAccessibilitySafeInit) {
        return;
    }

    window.__awaQaAccessibilitySafeInit = true;

    var qaNodeId = 0;
    var modalKeydownBound = false;
    var lastQuoteTrigger = null;
    var searchObserver = null;
    var syncScheduled = false;

    function normalizeText(value) {
        return String(value || '').replace(/\s+/g, ' ').trim();
    }

    function ensureNodeId(node, prefix) {
        if (!node) {
            return '';
        }

        if (node.id) {
            return node.id;
        }

        qaNodeId += 1;
        node.id = (prefix || 'awa-node') + '-' + qaNodeId;

        return node.id;
    }

    function appendDescribedBy(node, targetId) {
        var tokens;

        if (!node || !targetId) {
            return;
        }

        tokens = (node.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);

        if (tokens.indexOf(targetId) === -1) {
            tokens.push(targetId);
            node.setAttribute('aria-describedby', tokens.join(' '));
        }
    }

    function setLabelIfMissing(node, text) {
        var normalized = normalizeText(text);

        if (!node || !normalized) {
            return;
        }

        if (!node.getAttribute('aria-label')) {
            node.setAttribute('aria-label', normalized);
        }

        if (!node.getAttribute('title') && node.matches('a, button, input, select, textarea')) {
            node.setAttribute('title', normalized);
        }
    }

    function findDirectChild(parent, selector) {
        var children;
        var i;

        if (!parent) {
            return null;
        }

        children = parent.children || [];

        for (i = 0; i < children.length; i += 1) {
            if (children[i].matches && children[i].matches(selector)) {
                return children[i];
            }
        }

        return null;
    }

    function scheduleAccessibilitySync() {
        if (syncScheduled) {
            return;
        }

        syncScheduled = true;
        window.requestAnimationFrame(function () {
            syncScheduled = false;
            Array.prototype.slice.call(document.querySelectorAll('.block-search, [data-awa-component="search-autocomplete"]')).forEach(syncSearchAccessibility);
            syncMenuAccessibility();
        });
    }

    function prefersReducedMotion() {
        return typeof window.matchMedia === 'function'
            && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    function isFocusable(element) {
        if (!element || element.hidden || element.getAttribute('aria-hidden') === 'true') {
            return false;
        }

        return element.offsetParent !== null;
    }

    function getFocusableElements(container) {
        if (!container) {
            return [];
        }

        return Array.prototype.slice.call(container.querySelectorAll([
            'a[href]',
            'button:not([disabled])',
            'textarea:not([disabled])',
            'input:not([disabled]):not([type="hidden"])',
            'select:not([disabled])',
            '[tabindex]:not([tabindex="-1"])'
        ].join(','))).filter(isFocusable);
    }

    function ensureMainContentFocusable() {
        var mainContent = document.getElementById('maincontent');

        if (mainContent && !mainContent.hasAttribute('tabindex')) {
            mainContent.setAttribute('tabindex', '-1');
        }

        return mainContent;
    }

    function bindSkipLinks() {
        Array.prototype.slice.call(document.querySelectorAll('.awa-skip-link, .vmm-skip-nav')).forEach(function (link) {
            if (link.dataset.awaSkipBound === 'true') {
                return;
            }

            link.addEventListener('click', function () {
                var mainContent = ensureMainContentFocusable();

                if (!mainContent) {
                    return;
                }

                window.setTimeout(function () {
                    mainContent.focus({ preventScroll: false });
                }, 0);
            });

            link.dataset.awaSkipBound = 'true';
        });
    }

    function bindBackToTop() {
        var button = document.getElementById('awa-back-to-top');

        if (!button || button.dataset.awaBackToTopBound === 'true') {
            return;
        }

        function toggleVisibility() {
            var shouldShow = (window.pageYOffset || document.documentElement.scrollTop || 0) > 600;

            button.hidden = !shouldShow;
            button.classList.toggle('is-visible', shouldShow);
            button.setAttribute('aria-hidden', shouldShow ? 'false' : 'true');
        }

        button.addEventListener('click', function () {
            window.scrollTo({
                top: 0,
                behavior: prefersReducedMotion() ? 'auto' : 'smooth'
            });
        });

        window.addEventListener('scroll', toggleVisibility, { passive: true });
        toggleVisibility();

        button.dataset.awaBackToTopBound = 'true';
    }

    function syncMenuAccessibility() {
        var navs = document.querySelectorAll('.navigation.custommenu.main-nav, .navigation.verticalmenu.side-verticalmenu');

        Array.prototype.slice.call(navs).forEach(function (nav) {
            var rootTrigger = nav.querySelector('[data-role="awa-vertical-menu-trigger"]');

            if (rootTrigger) {
                rootTrigger.setAttribute('aria-haspopup', 'true');
            }

            Array.prototype.slice.call(nav.querySelectorAll('li')).forEach(function (item) {
                var link = findDirectChild(item, 'a');
                var toggle = findDirectChild(item, '.open-children-toggle');
                var panel = findDirectChild(item, '.submenu, .groupmenu, .subchildmenu, ul.level0');
                var itemLabel = normalizeText(link ? link.textContent : item.textContent) || 'Submenu';
                var isOpen;

                if (!panel) {
                    if (link) {
                        setLabelIfMissing(link, itemLabel);
                    }
                    return;
                }

                isOpen = item.classList.contains('active')
                    || item.classList.contains('_active')
                    || panel.classList.contains('opened')
                    || panel.classList.contains('active')
                    || panel.classList.contains('is-open')
                    || isFocusable(panel);

                ensureNodeId(panel, 'awa-menu-panel');
                panel.setAttribute('aria-hidden', isOpen ? 'false' : 'true');

                if (link) {
                    link.setAttribute('aria-haspopup', 'true');
                    setLabelIfMissing(link, itemLabel);
                }

                if (toggle) {
                    toggle.setAttribute('role', 'button');
                    toggle.setAttribute('tabindex', toggle.getAttribute('tabindex') || '0');
                    toggle.setAttribute('aria-controls', panel.id);
                    toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                    setLabelIfMissing(toggle, (isOpen ? 'Recolher ' : 'Expandir ') + itemLabel);
                }
            });
        });
    }

    function ensureSearchHelperText(scope) {
        var input = scope.querySelector('[data-awa-search-input="true"], #search, input[name="q"]');
        var helper = scope.querySelector('[data-awa-search-help]');

        if (!input) {
            return null;
        }

        if (!helper) {
            helper = document.createElement('span');
            helper.className = 'awa-sr-only';
            helper.setAttribute('data-awa-search-help', 'true');
            helper.textContent = 'Digite ao menos 2 caracteres para ver sugestões e use as setas para navegar.';
            scope.appendChild(helper);
        }

        ensureNodeId(helper, 'awa-search-help');
        appendDescribedBy(input, helper.id);

        return helper;
    }

    function syncSearchAccessibility(scope) {
        var input;
        var categorySelect;
        var panel;
        var resultsRoot;
        var optionNodes;

        if (!scope) {
            return;
        }

        input = scope.querySelector('[data-awa-search-input="true"], #search, input[name="q"]');
        categorySelect = scope.querySelector('[data-awa-search-category-select="true"], #choose_category');
        panel = scope.querySelector('#search_autocomplete, [data-awa-search-panel="true"]');
        resultsRoot = scope.querySelector('#searchsuite-autocomplete, [data-awa-search-results-root="true"], .searchsuite-autocomplete');

        ensureSearchHelperText(scope);

        if (input) {
            setLabelIfMissing(input, input.getAttribute('placeholder') || 'Buscar produtos');
        }

        if (categorySelect) {
            setLabelIfMissing(categorySelect, 'Categoria');
        }

        if (panel) {
            ensureNodeId(panel, 'awa-search-panel');
            panel.setAttribute('role', panel.getAttribute('role') || 'listbox');
            panel.setAttribute('aria-label', panel.getAttribute('aria-label') || 'Sugestões de busca');

            if (input && !input.getAttribute('aria-controls')) {
                input.setAttribute('aria-controls', panel.id);
            }
        }

        if (resultsRoot) {
            resultsRoot.setAttribute('aria-label', resultsRoot.getAttribute('aria-label') || 'Resultados da busca em tempo real');
            Array.prototype.slice.call(resultsRoot.querySelectorAll('a[href], button')).forEach(function (node) {
                var fallbackLabel = node.getAttribute('data-awa-option-label')
                    || node.getAttribute('aria-label')
                    || normalizeText(node.textContent);

                setLabelIfMissing(node, fallbackLabel);
            });
        }

        optionNodes = scope.querySelectorAll('.search-autocomplete li, .searchsuite-autocomplete li, [role="option"]');
        Array.prototype.slice.call(optionNodes).forEach(function (optionNode) {
            optionNode.setAttribute('role', 'option');
            optionNode.setAttribute('aria-selected', optionNode.getAttribute('aria-selected') || 'false');
            optionNode.setAttribute('tabindex', optionNode.getAttribute('tabindex') || '-1');
            ensureNodeId(optionNode, 'awa-search-option');
        });
    }

    function bindSearchAccessibility() {
        var searchScopes = document.querySelectorAll('.block-search, [data-awa-component="search-autocomplete"]');

        Array.prototype.slice.call(searchScopes).forEach(syncSearchAccessibility);

        if (searchObserver || typeof MutationObserver === 'undefined') {
            return;
        }

        searchObserver = new MutationObserver(function () {
            scheduleAccessibilitySync();
        });

        searchObserver.observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['class', 'style', 'aria-expanded', 'aria-hidden']
        });
    }

    function getQuoteModal() {
        return document.getElementById('awa-quote-modal');
    }

    function syncQuoteTriggerState(expanded) {
        Array.prototype.slice.call(document.querySelectorAll('[data-awa-quote-open]')).forEach(function (trigger) {
            trigger.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        });
    }

    function closeQuoteModal() {
        var modal = getQuoteModal();

        if (!modal) {
            return;
        }

        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('awa-modal-open');
        syncQuoteTriggerState(false);

        if (modalKeydownBound) {
            document.removeEventListener('keydown', handleQuoteModalKeydown, true);
            modalKeydownBound = false;
        }

        if (lastQuoteTrigger && typeof lastQuoteTrigger.focus === 'function') {
            lastQuoteTrigger.focus();
        }
    }

    function openQuoteModal(trigger) {
        var modal = getQuoteModal();
        var autofocusField;
        var focusable;

        if (!modal) {
            return;
        }

        lastQuoteTrigger = trigger || document.activeElement;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('awa-modal-open');
        syncQuoteTriggerState(true);

        autofocusField = modal.querySelector('[data-awa-quote-autofocus]');
        focusable = getFocusableElements(modal);

        window.setTimeout(function () {
            if (autofocusField && typeof autofocusField.focus === 'function') {
                autofocusField.focus();
                return;
            }

            if (focusable.length && typeof focusable[0].focus === 'function') {
                focusable[0].focus();
                return;
            }

            modal.focus();
        }, 0);

        if (!modalKeydownBound) {
            document.addEventListener('keydown', handleQuoteModalKeydown, true);
            modalKeydownBound = true;
        }
    }

    function trapQuoteModalFocus(event, modal) {
        var focusable = getFocusableElements(modal);
        var firstElement;
        var lastElement;

        if (!focusable.length) {
            event.preventDefault();
            modal.focus();
            return;
        }

        firstElement = focusable[0];
        lastElement = focusable[focusable.length - 1];

        if (event.shiftKey && document.activeElement === firstElement) {
            event.preventDefault();
            lastElement.focus();
            return;
        }

        if (!event.shiftKey && document.activeElement === lastElement) {
            event.preventDefault();
            firstElement.focus();
        }
    }

    function handleQuoteModalKeydown(event) {
        var modal = getQuoteModal();

        if (!modal || modal.getAttribute('aria-hidden') === 'true') {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            closeQuoteModal();
            return;
        }

        if (event.key === 'Tab') {
            trapQuoteModalFocus(event, modal);
        }
    }

    function bindQuoteModal() {
        var modal = getQuoteModal();

        Array.prototype.slice.call(document.querySelectorAll('[data-awa-quote-open]')).forEach(function (trigger) {
            if (trigger.dataset.awaQuoteOpenBound === 'true') {
                return;
            }

            trigger.addEventListener('click', function () {
                openQuoteModal(trigger);
            });

            trigger.dataset.awaQuoteOpenBound = 'true';
        });

        if (!modal || modal.dataset.awaQuoteModalBound === 'true') {
            return;
        }

        modal.addEventListener('click', function (event) {
            var target = event.target;

            if (target && target.closest('[data-awa-quote-close]')) {
                closeQuoteModal();
            }
        });

        modal.dataset.awaQuoteModalBound = 'true';
    }

    function init() {
        ensureMainContentFocusable();
        bindSkipLinks();
        bindBackToTop();
        bindQuoteModal();
        bindSearchAccessibility();
        scheduleAccessibilitySync();

        document.addEventListener('click', function (event) {
            if (event.target && event.target.closest('.navigation, .block-search, .search-autocomplete, .searchsuite-autocomplete')) {
                window.setTimeout(scheduleAccessibilitySync, 0);
            }
        }, true);

        window.addEventListener('resize', scheduleAccessibilitySync);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
        return;
    }

    init();
})(window, document);
