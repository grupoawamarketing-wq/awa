(function () {
    'use strict';

    if (window.__awaMinicartDeferInit) {
        return;
    }
    window.__awaMinicartDeferInit = true;

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
        var trigger = document.querySelector('.minicart-wrapper .showcart');
        var dropdown = document.querySelector('.minicart-wrapper [data-role="dropdownDialog"]');
        if (!trigger || !dropdown) {
            return;
        }

        var expanded = dropdown.classList.contains('active') ||
            dropdown.getAttribute('aria-hidden') === 'false' ||
            dropdown.style.display === 'block';
        trigger.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    }

    function initDropdown() {
        if (!window.require) {
            return;
        }

        window.require(['jquery', 'dropdownDialog'], function ($) {
            var minicartDropdown = $('[data-role="dropdownDialog"]');

            if (!minicartDropdown.length || minicartDropdown.data('mageDropdownDialog')) {
                updateExpandedState();
                return;
            }

            minicartDropdown.dropdownDialog({
                appendTo: '[data-block=minicart]',
                triggerTarget: '.showcart',
                timeout: '2000',
                closeOnMouseLeave: true,
                closeOnEscape: true,
                triggerClass: 'active',
                parentClass: 'active',
                buttons: []
            });

            updateExpandedState();
            $(document).on('click keyup', '.showcart, .block-minicart', function () {
                window.requestAnimationFrame(updateExpandedState);
            });
        });
    }

    function boot() {
        bindCheckoutFallback();
        window.setTimeout(initDropdown, 900);
    }

    if (document.readyState === 'complete') {
        boot();
    } else {
        window.addEventListener('load', boot, { once: true });
    }
})();
