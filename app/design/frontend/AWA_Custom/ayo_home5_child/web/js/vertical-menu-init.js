/**
 * AWA Motos — Vertical Menu Toggle Controller
 *
 * Manages the sidebar vertical-menu lifecycle:
 *  - Desktop >= 992 px : hover/focus opens category dropdown (Home 5 default behavior)
 *  - Mobile  <  992 px : animated drawer + overlay + submenu accordions
 *
 * The native Rokanthemes VerticalMenu jQuery plugin is still initialised for
 * its flyout-positioning logic (hover on desktop). AWA does NOT duplicate
 * that behaviour; it only adds: open/close of the category list,
 * expand/collapse "Show More", and mobile submenu toggles when the
 * Rokanthemes widget is absent.
 *
 * Important: the child theme CSS still expects the alias class `vmm-open`
 * for dropdown visibility, so we keep `menu-open` and `vmm-open` synchronized.
 *
 * @module js/vertical-menu-init
 */
define([
    'jquery',
    'rokanthemes/verticalmenu'
], function ($) {
    'use strict';

    function initRokanWidget($menus) {
        if (!$.isFunction($.fn.VerticalMenu)) {
            return false;
        }

        var ok = false;

        $menus.each(function () {
            var $menu = $(this);

            if (!$menu.data('awaRokanInit')) {
                $menu.VerticalMenu();
                $menu.data('awaRokanInit', 1);
            }

            ok = true;
        });

        return ok;
    }

    function debounce(fn, ms) {
        var timer;

        return function () {
            var context = this;
            var args = arguments;

            clearTimeout(timer);
            timer = setTimeout(function () {
                fn.apply(context, args);
            }, ms || 120);
        };
    }

    function pruneEmptyBlocks($list) {
        if (!$list || !$list.length) {
            return;
        }

        var contentSelector = 'img[src],picture source[srcset],video source[src],iframe[src],a[href],.block,.cms-block';

        $list.find('> li.vertical-menu-custom-block, > li.vertical-bg-img').each(function () {
            var $item = $(this);

            if (!$.trim($item.text()).length && !$item.find(contentSelector).length) {
                $item.remove();
            }
        });
    }

    return function (config, element) {
        var $nav = $(element);
        var $title = $nav.find('.title-category-dropdown');
        var $list = $nav.find('.togge-menu');
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
        var rokanActive;
        var mediaQuery = window.matchMedia
            ? window.matchMedia('(min-width: ' + desktopBreakpoint + 'px)')
            : null;

        if (!$nav.length || $nav.data('awaVMInit')) {
            return;
        }

        $nav.data('awaVMInit', 1);
        $nav.attr('data-awa-verticalmenu-owner', 'vertical-menu-init');

        rokanActive = initRokanWidget(
            $nav.filter('.verticalmenu').add($nav.find('.verticalmenu'))
        );

        pruneEmptyBlocks($list);

        function isDesktop() {
            return mediaQuery ? mediaQuery.matches : window.innerWidth >= desktopBreakpoint;
        }

        function syncDesktopPanelPosition() {
            var anchor = $title.get(0) || $nav.get(0);
            var rect;
            var availableWidth;
            var width;
            var top;
            var left;

            if (!isDesktop() || !$list.length || !anchor) {
                return;
            }

            rect = anchor.getBoundingClientRect();

            if (!rect.width && !rect.height) {
                return;
            }

            availableWidth = Math.min(window.innerWidth - rect.left - 8, 980);
            width = Math.max(availableWidth, 560);
            top = rect.bottom.toFixed(1) + 'px';
            left = rect.left.toFixed(1) + 'px';
            width = width.toFixed(1) + 'px';

            $list.get(0).style.setProperty('--vmm-top', top);
            $list.get(0).style.setProperty('--vmm-left', left);
            $list.get(0).style.setProperty('--vmm-width', width);

            $list.find('> li.ui-menu-item.level0 > .level0.submenu, > li.ui-menu-item.level0 > .vmm-empty-submenu').each(function () {
                this.style.setProperty('--vmm-top', top);
                this.style.setProperty('--vmm-left', left);
                this.style.setProperty('--vmm-width', width);
            });
        }

        function setMenuOpenState(isOpen) {
            var expanded = isOpen ? 'true' : 'false';

            $nav.toggleClass('menu-open', isOpen).toggleClass('vmm-open', isOpen);
            $list.toggleClass('menu-open', isOpen).toggleClass('vmm-open', isOpen);
            $title.toggleClass('active', isOpen).attr('aria-expanded', expanded);
        }

        function openMenu() {
            setMenuOpenState(true);

            if (isDesktop()) {
                syncDesktopPanelPosition();
                $list.stop(true, true).css('display', 'grid');
                $('body').removeClass('background_shadow_show');
            } else {
                $list.stop(true, true).fadeIn(200);
                $('body').addClass('background_shadow_show');
            }
        }

        function closeMenu() {
            setMenuOpenState(false);

            if (isDesktop()) {
                $list.stop(true, true).css('display', 'none');
            } else {
                $list.stop(true, true).fadeOut(200);
            }

            $('body').removeClass('background_shadow_show');
        }

        function isOpen() {
            return $list.hasClass('menu-open') || $list.hasClass('vmm-open');
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
                } else {
                    $toggle
                        .attr({
                            role: 'button',
                            tabindex: '0',
                            'aria-label': $toggle.attr('aria-label') || 'Expandir subcategorias',
                            'aria-expanded': $toggle.attr('aria-expanded') || 'false'
                        })
                        .addClass('navigation__toggle');
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

        function focusFirstCategoryLink() {
            var $first = $list.children('.ui-menu-item.level0:visible').children('a').first();

            if ($first.length) {
                $first.trigger('focus');
            }
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

        function syncOnResize() {
            if (isDesktop()) {
                getParentItems().each(function () {
                    resetParentItemState($(this), false);
                });

                syncDesktopPanelPosition();

                if (isOpen()) {
                    $list.stop(true, true).css('display', 'grid');
                    setMenuOpenState(true);
                } else {
                    $list.stop(true, true).css('display', 'none');
                    setMenuOpenState(false);
                }

                $('body').removeClass('background_shadow_show');
            } else {
                $nav.removeClass('menu-open vmm-open');
                $list.removeClass('menu-open vmm-open').hide();
                $title.removeClass('active').attr('aria-expanded', 'false');
                $('body').removeClass('background_shadow_show');
            }
        }

        $title.on('click' + namespace, function (event) {
            event.preventDefault();

            if (isDesktop()) {
                isOpen() ? closeMenu() : openMenu();
                return;
            }

            if (isOpen()) {
                closeMenu();
            } else {
                openMenu();
                scrollToActiveItem();
            }
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

        var hoverTimer;

        $nav.on('mouseenter' + namespace, function () {
            if (isDesktop()) {
                clearTimeout(hoverTimer);
                hoverTimer = window.setTimeout(function () { openMenu(); }, 120);
            }
        });

        $nav.on('mouseleave' + namespace, function () {
            var root = $nav.get(0);
            var active = document.activeElement;

            clearTimeout(hoverTimer);

            if (!isDesktop()) {
                return;
            }

            if (root && active && root.contains(active)) {
                return;
            }

            closeMenu();
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

        // Swipe-to-close no mobile (deslizar p/ esquerda fecha o menu)
        (function initSwipeToClose() {
            var touchStartX = 0;
            var touchStartY = 0;

            $list.on('touchstart' + namespace, function (e) {
                var touch = e.originalEvent.changedTouches[0];
                touchStartX = touch.screenX;
                touchStartY = touch.screenY;
            });

            $list.on('touchend' + namespace, function (e) {
                var touch = e.originalEvent.changedTouches[0];
                var dx = touch.screenX - touchStartX;
                var dy = Math.abs(touch.screenY - touchStartY);

                if (dx < -60 && dy < 100 && !isDesktop()) {
                    closeMenu();
                }
            });
        })();

        // Rola para a categoria ativa quando o menu abre no mobile
        function scrollToActiveItem() {
            var $active = $list.find('.awa-current-cat, .vmm-current-cat, .ui-menu-item.level0._active').first();
            var listEl = $list.get(0);

            if (!$active.length || !listEl || isDesktop()) {
                return;
            }

            window.setTimeout(function () {
                var itemTop = $active.position() ? $active.position().top : 0;
                listEl.scrollTop = Math.max(0, itemTop - 60);
            }, 220);
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
                    $anchor.find('span').text(expanding ? $anchor.data('hide-text') : $anchor.data('show-text'));
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

        // Fallback: fecha ao clicar fora do menu no mobile (útil quando o overlay está desativado por CSS)
        $(document).on('click' + namespace, function (event) {
            if (isDesktop() || !isOpen()) {
                return;
            }

            var target = event && event.target;
            var root = $nav.get(0);

            if (!target || !root) {
                return;
            }

            if (root.contains(target)) {
                return;
            }

            closeMenu();
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
            $(document).off(namespace);
            $(overlaySelector).off(namespace);
        });

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

        ensureMobileToggles();

        if (rokanActive) {
            bindRokanMobileBridgeHandlers();
        }

        syncOnResize();
        fixSectionAriaHidden();
    };
});
