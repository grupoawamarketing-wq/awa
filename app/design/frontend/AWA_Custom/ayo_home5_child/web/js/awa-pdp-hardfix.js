(function () {
    'use strict';

    function applyPdpHardfix() {
        if (!document.body || !document.body.classList.contains('catalog-product-view')) {
            return;
        }

        var galleryPlaceholders = document.querySelectorAll('.product.media .gallery-placeholder');
        galleryPlaceholders.forEach(function (placeholder) {
            var hasFotorama = placeholder.querySelector('.fotorama-item, .fotorama, .fotorama__stage');
            var fallback = placeholder.querySelector('img.gallery-placeholder__image');
            if (hasFotorama && fallback) {
                fallback.style.setProperty('display', 'none', 'important');
            }
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
