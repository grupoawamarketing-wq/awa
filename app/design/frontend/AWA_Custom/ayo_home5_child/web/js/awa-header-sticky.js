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

    var SCROLL_THRESHOLD = 60;

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

            var isSticky = scrollY > SCROLL_THRESHOLD;

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
