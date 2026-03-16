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
        var threshold = topHeader ? topHeader.offsetHeight + 20 : 80;
        var isSticky = false;
        var STICKY_CLASS = 'awa-header-sticky';
        var headerHeight = 0;

        function onScroll() {
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

        // Passive scroll listener for performance
        var supportsPassive = false;
        try {
            var opts = Object.defineProperty({}, 'passive', {
                get: function () { supportsPassive = true; return true; }
            });
            window.addEventListener('test', null, opts);
        } catch (e) {}

        window.addEventListener('scroll', onScroll, supportsPassive ? { passive: true } : false);
        onScroll();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
