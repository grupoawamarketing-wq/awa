(function () {
    'use strict';

    function applyPdpHardfix() {
        if (!document.body || !document.body.classList.contains('catalog-product-view')) {
            return;
        }

        var categories = document.querySelector('.awa-header-categories.menu_left_home1');
        if (categories) {
            categories.style.setProperty('display', 'none', 'important');
        }

        var categoryDropdown = document.querySelector('.sections.nav-sections.category-dropdown');
        if (categoryDropdown) {
            categoryDropdown.style.setProperty('display', 'none', 'important');
        }

        var arrows = document.querySelectorAll('.awa-pdp-related .swiper-button-next, .awa-pdp-related .swiper-button-prev');
        arrows.forEach(function (arrow) {
            arrow.style.setProperty('display', 'none', 'important');
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', applyPdpHardfix);
    } else {
        applyPdpHardfix();
    }

    window.addEventListener('load', applyPdpHardfix);
    setTimeout(applyPdpHardfix, 250);
    setTimeout(applyPdpHardfix, 1000);
})();
