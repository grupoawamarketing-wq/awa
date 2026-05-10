(function () {
    'use strict';

    if (window.__awaMinicartDeferInit) {
        return;
    }
    window.__awaMinicartDeferInit = true;

    let HEADER_MINICART_SHELL_SELECTOR = '[data-awa-header-minicart-shell="true"], .awa-header-minicart[data-awa-header-cart="true"]';
    let HEADER_MINICART_FALLBACK_SELECTOR = '[data-awa-header-minicart-fallback="true"], .awa-header-cart-fallback';
    let HEADER_MINICART_CONTENT_SELECTOR = '[data-awa-header-minicart-content="true"], .mini-carts';
    let MINICART_TRIGGER_SELECTORS = [
        '.minicart-wrapper .showcart',
        '.minicart-wrapper .action.showcart',
        '.showcart.header-mini-cart'
    ];
    let MINICART_TRIGGER_SELECTOR = MINICART_TRIGGER_SELECTORS.join(', ');
    let MINICART_DROPDOWN_SELECTOR = '.minicart-wrapper [data-role="dropdownDialog"]';

    function getHeaderMinicartShell() {
        return document.querySelector(HEADER_MINICART_SHELL_SELECTOR);
    }

    function getHeaderMinicartRoot() {
        let shell = getHeaderMinicartShell();

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

    function getMinicartParts() {
        let shell = getHeaderMinicartShell();
        let root = getHeaderMinicartRoot();

        return {
            shell: shell,
            root: root,
            trigger: queryRuntimeTrigger(root) || queryRuntimeTrigger(shell) || queryRuntimeTrigger(document),
            dropdown: queryDropdown(root) || queryDropdown(shell) || queryDropdown(document)
        };
    }

    function isVisible(element) {
        return !!(element && (element.offsetWidth || element.offsetHeight || element.getClientRects().length));
    }

    function isDropdownExpanded(dropdown) {
        let wrapper;
        let style;
        let isActuallyVisible;

        if (!dropdown) {
            return false;
        }

        wrapper = dropdown.closest('[data-block="minicart"], .minicart-wrapper');
        style = window.getComputedStyle ? window.getComputedStyle(dropdown) : null;
        isActuallyVisible = !!(
            style &&
            style.display !== 'none' &&
            style.visibility !== 'hidden' &&
            style.opacity !== '0' &&
            isVisible(dropdown)
        );

        return !!(
            isActuallyVisible ||
            (dropdown.getAttribute('aria-hidden') === 'false' && isActuallyVisible) ||
            ((dropdown.classList.contains('active') || dropdown.classList.contains('is-open')) && isActuallyVisible) ||
            (wrapper && isActuallyVisible && (
                wrapper.classList.contains('active') ||
                wrapper.classList.contains('is-open') ||
                wrapper.classList.contains('show')
            ))
        );
    }

    function syncFallbackAccessibility(shell, hasRuntimeTrigger) {
        let fallback = shell ? shell.querySelector(HEADER_MINICART_FALLBACK_SELECTOR) : null;

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

    function applyManualDropdownState(trigger, dropdown, expanded) {
        let wrapper = dropdown ? dropdown.closest('[data-block="minicart"], .minicart-wrapper') : null;
        let shell = getHeaderMinicartShell();

        if (!dropdown) {
            return;
        }

        if (wrapper) {
            wrapper.classList.toggle('active', expanded);
            wrapper.classList.toggle('is-open', expanded);
            wrapper.classList.toggle('show', expanded);
        }

        dropdown.classList.toggle('active', expanded);
        dropdown.classList.toggle('is-open', expanded);
        dropdown.setAttribute('aria-hidden', expanded ? 'false' : 'true');
        dropdown.style.display = expanded ? 'block' : 'none';
        dropdown.style.visibility = expanded ? 'visible' : 'hidden';

        if (trigger) {
            trigger.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        }

        syncShellState(shell, expanded);
    }

    function closeDropdown(trigger, dropdown) {
        var $dropdown;

        if (!dropdown) {
            return;
        }

        if (window.jQuery && window.jQuery.fn && typeof window.jQuery.fn.dropdownDialog === 'function') {
            $dropdown = window.jQuery(dropdown);
            if ($dropdown.data('mageDropdownDialog')) {
                $dropdown.dropdownDialog('close');
                window.requestAnimationFrame(updateExpandedState);
                window.setTimeout(updateExpandedState, 120);
                return;
            }
        }

        applyManualDropdownState(trigger, dropdown, false);
        window.requestAnimationFrame(updateExpandedState);
    }

    function openDropdown(trigger, dropdown) {
        var $dropdown;

        if (!dropdown) {
            return;
        }

        if (window.jQuery && window.jQuery.fn && typeof window.jQuery.fn.dropdownDialog === 'function') {
            $dropdown = window.jQuery(dropdown);
            if ($dropdown.data('mageDropdownDialog')) {
                $dropdown.dropdownDialog('open');
                window.requestAnimationFrame(updateExpandedState);
                window.setTimeout(updateExpandedState, 120);
                return;
            }
        }

        applyManualDropdownState(trigger, dropdown, true);
        window.requestAnimationFrame(updateExpandedState);
    }

    function buildScopedTriggerSelector() {
        return HEADER_MINICART_SHELL_SELECTOR.split(',').map(function (scopeSelector) {
            let scope = scopeSelector.trim();

            return MINICART_TRIGGER_SELECTORS.map(function (triggerSelector) {
                return scope + ' ' + triggerSelector;
            }).join(', ');
        }).join(', ');
    }

    function initDropdown(onReady) {
        let parts = getMinicartParts();

        if (!window.require) {
            if (typeof onReady === 'function') {
                onReady(null, parts.trigger || null, parts.dropdown || null);
            }
            return;
        }

        window.require(['jquery', 'dropdownDialog'], function ($) {
            let latestParts = getMinicartParts();
            let trigger = latestParts.trigger;
            let dropdown = latestParts.dropdown;
            var $dropdown = dropdown ? $(dropdown) : null;

            if ($dropdown && $dropdown.length && !$dropdown.data('mageDropdownDialog')) {
                $dropdown.dropdownDialog({
                    appendTo: '[data-block=minicart]',
                    triggerTarget: buildScopedTriggerSelector(),
                    timeout: '2000',
                    closeOnMouseLeave: false,
                    closeOnEscape: true,
                    triggerClass: 'is-open',
                    parentClass: 'is-open',
                    buttons: []
                });
            }

            if (!window.__awaMinicartDropdownSyncBound) {
                window.__awaMinicartDropdownSyncBound = true;

                $(document).on('click keyup', MINICART_TRIGGER_SELECTOR + ', .block-minicart', function () {
                    window.requestAnimationFrame(updateExpandedState);
                    window.setTimeout(updateExpandedState, 120);
                });
            }

            updateExpandedState();

            if (typeof onReady === 'function') {
                onReady($, trigger || null, dropdown || null);
            }
        }, function () {
            if (typeof onReady === 'function') {
                onReady(null, parts.trigger || null, parts.dropdown || null);
            }
            updateExpandedState();
        });
    }

    function toggleDropdown(trigger, dropdown, fallbackUrl) {
        initDropdown(function ($, latestTrigger, latestDropdown) {
            let resolvedTrigger = latestTrigger || trigger;
            let resolvedDropdown = latestDropdown || dropdown;
            var $dropdown = resolvedDropdown && $ ? $(resolvedDropdown) : null;

            if ($dropdown && $dropdown.length && $dropdown.data('mageDropdownDialog')) {
                if (isDropdownExpanded(resolvedDropdown)) {
                    $dropdown.dropdownDialog('close');
                } else {
                    $dropdown.dropdownDialog('open');
                }

                window.requestAnimationFrame(updateExpandedState);
                window.setTimeout(updateExpandedState, 120);
                return;
            }

            if (resolvedDropdown) {
                if (isDropdownExpanded(resolvedDropdown)) {
                    applyManualDropdownState(resolvedTrigger, resolvedDropdown, false);
                } else {
                    applyManualDropdownState(resolvedTrigger, resolvedDropdown, true);
                }

                window.requestAnimationFrame(updateExpandedState);
                return;
            }

            if (fallbackUrl) {
                window.location.assign(fallbackUrl);
            }
        });
    }

    function bindCheckoutFallback() {
        if (window.__awaMinicartCheckoutFallbackBound) {
            return;
        }

        window.__awaMinicartCheckoutFallbackBound = true;

        document.addEventListener('click', function (event) {
            let button = event.target && event.target.closest
                ? event.target.closest('#top-cart-btn-checkout')
                : null;
            let minicart = window.jQuery
                ? window.jQuery('[data-block="minicart"]')
                : null;
            let dropdown = window.jQuery
                ? window.jQuery('[data-role="dropdownDialog"]')
                : null;
            let checkoutUrl = window.checkout && typeof window.checkout.checkoutUrl === 'string'
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

    function bindTriggerGuard() {
        if (window.__awaMinicartTriggerGuardBound) {
            return;
        }

        window.__awaMinicartTriggerGuardBound = true;

        document.addEventListener('click', function (event) {
            let target = event.target;
            let trigger;
            let parts;
            let href;

            if (!target || !target.closest) {
                return;
            }

            trigger = target.closest(MINICART_TRIGGER_SELECTOR);
            if (!trigger) {
                return;
            }

            parts = getMinicartParts();
            if (!parts.shell || !parts.shell.contains(trigger) || !parts.dropdown) {
                return;
            }

            href = trigger.getAttribute('href') || '';

            event.preventDefault();
            if (typeof event.stopImmediatePropagation === 'function') {
                event.stopImmediatePropagation();
            }
            event.stopPropagation();

            toggleDropdown(parts.trigger || trigger, parts.dropdown, href);
        }, true);
    }

    function bindEscapeClose() {
        if (window.__awaMinicartEscapeCloseBound) {
            return;
        }

        window.__awaMinicartEscapeCloseBound = true;

        document.addEventListener('keydown', function (event) {
            let parts;

            if (event.key !== 'Escape') {
                return;
            }

            parts = getMinicartParts();
            if (!parts.dropdown || !isDropdownExpanded(parts.dropdown)) {
                return;
            }

            closeDropdown(parts.trigger, parts.dropdown);
        }, true);
    }

    function updateExpandedState() {
        let parts = getMinicartParts();
        let hasRuntimeTrigger = isVisible(parts.trigger);
        let expanded = hasRuntimeTrigger && isDropdownExpanded(parts.dropdown);

        syncFallbackAccessibility(parts.shell, hasRuntimeTrigger);

        if (parts.trigger) {
            parts.trigger.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        }

        syncShellState(parts.shell, expanded);
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
            let target = event.target;
            let parts = getMinicartParts();
            let interactiveSelector = MINICART_TRIGGER_SELECTOR + ', .block-minicart, ' + HEADER_MINICART_SHELL_SELECTOR;

            if (!target || !target.closest) {
                return;
            }

            if (!target.closest(interactiveSelector)) {
                if (parts.dropdown && isDropdownExpanded(parts.dropdown)) {
                    closeDropdown(parts.trigger, parts.dropdown);
                }
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

        let parts = getMinicartParts();
        let target = parts.shell || document.querySelector('[data-block="minicart"]') || document.body;

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
        bindTriggerGuard();
        bindEscapeClose();
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
