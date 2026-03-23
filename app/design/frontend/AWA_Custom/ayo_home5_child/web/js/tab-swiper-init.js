/* global define, window, setTimeout, clearTimeout */
/**
 * AWA Motos — Tab + Swiper Carousel Init
 *
 * Drop-in replacement for tab-carousel-init.js (Owl Carousel).
 * Keeps identical tab logic (keyboard, a11y, lazy-load, debounce).
 * Swaps Owl engine for Swiper 11.
 *
 * Usage in data-mage-init / x-magento-init:
 *   "js/tab-swiper-init": {
 *       "tabsSelector": "ul.tabs li",
 *       "contentSelector": ".tab_content",
 *       "carouselSelector": ".swiper",
 *       "swiper": { "slidesPerView": 3, ... }
 *   }
 */
define([
    'jquery',
    'swiper'
], function ($, Swiper) {
    'use strict';

    /* ─── Utilities ─── */

    function debounce(fn, wait) {
        var timer;

        return function () {
            var ctx = this,
                args = arguments;

            clearTimeout(timer);
            timer = setTimeout(function () {
                fn.apply(ctx, args);
            }, wait || 120);
        };
    }

    function raf(cb) {
        if (window.requestAnimationFrame) {
            window.requestAnimationFrame(cb);
        } else {
            setTimeout(cb, 16);
        }
    }

    /* ─── Keyboard helpers (unchanged from tab-carousel-init) ─── */

    function handleKeyboardTabs(key, currentTab, moveToTab, activateAndNotify) {
        if (key === 13 || key === 32) {
            activateAndNotify($(currentTab));
            return true;
        }
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
        var $all = $scope.find(sel),
            idx = $all.index(tab);

        if (!$all.length || idx < 0) { return; }

        var $next = $all.eq(getTargetTabIndex(dir, idx, $all.length));

        if ($next.length) {
            $next.trigger('focus');
            activateFn($next);
        }
    }

    /* ─── A11y ─── */

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
        $scope.off('click.awaSwiperTab keydown.awaSwiperTab', sel);
        $scope.on('click.awaSwiperTab keydown.awaSwiperTab', sel, function (e) {
            var key = e.which || e.keyCode;

            if (e.type === 'keydown') {
                if (handleKeyboardTabs(key, this, moveFn, activateFn)) {
                    e.preventDefault();
                }
                return;
            }
            e.preventDefault();
            activateFn($(this));
        });
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

    /* ─── Lazy tab loading (AJAX) ─── */

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

    /* ─── Tab system init ─── */

    function initTabs($scope, config, onTabActivated) {
        var tabsSel = config.tabsSelector || 'ul.tabs li',
            contentSel = config.contentSelector || '.tab_content',
            $tabs = $scope.find(tabsSel),
            $active = $tabs.filter('.active').first(),
            $tabList = $tabs.first().parent(),
            baseId = ($scope.attr('class') || 'awa-swiper-tab').replace(/[^a-zA-Z0-9_-]+/g, '-');

        function activateAndNotify($tab, force) {
            if (!force && $tab.hasClass('active') && $tab.attr('aria-selected') === 'true') {
                return;
            }
            var $panel = activateTab($scope, tabsSel, contentSel, $tab);

            if (typeof onTabActivated === 'function') {
                onTabActivated($panel, $tab);
            }
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

    /* ─── Swiper carousel manager ─── */

    function buildSwiperOptions(cfg) {
        var items = parseInt(cfg.items, 10) || 4,
            mobileItems = 1,
            tabletItems = Math.min(items, 2),
            desktopSmallItems = Math.min(items, 3),
            desktopItems = items;

        /* Read Owl v1 keys for backward compatibility */
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
            slidesPerGroup: cfg.scrollPerPage ? undefined : 1,
            breakpoints: {
                480: { slidesPerView: Math.max(mobileItems, 2), spaceBetween: 12 },
                768: { slidesPerView: tabletItems, spaceBetween: 16 },
                992: { slidesPerView: desktopSmallItems, spaceBetween: 16 },
                1200: { slidesPerView: desktopItems, spaceBetween: 20 }
            },
            a11y: {
                prevSlideMessage: 'Slide anterior',
                nextSlideMessage: 'Próximo slide',
                firstSlideMessage: 'Primeiro slide',
                lastSlideMessage: 'Último slide'
            }
        };
    }

    function initSwiperManager(config) {
        var carouselSel = config.carouselSelector,
            owlCfg = config.owl || config.swiper || {},
            baseOpts = buildSwiperOptions(owlCfg),
            instances = {};

        function destroyIn($container) {
            if (!$container || !$container.length) { return; }

            $container.find(carouselSel).each(function () {
                var key = $(this).data('awaSwiperKey');

                if (key && instances[key]) {
                    instances[key].destroy(true, true);
                    delete instances[key];
                }
            });
        }

        function ensureIn($container) {
            if (!$container || !$container.length || !carouselSel) { return; }

            $container.find(carouselSel).each(function () {
                var el = this,
                    $el = $(el),
                    key, instance;

                if ($el.attr('data-lazy-url')) { return; }

                key = $el.data('awaSwiperKey');

                if (key && instances[key]) {
                    /* Already initialized — just update on tab switch */
                    instances[key].update();
                    return;
                }

                if ($el.data('awaSwiperInit')) { return; }
                $el.data('awaSwiperInit', 1);

                key = 'swiper-' + Math.random().toString(36).slice(2, 9);
                $el.data('awaSwiperKey', key);

                try {
                    instance = new Swiper(el, baseOpts);
                    instances[key] = instance;
                } catch (e) {
                    $el.removeData('awaSwiperInit');
                }
            });
        }

        return {
            ensureIn: ensureIn,
            destroyIn: destroyIn
        };
    }

    /* ─── Main entry ─── */

    return function (config, element) {
        var $scope = $(element),
            currentConfig = config || {},
            carouselSel = currentConfig.carouselSelector,
            tabsSel = currentConfig.tabsSelector || 'ul.tabs li',
            contentSel = currentConfig.contentSelector || '.tab_content',
            swiperMgr = initSwiperManager(currentConfig),
            visibilityTimer = null;

        function ensureActiveCarousel() {
            var $activeTab = $scope.find(tabsSel + '.active').first(),
                $panel = findPanel($scope, tabsSel, contentSel, $activeTab);

            raf(function () {
                if (!$scope.is(':visible') || ($panel.length && !$panel.is(':visible'))) {
                    if (!visibilityTimer) {
                        visibilityTimer = setTimeout(function () {
                            visibilityTimer = null;
                            ensureActiveCarousel();
                        }, 120);
                    }
                    return;
                }
                swiperMgr.ensureIn($panel.length ? $panel : $scope);
            });
        }

        initTabs($scope, currentConfig, function ($panel) {
            raf(function () {
                if (!$panel || !$panel.length) {
                    swiperMgr.ensureIn($scope);
                    return;
                }

                var $lazyCarousel = carouselSel
                    ? $panel.find(carouselSel + '[data-lazy-url]')
                    : $panel.find('[data-lazy-url]');

                if ($lazyCarousel.length) {
                    loadLazyTab($lazyCarousel, function () {
                        swiperMgr.ensureIn($panel);
                    });
                    return;
                }

                swiperMgr.ensureIn($panel);
            });
        });

        /* Resize handler */
        var resizeNs = '.awaSwiperResize-' + Math.random().toString(36).slice(2, 9);

        $(window).off(resizeNs).on('resize' + resizeNs, debounce(function () {
            ensureActiveCarousel();
        }, 200));

        /* Initial carousel for active tab */
        ensureActiveCarousel();
    };
});
