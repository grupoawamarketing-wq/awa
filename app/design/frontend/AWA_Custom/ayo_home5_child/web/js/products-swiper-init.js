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
    'jquery',
    'swiper'
], function ($, Swiper) {
    'use strict';

    function buildSwiperOptions(cfg) {
        var items = parseInt(cfg.items, 10) || 4,
            mobileItems = 1,
            tabletItems = Math.min(items, 2),
            desktopSmallItems = Math.min(items, 3),
            desktopItems = items;

        if (cfg.itemsMobile && cfg.itemsMobile[1]) {
            mobileItems = parseInt(cfg.itemsMobile[1], 10) || 1;
        }
        if (cfg.itemsTablet && cfg.itemsTablet[1]) {
            tabletItems = parseInt(cfg.itemsTablet[1], 10) || tabletItems;
        }
        if (cfg.itemsDesktopSmall && cfg.itemsDesktopSmall[1]) {
            desktopSmallItems = parseInt(cfg.itemsDesktopSmall[1], 10) || desktopSmallItems;
        }
        if (cfg.itemsDesktop && cfg.itemsDesktop[1]) {
            desktopItems = parseInt(cfg.itemsDesktop[1], 10) || desktopItems;
        }

        return {
            slidesPerView: mobileItems,
            spaceBetween: parseInt(cfg.margin, 10) || 16,
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev'
            },
            pagination: cfg.pagination ? {
                el: '.swiper-pagination',
                clickable: true
            } : false,
            autoplay: cfg.autoPlay ? {
                delay: parseInt(cfg.slideSpeed, 10) || 5000,
                disableOnInteraction: true,
                pauseOnMouseEnter: true
            } : false,
            loop: false,
            watchOverflow: true,
            breakpoints: {
                480: { slidesPerView: Math.max(mobileItems, 2), spaceBetween: 12 },
                768: { slidesPerView: tabletItems, spaceBetween: 16 },
                992: { slidesPerView: desktopSmallItems, spaceBetween: 16 },
                1200: { slidesPerView: desktopItems, spaceBetween: 20 },
                1280: { slidesPerView: Math.min(desktopItems + 1, 5), spaceBetween: 20 }
            },
            a11y: {
                prevSlideMessage: 'Anterior',
                nextSlideMessage: 'Próximo',
                firstSlideMessage: 'Primeiro',
                lastSlideMessage: 'Último'
            }
        };
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

        $el.data('awaSwiperInit', 1);

        if (window.requestAnimationFrame) {
            window.requestAnimationFrame(function () {
                new Swiper($el[0], buildSwiperOptions(owlCfg));
            });
        } else {
            new Swiper($el[0], buildSwiperOptions(owlCfg));
        }
    };
});
