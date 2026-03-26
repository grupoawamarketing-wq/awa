/* global define, window */
/**
 * AWA Motos — Superdeals Swiper Init
 *
 * Drop-in replacement para superdeals-init.js (parent theme):
 * - Substitui Owl Carousel por Swiper 11
 * - Preserva TimeCircles countdown (inalterado)
 *
 * Uso em text/x-magento-init:
 *   ".hot-deal-tab-slider-customcss": {
 *       "js/superdeals-swiper-init": {
 *           "carouselSelector": ".hot-deal-slide",
 *           "countdownSelector": ".super-deal-countdown",
 *           "owl": { ... },
 *           "countdown": { ... },
 *           "labels": { ... }
 *       }
 *   }
 */
define([
    'jquery'
], function ($) {
    'use strict';

    function buildSwiperOptions(owlCfg) {
        var items = parseInt(owlCfg.items, 10) || 4,
            mobileItems = 1,
            tabletItems = Math.min(items, 2),
            desktopSmallItems = Math.min(items, 3),
            desktopItems = items;

        if (owlCfg.itemsMobile && owlCfg.itemsMobile[1]) {
            mobileItems = parseInt(owlCfg.itemsMobile[1], 10) || 1;
        }
        if (owlCfg.itemsTablet && owlCfg.itemsTablet[1]) {
            tabletItems = parseInt(owlCfg.itemsTablet[1], 10) || tabletItems;
        }
        if (owlCfg.itemsDesktopSmall && owlCfg.itemsDesktopSmall[1]) {
            desktopSmallItems = parseInt(owlCfg.itemsDesktopSmall[1], 10) || desktopSmallItems;
        }
        if (owlCfg.itemsDesktop && owlCfg.itemsDesktop[1]) {
            desktopItems = parseInt(owlCfg.itemsDesktop[1], 10) || desktopItems;
        }

        return {
            slidesPerView: mobileItems,
            spaceBetween: 16,
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev'
            },
            pagination: owlCfg.pagination ? {
                el: '.swiper-pagination',
                clickable: true
            } : false,
            autoplay: owlCfg.autoPlay ? {
                delay: parseInt(owlCfg.slideSpeed, 10) || 5000,
                disableOnInteraction: true,
                pauseOnMouseEnter: true
            } : false,
            loop: false,
            watchOverflow: true,
            breakpoints: {
                480: { slidesPerView: Math.max(mobileItems, 2), spaceBetween: 12 },
                768: { slidesPerView: tabletItems, spaceBetween: 16 },
                992: { slidesPerView: desktopSmallItems, spaceBetween: 16 },
                1200: { slidesPerView: desktopItems, spaceBetween: 20 }
            },
            a11y: {
                prevSlideMessage: 'Anterior',
                nextSlideMessage: 'Próximo'
            }
        };
    }

    return function (config, element) {
        var $scope = $(element),
            cfg = config || {},
            carouselSel = cfg.carouselSelector || '.hot-deal-slide',
            countdownSel = cfg.countdownSelector || '.super-deal-countdown',
            owlCfg = cfg.owl || {},
            labels = cfg.labels || {},
            countdownCfg = cfg.countdown || {};

        function doInit() {
            require(['swiper', 'rokanthemes/timecircles'], function (Swiper) {
                /* ─── Swiper init ─── */
                $scope.find(carouselSel).each(function () {
                    var el = this,
                        $el = $(el);

                    if ($el.data('awaSuperdealsSwiper')) { return; }
                    $el.data('awaSuperdealsSwiper', 1);

                    if (window.requestAnimationFrame) {
                        window.requestAnimationFrame(function () {
                            new Swiper(el, buildSwiperOptions(owlCfg));
                        });
                    } else {
                        new Swiper(el, buildSwiperOptions(owlCfg));
                    }
                });

                /* ─── TimeCircles countdown ─── */
                $scope.find(countdownSel).each(function () {
                    var $countdown = $(this);

                    if ($countdown.data('awaSuperdealsCountdownInit') || typeof $countdown.TimeCircles !== 'function') {
                        return;
                    }

                    $countdown.data('awaSuperdealsCountdownInit', 1);
                    $countdown.TimeCircles({
                        fg_width: parseFloat(countdownCfg.fg_width) || 0.01,
                        bg_width: parseFloat(countdownCfg.bg_width) || 1.2,
                        text_size: parseFloat(countdownCfg.text_size) || 0.07,
                        circle_bg_color: countdownCfg.circle_bg_color || '#ffffff',
                        time: {
                            Days: { show: true, text: labels.days || 'Days', color: '#f9bc02' },
                            Hours: { show: true, text: labels.hours || 'Hours', color: '#f9bc02' },
                            Minutes: { show: true, text: labels.minutes || 'Mins', color: '#f9bc02' },
                            Seconds: { show: true, text: labels.seconds || 'Secs', color: '#f9bc02' }
                        }
                    });
                });
            });
        }

        /* Defer until element enters viewport */
        if ('IntersectionObserver' in window) {
            var observer = new IntersectionObserver(function (entries) {
                for (var i = 0; i < entries.length; i++) {
                    if (entries[i].isIntersecting) {
                        observer.disconnect();
                        doInit();
                        break;
                    }
                }
            }, { rootMargin: '400px 0px' });
            observer.observe($scope[0]);
        } else {
            doInit();
        }
    };
});
