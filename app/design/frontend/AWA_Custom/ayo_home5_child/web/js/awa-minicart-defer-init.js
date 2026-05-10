(function () {
    'use strict';

    if (window.__awaMinicartDeferInit) {
        return;
    }
    window.__awaMinicartDeferInit = true;

    var HEADER_MINICART_SHELL_SELECTOR = '[data-awa-header-minicart-shell="true"], .awa-header-minicart[data-awa-header-cart="true"]';
    var HEADER_MINICART_FALLBACK_SELECTOR = '[data-awa-header-minicart-fallback="true"], .awa-header-cart-fallback';
    var HEADER_MINICART_CONTENT_SELECTOR = '[data-awa-header-minicart-content="true"], .mini-carts';
    var MINICART_TRIGGER_SELECTOR = '.minicart-wrapper .showcart, .minicart-wrapper .action.showcart';
    var MINICART_DROPDOWN_SELECTOR = '.minicart-wrapper [data-role="dropdownDialog"]';

    function getHeaderMinicartShell() {
        return document.querySelector(HEADER_MINICART_SHELL_SELECTOR);
    }

    function getHeaderMinicartRoot() {
        var shell = getHeaderMinicartShell();

        if (!shell) {
            return document;
        }

        return shell.querySelector(HEADER_MINICART_CONTENT_SELECTOR) || shell;
    }

    function queryRuntimeTrigger(root) {
        return (root || document).querySelector(MINICART_TRIGGER_SELECTOR);
    }

    function queryDropdown(root) {
        return (root || document).querySelector(MINICART_DROPDOWN_SELECTOR);
    }

    function isVisible(element) {
        return !!(element && (element.offsetWidth || element.offsetHeight || element.getClientRects().length));
    }

    function syncFallbackAccessibility(shell, hasRuntimeTrigger) {
        var fallback = shell ? shell.querySelector(HEADER_MINICART_FALLBACK_SELECTOR) : null;

        if (!shell || !fallback) {
            return;
        }

        shell.setAttribute('data-awa-minicart-ready', hasRuntimeTrigger ? '1' : '0');
        shell.classList.toggle('awa-header-minicart--ready', hasRuntimeTrigger);

        if (hasRuntimeTrigger) {
            fallback.setAttribute('aria-hidden', 'true');
            fallback.setAttribute('tabindex', '-1');
            return;
        }

        fallback.removeAttribute('aria-hidden');
        fallback.removeAttribute('tabindex');
    }

    function syncShellState(shell, expanded) {
        if (!shell) {
            return;
        }

        shell.setAttribute('data-awa-minicart-expanded', expanded ? '1' : '0');
        shell.classList.toggle('awa-header-minicart--expanded', expanded);
    }

    function bindCheckoutFallback() {
        if (window.__awaMinicartCheckoutFallbackBound) {
            return;
        }

        window.__awaMinicartCheckoutFallbackBound = true;

        document.addEventListener('click', function (event) {
            var button = event.target && event.target.closest
                ? event.target.closest('#top-cart-btn-checkout')
                : null;
            var minicart = window.jQuery
                ? window.jQuery('[data-block="minicart"]')
                : null;
            var dropdown = window.jQuery
                ? window.jQuery('[data-role="dropdownDialog"]')
                : null;
            var checkoutUrl = window.checkout && typeof window.checkout.checkoutUrl === 'string'
                ? window.checkout.checkoutUrl
                : '';

            if (!button || !checkoutUrl || (minicart && minicart.data('mageSidebar'))) {
                return;
            }

            event.preventDefault();
            if (typeof event.stopImmediatePropagation === 'function') {
                event.stopImmediatePropagation();
            }
            event.stopPropagation();

            if (dropdown && dropdown.length && dropdown.data('mageDropdownDialog')) {
                dropdown.dropdownDialog('close');
            }

            window.location.assign(checkoutUrl);
        }, true);
    }

    function updateExpandedState() {
        var shell = getHeaderMinicartShell();
        var root = getHeaderMinicartRoot();
        var trigger = queryRuntimeTrigger(root) || queryRuntimeTrigger(document);
        var dropdown = queryDropdown(root) || queryDropdown(document);
        var hasRuntimeTrigger = isVisible(trigger);

        syncFallbackAccessibility(shell, hasRuntimeTrigger);

        if (!trigger || !dropdown) {
            syncShellState(shell, false);
            return;
        }

        var expanded = dropdown.classList.contains('active') ||
            dropdown.getAttribute('aria-hidden') === 'false' ||
            dropdown.style.display === 'block';
        trigger.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        syncShellState(shell, expanded);
    }

    function initDropdown() {
        if (!window.require) {
            return;
        }

        window.require(['jquery', 'dropdownDialog'], function ($) {
            var scopedDropdownSelector = HEADER_MINICART_SHELL_SELECTOR + ' ' + MINICART_DROPDOWN_SELECTOR;
            var triggerTargetSelector = HEADER_MINICART_SHELL_SELECTOR + ' .showcart, ' +
                HEADER_MINICART_SHELL_SELECTOR + ' .action.showcart';
            var minicartDropdown = $(scopedDropdownSelector).first();

            if (!minicartDropdown.length) {
                minicartDropdown = $('[data-role="dropdownDialog"]').first();
            }

            if (!minicartDropdown.length || minicartDropdown.data('mageDropdownDialog')) {
                updateExpandedState();
                return;
            }

            minicartDropdown.dropdownDialog({
                appendTo: '[data-block=minicart]',
                triggerTarget: triggerTargetSelector,
                timeout: '2000',
                closeOnMouseLeave: true,
                closeOnEscape: true,
                triggerClass: 'active',
                parentClass: 'active',
                buttons: []
            });

            updateExpandedState();
            $(document).on('click keyup', triggerTargetSelector + ', .block-minicart', function () {
                window.requestAnimationFrame(updateExpandedState);
            });
        });
    }

    function scheduleBootstrapPasses() {
        if (window.__awaMinicartBootstrapScheduled) {
            return;
        }

        window.__awaMinicartBootstrapScheduled = true;

        [0, 250, 900, 1800, 3200].forEach(function (delay) {
            window.setTimeout(function () {
                updateExpandedState();
                initDropdown();
            }, delay);
        });
    }

    function bindInteractionSync() {
        if (window.__awaMinicartInteractionSyncBound) {
            return;
        }

        window.__awaMinicartInteractionSyncBound = true;

        document.addEventListener('click', function (event) {
            var target = event.target;

            if (!target || !target.closest) {
                return;
            }

            if (!target.closest(MINICART_TRIGGER_SELECTOR + ', .block-minicart, ' + HEADER_MINICART_SHELL_SELECTOR)) {
                return;
            }

            window.requestAnimationFrame(updateExpandedState);
            window.setTimeout(updateExpandedState, 120);
            window.setTimeout(updateExpandedState, 420);
        }, true);

        document.addEventListener('contentUpdated', function () {
            window.requestAnimationFrame(updateExpandedState);
        }, true);
    }

    function observeMinicartState() {
        if (window.__awaMinicartStateObserverBound || !window.MutationObserver) {
            return;
        }

        var target = getHeaderMinicartShell() || document.querySelector('[data-block="minicart"]') || document.body;

        if (!target) {
            return;
        }

        window.__awaMinicartStateObserverBound = true;

        new MutationObserver(function () {
            window.requestAnimationFrame(updateExpandedState);
        }).observe(target, {
            attributes: true,
            childList: true,
            subtree: true,
            attributeFilter: ['class', 'style', 'aria-hidden']
        });
    }

    function boot() {
        if (window.__awaMinicartDeferBooted) {
            scheduleBootstrapPasses();
            return;
        }

        window.__awaMinicartDeferBooted = true;

        bindCheckoutFallback();
        bindInteractionSync();
        observeMinicartState();
        updateExpandedState();
        scheduleBootstrapPasses();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }

    window.addEventListener('load', boot, { once: true });
})();
