/**
 * M09 + M10: Mobile accordion for vertical menu and footer
 * Adds .is-open toggle on click for elements matching CSS accordion rules
 * Only active on mobile (≤767px)
 */
!function () {
    'use strict';

    if (window.__awaMobileAccordionInit) return;
    window.__awaMobileAccordionInit = true;

    function isMobile() {
        return window.innerWidth <= 767;
    }

    function initFooterAccordion() {
        var footer = document.querySelector('.footer.content, .page_footer');
        if (!footer) return;

        footer.addEventListener('click', function (e) {
            if (!isMobile()) return;

            var title = e.target.closest('.footer-title, .velaFooterTitle, .block > .title');
            if (!title) return;

            var parent = title.parentElement;
            if (!parent) return;

            e.preventDefault();
            parent.classList.toggle('is-open');
        });
    }

    function initVmenuAccordion() {
        var vmenu = document.querySelector('.block-vertical-nav, .block.block-vmenu');
        if (!vmenu) return;

        vmenu.addEventListener('click', function (e) {
            if (!isMobile()) return;

            var link = e.target.closest('.vela-vertical-menu > li > a');
            if (!link) return;

            var li = link.parentElement;
            var submenu = li.querySelector('.submenu, .sub-menu');
            if (!submenu) return;

            e.preventDefault();
            li.classList.toggle('is-open');
        });
    }

    function init() {
        initFooterAccordion();
        initVmenuAccordion();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
}();
