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

    function initCarousel(config) {
        var carouselSelector = config.carouselSelector;
        var owlConfig = config.owl || {};
        var baseOptions;
        var mediaQueryList = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;
        var shouldReduceMotion = !!(mediaQueryList && mediaQueryList.matches);
        var autoplayGuardCarousels = [];
        var autoplayVisibilityBound = false;
        var manager = {
            ensureIn: function () {}
        };

        if (!carouselSelector) {
            return manager;
        }

        baseOptions = {
            lazyLoad: resolveBoolean(owlConfig.lazyLoad, true),
            autoPlay: shouldReduceMotion ? false : resolveBoolean(owlConfig.autoPlay, false),
            navigation: resolveBoolean(owlConfig.navigation, false),
            pagination: resolveBoolean(owlConfig.pagination, false),
            stopOnHover: resolveBoolean(owlConfig.stopOnHover, true),
            scrollPerPage: resolveBoolean(owlConfig.scrollPerPage, true),
            items: parseInt(owlConfig.items, 10) || 3,
            itemsDesktop: owlConfig.itemsDesktop || [1366, 3],
            itemsDesktopSmall: owlConfig.itemsDesktopSmall || [1199, 2],
            itemsTablet: owlConfig.itemsTablet || [991, 2],
            itemsMobile: owlConfig.itemsMobile || [680, 1],
            slideSpeed: parseInt(owlConfig.slideSpeed, 10) || 500,
            paginationSpeed: parseInt(owlConfig.paginationSpeed, 10) || 500,
            rewindSpeed: parseInt(owlConfig.rewindSpeed, 10) || 500,
            afterAction: function () {
                var $carousel = this.$elem && this.$elem.length ? this.$elem : $();

                if (this.$owlItems && this.$owlItems.length) {
                    this.$owlItems.removeClass('first-active');
                    this.$owlItems.eq(this.currentItem).addClass('first-active');
                }

                if ($carousel.length) {
                    syncFocusableState($carousel);
                }
            }
        };

        function syncFocusableState($carousel) {
            var focusableSelector = 'a, button, input, select, textarea, [tabindex]';

            if (!$carousel || !$carousel.length) {
                return;
            }

            $carousel.find('.owl-item').each(function () {
                var $item = $(this);
                var isActive = $item.hasClass('active') || $item.hasClass('center') || $item.hasClass('first-active');

                $item.attr('aria-hidden', isActive ? 'false' : 'true');

                $item.find(focusableSelector).each(function () {
                    var $el = $(this);
                    var originalTabindex = $el.data('awaOrigTabindex');

                    if (isActive) {
                        if (originalTabindex === '__none__') {
                            $el.removeAttr('tabindex');
                            $el.removeData('awaOrigTabindex');
                            return;
                        }

                        if (originalTabindex !== undefined) {
                            $el.attr('tabindex', originalTabindex);
                            $el.removeData('awaOrigTabindex');
                        }
                        return;
                    }

                    if (originalTabindex === undefined) {
                        $el.data('awaOrigTabindex', $el.attr('tabindex') !== undefined ? $el.attr('tabindex') : '__none__');
                    }

                    $el.attr('tabindex', '-1');
                });
            });
        }

        function setAutoplayState($carousel, shouldPlay) {
            var owl;

            if (!$carousel || !$carousel.length) {
                return;
            }

            owl = $carousel.data('owlCarousel');
            if (!owl) {
                return;
            }

            if (shouldPlay) {
                if (typeof owl.play === 'function') {
                    owl.play();
                    return;
                }

                $carousel.trigger('owl.play', 5000);
                return;
            }

            if (typeof owl.stop === 'function') {
                owl.stop();
                return;
            }

            $carousel.trigger('owl.stop');
        }

        function moveCarousel($carousel, direction) {
            var owl;

            if (!$carousel || !$carousel.length) {
                return;
            }

            owl = $carousel.data('owlCarousel');

            if (owl) {
                if (direction === 'next') {
                    if (typeof owl.next === 'function') {
                        owl.next();
                        return;
                    }
                    $carousel.trigger('owl.next');
                    return;
                }

                if (typeof owl.prev === 'function') {
                    owl.prev();
                    return;
                }

                $carousel.trigger('owl.prev');
                return;
            }

            $carousel.trigger(direction === 'next' ? 'next.owl.carousel' : 'prev.owl.carousel');
        }

        function moveCarouselToEdge($carousel, toEnd) {
            var owl;
            var targetIndex;

            if (!$carousel || !$carousel.length) {
                return;
            }

            owl = $carousel.data('owlCarousel');
            if (!owl) {
                $carousel.trigger('to.owl.carousel', [toEnd ? 9999 : 0, 250]);
                return;
            }

            if (typeof owl.goTo !== 'function') {
                return;
            }

            if (!toEnd) {
                owl.goTo(0);
                return;
            }

            targetIndex = typeof owl.maximumItem === 'number'
                ? owl.maximumItem
                : (typeof owl.itemsAmount === 'number' ? Math.max(0, owl.itemsAmount - 1) : 0);

            owl.goTo(targetIndex);
        }

        function bindKeyboardCarouselNavigation($carousel) {
            var keyHandler;

            if (!$carousel || !$carousel.length || $carousel.data('awaKeyboardNavBound')) {
                return;
            }

            $carousel.data('awaKeyboardNavBound', 1);

            if ($carousel.attr('tabindex') === undefined) {
                $carousel.attr('tabindex', '0');
                $carousel.data('awaKeyboardNavTabindexAdded', 1);
            }

            keyHandler = function (event) {
                var key = event.which || event.keyCode;
                var tagName = event.target && event.target.tagName ? event.target.tagName.toLowerCase() : '';

                if (tagName === 'input' || tagName === 'textarea' || tagName === 'select') {
                    return;
                }

                if (key === 37) {
                    event.preventDefault();
                    moveCarousel($carousel, 'prev');
                    return;
                }

                if (key === 39) {
                    event.preventDefault();
                    moveCarousel($carousel, 'next');
                    return;
                }

                if (key === 36) {
                    event.preventDefault();
                    moveCarouselToEdge($carousel, false);
                    return;
                }

                if (key === 35) {
                    event.preventDefault();
                    moveCarouselToEdge($carousel, true);
                }
            };

            $carousel.on('keydown.awaOwlKeyboardNav', keyHandler);

            $carousel.on('remove.awaOwlKeyboardNav', function () {
                $carousel.off('.awaOwlKeyboardNav');
                if ($carousel.data('awaKeyboardNavTabindexAdded')) {
                    $carousel.removeAttr('tabindex');
                    $carousel.removeData('awaKeyboardNavTabindexAdded');
                }
            });
        }

        function bindAutoplayGuard($carousel) {
            var pauseEvents;
            var resumeEvents;

            function syncAllByVisibility() {
                autoplayGuardCarousels = autoplayGuardCarousels.filter(function ($entry) {
                    return $entry && $entry.length && document.documentElement.contains($entry.get(0));
                });

                autoplayGuardCarousels.forEach(function ($entry) {
                    setAutoplayState($entry, !document.hidden);
                });
            }

            if (!$carousel || !$carousel.length || $carousel.data('awaAutoplayGuardBound')) {
                return;
            }

            $carousel.data('awaAutoplayGuardBound', 1);

            if (!baseOptions.autoPlay) {
                return;
            }

            pauseEvents = 'mouseenter.awaOwlAutoplayGuard focusin.awaOwlAutoplayGuard touchstart.awaOwlAutoplayGuard';
            resumeEvents = 'mouseleave.awaOwlAutoplayGuard focusout.awaOwlAutoplayGuard touchend.awaOwlAutoplayGuard';

            if ($.inArray($carousel, autoplayGuardCarousels) === -1) {
                autoplayGuardCarousels.push($carousel);
            }

            if (!autoplayVisibilityBound) {
                document.addEventListener('visibilitychange', syncAllByVisibility);
                autoplayVisibilityBound = true;
            }

            $carousel.on(pauseEvents, function () {
                setAutoplayState($carousel, false);
            });

            $carousel.on(resumeEvents, function () {
                if (!document.hidden) {
                    setAutoplayState($carousel, true);
                }
            });

            $carousel.on('remove.awaOwlAutoplayGuard', function () {
                autoplayGuardCarousels = autoplayGuardCarousels.filter(function ($entry) {
                    return !$entry || !$entry.length || $entry.get(0) !== $carousel.get(0);
                });
                $carousel.off('.awaOwlAutoplayGuard');
            });

            syncAllByVisibility();
        }

        function reinitCarousel($carousel) {
            var owl = $carousel.data('owlCarousel');

            if (!owl) {
                return;
            }

            $carousel.addClass('owl-loaded');

            if (typeof owl.reinit === 'function') {
                owl.reinit(baseOptions);
                bindAutoplayGuard($carousel);
                bindKeyboardCarouselNavigation($carousel);
                syncFocusableState($carousel);
                return;
            }

            $carousel.trigger('owl.update');
            bindAutoplayGuard($carousel);
            bindKeyboardCarouselNavigation($carousel);
            syncFocusableState($carousel);
        }

        function ensureIn($container) {
            if (!$container || !$container.length) {
                return;
            }

            $container.find(carouselSelector).each(function () {
                var $carousel = $(this);

                if ($carousel.data('owlCarousel') || $carousel.hasClass('owl-loaded')) {
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
                    $carousel.addClass('owl-loaded');
                    bindAutoplayGuard($carousel);
                    bindKeyboardCarouselNavigation($carousel);
                    syncFocusableState($carousel);
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
                if ($panel && $panel.length) {
                    carouselManager.ensureIn($panel);
                    return;
                }

                carouselManager.ensureIn($scope);
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
