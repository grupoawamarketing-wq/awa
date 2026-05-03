/* global define, window, setTimeout, clearTimeout */
define([
    'jquery',
    'rokanthemes/owl'
], function ($) {
    'use strict';

    function resolveBoolean(value, fallback) {
        if (value === undefined || value === null || value === '') {
            return fallback;
        }
        if (typeof value === 'string') {
            return !(value === 'false' || value === '0');
        }
        return !!value;
    }

    function debounce(fn, wait) {
        var timer;

        return function () {
            var context = this;
            var args = arguments;

            clearTimeout(timer);
            timer = setTimeout(function () {
                fn.apply(context, args);
            }, wait || 120);
        };
    }

    function runOnNextFrame(callback) {
        if (window && typeof window.requestAnimationFrame === 'function') {
            window.requestAnimationFrame(callback);
            return;
        }

        setTimeout(callback, 16);
    }

    function handleKeyboardTabs(key, currentTab, moveToTab, activateAndNotify) {
        if (key === 13 || key === 32) {
            activateAndNotify($(currentTab));
            return true;
        }

        if (key === 37) {
            moveToTab(currentTab, 'prev');
            return true;
        }

        if (key === 39) {
            moveToTab(currentTab, 'next');
            return true;
        }

        if (key === 36) {
            moveToTab(currentTab, 'first');
            return true;
        }

        if (key === 35) {
            moveToTab(currentTab, 'last');
            return true;
        }

        return false;
    }

    function getTargetTabIndex(direction, currentIndex, totalTabs) {
        if (direction === 'first') {
            return 0;
        }

        if (direction === 'last') {
            return totalTabs - 1;
        }

        if (direction === 'next') {
            return (currentIndex + 1) % totalTabs;
        }

        return (currentIndex - 1 + totalTabs) % totalTabs;
    }

    function moveToTab($scope, tabsSelector, currentTab, direction, activateAndNotify) {
        var $allTabs = $scope.find(tabsSelector);
        var currentIndex = $allTabs.index(currentTab);
        var targetIndex;
        var $next;

        if (!$allTabs.length || currentIndex < 0) {
            return;
        }

        targetIndex = getTargetTabIndex(direction, currentIndex, $allTabs.length);
        $next = $allTabs.eq(targetIndex);

        if ($next.length) {
            $next.trigger('focus');
            activateAndNotify($next);
        }
    }

    function applyTabA11y($scope, tabsSelector, contentSelector, $tabs, normalizedA11yId) {
        $tabs.each(function (index) {
            var $tab = $(this);
            var $panel = findTargetPanel($scope, tabsSelector, contentSelector, $tab);
            var panelId = $panel.attr('id');
            var tabId = $tab.attr('id');

            if (!tabId) {
                tabId = normalizedA11yId + '-tab-' + index;
                $tab.attr('id', tabId);
            }

            if (!panelId) {
                panelId = normalizedA11yId + '-panel-' + index;
                $panel.attr('id', panelId);
            }

            $tab.attr('role', 'tab')
                .attr('aria-controls', panelId);

            $panel.attr('role', 'tabpanel')
                .attr('aria-labelledby', tabId);
        });
    }

    function bindTabEvents($scope, tabsSelector, activateAndNotify, onMoveToTab) {
        $scope.off('click.awaTabCarousel keydown.awaTabCarousel', tabsSelector);
        $scope.on('click.awaTabCarousel keydown.awaTabCarousel', tabsSelector, function (event) {
            var key = event.which || event.keyCode;
            var isKeyboard = event.type === 'keydown';

            if (isKeyboard) {
                if (handleKeyboardTabs(key, this, onMoveToTab, activateAndNotify)) {
                    event.preventDefault();
                }
                return;
            }

            event.preventDefault();
            activateAndNotify($(this));
        });
    }

    function findTargetPanel($scope, tabsSelector, contentSelector, $tab) {
        var targetId = $tab.attr('rel');
        var $panels = $scope.find(contentSelector);
        var $target = $();

        if (targetId) {
            if (typeof $.escapeSelector === 'function') {
                $target = $scope.find('#' + $.escapeSelector(targetId));
            } else {
                $target = $scope.find('#' + targetId);
            }
        }

        if (!$target.length) {
            $target = $panels.first();
        }

        return $target;
    }

    function activateTab($scope, tabsSelector, contentSelector, $tab) {
        var $tabs = $scope.find(tabsSelector);
        var $panels = $scope.find(contentSelector);
        var $target = findTargetPanel($scope, tabsSelector, contentSelector, $tab);

        $tabs.removeClass('active')
            .attr('aria-selected', 'false')
            .attr('tabindex', '-1');

        $tab.addClass('active')
            .attr('aria-selected', 'true')
            .attr('tabindex', '0');

        $panels.stop(true, true).hide()
            .removeClass('animate1')
            .attr('aria-hidden', 'true');

        if ($target.length) {
            $target.addClass('animate1')
                .attr('aria-hidden', 'false')
                .stop(true, true)
                .fadeIn(150);
        }

        return $target;
    }

    /**
     * Load lazy tab content via AJAX.
     *
     * @param {jQuery} $carousel - The carousel element with data-lazy-url
     * @param {Function} callback - Called after HTML is injected
     */
    function loadLazyTab($carousel, callback) {
        var url = $carousel.attr('data-lazy-url');

        if (!url) {
            callback();
            return;
        }

        $carousel.html(
            '<div class="awa-lazy-tab-loading" style="text-align:center;padding:40px 20px;color:#94a3b8;">' +
                '<div style="display:inline-block;width:24px;height:24px;border:3px solid #e2e8f0;border-top-color:#3b82f6;border-radius:50%;animation:awa-spin 0.8s linear infinite;margin-bottom:10px;"></div>' +
                '<div style="font-size:13px;">Carregando produtos...</div>' +
            '</div>' +
            '<style>@keyframes awa-spin{to{transform:rotate(360deg)}}</style>'
        );

        $.ajax({
            url: url,
            type: 'GET',
            dataType: 'json',
            timeout: 15000
        }).done(function (response) {
            if (response && response.html) {
                $carousel.html(response.html);
                $carousel.removeAttr('data-lazy-url');
                $carousel.trigger('contentUpdated');
            } else {
                $carousel.html('');
                $carousel.removeAttr('data-lazy-url');
            }
            callback();
        }).fail(function () {
            $carousel.html(
                '<div style="text-align:center;padding:30px 20px;color:#94a3b8;font-size:13px;">' +
                    'Erro ao carregar produtos.' +
                '</div>'
            );
            $carousel.removeAttr('data-lazy-url');
        });
    }

    function initTabs($scope, config, onTabActivated) {
        var tabsSelector = config.tabsSelector || 'ul.tabs li';
        var contentSelector = config.contentSelector || '.tab_content';
        var $tabs = $scope.find(tabsSelector);
        var $active = $tabs.filter('.active').first();
        var $tabList = $tabs.first().parent();
        var $activePanel;
        var baseA11yId = $scope.attr('class') || 'awa-tab-group';
        var normalizedA11yId = baseA11yId.replace(/[^a-zA-Z0-9_-]+/g, '-');

        function activateAndNotify($tab, force) {
            if (!force && $tab.hasClass('active') && $tab.attr('aria-selected') === 'true') {
                return;
            }

            $activePanel = activateTab($scope, tabsSelector, contentSelector, $tab);

            if (typeof onTabActivated === 'function') {
                onTabActivated($activePanel, $tab);
            }
        }

        if (!$tabs.length || !$scope.find(contentSelector).length) {
            return;
        }

        if (!$active.length) {
            $active = $tabs.first();
        }

        $tabList.attr('role', 'tablist')
            .attr('aria-orientation', 'horizontal');

        applyTabA11y($scope, tabsSelector, contentSelector, $tabs, normalizedA11yId);
        bindTabEvents($scope, tabsSelector, activateAndNotify, function (currentTab, direction) {
            moveToTab($scope, tabsSelector, currentTab, direction, activateAndNotify);
        });

        activateAndNotify($active, true);
    }

    /**
     * Convert OWL Carousel v1 options to v2 format.
     * v1 uses itemsDesktop/itemsTablet/itemsMobile arrays; v2 uses responsive object.
     */
    function convertOwlV1ToV2(v1) {
        var responsive = {};
        var mobileItems = 1;
        var tabletItems = 2;
        var desktopSmallItems = 3;
        var desktopItems;

        desktopItems = parseInt(v1.items, 10) || 3;

        if (v1.itemsMobile && v1.itemsMobile[1]) {
            mobileItems = parseInt(v1.itemsMobile[1], 10) || 1;
        }
        if (v1.itemsTablet && v1.itemsTablet[1]) {
            tabletItems = parseInt(v1.itemsTablet[1], 10) || 2;
        }
        if (v1.itemsDesktopSmall && v1.itemsDesktopSmall[1]) {
            desktopSmallItems = parseInt(v1.itemsDesktopSmall[1], 10) || 3;
        }
        if (v1.itemsDesktop && v1.itemsDesktop[1]) {
            desktopItems = parseInt(v1.itemsDesktop[1], 10) || desktopItems;
        }

        responsive[0] = { items: mobileItems };
        responsive[v1.itemsMobile && v1.itemsMobile[0] ? v1.itemsMobile[0] : 680] = { items: mobileItems };
        responsive[v1.itemsTablet && v1.itemsTablet[0] ? v1.itemsTablet[0] : 991] = { items: tabletItems };
        responsive[v1.itemsDesktopSmall && v1.itemsDesktopSmall[0] ? v1.itemsDesktopSmall[0] : 1199] = { items: desktopSmallItems };
        responsive[v1.itemsDesktop && v1.itemsDesktop[0] ? v1.itemsDesktop[0] : 1366] = { items: desktopItems };

        return {
            items: desktopItems,
            responsive: responsive,
            nav: resolveBoolean(v1.navigation, false),
            dots: resolveBoolean(v1.pagination, false),
            autoplay: resolveBoolean(v1.autoPlay, false),
            autoplayHoverPause: resolveBoolean(v1.stopOnHover, true),
            slideBy: resolveBoolean(v1.scrollPerPage, true) ? 'page' : 1,
            smartSpeed: parseInt(v1.slideSpeed, 10) || 500,
            lazyLoad: resolveBoolean(v1.lazyLoad, true),
            loop: false,
            margin: parseInt(v1.margin, 10) || 0,
            onTranslated: function () {
                var $items = this.$stage ? this.$stage.children() : $();

                $items.removeClass('first-active');
                if (this._current !== undefined) {
                    $items.eq(this.relative(this._current)).addClass('first-active');
                }
            }
        };
    }

    function initCarousel(config) {
        var carouselSelector = config.carouselSelector;
        var owlConfig = config.owl || {};
        var baseOptions;
        var manager = {
            ensureIn: function () {}
        };

        if (!carouselSelector) {
            return manager;
        }

        baseOptions = convertOwlV1ToV2(owlConfig);

        function reinitCarousel($carousel) {
            if (!$carousel.data('owl.carousel') && !$carousel.data('owlCarousel')) {
                return;
            }

            $carousel.trigger('refresh.owl.carousel');
        }

        function ensureIn($container) {
            if (!$container || !$container.length) {
                return;
            }

            $container.find(carouselSelector).each(function () {
                var $carousel = $(this);

                // Skip lazy-loading carousels — they will be initialized after AJAX load
                if ($carousel.attr('data-lazy-url')) {
                    return;
                }

                if ($carousel.data('owl.carousel') || $carousel.data('owlCarousel') || $carousel.hasClass('owl-loaded')) {
                    reinitCarousel($carousel);
                    return;
                }

                if ($carousel.data('awaTabCarouselInit')) {
                    return;
                }

                if (typeof $carousel.owlCarousel !== 'function') {
                    return;
                }

                $carousel.data('awaTabCarouselInit', 1);
                try {
                    $carousel.owlCarousel(baseOptions);
                } catch (error) {
                    $carousel.removeData('awaTabCarouselInit');
                }
            });
        }

        manager.ensureIn = ensureIn;

        return manager;
    }

    return function (config, element) {
        var $scope = $(element);
        var $window = $(window);
        var currentConfig = config || {};
        var tabsSelector = currentConfig.tabsSelector || 'ul.tabs li';
        var contentSelector = currentConfig.contentSelector || '.tab_content';
        var carouselSelector = currentConfig.carouselSelector;
        var resizeNamespace = $scope.data('awaTabCarouselResizeNs');
        var visibilityRetryTimer = null;
        var carouselManager = initCarousel(currentConfig);

        if (!resizeNamespace) {
            resizeNamespace = '.awaTabCarouselResize-' + Math.random().toString(36).slice(2, 9);
            $scope.data('awaTabCarouselResizeNs', resizeNamespace);
        }

        function ensureActiveCarousel() {
            var $activeTab = $scope.find(tabsSelector + '.active').first();
            var $activePanel = findTargetPanel($scope, tabsSelector, contentSelector, $activeTab);

            runOnNextFrame(function () {
                if (!$scope.is(':visible') || ($activePanel.length && !$activePanel.is(':visible'))) {
                    if (!visibilityRetryTimer) {
                        visibilityRetryTimer = setTimeout(function () {
                            visibilityRetryTimer = null;
                            ensureActiveCarousel();
                        }, 120);
                    }
                    return;
                }

                if ($activePanel.length) {
                    carouselManager.ensureIn($activePanel);
                    return;
                }

                carouselManager.ensureIn($scope);
            });
        }

        initTabs($scope, currentConfig, function ($panel) {
            runOnNextFrame(function () {
                if (!$panel || !$panel.length) {
                    carouselManager.ensureIn($scope);
                    return;
                }

                // Check for lazy-loading carousel inside this panel
                var $lazyCarousel = carouselSelector
                    ? $panel.find(carouselSelector + '[data-lazy-url]')
                    : $panel.find('[data-lazy-url]');

                if ($lazyCarousel.length) {
                    loadLazyTab($lazyCarousel, function () {
                        carouselManager.ensureIn($panel);
                    });
                    return;
                }

                carouselManager.ensureIn($panel);
            });
        });

        $window.off('resize' + resizeNamespace + ' orientationchange' + resizeNamespace)
            .on('resize' + resizeNamespace + ' orientationchange' + resizeNamespace, debounce(function () {
                ensureActiveCarousel();
            }, 150));

        $scope.off('remove.awaTabCarouselCleanup')
            .on('remove.awaTabCarouselCleanup', function () {
                if (visibilityRetryTimer) {
                    clearTimeout(visibilityRetryTimer);
                    visibilityRetryTimer = null;
                }

                $window.off('resize' + resizeNamespace + ' orientationchange' + resizeNamespace);
            });
    };
});
