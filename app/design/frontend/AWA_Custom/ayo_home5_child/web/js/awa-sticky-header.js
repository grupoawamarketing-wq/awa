/**
 * AWA B2B — Sticky Header
 * Fixa o header principal ao rolar a página para baixo.
 * Ativa após ultrapassar a altura do top-header (utility bar).
 */
(function () {
    'use strict';

    // Wait for DOM ready
    function init() {
        var headerEl = document.getElementById('header')
            || document.querySelector('.header-container')
            || document.querySelector('header[role="banner"]');
        if (!headerEl) return;

        var topHeader = headerEl.querySelector('.top-header');
        var threshold = 80;
        var isSticky = false;
        var STICKY_CLASS = 'awa-header-sticky';
        var headerHeight = 0;
        var ticking = false;

        function recalcLayoutMetrics() {
            threshold = topHeader ? topHeader.offsetHeight + 20 : 80;
            if (isSticky) {
                headerHeight = headerEl.offsetHeight;
                document.body.style.paddingTop = headerHeight + 'px';
            }
        }

        function applyStickyState() {
            var scrollY = window.pageYOffset || document.documentElement.scrollTop;
            if (scrollY > threshold && !isSticky) {
                headerHeight = headerEl.offsetHeight;
                headerEl.classList.add(STICKY_CLASS);
                document.body.style.paddingTop = headerHeight + 'px';
                isSticky = true;
            } else if (scrollY <= threshold && isSticky) {
                headerEl.classList.remove(STICKY_CLASS);
                document.body.style.paddingTop = '';
                isSticky = false;
            }
        }

        function onScroll() {
            if (ticking) {
                return;
            }

            ticking = true;
            window.requestAnimationFrame(function () {
                applyStickyState();
                ticking = false;
            });
        }

        function onResize() {
            recalcLayoutMetrics();
            applyStickyState();
        }

        // Passive scroll listener for performance
        var supportsPassive = false;
        try {
            var opts = Object.defineProperty({}, 'passive', {
                get: function () { supportsPassive = true; return true; }
            });
            window.addEventListener('test', null, opts);
        } catch (e) {}

        recalcLayoutMetrics();
        window.addEventListener('scroll', onScroll, supportsPassive ? { passive: true } : false);
        window.addEventListener('resize', onResize, supportsPassive ? { passive: true } : false);
        applyStickyState();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
