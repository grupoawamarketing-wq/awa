/* global define, window, setTimeout, clearTimeout */
/**
 * AWA Motos — Unified Carousel Module (Swiper 11)
 *
 * Replaces: products-swiper-init, tab-swiper-init, superdeals-swiper-init.
 * Auto-detects mode from config:
 *   - tabsSelector present → tab mode (ARIA tabs + carousel per panel)
 *   - countdownSelector present → superdeals mode (carousel + TimeCircles)
 *   - neither → simple mode (single carousel)
 *
 * Usage:
 *   "js/awa-carousel": {
 *       "carouselSelector": ".swiper",
 *       "owl": { "items": 4, "itemsDesktop": [1366, 4], ... }
 *   }
 *
 *   "js/awa-carousel": {
 *       "tabsSelector": "ul.tabs li",
 *       "contentSelector": ".tab_content",
 *       "carouselSelector": ".swiper",
 *       "owl": { ... }
 *   }
 *
 *   "js/awa-carousel": {
 *       "carouselSelector": ".hot-deal-slide",
 *       "countdownSelector": ".super-deal-countdown",
 *       "owl": { ... },
 *       "countdown": { ... },
 *       "labels": { ... }
 *   }
 */
define([
    'jquery'
], function ($) {
    'use strict';

    /* ═══════════════════════════════════════════════════════════════
       SHARED UTILITIES
       ═══════════════════════════════════════════════════════════════ */

    var MOBILE_QUERY = '(max-width: 767px)';

    function normalizeCount(value, fallback, max) {
        var count = parseInt(value, 10);
        if (isNaN(count) || count < 1) { count = fallback; }
        if (typeof max === 'number') { count = Math.min(count, max); }
        return count;
    }

    function resolveBoolean(value, fallback) {
        if (value === undefined || value === null || value === '') { return fallback; }
        if (typeof value === 'string') { return !(value === 'false' || value === '0'); }
        return !!value;
    }

    function debounce(fn, wait) {
        var timer;
        return function () {
            var ctx = this, args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function () { fn.apply(ctx, args); }, wait || 120);
        };
    }

    function raf(cb) {
        if (window.requestAnimationFrame) { window.requestAnimationFrame(cb); }
        else { setTimeout(cb, 16); }
    }

    function isMobile() {
        return !!(window.matchMedia && window.matchMedia(MOBILE_QUERY).matches);
    }

    /* ═══════════════════════════════════════════════════════════════
       SWIPER OPTIONS BUILDER (unified breakpoints)
       ═══════════════════════════════════════════════════════════════ */

    function buildSwiperOptions(cfg) {
        var items = normalizeCount(cfg.items, 4, 4),
            mobileItems = 1,
            tabletItems = Math.min(items, 3),
            desktopSmallItems = Math.min(items, 4),
            desktopItems = items,
            baseSpace = parseInt(cfg.margin, 10),
            tabletSpace = parseInt(cfg.tabletSpaceBetween, 10),
            desktopSpace = parseInt(cfg.desktopSpaceBetween, 10),
            scrollPerPage = resolveBoolean(cfg.scrollPerPage, true);

        if (isNaN(baseSpace)) { baseSpace = 12; }
        if (isNaN(tabletSpace)) { tabletSpace = 14; }
        if (isNaN(desktopSpace)) { desktopSpace = 16; }

        /* Owl v1 backward-compat keys */
        if (cfg.itemsMobile && cfg.itemsMobile[1]) {
            mobileItems = normalizeCount(cfg.itemsMobile[1], 1, 2);
        }
        if (cfg.itemsTablet && cfg.itemsTablet[1]) {
            tabletItems = normalizeCount(cfg.itemsTablet[1], tabletItems, 4);
        }
        if (cfg.itemsDesktopSmall && cfg.itemsDesktopSmall[1]) {
            desktopSmallItems = normalizeCount(cfg.itemsDesktopSmall[1], desktopSmallItems, 4);
        }
        if (cfg.itemsDesktop && cfg.itemsDesktop[1]) {
            desktopItems = normalizeCount(cfg.itemsDesktop[1], desktopItems, 4);
        }

        var landscapeItems = Math.min(Math.max(mobileItems, 2), items);

        return {
            slidesPerView: mobileItems,
            slidesPerGroup: scrollPerPage ? mobileItems : 1,
            spaceBetween: baseSpace,
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
                    slidesPerView: landscapeItems,
                    slidesPerGroup: scrollPerPage ? landscapeItems : 1,
                    spaceBetween: 12
                },
                768: {
                    slidesPerView: tabletItems,
                    slidesPerGroup: scrollPerPage ? tabletItems : 1,
                    spaceBetween: tabletSpace
                },
                992: {
                    slidesPerView: desktopSmallItems,
                    slidesPerGroup: scrollPerPage ? desktopSmallItems : 1,
                    spaceBetween: desktopSpace
                },
                1200: {
                    slidesPerView: desktopItems,
                    slidesPerGroup: scrollPerPage ? desktopItems : 1,
                    spaceBetween: desktopSpace
                }
            },
            a11y: {
                prevSlideMessage: 'Slide anterior',
                nextSlideMessage: 'Próximo slide',
                firstSlideMessage: 'Primeiro slide',
                lastSlideMessage: 'Último slide'
            }
        };
    }

    /* ═══════════════════════════════════════════════════════════════
       SWIPER LOADER (singleton, lazy require)
       ═══════════════════════════════════════════════════════════════ */

    var SwiperCtor = null;

    function loadSwiper(done) {
        if (SwiperCtor) { done(SwiperCtor); return; }
        require(['swiper'], function (S) { SwiperCtor = S; done(S); });
    }

    /* ═══════════════════════════════════════════════════════════════
       INIT QUEUE — stagger Swiper instantiation to reduce long tasks
       ═══════════════════════════════════════════════════════════════ */

    var initQueue = [];
    var queueRunning = false;
    var QUEUE_GAP_MS = 80;

    function processQueue() {
        if (queueRunning || !initQueue.length) { return; }
        queueRunning = true;
        var item = initQueue.shift();

        loadSwiper(function (Swiper) {
            var go = function () {
                if (!item.$el.data('awaSwiperInit')) {
                    item.$el.data('awaSwiperInit', 1);
                    try { new Swiper(item.$el[0], item.opts); } catch (e) { item.$el.removeData('awaSwiperInit'); }
                }
                queueRunning = false;
                if (initQueue.length) { setTimeout(processQueue, QUEUE_GAP_MS); }
            };
            raf(go);
        });
    }

    function enqueueInit($el, opts) {
        if ($el.data('awaSwiperInit')) { return; }
        initQueue.push({ $el: $el, opts: opts });
        processQueue();
    }

    /* ═══════════════════════════════════════════════════════════════
       HOMEPAGE INTERACTION GATE — defer below-fold init on homepage
       ═══════════════════════════════════════════════════════════════ */

    var gateOpen = false;
    var gateBound = false;
    var gateQueue = [];
    var DESKTOP_GATE_MS = 1500;
    var MOBILE_GATE_MS = 2000;
    var MOBILE_IDLE_MS = 4500;
    var MOBILE_FALLBACK_MS = 900;

    function isHomepage() {
        var b = document.body;
        return b && (b.classList.contains('cms-index-index') ||
                     b.classList.contains('cms-home') ||
                     b.classList.contains('cms-homepage_ayo_home5'));
    }

    function flushGate() {
        gateOpen = true;
        while (gateQueue.length) { (gateQueue.shift())(); }
    }

    function bindGate() {
        if (gateBound) { return; }
        gateBound = true;
        var evts = ['pointerdown', 'keydown', 'touchstart', 'scroll', 'click'];
        var release = function (e) {
            if (gateOpen || (e && e.isTrusted === false)) { return; }
            evts.forEach(function (n) { window.removeEventListener(n, release, true); });
            flushGate();
        };
        evts.forEach(function (n) { window.addEventListener(n, release, true); });
        setTimeout(release, isMobile() ? MOBILE_GATE_MS : DESKTOP_GATE_MS);
    }

    function deferredInit(callback) {
        if (isMobile() && 'requestIdleCallback' in window) {
            window.requestIdleCallback(function () { callback(); }, { timeout: MOBILE_IDLE_MS });
        } else if (isMobile()) {
            setTimeout(callback, MOBILE_FALLBACK_MS);
        } else {
            callback();
        }
    }

    /* ═══════════════════════════════════════════════════════════════
       TAB SYSTEM (ARIA keyboard-navigable tabs with lazy loading)
       ═══════════════════════════════════════════════════════════════ */

    function handleKeyboardTabs(key, currentTab, moveToTab, activateAndNotify) {
        if (key === 13 || key === 32) { activateAndNotify($(currentTab)); return true; }
        if (key === 37) { moveToTab(currentTab, 'prev'); return true; }
        if (key === 39) { moveToTab(currentTab, 'next'); return true; }
        if (key === 36) { moveToTab(currentTab, 'first'); return true; }
        if (key === 35) { moveToTab(currentTab, 'last'); return true; }
        return false;
    }

    function getTargetTabIndex(dir, cur, total) {
        if (dir === 'first') { return 0; }
        if (dir === 'last') { return total - 1; }
        if (dir === 'next') { return (cur + 1) % total; }
        return (cur - 1 + total) % total;
    }

    function moveToTab($scope, sel, tab, dir, activateFn) {
        var $all = $scope.find(sel), idx = $all.index(tab);
        if (!$all.length || idx < 0) { return; }
        var $next = $all.eq(getTargetTabIndex(dir, idx, $all.length));
        if ($next.length) { $next.trigger('focus'); activateFn($next); }
    }

    function findPanel($scope, tabsSel, contentSel, $tab) {
        var targetId = $tab.attr('rel'),
            $panels = $scope.find(contentSel),
            $target = $();
        if (targetId) {
            $target = (typeof $.escapeSelector === 'function')
                ? $scope.find('#' + $.escapeSelector(targetId))
                : $scope.find('#' + targetId);
        }
        return $target.length ? $target : $panels.first();
    }

    function applyTabA11y($scope, tabsSel, contentSel, $tabs, baseId) {
        $tabs.each(function (i) {
            var $tab = $(this),
                $panel = findPanel($scope, tabsSel, contentSel, $tab),
                panelId = $panel.attr('id'),
                tabId = $tab.attr('id');
            if (!tabId) { tabId = baseId + '-tab-' + i; $tab.attr('id', tabId); }
            if (!panelId) { panelId = baseId + '-panel-' + i; $panel.attr('id', panelId); }
            $tab.attr('role', 'tab').attr('aria-controls', panelId);
            $panel.attr('role', 'tabpanel').attr('aria-labelledby', tabId);
        });
    }

    function bindTabEvents($scope, sel, activateFn, moveFn) {
        $scope.off('click.awaCarouselTab keydown.awaCarouselTab', sel);
        $scope.on('click.awaCarouselTab keydown.awaCarouselTab', sel, function (e) {
            var key = e.which || e.keyCode;
            if (e.type === 'keydown') {
                if (handleKeyboardTabs(key, this, moveFn, activateFn)) { e.preventDefault(); }
                return;
            }
            e.preventDefault();
            activateFn($(this));
        });
    }

    function activateTab($scope, tabsSel, contentSel, $tab) {
        var $tabs = $scope.find(tabsSel),
            $panels = $scope.find(contentSel),
            $target = findPanel($scope, tabsSel, contentSel, $tab);
        $tabs.removeClass('active').attr('aria-selected', 'false').attr('tabindex', '-1');
        $tab.addClass('active').attr('aria-selected', 'true').attr('tabindex', '0');
        $panels.stop(true, true).hide().removeClass('animate1').attr('aria-hidden', 'true');
        if ($target.length) {
            $target.addClass('animate1').attr('aria-hidden', 'false').stop(true, true).fadeIn(150);
        }
        return $target;
    }

    function loadLazyTab($carousel, callback) {
        var url = $carousel.attr('data-lazy-url');
        if (!url) { callback(); return; }

        $carousel.html(
            '<div style="text-align:center;padding:40px 20px;color:#94a3b8;">' +
            '<div style="display:inline-block;width:24px;height:24px;border:3px solid #e2e8f0;' +
            'border-top-color:#3b82f6;border-radius:50%;animation:awa-spin .8s linear infinite;margin-bottom:10px"></div>' +
            '<div style="font-size:13px">Carregando produtos...</div></div>' +
            '<style>@keyframes awa-spin{to{transform:rotate(360deg)}}</style>'
        );

        $.ajax({ url: url, type: 'GET', dataType: 'json', timeout: 15000 })
            .done(function (res) {
                if (res && res.html) {
                    $carousel.html(res.html);
                    $carousel.removeAttr('data-lazy-url');
                    $carousel.trigger('contentUpdated');
                } else {
                    $carousel.html('').removeAttr('data-lazy-url');
                }
                callback();
            })
            .fail(function () {
                $carousel.html(
                    '<div style="text-align:center;padding:30px 20px;color:#94a3b8;font-size:13px">' +
                    'Erro ao carregar produtos.</div>'
                ).removeAttr('data-lazy-url');
            });
    }

    function initTabs($scope, config, onTabActivated) {
        var tabsSel = config.tabsSelector || 'ul.tabs li',
            contentSel = config.contentSelector || '.tab_content',
            $tabs = $scope.find(tabsSel),
            $active = $tabs.filter('.active').first(),
            $tabList = $tabs.first().parent(),
            baseId = ($scope.attr('class') || 'awa-carousel').replace(/[^a-zA-Z0-9_-]+/g, '-');

        function activateAndNotify($tab, force) {
            if (!force && $tab.hasClass('active') && $tab.attr('aria-selected') === 'true') { return; }
            var $panel = activateTab($scope, tabsSel, contentSel, $tab);
            if (typeof onTabActivated === 'function') { onTabActivated($panel, $tab); }
        }

        if (!$tabs.length || !$scope.find(contentSel).length) { return; }
        if (!$active.length) { $active = $tabs.first(); }

        $tabList.attr('role', 'tablist').attr('aria-orientation', 'horizontal');
        applyTabA11y($scope, tabsSel, contentSel, $tabs, baseId);
        bindTabEvents($scope, tabsSel, activateAndNotify, function (cur, dir) {
            moveToTab($scope, tabsSel, cur, dir, activateAndNotify);
        });

        activateAndNotify($active, true);
    }

    /* ═══════════════════════════════════════════════════════════════
       SWIPER MANAGER — instance lifecycle for tab panels
       ═══════════════════════════════════════════════════════════════ */

    function createSwiperManager(carouselSel, owlCfg) {
        var baseOpts = buildSwiperOptions(owlCfg),
            instances = {},
            pending = [];

        function create($container) {
            if (!$container || !$container.length || !carouselSel) { return; }

            $container.find(carouselSel).each(function () {
                var $el = $(this), key;
                if ($el.attr('data-lazy-url') || $el.data('awaSwiperInit')) { return; }

                key = $el.data('awaSwiperKey');
                if (key && instances[key]) { instances[key].update(); return; }

                $el.data('awaSwiperInit', 1);
                key = 'sc-' + Math.random().toString(36).slice(2, 9);
                $el.data('awaSwiperKey', key);

                try {
                    instances[key] = new SwiperCtor($el[0], baseOpts);
                } catch (e) {
                    $el.removeData('awaSwiperInit');
                }
            });
        }

        return {
            ensureIn: function ($container) {
                if (!$container || !$container.length) { return; }
                if (SwiperCtor) { create($container); return; }
                pending.push($container);
                loadSwiper(function () {
                    var p = pending.splice(0);
                    for (var i = 0; i < p.length; i++) { create(p[i]); }
                });
            },
            destroyIn: function ($container) {
                if (!$container || !$container.length) { return; }
                $container.find(carouselSel).each(function () {
                    var key = $(this).data('awaSwiperKey');
                    if (key && instances[key]) { instances[key].destroy(true, true); delete instances[key]; }
                });
            }
        };
    }

    /* ═══════════════════════════════════════════════════════════════
       INTERSECTION OBSERVER HELPER
       ═══════════════════════════════════════════════════════════════ */

    function observeViewport($el, callback) {
        if (!('IntersectionObserver' in window)) { callback(); return; }
        var margin = isMobile() ? '160px 0px' : '280px 0px';
        var obs = new IntersectionObserver(function (entries) {
            for (var i = 0; i < entries.length; i++) {
                if (entries[i].isIntersecting) { obs.disconnect(); callback(); return; }
            }
        }, { rootMargin: margin });
        obs.observe($el[0]);
    }

    /* ═══════════════════════════════════════════════════════════════
       MODE: SIMPLE (single carousel, homepage defer)
       ═══════════════════════════════════════════════════════════════ */

    function bootstrapSimple($scope, cfg) {
        var owlCfg = cfg.owl || cfg,
            carouselSel = cfg.carouselSelector || '.swiper',
            $el = $scope.is(carouselSel) ? $scope : $scope.find(carouselSel).first(),
            opts;

        if (!$el.length || $el.data('awaSwiperInit')) { return; }
        opts = buildSwiperOptions(owlCfg);

        function scheduleInit() {
            deferredInit(function () { enqueueInit($el, opts); });
        }

        if (isHomepage() && !gateOpen && !window.__awaApplyReleased) {
            gateQueue.push(scheduleInit);
            bindGate();
        } else {
            scheduleInit();
        }
    }

    /* ═══════════════════════════════════════════════════════════════
       MODE: TABS (ARIA tabs + carousel per panel)
       ═══════════════════════════════════════════════════════════════ */

    function bootstrapTabs($scope, cfg) {
        var carouselSel = cfg.carouselSelector,
            tabsSel = cfg.tabsSelector || 'ul.tabs li',
            contentSel = cfg.contentSelector || '.tab_content',
            owlCfg = cfg.owl || cfg.swiper || {},
            mgr = createSwiperManager(carouselSel, owlCfg),
            visTimer = null;

        function ensureActive() {
            var $active = $scope.find(tabsSel + '.active').first(),
                $panel = findPanel($scope, tabsSel, contentSel, $active);
            raf(function () {
                if (!$scope.is(':visible') || ($panel.length && !$panel.is(':visible'))) {
                    if (!visTimer) {
                        visTimer = setTimeout(function () { visTimer = null; ensureActive(); }, 120);
                    }
                    return;
                }
                mgr.ensureIn($panel.length ? $panel : $scope);
            });
        }

        initTabs($scope, cfg, function ($panel) {
            raf(function () {
                if (!$panel || !$panel.length) { mgr.ensureIn($scope); return; }
                var $lazy = carouselSel
                    ? $panel.find(carouselSel + '[data-lazy-url]')
                    : $panel.find('[data-lazy-url]');
                if ($lazy.length) {
                    loadLazyTab($lazy, function () { mgr.ensureIn($panel); });
                    return;
                }
                mgr.ensureIn($panel);
            });
        });

        var resizeNs = '.awaCarouselResize-' + Math.random().toString(36).slice(2, 9);
        $(window).off(resizeNs).on('resize' + resizeNs, debounce(function () { ensureActive(); }, 200));
        ensureActive();
    }

    /* ═══════════════════════════════════════════════════════════════
       MODE: SUPERDEALS (carousel + TimeCircles countdown)
       ═══════════════════════════════════════════════════════════════ */

    function bootstrapSuperdeals($scope, cfg) {
        var carouselSel = cfg.carouselSelector || '.hot-deal-slide',
            countdownSel = cfg.countdownSelector || '.super-deal-countdown',
            owlCfg = cfg.owl || {},
            labels = cfg.labels || {},
            countdownCfg = cfg.countdown || {};

        require(['swiper', 'rokanthemes/timecircles'], function (Swiper) {
            SwiperCtor = Swiper;
            var opts = buildSwiperOptions(owlCfg);

            $scope.find(carouselSel).each(function () {
                var $el = $(this);
                if ($el.data('awaSwiperInit')) { return; }
                $el.data('awaSwiperInit', 1);
                raf(function () { new Swiper($el[0], opts); });
            });

            $scope.find(countdownSel).each(function () {
                var $cd = $(this);
                if ($cd.data('awaCountdownInit') || typeof $cd.TimeCircles !== 'function') { return; }
                $cd.data('awaCountdownInit', 1);
                $cd.TimeCircles({
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

    /* ═══════════════════════════════════════════════════════════════
       MAIN ENTRY POINT
       ═══════════════════════════════════════════════════════════════ */

    return function (config, element) {
        var $scope = $(element);
        if (!$scope.length) { return; }

        var cfg = config || {},
            hasTabs = !!cfg.tabsSelector,
            hasCountdown = !!cfg.countdownSelector;

        observeViewport($scope, function () {
            if (hasTabs) {
                bootstrapTabs($scope, cfg);
            } else if (hasCountdown) {
                bootstrapSuperdeals($scope, cfg);
            } else {
                bootstrapSimple($scope, cfg);
            }
        });
    };
});
