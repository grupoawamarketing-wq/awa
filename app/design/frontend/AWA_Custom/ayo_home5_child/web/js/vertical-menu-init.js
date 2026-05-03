/**
 * AWA Motos — Vertical Menu Toggle Controller (v2 — padronizado Ayo)
 *
 * Manages the sidebar vertical-menu lifecycle:
 *  - Desktop >= 992 px : hover/focus opens category dropdown (Home 5 default)
 *  - Home page desktop  : menu starts expanded (keepDesktopMenuExpanded)
 *  - Mobile  <  992 px : animated drawer + overlay + submenu accordions
 *
 * Restores Ayo parent behaviour (isHomeContext / keepDesktopMenuExpanded)
 * while keeping AWA enhancements: vmm-open class sync, --vmm-* CSS variables
 * for fixed submenu positioning, swipe-to-close, scroll-to-active, and
 * backdrop integration.
 *
 * @module js/vertical-menu-init
 */
define([
    'jquery',
    'rokanthemes/verticalmenu'
], function ($) {
    'use strict';

    /* ================================================================ */
    /*  Helpers (shared across instances)                               */
    /* ================================================================ */

    /**
     * Initialise the Rokanthemes VerticalMenu plugin (idempotent).
     * @param  {jQuery} $menus
     * @return {boolean} true when at least one widget is active
     */
    function initRokanWidget($menus) {
        if (!$.isFunction($.fn.VerticalMenu)) {
            return false;
        }

        var ok = false;

        $menus.each(function () {
            var $m = $(this);

            if (!$m.data('awaRokanInit')) {
                $m.VerticalMenu();
                $m.data('awaRokanInit', 1);
            }

            ok = true;
        });

        return ok;
    }

    /** Trailing-edge debounce. */
    function debounce(fn, ms) {
        var t;

        return function () {
            var ctx  = this;
            var args = arguments;

            clearTimeout(t);
            t = setTimeout(function () { fn.apply(ctx, args); }, ms || 120);
        };
    }

    /** Remove empty CMS placeholder <li> nodes the block renderer may inject. */
    function pruneEmptyBlocks($list) {
        if (!$list || !$list.length) {
            return;
        }

        var has = 'img[src],picture source[srcset],video source[src],iframe[src],a[href],.block,.cms-block';

        $list.find('> li.vertical-menu-custom-block, > li.vertical-bg-img').each(function () {
            var $li = $(this);

            if (!$.trim($li.text()).length && !$li.find(has).length) {
                $li.remove();
            }
        });
    }

    /* ================================================================ */
    /*  Component (called once per x-magento-init match)                */
    /* ================================================================ */

    return function (config, element) {
        var $nav        = $(element);
        var $title      = $nav.find('.title-category-dropdown');
        var $list       = $nav.find('.togge-menu');
        var $expandLink = $nav.find('.vm-toggle-categories');
        var $items      = $nav.find('.ui-menu-item.level0');

        /* ---- config ------------------------------------------------ */
        var safeUid = ($nav.attr('id') || $title.attr('aria-controls') || 'avm-' + Math.random().toString(36).slice(2))
                          .replace(/[^a-zA-Z0-9_-]/g, '');
        var overlaySelector   = (config && config.overlaySelector) || '.shadow_bkg_show';
        var desktopBreakpoint = parseInt(config && config.desktopBreakpoint, 10) || 992;
        var limitItemShow     = parseInt($list.attr('data-limit-show'), 10)
                                || parseInt(config && config.limitShow, 10) || 0;
        var childPanelSelector = '.submenu, ul.level0, .subchildmenu';
        var NS = '.awaVM-' + safeUid;

        /* ---- guard: never double-init ------------------------------ */
        if (!$nav.length || $nav.data('awaVMInit')) {
            return;
        }

        $nav.data('awaVMInit', 1);
        $nav.attr('data-awa-verticalmenu-owner', 'vertical-menu-init');

        /* ---- Fix: force title background via inline style ---------- */
        /* Multiple CSS shorthand rules with !important conflict,     */
        /* making the computed background transparent. Inline longhand */
        /* overrides all stylesheet rules reliably.                    */
        if ($title.length) {
            $title.css({
                'background-color': '#b73337',
                'color': '#fff'
            });
            $title[0].style.setProperty('background-color', '#b73337', 'important');
        }

        /* ---- Rokanthemes flyout widget ------------------------------ */
        var rokanActive = initRokanWidget(
            $nav.filter('.verticalmenu').add($nav.find('.verticalmenu'))
        );

        pruneEmptyBlocks($list);

        /* ---- Fix: aria-label on level-top links includes badge text --- */
        /* Rokanthemes .VerticalMenu() sets aria-label from full textContent,
           which includes .cat-label badge text even when it is aria-hidden.
           Correct each link aria-label to use only .navigation__label text. */
        $nav.find('a.level-top, a.navigation__link').each(function () {
            var $a     = $(this);
            var $label = $a.children('.navigation__label');

            if ($a.attr('aria-label') && $a.find('.cat-label[aria-hidden]').length && $label.length) {
                $a.attr('aria-label', $label.text().trim());
            }
        });

        /* ---- viewport ---------------------------------------------- */
        var mql = window.matchMedia
            ? window.matchMedia('(min-width: ' + desktopBreakpoint + 'px)')
            : null;

        function isDesktop() {
            return mql ? mql.matches : window.innerWidth >= desktopBreakpoint;
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
            /* AWA AUDIT-2026-04: Menu should NOT auto-expand on homepage.
               Hero banner visibility is priority. Menu opens on hover/click. */
            return false;
        }

        /* ---- Submenu position sync (CSS vars for fixed flyouts) ----- */
        function syncDesktopPanelPosition() {
            var anchor = $title.get(0) || $nav.get(0);
            var rect;
            var top;
            var left;

            if (!isDesktop() || !$list.length || !anchor) {
                return;
            }

            rect = anchor.getBoundingClientRect();

            if (!rect.width && !rect.height) {
                return;
            }

            top  = rect.bottom.toFixed(1) + 'px';
            left = rect.left.toFixed(1) + 'px';

            $list.get(0).style.setProperty('--vmm-top', top);
            $list.get(0).style.setProperty('--vmm-left', left);

            $list.find('> li.ui-menu-item.level0 > .level0.submenu, > li.ui-menu-item.level0 > .vmm-empty-submenu').each(function () {
                this.style.setProperty('--vmm-top', top);
                this.style.setProperty('--vmm-left', left);
            });
        }

        /* ============================================================ */
        /*  Open / Close (syncs both menu-open and vmm-open classes)    */
        /* ============================================================ */

        function setMenuOpenState(open) {
            var expanded = open ? 'true' : 'false';

            $nav.toggleClass('menu-open', open).toggleClass('vmm-open', open);
            $list.toggleClass('menu-open', open).toggleClass('vmm-open', open);
            $title.toggleClass('active', open).attr('aria-expanded', expanded);
        }

        function openMenu() {
            setMenuOpenState(true);

            if (isDesktop()) {
                closeDesktopSiblingSubmenus();
                $list.stop(true, true).removeAttr('style').show();
                syncDesktopPanelPosition();
                $('body').removeClass('background_shadow_show');
            } else {
                $list.stop(true, true).fadeIn(200);
                $('body').addClass('background_shadow_show');
            }
        }

        function setDesktopSubmenuInlineState($item, open) {
            var $panel;

            if (!isDesktop() || !$item || !$item.length) {
                return;
            }

            $panel = $item.children('.submenu, .level0.submenu').first();

            if (!$panel.length) {
                return;
            }

            if (open) {
                $item.addClass('vmm-active');
                $panel.css({
                    visibility: 'visible',
                    opacity: '1',
                    pointerEvents: 'auto'
                });
                return;
            }

            $item.removeClass('vmm-active');
            $panel.css({
                visibility: '',
                opacity: '',
                pointerEvents: ''
            });
        }

        function closeDesktopSiblingSubmenus($activeItem) {
            var $targets;

            if (!isDesktop()) {
                return;
            }

            $targets = $nav.find('li.ui-menu-item.level0.parent');

            if ($activeItem && $activeItem.length) {
                $targets = $targets.not($activeItem);
            }

            $targets.each(function () {
                var $item = $(this);
                var $panel = $item.children('.submenu, .level0.submenu').first();

                $item.removeClass('vmm-active _active is-open active ui-state-active awa-vmf-active');
                $item.children('a.level-top, > a').attr('aria-expanded', 'false');
                $item.children('.open-children-toggle').attr('aria-expanded', 'false');

                if ($panel.length) {
                    $panel.removeClass('opened');
                    $panel.css({
                        display: 'none',
                        visibility: 'hidden',
                        opacity: '0',
                        pointerEvents: 'none'
                    });
                }
            });
        }

        function closeMenu() {
            setMenuOpenState(false);

            if (isDesktop()) {
                $list.stop(true, true).hide();
            } else {
                $list.stop(true, true).fadeOut(200);
            }

            $('body').removeClass('background_shadow_show');
        }

        function isOpen() {
            return $list.hasClass('menu-open') || $list.hasClass('vmm-open');
        }

        /* ============================================================ */
        /*  Mobile sub-menu toggles                                     */
        /* ============================================================ */

        function ensureMobileToggles() {
            $nav.find('.ui-menu-item.parent, .ui-menu-item.level0.parent').each(function () {
                var $li = $(this);
                var $t  = $li.children('.open-children-toggle');

                if (!$t.length) {
                    $li.append(
                        '<div class="open-children-toggle navigation__toggle" role="button"' +
                        ' aria-label="Expandir subcategorias" aria-expanded="false" tabindex="0"></div>'
                    );
                } else {
                    $t.attr({
                        'role':          'button',
                        'tabindex':      '0',
                        'aria-label':    $t.attr('aria-label') || 'Expandir subcategorias',
                        'aria-expanded': $t.attr('aria-expanded') || 'false'
                    }).addClass('navigation__toggle');
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
            var $first = $list.children('.ui-menu-item.level0:visible').children('a').first();

            if ($first.length) {
                $first.trigger('focus');
            }
        }

        function bindRokanMobileBridgeHandlers() {
            var $toggles = $nav.find('.open-children-toggle');

            $toggles.off('click' + NS + ' keydown' + NS);

            $toggles.on('click' + NS, function () {
                var $t = $(this);
                var $p = $t.parent();

                if (isDesktop()) {
                    return;
                }

                window.setTimeout(function () {
                    var $panel = getFirstDirectChildPanel($p);
                    var opened = $panel.length && $panel.hasClass('opened');

                    if (opened) {
                        closeSiblingParentItems($p, true);
                    }

                    syncParentItemStateFromPanels($p);
                    syncAllMobileParentStates();
                }, 0);
            });

            $toggles.on('keydown' + NS, function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    $(this).trigger('click');
                    return;
                }

                if (e.key === 'Escape' && !isDesktop()) {
                    e.preventDefault();
                    e.stopPropagation();
                    resetParentItemState($(this).parent(), true);
                }
            });
        }

        /** Reset accordion & visibility when viewport crosses the breakpoint. */
        function syncOnResize() {
            if (isDesktop()) {
                /* Clean up mobile accordion + stale Rokanthemes "opened" class */
                getParentItems().each(function () {
                    resetParentItemState($(this), false);
                });
                closeDesktopSiblingSubmenus();

                /* Re-sync list visibility to current state */
                $list.stop(true, true).removeAttr('style');

                if (keepDesktopMenuExpanded()) {
                    setMenuOpenState(true);
                } else {
                    /* Bug #8 fix: Rokanthemes .VerticalMenu() may add menu-open/vmm-open
                       to $nav on init. setMenuOpenState(false) strips those stale classes
                       from $nav AND $list so CSS state is authoritative. */
                    setMenuOpenState(false);
                }

                if (isOpen()) {
                    $list.show();
                    $title.addClass('active').attr('aria-expanded', 'true');
                } else {
                    $list.hide();
                    $title.removeClass('active').attr('aria-expanded', 'false');
                }

                $('body').removeClass('background_shadow_show');

                syncDesktopPanelPosition();
            } else {
                /* Entering mobile — collapse */
                setMenuOpenState(false);
                $list.hide();
                $('body').removeClass('background_shadow_show');
            }
        }

        /* ============================================================ */
        /*  Event binding                                               */
        /* ============================================================ */

        /* ---- title click (main toggle) ----------------------------- */
        $title.on('click' + NS, function (e) {
            e.preventDefault();

            if (isDesktop()) {
                if (keepDesktopMenuExpanded()) {
                    openMenu();
                    return;
                }

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

        $title.on('keydown' + NS, function (e) {
            if (e.key === 'Escape') {
                e.preventDefault();

                if (keepDesktopMenuExpanded()) {
                    return;
                }

                closeMenu();
                return;
            }

            if (e.key === 'ArrowDown' && isDesktop()) {
                e.preventDefault();
                openMenu();
                focusFirstCategoryLink();
                return;
            }

            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();

                if (isDesktop()) {
                    openMenu();
                    focusFirstCategoryLink();
                    return;
                }

                $title.trigger('click' + NS);
            }
        });

        /* ---- desktop hover/focus (Home 5 default behavior) ---------- */
        $nav.on('mouseenter' + NS, function () {
            if (isDesktop()) {
                openMenu();
            }
        });

        $nav.on('mouseleave' + NS, function () {
            var root = $nav.get(0);
            var active = document.activeElement;

            if (!isDesktop()) {
                return;
            }

            if (root && active && root.contains(active)) {
                return;
            }

            if (keepDesktopMenuExpanded()) {
                return;
            }

            closeMenu();
        });

        $nav.on('focusin' + NS, function () {
            if (isDesktop()) {
                openMenu();
            }
        });

        $nav.on('focusout' + NS, function () {
            if (!isDesktop()) {
                return;
            }

            window.setTimeout(function () {
                var root = $nav.get(0);
                var active = document.activeElement;

                if (root && active && root.contains(active)) {
                    return;
                }

                if (keepDesktopMenuExpanded()) {
                    return;
                }

                closeMenu();
            }, 0);
        });

        $nav.on('keydown' + NS, function (e) {
            if (e.key !== 'Escape') {
                return;
            }

            if (!isDesktop() && !isOpen()) {
                return;
            }

            if (keepDesktopMenuExpanded()) {
                return;
            }

            e.stopPropagation();
            closeMenu();
            $title.trigger('focus');
        });

        if (rokanActive) {
            /* Keyboard desktop should reuse Rokanthemes flyout-positioning handlers. */
            $nav.on('focusin' + NS, 'li.level0.parent, li.classic .subchildmenu > li.parent', function () {
                if (isDesktop()) {
                    $(this).triggerHandler('mouseenter');
                }
            });
        }

        /* ---- deterministic desktop submenu visibility -------------- */
        $nav.on('mouseenter' + NS, 'li.ui-menu-item.level0.parent', function () {
            var $item = $(this);
            closeDesktopSiblingSubmenus($item);
            setDesktopSubmenuInlineState($item, true);
        });

        $nav.on('focusin' + NS, 'li.ui-menu-item.level0.parent', function () {
            var $item = $(this);
            closeDesktopSiblingSubmenus($item);
            setDesktopSubmenuInlineState($item, true);
        });

        $nav.on('mouseleave' + NS, 'li.ui-menu-item.level0.parent', function () {
            setDesktopSubmenuInlineState($(this), false);
        });

        $nav.on('focusout' + NS, 'li.ui-menu-item.level0.parent', function () {
            var $item = $(this);

            window.setTimeout(function () {
                var active = document.activeElement;
                var node = $item.get(0);

                if (node && active && node.contains(active)) {
                    return;
                }

                setDesktopSubmenuInlineState($item, false);
            }, 0);
        });

        /* ---- mobile submenu accordion (only when Rokanthemes absent) */
        if (!rokanActive) {
            $nav.on('click' + NS, '.open-children-toggle', function (e) {
                e.preventDefault();
                e.stopPropagation();

                if (isDesktop()) {
                    return;
                }

                var $t = $(this);
                var $p = $t.parent();
                var expanding = !$p.hasClass('_active');

                if (expanding) {
                    closeSiblingParentItems($p, true);
                }

                $p.toggleClass('_active');
                $t.attr('aria-expanded', expanding ? 'true' : 'false');
                $p.children(childPanelSelector).stop(true, true).slideToggle(200);
            });

            $nav.on('keydown' + NS, '.open-children-toggle', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    $(this).trigger('click');
                    return;
                }

                if (e.key === 'Escape' && !isDesktop()) {
                    e.preventDefault();
                    e.stopPropagation();
                    resetParentItemState($(this).parent(), true);
                }
            });
        } else {
            /* Rokanthemes binds direct click handlers with stopPropagation().
               Bridge must be direct too, otherwise delegated handlers on $nav won't fire. */
            bindRokanMobileBridgeHandlers();
        }

        /* ---- swipe-to-close on mobile (deslizar p/ esquerda fecha) -- */
        (function initSwipeToClose() {
            var touchStartX = 0;
            var touchStartY = 0;

            $list.on('touchstart' + NS, function (e) {
                var touch = e.originalEvent.changedTouches[0];
                touchStartX = touch.screenX;
                touchStartY = touch.screenY;
            });

            $list.on('touchend' + NS, function (e) {
                var touch = e.originalEvent.changedTouches[0];
                var dx = touch.screenX - touchStartX;
                var dy = Math.abs(touch.screenY - touchStartY);

                if (dx < -60 && dy < 100 && !isDesktop()) {
                    closeMenu();
                }
            });
        })();

        /* Rola para a categoria ativa quando o menu abre no mobile */
        function scrollToActiveItem() {
            var $active = $list.find('.awa-current-cat, .ui-menu-item.level0._active').first();
            var listEl  = $list.get(0);

            if (!$active.length || !listEl || isDesktop()) {
                return;
            }

            window.setTimeout(function () {
                var itemTop = $active.position() ? $active.position().top : 0;
                listEl.scrollTop = Math.max(0, itemTop - 60);
            }, 220);
        }

        /* ---- "Show more / Show less" -------------------------------- */
        (function initExpandLink() {
            if (limitItemShow <= 0) {
                $expandLink.closest('.expand-category-link').hide();
                return;
            }

            if ($items.length <= limitItemShow) {
                $expandLink.closest('.expand-category-link').hide();
                return;
            }

            $items.each(function (i) {
                if (i >= limitItemShow) {
                    $(this).addClass('orther-link').hide();
                }
            });

            $expandLink.closest('.expand-category-link').show();

            $expandLink.on('click' + NS, function (e) {
                e.preventDefault();

                var $a        = $(this);
                var $hidden   = $nav.find('.ui-menu-item.level0.orther-link');
                var expanding = !$a.hasClass('expanding');

                $a.toggleClass('expanding', expanding)
                   .closest('.expand-category-link').toggleClass('expanding', expanding);

                if ($a.data('show-text') && $a.data('hide-text')) {
                    $a.find('span').text(expanding ? $a.data('hide-text') : $a.data('show-text'));
                }

                $a.attr('aria-expanded', expanding ? 'true' : 'false');
                $hidden.stop(true, true)[expanding ? 'fadeIn' : 'fadeOut'](180);
            }).on('keydown' + NS, function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    $(this).trigger('click');
                    return;
                }

                if (e.key === 'Escape' && $(this).hasClass('expanding')) {
                    e.preventDefault();
                    $(this).trigger('click');
                }
            });
        })();

        /* ---- overlay click → close (mobile) ------------------------ */
        $(overlaySelector).on('click' + NS, function () {
            if (!isDesktop()) {
                closeMenu();
            }
        });

        /* ---- resize ------------------------------------------------ */
        $(window).on('resize' + NS, debounce(function () {
            ensureMobileToggles();

            if (rokanActive) {
                bindRokanMobileBridgeHandlers();
            }

            syncOnResize();
        }, 120));

        /* ---- cleanup on DOM removal -------------------------------- */
        $nav.on('remove' + NS, function () {
            $(window).off(NS);
            $(overlaySelector).off(NS);
        });

        /* ============================================================ */
        /*  ARIA fix — prevent tabs-widget aria-hidden from hiding      */
        /*  the focusable trigger h2 from assistive technology.         */
        /* ============================================================ */

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
                var el  = this;
                var obs = new MutationObserver(function (mutations) {
                    var i, m;

                    for (i = 0; i < mutations.length; i++) {
                        m = mutations[i];

                        if (m.attributeName === 'aria-hidden'
                                && el.getAttribute('aria-hidden') !== null) {
                            el.removeAttribute('aria-hidden');
                        }
                    }
                });

                obs.observe(el, { attributes: true, attributeFilter: ['aria-hidden'] });

                $nav.one('remove' + NS, function () { obs.disconnect(); });
            });
        }
        /* ============================================================ */
        /*  Scroll Shadow Indicators (v2 enhancement)                   */
        /* ============================================================ */

        function initScrollShadows() {
            var listEl = $list.get(0);

            if (!listEl) {
                return;
            }

            function updateShadows() {
                var scrollTop     = listEl.scrollTop;
                var scrollH       = listEl.scrollHeight;
                var clientH       = listEl.clientHeight;
                var canScrollUp   = scrollTop > 4;
                var canScrollDown = (scrollTop + clientH) < (scrollH - 4);

                $list.toggleClass('vmm-scroll-top', canScrollUp);
                $list.toggleClass('vmm-scroll-bottom', canScrollDown);
            }

            listEl.addEventListener('scroll', updateShadows, { passive: true });

            var shadowObs = new MutationObserver(function () {
                window.setTimeout(updateShadows, 50);
            });

            shadowObs.observe(listEl, { childList: true, attributes: true, subtree: false });
            $nav.one('remove' + NS, function () { shadowObs.disconnect(); });

            window.setTimeout(updateShadows, 100);
        }

        /* ============================================================ */
        /*  Current Category Highlight (v2 enhancement)                 */
        /* ============================================================ */

        function highlightCurrentCategory() {
            var currentPath = window.location.pathname.replace(/\/$/, '').toLowerCase();

            if (!currentPath || currentPath === '' || currentPath === '/') {
                return;
            }

            $nav.find('li.ui-menu-item.level0').each(function () {
                var $li = $(this);
                var $a  = $li.children('a.level-top').first();

                if (!$a.length) {
                    return;
                }

                var href = ($a.attr('href') || '').replace(/\/$/, '').toLowerCase();

                if (!href || href === '#' || href === 'javascript:void(0)') {
                    return;
                }

                try {
                    var linkPath = new URL(href, window.location.origin).pathname
                                       .replace(/\/$/, '').toLowerCase();

                    if (linkPath === currentPath) {
                        $li.addClass('vmm-current-category');
                    }
                } catch (e) {
                    /* ignore invalid URLs */
                }
            });
        }

        /* ============================================================ */
        /*  "Todas as Categorias" link styling (v2 enhancement)         */
        /* ============================================================ */

        function markAllCategoriesLink() {
            /* Check li.ui-menu-item.level0 */
            $nav.find("li.ui-menu-item.level0").each(function () {
                var $li = $(this);
                var $a  = $li.children("a.level-top").first();

                if (!$a.length) {
                    return;
                }

                var text = ($a.text() || "").trim().toLowerCase();

                if (text.indexOf("todas as categorias") > -1
                        || text.indexOf("todas categorias") > -1
                        || text.indexOf("ver todas") > -1) {
                    $li.addClass("vmm-all-categories");
                }
            });

            /* Also check expand-category-link (Rokanthemes "show more" link) */
            $nav.find("li.expand-category-link").each(function () {
                var $li = $(this);
                var text = ($li.text() || "").trim().toLowerCase();

                if (text.indexOf("todas as categorias") > -1
                        || text.indexOf("todas categorias") > -1
                        || text.indexOf("ver todas") > -1) {
                    $li.addClass("vmm-all-categories");
                }
            });
        }

        /* ============================================================ */
        /*  Stagger Entrance Animation (v2 enhancement)                 */
        /* ============================================================ */

        var staggerTimeout = null;

        function triggerStaggerAnimation() {
            if (!isDesktop()) {
                return;
            }

            if ($list.hasClass('vmm-animate-in')) {
                return;
            }

            if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                return;
            }

            $list.addClass('vmm-animate-in');

            clearTimeout(staggerTimeout);
            staggerTimeout = setTimeout(function () {
                $list.removeClass('vmm-animate-in');
            }, 500);
        }

        /* Patch openMenu to trigger stagger */
        var _origOpenMenu = openMenu;
        openMenu = function () {
            _origOpenMenu();
            triggerStaggerAnimation();
        };

        /* ============================================================ */
        /*  Hover Intent for Flyout (v2 enhancement)                    */
        /* ============================================================ */

        (function initHoverIntent() {
            if (!isDesktop()) {
                return;
            }

            var hoverTimer = null;
            var HOVER_DELAY = 100;

            $nav.off('mouseenter' + NS, 'li.ui-menu-item.level0.parent');
            $nav.off('mouseleave' + NS, 'li.ui-menu-item.level0.parent');

            $nav.on('mouseenter' + NS + '-intent', 'li.ui-menu-item.level0.parent', function () {
                var $item = $(this);

                clearTimeout(hoverTimer);
                hoverTimer = setTimeout(function () {
                    closeDesktopSiblingSubmenus($item);
                    setDesktopSubmenuInlineState($item, true);
                }, HOVER_DELAY);
            });

            $nav.on('mouseleave' + NS + '-intent', 'li.ui-menu-item.level0.parent', function () {
                var $item = $(this);

                clearTimeout(hoverTimer);
                setDesktopSubmenuInlineState($item, false);
            });

            $nav.on('focusin' + NS, 'li.ui-menu-item.level0.parent', function () {
                var $item = $(this);
                closeDesktopSiblingSubmenus($item);
                setDesktopSubmenuInlineState($item, true);
            });
        })();

        /* ============================================================ */
        /*  Boot                                                        */
        /* ============================================================ */

        ensureMobileToggles();

        if (rokanActive) {
            bindRokanMobileBridgeHandlers();
        }

        syncOnResize();
        fixSectionAriaHidden();
        initScrollShadows();
        highlightCurrentCategory();
        markAllCategoriesLink();
    };
});
