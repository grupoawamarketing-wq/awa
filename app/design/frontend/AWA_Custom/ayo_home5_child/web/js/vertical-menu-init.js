define([
    'jquery',
    'rokanthemes/verticalmenu'
], function ($) {
    'use strict';

    function initRokanWidget($menus) {
        if (!$.isFunction($.fn.VerticalMenu)) {
            return false;
        }

        var initialized = false;

        $menus.each(function () {
            var $menu = $(this);

            if (!$menu.data('awaRokanInit')) {
                $menu.VerticalMenu();
                $menu.data('awaRokanInit', 1);
            }

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

    function pruneEmptyBlocks($list) {
        if (!$list || !$list.length) {
            return;
        }

        var selector = 'img[src],picture source[srcset],video source[src],iframe[src],a[href],.block,.cms-block';

        $list.find('> li.vertical-menu-custom-block, > li.vertical-bg-img').each(function () {
            var $item = $(this);

            if (!$.trim($item.text()).length && !$item.find(selector).length) {
                $item.remove();
            }
        });
    }

    return function (config, element) {
        var $nav = $(element);
        var $title = $nav.find('.title-category-dropdown');
        var $list = $nav.find('.togge-menu');
        var $status = $nav.find('[data-role="awa-vertical-menu-status"]');
        var $expandLink = $nav.find('.vm-toggle-categories');
        var $items = $nav.find('.ui-menu-item.level0');
        var safeUid = ($nav.attr('id') || $title.attr('aria-controls') || 'avm-' + Math.random().toString(36).slice(2))
            .replace(/[^a-zA-Z0-9_-]/g, '');
        var overlaySelector = (config && config.overlaySelector) || '.shadow_bkg_show';
        var desktopBreakpoint = parseInt(config && config.desktopBreakpoint, 10) || 992;
        var limitItemShow = parseInt($list.attr('data-limit-show'), 10)
            || parseInt(config && config.limitShow, 10) || 0;
        var childPanelSelector = '.submenu, ul.level0, .subchildmenu';
        var namespace = '.awaVM-' + safeUid;
        var outsideNamespace = '.awaVMOutside-' + safeUid;
        var outsideEvents = window.PointerEvent ?
            ('pointerdown' + outsideNamespace) :
            ('touchstart' + outsideNamespace + ' mousedown' + outsideNamespace);
        var triggerLabel = $.trim($title.attr('aria-label') || $title.text()) || 'Departamentos';
        var rokanActive;
        var desktopHoverCloseTimer = null;

        if (!$nav.length || $nav.data('awaVMInit')) {
            return;
        }

        $nav.data('awaVMInit', 1);
        $nav.attr('data-awa-verticalmenu-owner', 'vertical-menu-init');
        $nav.attr('data-awa-side-inline-flyout', 'true');

        rokanActive = initRokanWidget(
            $nav.filter('.verticalmenu').add($nav.find('.verticalmenu'))
        );

        pruneEmptyBlocks($list);

        function isDesktop() {
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

            return body.classList.contains('cms-index-index')
                || body.classList.contains('cms-home')
                || body.classList.contains('cms-homepage_ayo_home5')
                || body.classList.contains('cms-homepage_ayo_home5_demo_stage');
        }

        function keepDesktopMenuExpanded() {
            return isDesktop() && isHomeContext();
        }

        function setStatusMessage(message) {
            if ($status.length) {
                $status.text(message);
            }
        }

        function syncMenuA11yState(isOpen) {
            var expandedValue = isOpen ? 'true' : 'false';
            var hiddenValue = isOpen ? 'false' : 'true';

            $nav
                .attr('data-menu-state', isOpen ? 'open' : 'closed')
                .toggleClass('menu-open', isOpen)
                .toggleClass('vmm-open', isOpen);
            $title
                .toggleClass('active', isOpen)
                .toggleClass('is-open', isOpen)
                .attr('aria-expanded', expandedValue)
                .attr('aria-label', isOpen ? ('Fechar ' + triggerLabel) : triggerLabel);
            $list
                .toggleClass('menu-open', isOpen)
                .toggleClass('vmm-open', isOpen)
                .attr('aria-hidden', hiddenValue);

            setStatusMessage(isOpen ? 'Menu aberto' : 'Menu fechado');
        }

        function isOpen() {
            return $list.hasClass('menu-open') || $list.hasClass('vmm-open');
        }

        function bindOutsideInteractionHandlers() {
            $(document)
                .off(outsideEvents)
                .on(outsideEvents, function (event) {
                    var $target = $(event.target);

                    if (!$target.closest($nav).length &&
                            !$target.closest('.awa-side-submenu-portal').length &&
                            !$target.closest('.awa-nav-overlay').length) {
                        closeMenu();
                    }
                });
        }

        function unbindOutsideInteractionHandlers() {
            $(document).off(outsideEvents);
        }

        function getTopLevelKeyboardItems() {
            return $list.children('.ui-menu-item.level0:visible').children('a.level-top:visible');
        }

        function focusTopLevelItem(index) {
            var $links = getTopLevelKeyboardItems();

            if ($links.length) {
                $links.eq(index).trigger('focus');
            }
        }

        function focusFirstCategoryLink() {
            focusTopLevelItem(0);
        }

        function focusLastCategoryLink() {
            var $links = getTopLevelKeyboardItems();

            if ($links.length) {
                $links.last().trigger('focus');
            }
        }

        function setNavBarClip(clip) {
            var element = $nav.closest('.header-control.header-nav')[0]
                || document.querySelector('.header-control.header-nav.awa-nav-bar');

            if (!element) {
                return;
            }

            element.style.setProperty('overflow', clip ? 'hidden' : 'visible', 'important');
        }

        function openMenu() {
            syncMenuA11yState(true);

            if (isDesktop()) {
                $list.stop(true, true).removeAttr('style').show();
                $('body').removeClass('background_shadow_show');
                setNavBarClip(false);
                bindOutsideInteractionHandlers();
                return;
            }

            $list.stop(true, true).fadeIn(200);
            $('body').addClass('background_shadow_show');
            bindOutsideInteractionHandlers();
        }

        function closeMenu() {
            if (keepDesktopMenuExpanded()) {
                openMenu();
                return;
            }

            syncMenuA11yState(false);

            if (isDesktop()) {
                $list.stop(true, true).hide();
                $('body').removeClass('background_shadow_show');
                setNavBarClip(true);
                unbindOutsideInteractionHandlers();
                return;
            }

            $list.stop(true, true).fadeOut(200);
            $('body').removeClass('background_shadow_show');
            unbindOutsideInteractionHandlers();
        }

        function ensureMobileToggles() {
            $nav.find('.ui-menu-item.parent, .ui-menu-item.level0.parent').each(function () {
                var $item = $(this);
                var $toggle = $item.children('.open-children-toggle');

                if (!$toggle.length) {
                    $item.append(
                        '<div class="open-children-toggle navigation__toggle" role="button"' +
                        ' aria-label="Expandir subcategorias" aria-expanded="false" tabindex="0"></div>'
                    );
                    return;
                }

                $toggle.attr({
                    role: 'button',
                    tabindex: '0',
                    'aria-label': $toggle.attr('aria-label') || 'Expandir subcategorias',
                    'aria-expanded': $toggle.attr('aria-expanded') || 'false'
                }).addClass('navigation__toggle');
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
            var shouldAnimate = !!animateNested;

            $item.removeClass('_active');
            $item.children('a').removeClass('ui-state-active');
            $item.children('.open-children-toggle').attr('aria-expanded', 'false');

            getDirectChildPanels($item).each(function () {
                var $panel = $(this);

                $panel.removeClass('opened');

                if ($panel.hasClass('subchildmenu')) {
                    if (shouldAnimate) {
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

        function bindRokanMobileBridgeHandlers() {
            var $toggles = $nav.find('.open-children-toggle');

            $toggles.off('click' + namespace + ' keydown' + namespace);

            $toggles.on('click' + namespace, function () {
                var $toggle = $(this);
                var $parent = $toggle.parent();

                if (isDesktop()) {
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

            $toggles.on('keydown' + namespace, function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    $(this).trigger('click');
                    return;
                }

                if (event.key === 'Escape' && !isDesktop()) {
                    event.preventDefault();
                    event.stopPropagation();
                    resetParentItemState($(this).parent(), true);
                }
            });
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
                var element = this;
                var observer = new MutationObserver(function (mutations) {
                    var index;
                    var mutation;

                    for (index = 0; index < mutations.length; index++) {
                        mutation = mutations[index];

                        if (mutation.attributeName === 'aria-hidden'
                                && element.getAttribute('aria-hidden') !== null) {
                            element.removeAttribute('aria-hidden');
                        }
                    }
                });

                observer.observe(element, { attributes: true, attributeFilter: ['aria-hidden'] });

                $nav.one('remove' + namespace, function () {
                    observer.disconnect();
                });
            });
        }

        function syncOnResize() {
            if (isDesktop()) {
                getParentItems().each(function () {
                    resetParentItemState($(this), false);
                });

                $list.stop(true, true).removeAttr('style');

                if (keepDesktopMenuExpanded()) {
                    openMenu();
                    return;
                }

                if (isOpen()) {
                    $list.show();
                    syncMenuA11yState(true);
                    setNavBarClip(false);
                    bindOutsideInteractionHandlers();
                } else {
                    $list.hide();
                    syncMenuA11yState(false);
                    setNavBarClip(true);
                    unbindOutsideInteractionHandlers();
                }

                $('body').removeClass('background_shadow_show');
                return;
            }

            $nav.removeClass('menu-open vmm-open');
            $list.removeClass('menu-open vmm-open').hide();
            syncMenuA11yState(false);
            $('body').removeClass('background_shadow_show');
            setNavBarClip(true);
            unbindOutsideInteractionHandlers();
        }

        $title.on('click' + namespace, function (event) {
            event.preventDefault();
            event.stopPropagation();

            if (keepDesktopMenuExpanded()) {
                openMenu();
                return;
            }

            if (isOpen()) {
                closeMenu();
                return;
            }

            openMenu();
        });

        $title.on('keydown' + namespace, function (event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                closeMenu();
                return;
            }

            if (event.key === 'ArrowDown' && isDesktop()) {
                event.preventDefault();
                openMenu();
                focusFirstCategoryLink();
                return;
            }

            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();

                if (isDesktop()) {
                    openMenu();
                    focusFirstCategoryLink();
                    return;
                }

                $title.trigger('click' + namespace);
            }
        });

        $nav.on('mouseenter' + namespace, function () {
            if (!isDesktop()) {
                return;
            }

            if (desktopHoverCloseTimer) {
                clearTimeout(desktopHoverCloseTimer);
                desktopHoverCloseTimer = null;
            }

            openMenu();
        });

        $nav.on('mouseleave' + namespace, function () {
            var root = $nav.get(0);
            var active = document.activeElement;

            if (!isDesktop()) {
                return;
            }

            if (root && active && root.contains(active)) {
                return;
            }

            if (desktopHoverCloseTimer) {
                clearTimeout(desktopHoverCloseTimer);
            }

            desktopHoverCloseTimer = window.setTimeout(function () {
                desktopHoverCloseTimer = null;
                closeMenu();
            }, 120);
        });

        $nav.on('focusin' + namespace, function () {
            if (isDesktop()) {
                openMenu();
            }
        });

        $nav.on('focusout' + namespace, function () {
            if (!isDesktop()) {
                return;
            }

            window.setTimeout(function () {
                var root = $nav.get(0);
                var active = document.activeElement;

                if (root && active && root.contains(active)) {
                    return;
                }

                closeMenu();
            }, 0);
        });

        $nav.on('keydown' + namespace, function (event) {
            if (event.key !== 'Escape') {
                return;
            }

            if (!isDesktop() && !isOpen()) {
                return;
            }

            event.stopPropagation();
            closeMenu();
            $title.trigger('focus');
        });

        $list.on('keydown' + namespace, '> li.ui-menu-item.level0 > a.level-top', function (event) {
            var $links;
            var currentIndex;

            if (!isOpen()) {
                return;
            }

            $links = getTopLevelKeyboardItems();
            currentIndex = $links.index(this);

            if (!$links.length || currentIndex === -1) {
                return;
            }

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                focusTopLevelItem((currentIndex + 1) % $links.length);
                return;
            }

            if (event.key === 'ArrowUp') {
                event.preventDefault();
                focusTopLevelItem((currentIndex - 1 + $links.length) % $links.length);
                return;
            }

            if (event.key === 'Home') {
                event.preventDefault();
                focusFirstCategoryLink();
                return;
            }

            if (event.key === 'End') {
                event.preventDefault();
                focusLastCategoryLink();
                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                closeMenu();
                $title.trigger('focus');
            }
        });

        if (rokanActive) {
            $nav.on('focusin' + namespace, 'li.level0.parent, li.classic .subchildmenu > li.parent', function () {
                if (isDesktop()) {
                    $(this).triggerHandler('mouseenter');
                }
            });
        }

        if (!rokanActive) {
            $nav.on('click' + namespace, '.open-children-toggle', function (event) {
                var $toggle;
                var $parent;
                var expanding;

                event.preventDefault();
                event.stopPropagation();

                if (isDesktop()) {
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

            $nav.on('keydown' + namespace, '.open-children-toggle', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    $(this).trigger('click');
                    return;
                }

                if (event.key === 'Escape' && !isDesktop()) {
                    event.preventDefault();
                    event.stopPropagation();
                    resetParentItemState($(this).parent(), true);
                }
            });
        } else {
            bindRokanMobileBridgeHandlers();
        }

        (function initExpandLink() {
            if (limitItemShow <= 0) {
                $expandLink.closest('.expand-category-link').hide();
                return;
            }

            if ($items.length <= limitItemShow) {
                $expandLink.closest('.expand-category-link').hide();
                return;
            }

            $items.each(function (index) {
                if (index >= limitItemShow) {
                    $(this).addClass('orther-link').hide();
                }
            });

            $expandLink.closest('.expand-category-link').show();

            $expandLink.on('click' + namespace, function (event) {
                var $anchor;
                var $hidden;
                var expanding;

                event.preventDefault();

                $anchor = $(this);
                $hidden = $nav.find('.ui-menu-item.level0.orther-link');
                expanding = !$anchor.hasClass('expanding');

                $anchor.toggleClass('expanding', expanding)
                    .closest('.expand-category-link').toggleClass('expanding', expanding);

                if ($anchor.data('show-text') && $anchor.data('hide-text')) {
                    $anchor.find('span').text(
                        expanding ? $anchor.data('hide-text') : $anchor.data('show-text')
                    );
                }

                $anchor.attr('aria-expanded', expanding ? 'true' : 'false');
                $hidden.stop(true, true)[expanding ? 'fadeIn' : 'fadeOut'](180);
            }).on('keydown' + namespace, function (event) {
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
        })();

        $(overlaySelector).on('click' + namespace, function () {
            if (!isDesktop()) {
                closeMenu();
            }
        });

        $(window).on('resize' + namespace, debounce(function () {
            ensureMobileToggles();

            if (rokanActive) {
                bindRokanMobileBridgeHandlers();
            }

            syncOnResize();
        }, 120));

        $nav.on('remove' + namespace, function () {
            $(window).off(namespace);
            $(overlaySelector).off(namespace);
            unbindOutsideInteractionHandlers();
        });

        ensureMobileToggles();

        if (rokanActive) {
            bindRokanMobileBridgeHandlers();
        }

        syncOnResize();
        fixSectionAriaHidden();
    };
});
