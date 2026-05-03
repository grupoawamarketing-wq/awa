/* global define */

define([
    'jquery',
    'swiper'
], function ($, Swiper) {
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
     * Convert OWL v1/v2 legacy options to Swiper format.
     */
    function convertToSwiper(opts) {
        var desktopItems = parseInt(opts.items || opts.itemsDesktop || 3, 10) || 3;
        var swiperConfig = {
            slidesPerView: desktopItems,
            spaceBetween: parseInt(opts.margin, 10) || parseInt(opts.slideMargin, 10) || 0,
            // Keep loop disabled to prevent Swiper loopFix warnings on short carousels.
            loop: false,
            autoHeight: resolveBoolean(opts.autoHeight, false),
            breakpoints: {}
        };

        var autoplayAttr = opts.autoPlay || opts.autoplay;
        if (autoplayAttr && autoplayAttr !== 'false' && autoplayAttr !== '0') {
            var delay = typeof autoplayAttr === 'number' ? autoplayAttr : (parseInt(opts.slideSpeed, 10) || parseInt(opts.autoplayTimeout, 10) || 5000);
            swiperConfig.autoplay = {
                delay: Math.max(delay, 500),
                disableOnInteraction: false,
                pauseOnMouseEnter: resolveBoolean(opts.stopOnHover, true) || resolveBoolean(opts.autoplayHoverPause, true)
            };
        }

        var speed = parseInt(opts.slideSpeed, 10) || parseInt(opts.smartSpeed, 10);
        if (speed) {
            swiperConfig.speed = speed;
        }

        var nav = resolveBoolean(opts.navigation, false) || resolveBoolean(opts.nav, false);
        var dots = resolveBoolean(opts.pagination, false) || resolveBoolean(opts.dots, false);
        
        if (nav) {
            swiperConfig.navigation = {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev'
            };
        }
        if (dots) {
            swiperConfig.pagination = {
                el: '.swiper-pagination',
                clickable: true
            };
        }

        var hasV1Responsive = V1_RESPONSIVE_KEYS.some(function(k) { return opts[k] !== undefined; });
        var res = {};
        
        if (hasV1Responsive) {
            var mItems = 1, tItems = 2, dsItems = 3;
            if (opts.itemsMobile && opts.itemsMobile[1]) mItems = parseInt(opts.itemsMobile[1], 10);
            if (opts.itemsTablet && opts.itemsTablet[1]) tItems = parseInt(opts.itemsTablet[1], 10);
            if (opts.itemsDesktopSmall && opts.itemsDesktopSmall[1]) dsItems = parseInt(opts.itemsDesktopSmall[1], 10);
            
            res[0] = { slidesPerView: mItems };
            res[opts.itemsMobile && opts.itemsMobile[0] ? opts.itemsMobile[0] : 480] = { slidesPerView: Math.max(1, mItems) };
            res[opts.itemsTablet && opts.itemsTablet[0] ? opts.itemsTablet[0] : 768] = { slidesPerView: tItems };
            res[opts.itemsDesktopSmall && opts.itemsDesktopSmall[0] ? opts.itemsDesktopSmall[0] : 992] = { slidesPerView: dsItems };
            res[opts.itemsDesktop && opts.itemsDesktop[0] ? opts.itemsDesktop[0] : 1200] = { slidesPerView: desktopItems };
        } else if (opts.responsive && Object.keys(opts.responsive).length > 0) {
            for (var bp in opts.responsive) {
                if (opts.responsive.hasOwnProperty(bp)) {
                    res[bp] = { slidesPerView: parseInt(opts.responsive[bp].items, 10) || 1 };
                }
            }
        } else {
            res[0] = { slidesPerView: Math.max(1, Math.min(desktopItems, 1)) };
            res[480] = { slidesPerView: Math.max(1, Math.min(desktopItems, 2)) };
            res[768] = { slidesPerView: Math.max(1, Math.min(desktopItems, 2)) };
            res[992] = { slidesPerView: Math.max(1, Math.min(desktopItems, 3)) };
            res[1200] = { slidesPerView: desktopItems };
        }
        swiperConfig.breakpoints = res;

        return swiperConfig;
    }

    function initSwiperShim($el, options) {
        if (!$el || !$el.length || $el.hasClass('swiper-initialized')) {
            return;
        }

        $el.removeClass('owl').addClass('swiper owl-carousel-shim');
        if (!$el.find('> .swiper-wrapper').length) {
            $el.wrapInner('<div class="swiper-wrapper"></div>');
        }
        $el.find('> .swiper-wrapper > *').each(function() {
            var $child = $(this);
            if (!$child.hasClass('swiper-slide')) {
                $child.addClass('swiper-slide');
            }
        });

        if (options.navigation && !$el.find('.swiper-button-next').length) {
            $el.append('<div class="swiper-button-prev"></div><div class="swiper-button-next"></div>');
        }
        if (options.pagination && !$el.find('.swiper-pagination').length) {
            $el.append('<div class="swiper-pagination"></div>');
        }

        setTimeout(function () {
            try {
                // Force loop off right before init to avoid late option mutations.
                var finalOptions = Object.assign({}, options, { loop: false });
                if (finalOptions.autoplay) {
                    finalOptions.autoplay = Object.assign({}, finalOptions.autoplay);
                }
                new Swiper($el[0], finalOptions);
                $el.addClass('owl-loaded swiper-loaded'); // Keep legacy flag happy
            } catch (e) {
                // Swiper init failed — element may not be ready or config is invalid
            }
        }, 100);
    }

    return function (config, element) {
        var cfg = config || {};
        var $container = $(element);
        var $el, swiperOptions;

        if (!$container.length) {
            return;
        }

        if (cfg.carouselSelector) {
            $el = $container.find(cfg.carouselSelector).first();
            swiperOptions = convertToSwiper(cfg.owl ? $.extend({}, cfg.owl) : {});
        } else {
            $el = $container;
            swiperOptions = convertToSwiper($.extend({}, cfg));
        }

        if (!$el.length) {
            return;
        }

        initSwiperShim($el, swiperOptions);
    };
});
