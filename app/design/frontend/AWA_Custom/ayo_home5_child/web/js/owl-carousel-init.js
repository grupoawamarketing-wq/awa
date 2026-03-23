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
            /* Add .owl-carousel after init for CSS nav arrow targeting */
            if (!$carousel.hasClass('owl-carousel')) {
                $carousel.addClass('owl-carousel');
            }
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
        var desktopItems = parseInt(owlConfig.items, 10) || 4;

        /* OWL v2 responsive format — maps v1 breakpoints to v2 */
        var responsive = {};

        if (owlConfig.responsive && typeof owlConfig.responsive === 'object' && Object.keys(owlConfig.responsive).length > 0) {
            responsive = owlConfig.responsive;
        } else {
            var mobileItems = 1;
            var tabletItems = Math.min(desktopItems, 2);
            var desktopSmallItems = Math.min(desktopItems, 3);

            /* Read v1 keys if provided */
            if (owlConfig.itemsMobile && owlConfig.itemsMobile[1]) {
                mobileItems = parseInt(owlConfig.itemsMobile[1], 10) || 1;
            }
            if (owlConfig.itemsTablet && owlConfig.itemsTablet[1]) {
                tabletItems = parseInt(owlConfig.itemsTablet[1], 10) || tabletItems;
            }
            if (owlConfig.itemsDesktopSmall && owlConfig.itemsDesktopSmall[1]) {
                desktopSmallItems = parseInt(owlConfig.itemsDesktopSmall[1], 10) || desktopSmallItems;
            }
            if (owlConfig.itemsDesktop && owlConfig.itemsDesktop[1]) {
                desktopItems = parseInt(owlConfig.itemsDesktop[1], 10) || desktopItems;
            }

            responsive[0] = { items: mobileItems };
            responsive[480] = { items: Math.max(1, Math.min(desktopItems, 2)) };
            responsive[768] = { items: tabletItems };
            responsive[992] = { items: desktopSmallItems };
            responsive[1200] = { items: desktopItems };
        }

        var options = {
            items: desktopItems,
            responsive: responsive,
            margin: parseInt(owlConfig.margin, 10) || 0,
            nav: resolveBoolean(owlConfig.navigation, true),
            navText: ['<span aria-label="Anterior">&#8249;</span>', '<span aria-label="Próximo">&#8250;</span>'],
            dots: resolveBoolean(owlConfig.pagination, false),
            autoplay: resolveBoolean(owlConfig.autoPlay, false),
            autoplayHoverPause: resolveBoolean(owlConfig.stopOnHover, true),
            slideBy: resolveBoolean(owlConfig.scrollPerPage, true) ? 'page' : 1,
            smartSpeed: parseInt(owlConfig.slideSpeed, 10) || 500,
            lazyLoad: resolveBoolean(owlConfig.lazyLoad, true),
            loop: resolveBoolean(owlConfig.loop, false)
        };

        $scope.find(carouselSelector).each(function () {
            var $carousel = $(this);

            if ($carousel.data('owl.carousel') || $carousel.hasClass('owl-loaded') || $carousel.data(dataFlag)) {
                return;
            }

            $carousel.data(dataFlag, 1);
            initWhenVisible($carousel, options, 8, 120, dataFlag);
        });
    };
});
