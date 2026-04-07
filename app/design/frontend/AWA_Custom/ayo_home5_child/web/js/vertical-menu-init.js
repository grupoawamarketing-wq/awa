define([
    'jquery',
    'rokanthemes/verticalmenu'
], function ($) {
    'use strict';

    function initRokanVerticalMenuWidget($menus) {
        var initialized = false;

        if (!$.isFunction($.fn.VerticalMenu)) {
            return initialized;
        }

        $menus.each(function () {
            var $menu = $(this);

            if ($menu.data('awaRokanVerticalMenuInit')) {
                return;
            }

            $menu.VerticalMenu();
            $menu.data('awaRokanVerticalMenuInit', 1);
            initialized = true;
        });

        return initialized;
    }

    function debounce(fn, wait) {
        var timer = null;

        return function () {
            var args = arguments;
            var context = this;

            clearTimeout(timer);
            timer = setTimeout(function () {
                fn.apply(context, args);
            }, wait || 120);
        };
    }

    function cleanupAyoVerticalArtifacts($menuList) {
        if (!$menuList || !$menuList.length) {
            return;
        }

        $menuList.find('> li.vertical-menu-custom-block').each(function () {
            var $block = $(this);
            var hasRenderableContent = $.trim($block.text()).length > 0 ||
                $block.find('img[src], picture source[srcset], video source[src], iframe[src], a[href], .block, .cms-block').length > 0;

            if (!hasRenderableContent) {
                $block.remove();
            }
        });

        $menuList.find('> li.vertical-bg-img').each(function () {
            var $promoBlock = $(this);
            var hasPromoContent = $.trim($promoBlock.text()).length > 0 ||
                $promoBlock.find('img[src], picture source[srcset], video source[src], iframe[src], a[href]').length > 0;

            if (!hasPromoContent) {
                $promoBlock.remove();
            }
        });
    }

    return function (config, element) {
        var $nav = $(element);
        var $toggleMenu = $nav.find('.togge-menu');
        var $title = $nav.find('.title-category-dropdown');
        var $expandLink = $nav.find('.vm-toggle-categories');
        var $items = $nav.find('.ui-menu-item.level0');
        var menuUid = $nav.attr('id') || $title.attr('aria-controls') || ('awa-vertical-' + Math.random().toString(36).slice(2));
        var safeUid = String(menuUid).replace(/[^a-zA-Z0-9_-]/g, '');
        var overlaySelector = (config && config.overlaySelector) || '.shadow_bkg_show';
        var desktopBreakpoint = parseInt(config && config.desktopBreakpoint, 10) || 992;
        var defaultLimit = parseInt(config && config.limitShow, 10) || 0;
        var limitItemShow = parseInt($toggleMenu.attr('data-limit-show'), 10) || defaultLimit;
        var childPanelSelector = '.submenu, ul.level0, .subchildmenu';
        var resizeNamespace = '.awaVerticalMenuResize-' + safeUid;
        var overlayClickNamespace = '.awaVerticalMenuOverlay-' + safeUid;
        var eventNamespace = '.awaVerticalMenu-' + safeUid;
        var rokanWidgetEnabled;
        var $rootVerticalMenus;

        if (!$nav.length || $nav.data('awaVerticalMenuInit')) {
            return;
        }

        $nav.data('awaVerticalMenuInit', 1);
        $nav.attr('data-awa-verticalmenu-owner', 'vertical-menu-init');

        $rootVerticalMenus = $nav.filter('.verticalmenu').add($nav.find('.verticalmenu'));
        rokanWidgetEnabled = initRokanVerticalMenuWidget($rootVerticalMenus);
        cleanupAyoVerticalArtifacts($toggleMenu);

        function isDesktopViewport() {
            if (window.matchMedia) {
                return window.matchMedia('(min-width: ' + desktopBreakpoint + 'px)').matches;
            }

            return window.innerWidth >= desktopBreakpoint;
        }

        function isHomeContext() {
            var body = document.body;

            if (!body) {
                return false;
            }

            return body.classList.contains('cms-index-index') ||
                body.classList.contains('cms-home') ||
                body.classList.contains('cms-homepage_ayo_home5') ||
                body.classList.contains('cms-homepage_ayo_home5_demo_stage');
        }

        var hoverCloseTimer = null;
        var HOVER_CLOSE_DELAY = 280;

        function keepDesktopMenuExpanded() {
            return false; // AWA: menu abre no hover, fecha ao sair
        }

        function setDesktopMenuVisibility(isVisible) {
            if (!$toggleMenu.length) {
                return;
            }

            $toggleMenu.each(function () {
                var style = this.style;

                style.setProperty('display', isVisible ? 'block' : 'none', 'important');

                if (isVisible) {
                    style.setProperty('visibility', 'visible', 'important');
                    style.setProperty('opacity', '1', 'important');
                    style.setProperty('pointer-events', 'auto', 'important');
                    style.setProperty('transform', 'none', 'important');
                    style.setProperty('overflow', 'visible', 'important');
                    return;
                }

                style.removeProperty('visibility');
                style.removeProperty('opacity');
                style.removeProperty('pointer-events');
                style.removeProperty('transform');
            });
        }

        function clearDesktopMenuVisibility() {
            if (!$toggleMenu.length) {
                return;
            }

            $toggleMenu.each(function () {
                var style = this.style;

                style.removeProperty('display');
                style.removeProperty('visibility');
                style.removeProperty('opacity');
                style.removeProperty('pointer-events');
                style.removeProperty('transform');
            });
        }

        function openMenu(withOverlay) {
            if (isDesktopViewport()) {
                $toggleMenu.addClass('menu-open').stop(true, true);
                setDesktopMenuVisibility(true);
                $nav.addClass('awa-menu-expanded');
                $title.addClass('active').attr('aria-expanded', 'true');
                $('body').removeClass('background_shadow_show');
                $(document).trigger('awa:vmenu:open');
                return;
            }

            clearDesktopMenuVisibility();
            $toggleMenu.addClass('menu-open').stop(true, true).fadeIn(200);
            $title.addClass('active').attr('aria-expanded', 'true');
            $('body').toggleClass('background_shadow_show', !!withOverlay);
        }

        function closeMenu() {
            if (isDesktopViewport()) {
                $toggleMenu.removeClass('menu-open').stop(true, true);
                setDesktopMenuVisibility(false);
                $nav.removeClass('awa-menu-expanded');
                $title.removeClass('active').attr('aria-expanded', 'false');
                $('body').removeClass('background_shadow_show');
                $(document).trigger('awa:vmenu:close');
                return;
            }

            clearDesktopMenuVisibility();
            $toggleMenu.removeClass('menu-open').stop(true, true).fadeOut(200);
            $title.removeClass('active').attr('aria-expanded', 'false');
            $('body').removeClass('background_shadow_show');
        }

        function isMenuOpenState() {
            return $toggleMenu.hasClass('menu-open') || $title.hasClass('active');
        }

        function ensureMobileSubmenuToggles() {
            $nav.find('.ui-menu-item.parent, .ui-menu-item.level0.parent').each(function () {
                var $item = $(this);
                var $toggle = $item.children('.open-children-toggle');

                if (!$toggle.length) {
                    $item.append('<div class="open-children-toggle navigation__toggle" role="button" aria-label="Expandir subcategorias" aria-expanded="false" tabindex="0" data-awa-vtoggle="1"></div>');
                    return;
                }

                $toggle
                    .addClass('navigation__toggle')
                    .attr('role', 'button')
                    .attr('tabindex', '0')
                    .attr('aria-label', $toggle.attr('aria-label') || 'Expandir subcategorias')
                    .attr('data-awa-vtoggle', '1');

                if (!$toggle.attr('aria-expanded')) {
                    $toggle.attr('aria-expanded', 'false');
                }
            });
        }

        function getParentItems($root) {
            return ($root || $nav).find('.ui-menu-item.parent, .ui-menu-item.level0.parent');
        }

        function getDirectChildPanels($item) {
            return $item.children(childPanelSelector);
        }

        function getFirstDirectChildPanel($item) {
            return getDirectChildPanels($item).first();
        }

        function resetParentItemState($item, animateNested) {
            var nestedAnimate = !!animateNested;

            $item.removeClass('_active');
            $item.children('a').removeClass('ui-state-active');
            $item.children('.open-children-toggle').attr('aria-expanded', 'false');

            getDirectChildPanels($item).each(function () {
                var $panel = $(this);

                $panel.removeClass('opened');

                if ($panel.hasClass('subchildmenu')) {
                    if (nestedAnimate) {
                        $panel.stop(true, true).slideUp(200);
                    } else {
                        $panel.stop(true, true).removeAttr('style');
                    }
                } else {
                    $panel.removeAttr('style');
                }
            });

            getParentItems($item).each(function () {
                var $child = $(this);

                if ($child[0] === $item[0]) {
                    return;
                }

                $child.removeClass('_active');
                $child.children('a').removeClass('ui-state-active');
                $child.children('.open-children-toggle').attr('aria-expanded', 'false');
                $child.children(childPanelSelector).removeClass('opened').removeAttr('style');
            });
        }

        function closeSiblingParentItems($item, animateNested) {
            $item.siblings('.ui-menu-item.parent, .ui-menu-item.level0.parent').each(function () {
                resetParentItemState($(this), animateNested);
            });
        }

        function syncParentItemStateFromPanels($item) {
            var $panel = getFirstDirectChildPanel($item);
            var opened = $panel.length && $panel.hasClass('opened');

            $item.toggleClass('_active', !!opened);
            $item.children('a').toggleClass('ui-state-active', !!opened);
            $item.children('.open-children-toggle').attr('aria-expanded', opened ? 'true' : 'false');
        }

        function syncAllMobileParentStates() {
            getParentItems().each(function () {
                syncParentItemStateFromPanels($(this));
            });
        }

        function focusFirstCategoryLink() {
            var $first = $toggleMenu.children('.ui-menu-item.level0:visible').children('a').first();

            if ($first.length) {
                $first.trigger('focus');
            }
        }

        function bindRokanMobileBridgeHandlers() {
            var $toggles = $nav.find('.open-children-toggle');

            $toggles.off('click' + eventNamespace + ' keydown' + eventNamespace);

            $toggles.on('click' + eventNamespace, function () {
                var $toggle = $(this);
                var $parent = $toggle.parent();

                if (isDesktopViewport()) {
                    return;
                }

                window.setTimeout(function () {
                    var $panel = getFirstDirectChildPanel($parent);
                    var opened = $panel.length && $panel.hasClass('opened');

                    if (opened) {
                        closeSiblingParentItems($parent, true);
                    }

                    syncParentItemStateFromPanels($parent);
                    syncAllMobileParentStates();
                }, 0);
            });

            $toggles.on('keydown' + eventNamespace, function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    $(this).trigger('click');
                    return;
                }

                if (event.key === 'Escape' && !isDesktopViewport()) {
                    event.preventDefault();
                    event.stopPropagation();
                    resetParentItemState($(this).parent(), true);
                }
            });
        }

        function syncOnResize() {
            if (isDesktopViewport()) {
                getParentItems().each(function () {
                    resetParentItemState($(this), false);
                });

                $toggleMenu.stop(true, true);

                if (keepDesktopMenuExpanded()) {
                    openMenu(false);
                    return;
                }

                if (isMenuOpenState()) {
                    setDesktopMenuVisibility(true);
                    $nav.addClass('awa-menu-expanded');
                    $title.addClass('active').attr('aria-expanded', 'true');
                } else {
                    setDesktopMenuVisibility(false);
                    $nav.removeClass('awa-menu-expanded');
                    $title.removeClass('active').attr('aria-expanded', 'false');
                }

                $('body').removeClass('background_shadow_show');
                return;
            }

            clearDesktopMenuVisibility();
            $nav.removeClass('awa-menu-expanded');
            $toggleMenu.removeClass('menu-open').hide();
            $title.removeClass('active').attr('aria-expanded', 'false');
            $('body').removeClass('background_shadow_show');
        }

        function fixSectionAriaHidden() {
            var $panels = $nav
                .closest('[data-role="content"], .section-item-content')
                .add($nav.closest('#nav-sections, .sections.nav-sections.category-dropdown'));

            if (!$panels.length) {
                return;
            }

            $panels.removeAttr('aria-hidden');

            if (typeof MutationObserver === 'undefined') {
                return;
            }

            $panels.each(function () {
                var el = this;
                var observer = new MutationObserver(function (mutations) {
                    var i;
                    var mutation;

                    for (i = 0; i < mutations.length; i++) {
                        mutation = mutations[i];

                        if (mutation.attributeName === 'aria-hidden' && el.getAttribute('aria-hidden') !== null) {
                            el.removeAttribute('aria-hidden');
                        }
                    }
                });

                observer.observe(el, { attributes: true, attributeFilter: ['aria-hidden'] });

                $nav.one('remove' + eventNamespace, function () {
                    observer.disconnect();
                });
            });
        }

        function applyItemLimit() {
            var $menuItems;
            var $expandContainer;
            var $expandAnchor;

            if (limitItemShow <= 0) {
                return;
            }

            $menuItems = $toggleMenu.children('li.ui-menu-item.level0').not('.expand-category-link');
            $expandContainer = $toggleMenu.children('li.expand-category-link');
            $expandAnchor = $expandContainer.find('a').first();

            if (!$expandAnchor.length) {
                $expandAnchor = $expandContainer;
            }

            if ($menuItems.length <= limitItemShow) {
                $expandContainer.hide();
                return;
            }

            $menuItems.each(function (index) {
                var $item = $(this);

                if (index >= limitItemShow) {
                    $item.addClass('orther-link').hide();
                }
            });

            $expandContainer.show();

            $expandAnchor
                .attr({
                    role: 'button',
                    'aria-expanded': 'false'
                })
                .off('click.awaExpand keydown.awaExpand')
                .on('click.awaExpand', function (event) {
                    var $link = $(this);
                    var expanding;

                    event.preventDefault();

                    expanding = !$link.hasClass('expanding');
                    $expandContainer.toggleClass('expanding', expanding);
                    $link.toggleClass('expanding', expanding);

                    if ($link.data('show-text') && $link.data('hide-text')) {
                        $link.find('span').text(
                            expanding ? $link.data('hide-text') : $link.data('show-text')
                        );
                    }

                    $link.attr('aria-expanded', expanding ? 'true' : 'false');
                    $link.find('> span').attr('aria-expanded', expanding ? 'true' : 'false');

                    if (expanding) {
                        $toggleMenu.find('.ui-menu-item.level0.orther-link').stop(true, true).fadeIn(180);
                    } else {
                        $toggleMenu.find('.ui-menu-item.level0.orther-link').stop(true, true).fadeOut(180);
                    }
                })
                .on('keydown.awaExpand', function (event) {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        $(this).trigger('click');
                        return;
                    }

                    if (event.key === 'Escape' && $(this).hasClass('expanding')) {
                        event.preventDefault();
                        $(this).trigger('click');
                    }
                });
        }

        $title.on('click' + eventNamespace, function (event) {
            event.preventDefault();

            if (keepDesktopMenuExpanded()) {
                openMenu(false);
                return;
            }

            if (isMenuOpenState()) {
                closeMenu();
                return;
            }

            openMenu(!isDesktopViewport());
        });

        $title.on('keydown' + eventNamespace, function (event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                closeMenu();
                return;
            }

            if (event.key === 'ArrowDown' && isDesktopViewport()) {
                event.preventDefault();
                openMenu(false);
                focusFirstCategoryLink();
                return;
            }

            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();

                if (isDesktopViewport()) {
                    openMenu(false);
                    focusFirstCategoryLink();
                    return;
                }

                $title.trigger('click' + eventNamespace);
            }
        });

        $nav.on('mouseenter' + eventNamespace, function () {
            if (!isDesktopViewport()) {
                return;
            }

            if (hoverCloseTimer) {
                clearTimeout(hoverCloseTimer);
                hoverCloseTimer = null;
            }

            openMenu(false);
        });

        // Also bind on the menu_left_home1 parent so hovering the title opens
        (function () {
            var menuLeftParent = $nav.closest('.menu_left_home1').get(0);
            if (menuLeftParent) {
                menuLeftParent.addEventListener('mouseenter', function () {
                    if (!isDesktopViewport()) {
                        return;
                    }
                    if (hoverCloseTimer) {
                        clearTimeout(hoverCloseTimer);
                        hoverCloseTimer = null;
                    }
                    openMenu(false);
                }, { passive: true });

                menuLeftParent.addEventListener('mouseleave', function () {
                    if (!isDesktopViewport()) {
                        return;
                    }
                    if (hoverCloseTimer) {
                        clearTimeout(hoverCloseTimer);
                    }
                    hoverCloseTimer = setTimeout(function () {
                        hoverCloseTimer = null;
                        var active = document.activeElement;
                        if (active && menuLeftParent.contains(active)) {
                            active.blur();
                        }
                        closeMenu();
                    }, HOVER_CLOSE_DELAY);
                }, { passive: true });
            }
        })();

        // Native fallback — ensures hover works even without jQuery delegation
        $nav.get(0).addEventListener('mouseenter', function () {
            if (!isDesktopViewport()) {
                return;
            }

            if (hoverCloseTimer) {
                clearTimeout(hoverCloseTimer);
                hoverCloseTimer = null;
            }

            openMenu(false);
        }, { passive: true });

        $nav.on('mouseleave' + eventNamespace, function () {
            if (!isDesktopViewport()) {
                return;
            }

            if (keepDesktopMenuExpanded()) {
                openMenu(false);
                return;
            }

            if (hoverCloseTimer) {
                clearTimeout(hoverCloseTimer);
            }

            hoverCloseTimer = setTimeout(function () {
                hoverCloseTimer = null;

                // Blur any focused element inside nav before closing
                var active = document.activeElement;
                if (active && $nav.get(0).contains(active)) {
                    active.blur();
                }

                closeMenu();
            }, HOVER_CLOSE_DELAY);
        });

        $nav.on('focusin' + eventNamespace, function () {
            if (!isDesktopViewport()) {
                return;
            }

            openMenu(false);
        });

        $nav.on('focusout' + eventNamespace, function () {
            if (!isDesktopViewport()) {
                return;
            }

            window.setTimeout(function () {
                var root = $nav.get(0);
                var active = document.activeElement;

                if (root && active && root.contains(active)) {
                    return;
                }

                if (keepDesktopMenuExpanded()) {
                    openMenu(false);
                    return;
                }

                closeMenu();
            }, 0);
        });

        $nav.on('keydown' + eventNamespace, function (event) {
            if (event.key !== 'Escape') {
                return;
            }

            if (!isDesktopViewport() && !isMenuOpenState()) {
                return;
            }

            if (keepDesktopMenuExpanded()) {
                openMenu(false);
                return;
            }

            event.stopPropagation();
            closeMenu();
            $title.trigger('focus');
        });

        if (rokanWidgetEnabled) {
            $nav.on('focusin' + eventNamespace, 'li.level0.parent, li.classic .subchildmenu > li.parent', function () {
                if (isDesktopViewport()) {
                    $(this).triggerHandler('mouseenter');
                }
            });
        } else {
            $nav.on('click' + eventNamespace, '.open-children-toggle', function (event) {
                var $toggle;
                var $parent;
                var expanding;

                event.preventDefault();
                event.stopPropagation();

                if (isDesktopViewport()) {
                    return;
                }

                $toggle = $(this);
                $parent = $toggle.parent();
                expanding = !$parent.hasClass('_active');

                if (expanding) {
                    closeSiblingParentItems($parent, true);
                }

                $parent.toggleClass('_active');
                $toggle.attr('aria-expanded', expanding ? 'true' : 'false');
                $parent.children(childPanelSelector).stop(true, true).slideToggle(200);
            });

            $nav.on('keydown' + eventNamespace, '.open-children-toggle', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    $(this).trigger('click');
                    return;
                }

                if (event.key === 'Escape' && !isDesktopViewport()) {
                    event.preventDefault();
                    event.stopPropagation();
                    resetParentItemState($(this).parent(), true);
                }
            });
        }

        $(overlaySelector).on('click' + overlayClickNamespace, function () {
            if (!isDesktopViewport()) {
                closeMenu();
            }
        });

        $(window).on('resize' + resizeNamespace, debounce(function () {
            ensureMobileSubmenuToggles();

            if (rokanWidgetEnabled) {
                bindRokanMobileBridgeHandlers();
            }

            syncOnResize();
        }, 120));

        $nav.on('remove' + eventNamespace, function () {
            $(window).off(resizeNamespace);
            $(overlaySelector).off(overlayClickNamespace);
        });

        ensureMobileSubmenuToggles();

        if (rokanWidgetEnabled) {
            bindRokanMobileBridgeHandlers();
        }

        applyItemLimit();
        syncOnResize();
        fixSectionAriaHidden();
    };
});
