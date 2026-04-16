/* global define, window, setTimeout, clearTimeout */
/**
 * AWA Motos — Products Swiper Init
 *
 * Initializer simples para vitrines de produto sem abas.
 * Lê configuração no formato Owl v1 (items, itemsDesktop, etc.)
 * e inicializa Swiper 11 no elemento.
 *
 * Uso em text/x-magento-init:
 *   ".rokan-bestseller": {
 *       "js/products-swiper-init": {
 *           "carouselSelector": ".swiper",
 *           "owl": { "items": 4, "itemsDesktopSmall": [980, 3], ... }
 *       }
 *   }
 */
define([
    'jquery'
], function ($) {
    'use strict';

    function normalizeCount(value, fallback, max) {
        var count = parseInt(value, 10);

        if (isNaN(count) || count < 1) {
            count = fallback;
        }

        if (typeof max === 'number') {
            count = Math.min(count, max);
        }

        return count;
    }

    function resolveBoolean(value, fallback) {
        if (value === undefined || value === null || value === '') {
            return fallback;
        }

        if (typeof value === 'string') {
            return !(value === 'false' || value === '0');
        }

        return !!value;
    }

    function buildSwiperOptions(cfg) {
        var items = normalizeCount(cfg.items, 4, 4),
            mobileItems = 1,
            tabletItems = Math.min(items, 2),
            desktopSmallItems = Math.min(items, 3),
            desktopItems = items,
            baseSpaceBetween = parseInt(cfg.margin, 10),
            tabletSpaceBetween = parseInt(cfg.tabletSpaceBetween, 10),
            desktopSpaceBetween = parseInt(cfg.desktopSpaceBetween, 10),
            scrollPerPage = resolveBoolean(cfg.scrollPerPage, true);

        if (isNaN(baseSpaceBetween)) {
            baseSpaceBetween = 12;
        }

        if (isNaN(tabletSpaceBetween)) {
            tabletSpaceBetween = 14;
        }

        if (isNaN(desktopSpaceBetween)) {
            desktopSpaceBetween = 16;
        }

        if (cfg.itemsMobile && cfg.itemsMobile[1]) {
            mobileItems = normalizeCount(cfg.itemsMobile[1], 1, 2);
        }
        if (cfg.itemsTablet && cfg.itemsTablet[1]) {
            tabletItems = normalizeCount(cfg.itemsTablet[1], tabletItems, 3);
        }
        if (cfg.itemsDesktopSmall && cfg.itemsDesktopSmall[1]) {
            desktopSmallItems = normalizeCount(cfg.itemsDesktopSmall[1], desktopSmallItems, 3);
        }
        if (cfg.itemsDesktop && cfg.itemsDesktop[1]) {
            desktopItems = normalizeCount(cfg.itemsDesktop[1], desktopItems, 4);
        }

        return {
            slidesPerView: mobileItems,
            slidesPerGroup: scrollPerPage ? mobileItems : 1,
            spaceBetween: baseSpaceBetween,
            navigation: resolveBoolean(cfg.navigation, true) ? {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev'
            } : false,
            pagination: resolveBoolean(cfg.pagination, false) ? {
                el: '.swiper-pagination',
                clickable: true
            } : false,
            autoplay: resolveBoolean(cfg.autoPlay, false) ? {
                delay: parseInt(cfg.slideSpeed, 10) || 5000,
                disableOnInteraction: true,
                pauseOnMouseEnter: true
            } : false,
            loop: false,
            watchOverflow: true,
            breakpoints: {
                480: {
                    slidesPerView: mobileItems,
                    slidesPerGroup: scrollPerPage ? mobileItems : 1,
                    spaceBetween: 12
                },
                768: {
                    slidesPerView: tabletItems,
                    slidesPerGroup: scrollPerPage ? tabletItems : 1,
                    spaceBetween: tabletSpaceBetween
                },
                992: {
                    slidesPerView: desktopSmallItems,
                    slidesPerGroup: scrollPerPage ? desktopSmallItems : 1,
                    spaceBetween: desktopSpaceBetween
                },
                1200: {
                    slidesPerView: desktopItems,
                    slidesPerGroup: scrollPerPage ? desktopItems : 1,
                    spaceBetween: desktopSpaceBetween
                }
            },
            a11y: {
                prevSlideMessage: 'Anterior',
                nextSlideMessage: 'Próximo',
                firstSlideMessage: 'Primeiro',
                lastSlideMessage: 'Último'
            }
        };
    }

    function initSwiper($el, owlCfg) {
        if ($el.data('awaSwiperInit')) { return; }
        $el.data('awaSwiperInit', 1);

        require(['swiper'], function (Swiper) {
            if (window.requestAnimationFrame) {
                window.requestAnimationFrame(function () {
                    new Swiper($el[0], buildSwiperOptions(owlCfg));
                });
            } else {
                new Swiper($el[0], buildSwiperOptions(owlCfg));
            }
        });
    }

    return function (config, element) {
        var $scope = $(element),
            cfg = config || {},
            owlCfg = cfg.owl || cfg,
            carouselSel = cfg.carouselSelector || '.swiper',
            $el;

        $el = $scope.is(carouselSel) ? $scope : $scope.find(carouselSel).first();

        if (!$el.length || $el.data('awaSwiperInit')) {
            return;
        }

        /* Defer Swiper init until element enters viewport (or fallback) */
        if ('IntersectionObserver' in window) {
            var observer = new IntersectionObserver(function (entries) {
                for (var i = 0; i < entries.length; i++) {
                    if (entries[i].isIntersecting) {
                        observer.disconnect();
                        initSwiper($el, owlCfg);
                        break;
                    }
                }
            }, { rootMargin: '400px 0px' });
            observer.observe($el[0]);
        } else {
            initSwiper($el, owlCfg);
        }
    };
});
