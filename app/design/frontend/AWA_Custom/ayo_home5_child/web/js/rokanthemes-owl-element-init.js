/* global define */

define([
    'jquery',
    'rokanthemes/owl'
], function ($) {
    'use strict';

    var V1_RESPONSIVE_KEYS = ['itemsDesktop', 'itemsDesktopSmall', 'itemsTablet', 'itemsMobile'];

    function resolveBoolean(value, fallback) {
        if (value === undefined || value === null || value === '') {
            return fallback;
        }
        if (typeof value === 'string') {
            return !(value === 'false' || value === '0');
        }
        return !!value;
    }

    /**
     * Convert OWL v1 options to v2 format.
     */
    function convertToV2(opts) {
        var v2 = {};
        var responsive = {};
        var hasV1Responsive = false;
        var mobileItems, tabletItems, desktopSmallItems, desktopItems;
        var i;

        for (i = 0; i < V1_RESPONSIVE_KEYS.length; i++) {
            if (opts[V1_RESPONSIVE_KEYS[i]] !== undefined) {
                hasV1Responsive = true;
                break;
            }
        }

        desktopItems = parseInt(opts.items, 10) || 3;

        if (hasV1Responsive) {
            mobileItems = 1;
            tabletItems = 2;
            desktopSmallItems = 3;

            if (opts.itemsMobile && opts.itemsMobile[1]) {
                mobileItems = parseInt(opts.itemsMobile[1], 10) || 1;
            }
            if (opts.itemsTablet && opts.itemsTablet[1]) {
                tabletItems = parseInt(opts.itemsTablet[1], 10) || 2;
            }
            if (opts.itemsDesktopSmall && opts.itemsDesktopSmall[1]) {
                desktopSmallItems = parseInt(opts.itemsDesktopSmall[1], 10) || 3;
            }
            if (opts.itemsDesktop && opts.itemsDesktop[1]) {
                desktopItems = parseInt(opts.itemsDesktop[1], 10) || desktopItems;
            }

            responsive[0] = { items: mobileItems };
            responsive[opts.itemsMobile && opts.itemsMobile[0] ? opts.itemsMobile[0] : 680] = { items: mobileItems };
            responsive[opts.itemsTablet && opts.itemsTablet[0] ? opts.itemsTablet[0] : 991] = { items: tabletItems };
            responsive[opts.itemsDesktopSmall && opts.itemsDesktopSmall[0] ? opts.itemsDesktopSmall[0] : 1199] = { items: desktopSmallItems };
            responsive[opts.itemsDesktop && opts.itemsDesktop[0] ? opts.itemsDesktop[0] : 1366] = { items: desktopItems };

            v2.responsive = responsive;
        } else if (opts.responsive && Object.keys(opts.responsive).length > 0) {
            v2.responsive = opts.responsive;
        } else {
            /* Auto-generate responsive breakpoints from items count */
            mobileItems = Math.max(1, Math.min(desktopItems, 1));
            tabletItems = Math.max(1, Math.min(desktopItems, 2));
            desktopSmallItems = Math.max(1, Math.min(desktopItems, 3));

            responsive[0] = { items: mobileItems };
            responsive[480] = { items: Math.max(1, Math.min(desktopItems, 2)) };
            responsive[768] = { items: tabletItems };
            responsive[992] = { items: desktopSmallItems };
            responsive[1200] = { items: desktopItems };

            v2.responsive = responsive;
        }

        v2.items = desktopItems;
        v2.nav = resolveBoolean(opts.navigation, false);
        v2.navText = ['<span aria-label="Anterior">&#8249;</span>', '<span aria-label="Próximo">&#8250;</span>'];
        v2.dots = resolveBoolean(opts.pagination, false);
        v2.autoplay = resolveBoolean(opts.autoPlay, false);
        v2.autoplayHoverPause = resolveBoolean(opts.stopOnHover, true);
        v2.slideBy = resolveBoolean(opts.scrollPerPage, true) ? 'page' : 1;
        v2.smartSpeed = parseInt(opts.slideSpeed, 10) || 250;
        v2.lazyLoad = resolveBoolean(opts.lazyLoad, false);
        v2.loop = resolveBoolean(opts.loop, false);
        v2.margin = parseInt(opts.margin, 10) || 0;

        return v2;
    }

    function normalizeOptions(rawOptions) {
        return convertToV2(rawOptions || {});
    }

    function initWhenReady($el, options, attemptsLeft) {
        var remaining = (attemptsLeft !== undefined) ? attemptsLeft : 8;

        if (!$el || !$el.length) {
            return;
        }

        if (typeof $el.owlCarousel === 'function') {
            try {
                $el.owlCarousel(options);
                $el.addClass('owl-loaded');
                /* Add .owl-carousel after init for unified CSS targeting */
                if (!$el.hasClass('owl-carousel')) {
                    $el.addClass('owl-carousel');
                }
            } catch (e) {
                $el.removeData('awaOwlElementInit');
            }
            return;
        }

        if (remaining <= 0) {
            $el.removeData('awaOwlElementInit');
            return;
        }

        setTimeout(function () {
            initWhenReady($el, options, remaining - 1);
        }, 150);
    }

    /**
     * Magento initializer for Owl Carousel v1.
     *
     * Pattern A — bind directly to the .owl element:
     *   ".rokan-featuredproduct .owl": {
     *     "js/rokanthemes-owl-element-init": { items: 4, navigation: true, ... }
     *   }
     *
     * Pattern B — bind to container, find carousel inside via carouselSelector:
     *   ".rokan-featuredproduct": {
     *     "js/rokanthemes-owl-element-init": {
     *       "carouselSelector": ".owl",
     *       "owl": { items: 4, navigation: true, ... }
     *     }
     *   }
     */
    return function (config, element) {
        var cfg = config || {};
        var $container = $(element);
        var $el, owlOptions;

        if (!$container.length) {
            return;
        }

        if (cfg.carouselSelector) {
            /* Pattern B */
            $el = $container.find(cfg.carouselSelector).first();
            owlOptions = normalizeOptions(cfg.owl ? $.extend({}, cfg.owl) : {});
        } else {
            /* Pattern A — element IS the carousel */
            $el = $container;
            owlOptions = normalizeOptions($.extend({}, cfg));
        }

        if (!$el.length) {
            return;
        }

        if ($el.data('owl.carousel') || $el.data('owlCarousel') || $el.hasClass('owl-loaded') || $el.data('awaOwlElementInit')) {
            return;
        }

        $el.data('awaOwlElementInit', 1);
        initWhenReady($el, owlOptions, 8);
    };
});
