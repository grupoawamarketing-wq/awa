/**
 * Checkout Simplified Header — v5 (setProperty interception)
 *
 * Problem: awa-bundle-site.js continuously re-applies inline styles
 * (display: grid !important) on .wp-header and .top-search on mobile.
 * MutationObserver-based approaches lose the race because awa-bundle-site
 * re-applies faster than any debounced callback can enforce.
 *
 * Solution: Intercept style.setProperty() and setAttribute('style')
 * on the target elements. When awa-bundle-site.js tries to set
 * display: grid, our interceptor silently redirects to display: flex
 * (for wp-header) or display: none (for top-search). This is
 * synchronous — no race condition possible.
 *
 * Only active on checkout pages (body.rokanthemes-onepagecheckout)
 * and only on mobile (≤991px).
 */
define([], function () {
    'use strict';

    /** @type {Set<string>} Properties to block entirely on wp-header */
    var BLOCKED_PROPS = [
        'grid-template-columns', 'grid-template-rows',
        'grid-template-areas', 'grid-template'
    ];

    /**
     * Install a setProperty interceptor on an element's style object.
     *
     * @param {HTMLElement} el - Target element
     * @param {Object} overrides - Map of property name → {value, priority}
     * @param {string[]} blocked - Property names to silently ignore
     * @returns {Function} The original setProperty (bound)
     */
    function interceptSetProperty(el, overrides, blocked) {
        var orig = el.style.setProperty.bind(el.style);

        el.style.setProperty = function (prop, value, priority) {
            if (overrides[prop]) {
                orig(prop, overrides[prop].value, overrides[prop].priority);
                return;
            }

            if (blocked.indexOf(prop) !== -1) {
                return;
            }

            orig(prop, value, priority);
        };

        return orig;
    }

    /**
     * Block setAttribute('style', ...) on an element so bulk style
     * reassignment by awa-bundle-site.js is ignored.
     *
     * @param {HTMLElement} el - Target element
     */
    function blockSetAttribute(el) {
        var orig = el.setAttribute.bind(el);

        el.setAttribute = function (name, value) {
            if (name === 'style') {
                return;
            }

            orig(name, value);
        };
    }

    /**
     * Apply initial desired styles using the original setProperty.
     *
     * @param {Function} origSet - Original setProperty (bound)
     * @param {Object} styles - Map of property name → {value, priority}
     */
    function applyInitial(origSet, styles) {
        var props = Object.keys(styles);

        for (var i = 0; i < props.length; i++) {
            origSet(props[i], styles[props[i]].value, styles[props[i]].priority);
        }
    }

    return function () {
        if (!document.body.classList.contains('rokanthemes-onepagecheckout')) {
            return;
        }

        // Only intercept on mobile (≤991px) — desktop CSS handles it fine
        if (window.innerWidth > 991) {
            return;
        }

        var wpH = document.querySelector('.wp-header');
        var topS = document.querySelector('.wp-header > .top-search');

        if (!wpH) {
            return;
        }

        // --- wp-header: force flex layout, block grid ---
        var wpOverrides = {
            'display': {value: 'flex', priority: 'important'},
            'gap': {value: '0', priority: 'important'}
        };

        var wpStyles = {
            'display': {value: 'flex', priority: 'important'},
            'align-items': {value: 'center', priority: 'important'},
            'justify-content': {value: 'center', priority: 'important'},
            'padding': {value: '8px 12px', priority: 'important'},
            'gap': {value: '0', priority: 'important'},
            'min-height': {value: 'auto', priority: 'important'},
            'height': {value: 'auto', priority: 'important'}
        };

        // Clear existing inline styles before intercepting
        wpH.removeAttribute('style');
        var origWpSet = interceptSetProperty(wpH, wpOverrides, BLOCKED_PROPS);

        blockSetAttribute(wpH);
        applyInitial(origWpSet, wpStyles);

        // --- top-search: force hidden ---
        if (topS) {
            var tsOverrides = {
                'display': {value: 'none', priority: 'important'},
                'min-height': {value: '0', priority: 'important'},
                'height': {value: '0', priority: 'important'}
            };

            var tsStyles = {
                'display': {value: 'none', priority: 'important'},
                'height': {value: '0', priority: 'important'},
                'overflow': {value: 'hidden', priority: 'important'},
                'min-height': {value: '0', priority: 'important'}
            };

            topS.removeAttribute('style');
            var origTsSet = interceptSetProperty(topS, tsOverrides, BLOCKED_PROPS);

            blockSetAttribute(topS);
            applyInitial(origTsSet, tsStyles);
        }
    };
});
