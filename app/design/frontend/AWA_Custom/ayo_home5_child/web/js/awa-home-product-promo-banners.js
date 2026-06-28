(function () {
    'use strict';

    if (window.__awaHomeProductPromoBannersInit) {
        return;
    }
    window.__awaHomeProductPromoBannersInit = true;

    function pushPromotionEvent(eventName, payload) {
        window.dataLayer = window.dataLayer || [];
        try {
            window.dataLayer.push(Object.assign({ event: eventName }, payload || {}));
        } catch (e) {
            /* ignore analytics failures */
        }
    }

    function initPromoBanners(root) {
        var items = root.querySelectorAll('.awa-product-promo-banners__item[data-promo-id]');
        if (!items.length) {
            return;
        }

        var viewed = new WeakSet();

        if ('IntersectionObserver' in window) {
            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting || entry.intersectionRatio < 0.5) {
                        return;
                    }

                    var el = entry.target;
                    if (viewed.has(el)) {
                        return;
                    }

                    viewed.add(el);
                    pushPromotionEvent('view_promotion', {
                        promotion_id: el.getAttribute('data-promo-id') || '',
                        promotion_name: el.getAttribute('data-promo-name') || '',
                        creative_slot: el.getAttribute('data-promo-slot') || '',
                        location_id: 'home_product_promo_banners'
                    });
                });
            }, { threshold: 0.5, rootMargin: '0px 0px -10% 0px' });

            items.forEach(function (el) {
                observer.observe(el);
            });
        }

        items.forEach(function (el) {
            if (el.dataset.awaPromoTracked) {
                return;
            }
            el.dataset.awaPromoTracked = '1';
            el.addEventListener('click', function () {
                pushPromotionEvent('select_promotion', {
                    promotion_id: el.getAttribute('data-promo-id') || '',
                    promotion_name: el.getAttribute('data-promo-name') || '',
                    creative_slot: el.getAttribute('data-promo-slot') || '',
                    location_id: 'home_product_promo_banners'
                });
            });
        });
    }

    function boot() {
        document.querySelectorAll('.awa-product-promo-banners').forEach(initPromoBanners);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
