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

    /* Queue curta de init — evita múltiplos new Swiper() no mesmo frame.
     * Objetivo: reduzir long tasks de layout/reflow no carregamento da home mobile. */
    var swiperInitQueue = [];
    var swiperInitRunning = false;
    var swiperCtor = null;
    var SWIPER_INIT_GAP_MS = 80;
    var MOBILE_VIEWPORT_QUERY = '(max-width: 767px)';
    var MOBILE_IDLE_TIMEOUT_MS = 4500;
    var MOBILE_FALLBACK_DELAY_MS = 900;
    var DESKTOP_INTERACTION_DELAY_MS = 5500;
    var MOBILE_INTERACTION_DELAY_MS = 6000;
    var interactionGateOpen = false;
    var interactionGateBound = false;
    var interactionQueue = [];

    function isMobileViewport() {
        return !!(window.matchMedia && window.matchMedia(MOBILE_VIEWPORT_QUERY).matches);
    }

    function runDeferredInit(callback) {
        if (isMobileViewport()) {
            if ('requestIdleCallback' in window) {
                window.requestIdleCallback(function () {
                    callback();
                }, { timeout: MOBILE_IDLE_TIMEOUT_MS });
                return;
            }

            window.setTimeout(callback, MOBILE_FALLBACK_DELAY_MS);
            return;
        }

        callback();
    }

    function isHomepageContext() {
        var body = document.body;

        if (!body) {
            return false;
        }

        return body.classList.contains('cms-index-index')
            || body.classList.contains('cms-home')
            || body.classList.contains('cms-homepage_ayo_home5');
    }

    function shouldDelayInit() {
        return isHomepageContext();
    }

    function flushInteractionGate() {
        interactionGateOpen = true;

        while (interactionQueue.length) {
            (interactionQueue.shift())();
        }
    }

    function bindInteractionGate() {
        if (interactionGateBound) {
            return;
        }

        interactionGateBound = true;

        var events = ['pointerdown', 'keydown', 'touchstart'];
        var release = function (event) {
            if (interactionGateOpen) {
                return;
            }

            if (event && event.isTrusted === false) {
                return;
            }

            events.forEach(function (eventName) {
                window.removeEventListener(eventName, release, true);
            });

            flushInteractionGate();
        };

        events.forEach(function (eventName) {
            window.addEventListener(eventName, release, true);
        });

        window.setTimeout(release, isMobileViewport() ? MOBILE_INTERACTION_DELAY_MS : DESKTOP_INTERACTION_DELAY_MS);
    }

    function loadSwiper(done) {
        if (swiperCtor) {
            done(swiperCtor);
            return;
        }

        require(['swiper'], function (Swiper) {
            swiperCtor = Swiper;
            done(Swiper);
        });
    }

    function runQueuedSwiperInit() {
        var item;

        if (swiperInitRunning || !swiperInitQueue.length) {
            return;
        }

        swiperInitRunning = true;
        item = swiperInitQueue.shift();

        loadSwiper(function (Swiper) {
            var execute = function () {
                new Swiper(item.$el[0], buildSwiperOptions(item.owlCfg));
                swiperInitRunning = false;

                if (swiperInitQueue.length) {
                    window.setTimeout(runQueuedSwiperInit, SWIPER_INIT_GAP_MS);
                }
            };

            if (window.requestAnimationFrame) {
                window.requestAnimationFrame(execute);
            } else {
                execute();
            }
        });
    }

    function enqueueSwiperInit($el, owlCfg) {
        swiperInitQueue.push({
            $el: $el,
            owlCfg: owlCfg
        });
        runQueuedSwiperInit();
    }

    function initSwiper($el, owlCfg) {
        if ($el.data('awaSwiperInit')) { return; }
        $el.data('awaSwiperInit', 1);

        var scheduleInit = function () {
            runDeferredInit(function () {
                enqueueSwiperInit($el, owlCfg);
            });
        };

        if (shouldDelayInit() && !interactionGateOpen) {
            interactionQueue.push(scheduleInit);
            bindInteractionGate();
            return;
        }

        scheduleInit();
    }

    function isNearViewport(element) {
        var rect;
        var viewportHeight;
        var preloadBand;

        if (!element || typeof element.getBoundingClientRect !== 'function') {
            return false;
        }

        rect = element.getBoundingClientRect();
        viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;
        if (isMobileViewport()) {
            preloadBand = Math.max(160, viewportHeight * 0.35);
        } else {
            preloadBand = Math.max(280, viewportHeight * 0.5);
        }

        return rect.top <= viewportHeight + preloadBand && rect.bottom >= -preloadBand;
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

        if (isNearViewport($el[0])) {
            initSwiper($el, owlCfg);
            return;
        }

        /* Defer Swiper init until element enters viewport (or fallback) */
        if ('IntersectionObserver' in window) {
            var rootMargin = isMobileViewport() ? '160px 0px' : '280px 0px';
            var observer = new IntersectionObserver(function (entries) {
                for (var i = 0; i < entries.length; i++) {
                    if (entries[i].isIntersecting) {
                        observer.disconnect();
                        initSwiper($el, owlCfg);
                        break;
                    }
                }
            }, { rootMargin: rootMargin });
            observer.observe($el[0]);
        } else {
            initSwiper($el, owlCfg);
        }
    };
});
