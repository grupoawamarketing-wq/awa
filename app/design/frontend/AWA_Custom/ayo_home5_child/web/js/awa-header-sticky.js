/**
 * AWA Header Sticky — condensed state on scroll
 *
 * Adds `awa-header-condensed` to .awa-site-header and
 * `is-sticky` + `awa-header-condensed` to .header-wrapper-sticky
 * when the page is scrolled past SCROLL_THRESHOLD px.
 *
 * Uses requestAnimationFrame for throttle (no lodash/underscore needed).
 * Respects prefers-reduced-motion: skips class toggle animation context.
 *
 * RequireJS path: awa-header-sticky (registered in requirejs-config.js)
 */
define([], function () {
    'use strict';

    var SCROLL_THRESHOLD_BASE = 60;

    /**
     * Bug #19: O banner B2B fica acima do wrapper sticky e tem altura ~40px.
     * Ao ativar o condensed com threshold fixo de 60px, o header condensa enquanto
     * o banner ainda está parcialmente visível. Corrigido medindo a altura real do
     * banner e somando ao threshold base.
     */
    function getScrollThreshold() {
        var bar = document.getElementById('awa-b2b-promo-bar');
        if (!bar || bar.style.display === 'none') {
            return SCROLL_THRESHOLD_BASE;
        }
        var h = bar.getBoundingClientRect().height;
        return SCROLL_THRESHOLD_BASE + (h > 0 ? Math.round(h) : 0);
    }

    return function () {
        /** @type {Element|null} */
        var header = document.querySelector('.awa-site-header');
        /** @type {Element|null} */
        var stickyWrapper = document.querySelector('.header-wrapper-sticky');

        if (!header) {
            return;
        }

        var ticking = false;

        /**
         * Apply or remove sticky classes based on current scrollY.
         */
        function updateStickyState() {
            var scrollY = window.pageYOffset !== undefined
                ? window.pageYOffset
                : (document.documentElement || document.body.parentNode || document.body).scrollTop;

            var isSticky = scrollY > getScrollThreshold();

            header.classList.toggle('awa-header-condensed', isSticky);

            if (stickyWrapper) {
                stickyWrapper.classList.toggle('is-sticky', isSticky);
                // Match existing LESS: .header-wrapper-sticky.awa-header-condensed { box-shadow: … }
                stickyWrapper.classList.toggle('awa-header-condensed', isSticky);
            }

            ticking = false;
        }

        /**
         * Scroll listener — deferred to rAF to avoid layout thrash.
         */
        function onScroll() {
            if (!ticking) {
                window.requestAnimationFrame(updateStickyState);
                ticking = true;
            }
        }

        window.addEventListener('scroll', onScroll, { passive: true });

        // Sync on init (handles page refresh mid-scroll / back-navigation).
        updateStickyState();
    };
});
