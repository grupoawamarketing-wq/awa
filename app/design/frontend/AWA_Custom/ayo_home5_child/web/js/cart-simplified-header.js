define([], function () {
    'use strict';

    var BLOCKED_GRID_PROPS = [
        'grid-template-columns',
        'grid-template-rows',
        'grid-template-areas',
        'grid-template',
        'gap',
        'grid-column'
    ];
    var HEADER_ROW_SELECTOR = '.header .awa-main-header__inner[data-awa-header-row], .header .wp-header[data-awa-header-row]';
    var HEADER_TOP_SEARCH_SELECTOR = '.header .awa-main-header__inner[data-awa-header-row] > .top-search, .header .wp-header[data-awa-header-row] > .top-search';
    var HEADER_MINICART_SELECTOR = '.header [data-awa-header-minicart-shell="true"], .header .awa-header-minicart[data-awa-header-cart="true"]';

    function interceptSetProperty(element, overrides, blocked) {
        var original = element.style.setProperty.bind(element.style);

        element.style.setProperty = function (property, value, priority) {
            if (overrides[property]) {
                original(property, overrides[property].value, overrides[property].priority);
                return;
            }

            if (blocked.indexOf(property) !== -1) {
                return;
            }

            original(property, value, priority);
        };

        return original;
    }

    function blockSetAttribute(element) {
        var original = element.setAttribute.bind(element);

        element.setAttribute = function (name, value) {
            if (name === 'style') {
                return;
            }

            original(name, value);
        };
    }

    function applyInitial(originalSetProperty, styles) {
        Object.keys(styles).forEach(function (property) {
            originalSetProperty(property, styles[property].value, styles[property].priority);
        });
    }

    function install() {
        if (!document.body.classList.contains('checkout-cart-index')) {
            return true;
        }

        if (window.innerWidth > 767) {
            return true;
        }

        var wrapper = document.querySelector(HEADER_ROW_SELECTOR);
        var topSearch = document.querySelector(HEADER_TOP_SEARCH_SELECTOR);
        var miniCart = document.querySelector(HEADER_MINICART_SELECTOR);

        if (!wrapper || !topSearch) {
            return false;
        }

        if (!wrapper.hasAttribute('data-awa-cart-header-patched')) {
            wrapper.removeAttribute('style');

            var wrapperOverrides = {
                'display': {value: 'grid', priority: 'important'},
                'grid-template-columns': {value: 'minmax(0, 1fr)', priority: 'important'},
                'grid-template-areas': {value: '"brand" "search"', priority: 'important'},
                'row-gap': {value: '12px', priority: 'important'},
                'justify-items': {value: 'center', priority: 'important'},
                'padding-inline': {value: '18px', priority: 'important'}
            };

            var originalWrapperSet = interceptSetProperty(wrapper, wrapperOverrides, BLOCKED_GRID_PROPS);

            blockSetAttribute(wrapper);
            applyInitial(originalWrapperSet, {
                'display': {value: 'grid', priority: 'important'},
                'grid-template-columns': {value: 'minmax(0, 1fr)', priority: 'important'},
                'grid-template-areas': {value: '"brand" "search"', priority: 'important'},
                'align-items': {value: 'center', priority: 'important'},
                'justify-items': {value: 'center', priority: 'important'},
                'row-gap': {value: '12px', priority: 'important'},
                'padding-inline': {value: '18px', priority: 'important'}
            });

            wrapper.setAttribute('data-awa-cart-header-patched', 'true');
        }

        if (!topSearch.hasAttribute('data-awa-cart-top-search-patched')) {
            topSearch.removeAttribute('style');

            var topSearchOverrides = {
                'display': {value: 'block', priority: 'important'},
                'grid-column': {value: '1 / -1', priority: 'important'},
                'grid-area': {value: 'search', priority: 'important'},
                'max-width': {value: '360px', priority: 'important'},
                'width': {value: '100%', priority: 'important'},
                'min-width': {value: '0', priority: 'important'},
                'margin': {value: '0 auto', priority: 'important'}
            };

            var originalTopSearchSet = interceptSetProperty(topSearch, topSearchOverrides, BLOCKED_GRID_PROPS);

            blockSetAttribute(topSearch);
            applyInitial(originalTopSearchSet, {
                'display': {value: 'block', priority: 'important'},
                'grid-area': {value: 'search', priority: 'important'},
                'grid-column': {value: '1 / -1', priority: 'important'},
                'width': {value: '100%', priority: 'important'},
                'max-width': {value: '360px', priority: 'important'},
                'min-width': {value: '0', priority: 'important'},
                'margin': {value: '0 auto', priority: 'important'},
                'position': {value: 'relative', priority: 'important'}
            });

            topSearch.setAttribute('data-awa-cart-top-search-patched', 'true');
        }

        if (miniCart && !miniCart.hasAttribute('data-awa-cart-minicart-patched')) {
            miniCart.removeAttribute('style');

            var miniCartOverrides = {
                'display': {value: 'none', priority: 'important'},
                'width': {value: '0', priority: 'important'},
                'min-width': {value: '0', priority: 'important'},
                'max-width': {value: '0', priority: 'important'},
                'margin': {value: '0', priority: 'important'}
            };

            var originalMiniCartSet = interceptSetProperty(miniCart, miniCartOverrides, BLOCKED_GRID_PROPS);

            blockSetAttribute(miniCart);
            applyInitial(originalMiniCartSet, {
                'display': {value: 'none', priority: 'important'},
                'width': {value: '0', priority: 'important'},
                'min-width': {value: '0', priority: 'important'},
                'max-width': {value: '0', priority: 'important'},
                'height': {value: '0', priority: 'important'},
                'min-height': {value: '0', priority: 'important'},
                'overflow': {value: 'hidden', priority: 'important'},
                'margin': {value: '0', priority: 'important'}
            });

            miniCart.setAttribute('data-awa-cart-minicart-patched', 'true');
        }

        return true;
    }

    return function () {
        if (!document.body.classList.contains('checkout-cart-index')) {
            return;
        }

        var attempts = 0;

        function tick() {
            attempts += 1;

            if (install() || attempts > 120) {
                return;
            }

            window.requestAnimationFrame(tick);
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', tick, {once: true});
            return;
        }

        tick();
    };
});
