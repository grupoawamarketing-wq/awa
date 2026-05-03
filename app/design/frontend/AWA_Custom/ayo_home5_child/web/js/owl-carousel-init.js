/* global define, setTimeout */

define([
    'jquery',
    'rokanthemes/owl'
], function ($) {
    'use strict';

    function initWhenVisible($carousel, options, attemptsLeft, delayMs, dataFlag) {
        var delay = delayMs || 120;
        var remaining = attemptsLeft || 8;

        if (!$carousel || !$carousel.length) {
            return;
        }

        if (typeof $carousel.owlCarousel !== 'function') {
            if (remaining <= 0) {
                if (dataFlag) {
                    $carousel.removeData(dataFlag);
                }
                return;
            }

            setTimeout(function () {
                initWhenVisible($carousel, options, remaining - 1, delay, dataFlag);
            }, delay);
            return;
        }

        // Owl v1 calcula larguras na inicialização. Se o container estiver oculto
        // (tabs/sections lazy) ou width=0, o resultado costuma ser itens estreitos.
        if (!$carousel.is(':visible') || $carousel.width() < 10) {
            if (remaining <= 0) {
                if (dataFlag) {
                    $carousel.removeData(dataFlag);
                }
                return;
            }

            setTimeout(function () {
                initWhenVisible($carousel, options, remaining - 1, delay, dataFlag);
            }, delay);
            return;
        }

        try {
            $carousel.owlCarousel(options);
        } catch (error) {
            if (dataFlag) {
                $carousel.removeData(dataFlag);
            }
        }
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

    return function (config, element) {
        var $scope = $(element);
        var carouselSelector = config.carouselSelector || '.owl';
        var owlConfig = config.owl || {};
        var dataFlag = config.dataFlag || 'awaOwlInit';
        var withFirstActive = resolveBoolean(owlConfig.withFirstActive, true);
        var options = {
            lazyLoad: resolveBoolean(owlConfig.lazyLoad, true),
            autoPlay: resolveBoolean(owlConfig.autoPlay, false),
            navigation: resolveBoolean(owlConfig.navigation, true),
            pagination: resolveBoolean(owlConfig.pagination, false),
            stopOnHover: resolveBoolean(owlConfig.stopOnHover, true),
            scrollPerPage: resolveBoolean(owlConfig.scrollPerPage, true),
            items: parseInt(owlConfig.items, 10) || 4,
            itemsDesktop: owlConfig.itemsDesktop || [1199, 4],
            itemsDesktopSmall: owlConfig.itemsDesktopSmall || [980, 3],
            itemsTablet: owlConfig.itemsTablet || [768, 2],
            itemsMobile: owlConfig.itemsMobile || [479, 1],
            slideSpeed: parseInt(owlConfig.slideSpeed, 10) || 500,
            paginationSpeed: parseInt(owlConfig.paginationSpeed, 10) || 500,
            rewindSpeed: parseInt(owlConfig.rewindSpeed, 10) || 500
        };

        if (withFirstActive) {
            options.afterAction = function () {
                if (this.$owlItems && this.$owlItems.length) {
                    this.$owlItems.removeClass('first-active');
                    this.$owlItems.eq(this.currentItem).addClass('first-active');
                }
            };
        }

        $scope.find(carouselSelector).each(function () {
            var $carousel = $(this);

            if ($carousel.data('owlCarousel') || $carousel.hasClass('owl-loaded') || $carousel.data(dataFlag)) {
                return;
            }

            $carousel.data(dataFlag, 1);
            initWhenVisible($carousel, options, 8, 120, dataFlag);
        });
    };
});
