/**
 * superdeals-init.js — AWA override do tema pai (ayo/ayo_home5)
 *
 * Mudança perf (2026-05-02): rokanthemes/timecircles removido das deps EAGER do define().
 * Lazy-load via require() somente quando há elementos countdown reais na página.
 */
define([
    'jquery',
    'rokanthemes/owl'
], function ($) {
    'use strict';

    function resolveBoolean(value, fallback) {
        if (value === undefined || value === null || value === '') { return fallback; }
        if (typeof value === 'string') { return !(value === 'false' || value === '0'); }
        return !!value;
    }

    return function (config, element) {
        var $scope = $(element);
        let carouselSelector = config.carouselSelector || 'ul.owl.awa-carousel__track';
        let countdownSelector = config.countdownSelector || '.super-deal-countdown';
        let owlConfig = config.owl || {};
        let labels = config.labels || {};
        let countdownConfig = config.countdown || {};
        let carouselOptions = {
            lazyLoad: resolveBoolean(owlConfig.lazyLoad, true),
            items: parseInt(owlConfig.items, 10) || 5,
            itemsDesktop: owlConfig.itemsDesktop || [1366, 5],
            itemsDesktopSmall: owlConfig.itemsDesktopSmall || [1024, 4],
            itemsTablet: owlConfig.itemsTablet || [768, 3],
            itemsMobile: owlConfig.itemsMobile || [479, 2],
            navigation: resolveBoolean(owlConfig.navigation, true),
            pagination: resolveBoolean(owlConfig.pagination, false),
            autoPlay: resolveBoolean(owlConfig.autoPlay, false),
            afterAction: function () {
                if (this.$owlItems && this.$owlItems.length) {
                    this.$owlItems.removeClass('first-active');
                    this.$owlItems.eq(this.currentItem).addClass('first-active');
                }
            }
        };

        if (window.AWA_SHELF_CAROUSEL && typeof window.AWA_SHELF_CAROUSEL.scan === 'function') {
            $scope.addClass('awa-shelf awa-shelf--carousel awa-shelf--has-countdown');
            window.AWA_SHELF_CAROUSEL.scan($scope[0]);
            if (typeof window.AWA_SHELF_CAROUSEL.scheduleEqualize === 'function') {
                window.AWA_SHELF_CAROUSEL.scheduleEqualize($scope[0]);
            }
        } else {
            $scope.find(carouselSelector).each(function () {
                var $carousel = $(this);
                if ($carousel.data('owlCarousel') || $carousel.hasClass('owl-loaded') || $carousel.data('awaSuperdealsCarouselInit')) { return; }
                $carousel.data('awaSuperdealsCarouselInit', 1);
                if (typeof $carousel.owlCarousel !== 'function') { $carousel.removeData('awaSuperdealsCarouselInit'); return; }
                $carousel.owlCarousel(carouselOptions);
            });
        }

        // Lazy-load timecircles somente quando há elementos countdown reais na página
        var $countdownEls = $scope.find(countdownSelector);
        if ($countdownEls.length > 0) {
            require(['rokanthemes/timecircles'], function () {
                $countdownEls.each(function () {
                    var $countdown = $(this);
                    if ($countdown.data('awaSuperdealsCountdownInit') || typeof $countdown.TimeCircles !== 'function') { return; }
                    $countdown.data('awaSuperdealsCountdownInit', 1);
                    $countdown.TimeCircles({
                        fg_width: parseFloat(countdownConfig.fg_width) || 0.01,
                        bg_width: parseFloat(countdownConfig.bg_width) || 1.2,
                        text_size: parseFloat(countdownConfig.text_size) || 0.07,
                        circle_bg_color: countdownConfig.circle_bg_color || '#ffffff',
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
    };
});
