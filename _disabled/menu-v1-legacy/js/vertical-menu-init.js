/**
 * AWA Motos — Vertical Menu Toggle Controller (v2 — padronizado Ayo)
 *
 * Manages the sidebar vertical-menu lifecycle:
 *  - Desktop >= 992 px : hover/focus opens category dropdown (Home 5 default)
 *  - Home page desktop  : menu starts closed; hover/click opens it
 *  - Mobile  <  992 px : animated drawer + overlay + submenu accordions
 *
 * Keeps Ayo parent interactions without pinning the home menu open
 * while keeping AWA enhancements: vmm-open class sync, --vmm-* CSS variables
 * for fixed submenu positioning, swipe-to-close, scroll-to-active, and
 * backdrop integration.
 *
 * @module js/vertical-menu-init
 */
define([
    'jquery',
    'rokanthemes/verticalmenu',
    'js/vertical-menu-enhance'
], function ($, _verticalMenuPlugin, enhanceVerticalMenu) {
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

        let ok = false;

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
        let t;

        return function () {
            let ctx  = this;
            let args = arguments;

            clearTimeout(t);
            t = setTimeout(function () { fn.apply(ctx, args); }, ms || 120);
        };
    }

    /** Coalesce high-frequency layout work to one animation frame. */
    function rafThrottle(fn) {
        let scheduled = 0;

        return function () {
            let ctx  = this;
            let args = arguments;

            if (scheduled) {
                return;
            }

            scheduled = window.requestAnimationFrame(function () {
                scheduled = 0;
                fn.apply(ctx, args);
            });
        };
    }

    function runWhenIdle(fn, timeoutMs) {
        if (typeof window.requestIdleCallback === 'function') {
            window.requestIdleCallback(fn, { timeout: timeoutMs || 800 });
            return;
        }

        window.setTimeout(fn, 0);
    }

    /** Remove empty CMS placeholder <li> nodes the block renderer may inject. */
    function pruneEmptyBlocks($list) {
        if (!$list || !$list.length) {
            return;
        }

        let has = 'img[src],picture source[srcset],video source[src],iframe[src],a[href],.block,.cms-block';

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
        if (window.__AWA_MENU_V2) {
            return;
        }
        var $nav        = $(element);
        var $title      = $nav.find('.title-category-dropdown');
        var $list       = $nav.find('.togge-menu');
        var $expandLink = $nav.find('.vm-toggle-categories');
        var $items      = $nav.find('.ui-menu-item.level0');

        /* ---- config ------------------------------------------------ */
        let safeUid = ($nav.attr('id') || $title.attr('aria-controls') || 'avm-' + Math.random().toString(36).slice(2))
                          .replace(/[^a-zA-Z0-9_-]/g, '');
        let overlaySelector   = (config && config.overlaySelector) || '.shadow_bkg_show';
        let desktopBreakpoint = parseInt(config && config.desktopBreakpoint, 10) || 992;
        let limitItemShow     = parseInt($list.attr('data-limit-show'), 10)
                                || parseInt(config && config.limitShow, 10) || 0;
        let childPanelSelector = '.submenu, ul.level0, .subchildmenu';
        let NS = '.awaVM-' + safeUid;

        /* ---- guard: never double-init ------------------------------ */
        if (!$nav.length || !$title.length || !$list.length || $nav.data('awaVMInit')) {
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
                'background-color': 'var(--awa-primary, #A33B3B)',
                'color': '#fff'
            });
            $title[0].style.setProperty('background-color', 'var(--awa-primary, #A33B3B)', 'important');
        }

        /* ---- Rokanthemes flyout widget ------------------------------ */
        let rokanActive = initRokanWidget(
            $nav.filter('.verticalmenu').add($nav.find('.verticalmenu'))
        );

        if (typeof enhanceVerticalMenu === 'function') {
            enhanceVerticalMenu($nav);
        }

        pruneEmptyBlocks($list);

        /* ---- Fix: aria-label and title on level-top links include badge text */
        /* Rokanthemes .VerticalMenu() sets aria-label/title from full textContent,
           which concatenates .cat-label badge text without a space separator.
           Example: "Bauletos" + "QUENTE" => aria-label/title = "BauletosQUENTE".
           Correct to use only .navigation__label text (or clone text without children). */
        $nav.find('a.level-top, a.navigation__link').each(function () {
            var $a     = $(this);
            var $label = $a.children('.navigation__label');

            var labelText = $label.length
                ? $label.text().trim()
                : $a.clone().children().remove().end().text().trim();

            if (!labelText) { return; }

            if ($a.attr('aria-label') && $a.find('.cat-label[aria-hidden]').length) {
                $a.attr('aria-label', labelText);
            }

            var titleAttr = this.getAttribute('title');
            if (titleAttr && titleAttr !== labelText) {
                this.removeAttribute('title');
            }
        });

        /* ---- Fix: "Ver tudo" → "Ver todas" (gramática PT subcategorias femininas) */
        $nav.find('.navigation__inner-item--all a, .vmm-view-all-link').each(function () {
            var $a = $(this);
            if ($a.text().trim() === 'Ver tudo') {
                $a.text('Ver todas');
            }
        });

        /* ---- viewport ---------------------------------------------- */
        let mql = window.matchMedia
            ? window.matchMedia('(min-width: ' + desktopBreakpoint + 'px)')
            : null;

        function isDesktop() {
            return mql ? mql.matches : window.innerWidth >= desktopBreakpoint;
        }

        function keepDesktopMenuExpanded() {
            /* A home atual inicia fechada por CSS; manter expandido faz o menu
               ficar preso sobre o hero depois do primeiro hover/clique. */
            return false;
        }

        /** Desktop: hover/focus abre o dropdown em todas as páginas, inclusive home. */
        function shouldAutoOpenOnHover() {
            return isDesktop();
        }

        function isVisible(el) {
            let rect;
            let styles;

            if (!el) {
                return false;
            }

            rect = el.getBoundingClientRect();
            styles = window.getComputedStyle(el);

            return styles.display !== 'none'
                && styles.visibility !== 'hidden'
                && rect.width > 0
                && rect.height > 0;
        }

        let overlayBlocksCache = null;

        function invalidateOverlayBlocksCache() {
            overlayBlocksCache = null;
        }

        function headerOverlayBlocksMenu() {
            if (overlayBlocksCache !== null) {
                return overlayBlocksCache;
            }

            let body = document.body;
            /* Só bloquear quando overlay realmente estiver aberto.
               Evita falso-positivo em elementos de autocomplete/minicart
               que permanecem no DOM com tamanho > 0, mas fechados. */
            let activeMinicart = document.querySelector(
                '.awa-site-header .minicart-wrapper.active, ' +
                '.awa-site-header .minicart-wrapper.show, ' +
                '.awa-site-header .minicart-wrapper.is-open, ' +
                '.awa-site-header .minicart-wrapper[aria-expanded="true"], ' +
                '.awa-site-header .minicart-wrapper .block-minicart._active'
            );
            let activeSearch = document.querySelector(
                '#search_autocomplete.is-open, ' +
                '.mst-searchautocomplete__autocomplete._active, ' +
                '.mst-searchautocomplete__autocomplete.is-open, ' +
                '.searchsuite-autocomplete.is-open, ' +
                '.search-content.is-open, ' +
                '.search-content.is-focused, ' +
                'form#search_mini_form.is-open, ' +
                'form#search_mini_form.is-focused'
            );

            overlayBlocksCache = !!(
                (body && body.classList.contains('awa-minicart-overlay-active')) ||
                (body && body.classList.contains('searchautocomplete__active')) ||
                activeMinicart ||
                activeSearch
            );

            return overlayBlocksCache;
        }

        /**
         * Fecha o menu na home quando busca/minicart abrem; reabre ao fechar overlay.
         */
        function syncHeaderOverlayState() {
            if (!isDesktop()) {
                return;
            }

            if (headerOverlayBlocksMenu()) {
                if (isOpen()) {
                    setMenuOpenState(false);

                    if ($list[0]) {
                        $list[0].style.setProperty('display', 'none', 'important');
                    }
                }
                return;
            }

            if (keepDesktopMenuExpanded()) {
                openMenu();
            }
        }

        function initHeaderOverlayWatcher() {
            let body = document.body;
            let overlayObs;

            if (!body || typeof MutationObserver === 'undefined') {
                return;
            }

            overlayObs = new MutationObserver(debounce(function (mutations) {
                let i;
                let relevant = false;

                for (i = 0; i < mutations.length; i++) {
                    if (mutations[i].type === 'attributes') {
                        relevant = true;
                        break;
                    }
                }

                if (!relevant) {
                    return;
                }

                invalidateOverlayBlocksCache();
                syncHeaderOverlayState();
            }, 50));

            overlayObs.observe(body, { attributes: true, attributeFilter: ['class'] });

            [
                '.awa-site-header .minicart-wrapper',
                '#search_autocomplete',
                '.mst-searchautocomplete__autocomplete',
                '.searchsuite-autocomplete'
            ].forEach(function (selector) {
                document.querySelectorAll(selector).forEach(function (node) {
                    overlayObs.observe(node, {
                        attributes: true,
                        attributeFilter: ['class', 'aria-hidden', 'style']
                    });
                });
            });

            $nav.one('remove' + NS, function () {
                overlayObs.disconnect();
            });

            syncHeaderOverlayState();
        }

        /* ---- Submenu position sync (CSS vars for fixed flyouts) ----- */
        let cachedPanelTop = '';
        let cachedPanelLeft = '';

        function syncDesktopPanelPositionNow() {
            let anchor = $title.get(0) || $nav.get(0);
            let rect;
            let top;
            let left;
            let listNode;

            if (!isDesktop() || !$list.length || !anchor) {
                return;
            }

            rect = anchor.getBoundingClientRect();

            if (!rect.width && !rect.height) {
                return;
            }

            top  = rect.bottom.toFixed(1) + 'px';
            left = rect.left.toFixed(1) + 'px';

            if (top === cachedPanelTop && left === cachedPanelLeft) {
                return;
            }

            cachedPanelTop = top;
            cachedPanelLeft = left;
            listNode = $list.get(0);
            listNode.style.setProperty('--vmm-top', top);
            listNode.style.setProperty('--vmm-left', left);

            $list.find('> li.ui-menu-item.level0 > .level0.submenu, > li.ui-menu-item.level0 > .vmm-empty-submenu').each(function () {
                this.style.setProperty('--vmm-top', top);
                this.style.setProperty('--vmm-left', left);
            });
        }

        let syncDesktopPanelPosition = rafThrottle(syncDesktopPanelPositionNow);

        /**
         * Painel mega-menu no LI ou portado para body (awa-vertical-menu-flyout-fix.js).
         */
        function resolveLevel0SubmenuPanel($item) {
            var $p = $item.children('.submenu, .level0.submenu, .navigation__submenu').first();
            let id;
            let el;

            if ($p.length) {
                return $p;
            }

            id = $item.attr('data-menu');

            if (!id || typeof document === 'undefined') {
                return $();
            }

            el = document.querySelector('.awa-vmf-portal[data-aw-vmf-li-menu="' + id + '"]');

            return el ? $(el) : $();
        }

        function isInsidePortaledFlyout(node) {
            if (!node || typeof node.closest !== 'function') {
                return false;
            }

            return !!node.closest('.awa-vmf-portal');
        }

        /* ============================================================ */
        /*  Open / Close (syncs both menu-open and vmm-open classes)    */
        /* ============================================================ */

        function setMenuOpenState(open) {
            let expanded = open ? 'true' : 'false';
            let nextState = open ? 'open' : 'closed';

            if ($list.attr('data-awa-menu-state') === nextState
                    && $title.attr('aria-expanded') === expanded) {
                return;
            }

            $nav.toggleClass('menu-open', open).toggleClass('vmm-open', open);
            $list.toggleClass('menu-open', open).toggleClass('vmm-open', open);
            $list.attr('aria-hidden', open ? 'false' : 'true');
            $list.attr('data-awa-menu-state', open ? 'open' : 'closed');

            if (open) {
                $list.addClass('vmm-animate-in');
            } else {
                $list.removeClass('vmm-animate-in');
            }

            $title.toggleClass('active', open).attr('aria-expanded', expanded);
            announceMenuState(open);
        }

        function announceMenuState(open) {
            var $status = $nav.find('[data-role="awa-vertical-menu-status"]');

            if (!$status.length) {
                return;
            }

            $status.text(open
                ? 'Menu de departamentos aberto. Use Tab ou as setas para navegar.'
                : 'Menu de departamentos fechado. Pressione Enter para abrir.');
        }

        function openMenu() {
            if (headerOverlayBlocksMenu()) {
                setMenuOpenState(false);
                if ($list[0]) {
                    $list[0].style.setProperty('display', 'none', 'important');
                }
                return;
            }

            if (isOpen() && isDesktop()) {
                syncDesktopPanelPosition();
                return;
            }

            setMenuOpenState(true);

            if (isDesktop()) {
                closeDesktopSiblingSubmenus();
                $list.stop(true, true);
                /* JS-2 fix: do not removeAttr('style') — that wipes the hotfix's
                   display:grid !important. Instead clear only animation leftovers
                   and explicitly force display:grid (consistent with CSS grid layout). */
                if ($list[0]) {
                    $list[0].style.setProperty('display', 'grid', 'important');
                    $list[0].style.removeProperty('height');
                    $list[0].style.removeProperty('max-height');
                    $list[0].style.removeProperty('overflow');
                    $list[0].style.removeProperty('opacity');
                }
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

            $panel = resolveLevel0SubmenuPanel($item);

            if (!$panel.length) {
                return;
            }

            if (open) {
                $item.addClass('vmm-active');
                /* JS-3b fix: use setProperty !important so open state reliably
                   overrides any prior !important close state on portaled panels. */
                let pNodeOpen = $panel[0];
                pNodeOpen.style.setProperty('visibility', 'visible', 'important');
                pNodeOpen.style.setProperty('opacity', '1', 'important');
                pNodeOpen.style.setProperty('pointer-events', 'auto', 'important');
                return;
            }

            $item.removeClass('vmm-active');
            /* JS-3b close: remove inline overrides — CSS rules (BUG-9) take over
               for panels in normal DOM; portaled panels lose !important state
               and become hidden by their own portal detach sequence. */
            let pNodeClose = $panel[0];
            pNodeClose.style.removeProperty('visibility');
            pNodeClose.style.removeProperty('opacity');
            pNodeClose.style.removeProperty('pointer-events');
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
                var $panel = resolveLevel0SubmenuPanel($item);

                $item.removeClass('vmm-active _active is-open active ui-state-active awa-vmf-active');
                $item.children('a.level-top, > a').attr('aria-expanded', 'false');
                $item.children('.open-children-toggle').attr('aria-expanded', 'false');

                if ($panel.length) {
                    $panel.removeClass('opened');
                    /* JS-3 fix: use setProperty !important to override the portal's
                       visibility:visible !important / opacity:1 !important from
                       awa-vertical-menu-flyout-fix.js portaled panels. */
                    let pNode = $panel[0];
                    pNode.style.setProperty('display', 'none', 'important');
                    pNode.style.setProperty('visibility', 'hidden', 'important');
                    pNode.style.setProperty('opacity', '0', 'important');
                    pNode.style.setProperty('pointer-events', 'none', 'important');
                }
            });
        }

        function closeMenu() {
            setMenuOpenState(false);

            if (isDesktop()) {
                $list.stop(true, true);
                /* JS-2b fix: use setProperty !important to reliably override the
                   hotfix's display:grid !important or CSS display:grid !important. */
                if ($list[0]) {
                    $list[0].style.setProperty('display', 'none', 'important');
                }
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
                var catName = ($li.children('a').first().find('.navigation__label').text()
                    || $li.children('a').first().text() || '').trim();
                var label = catName
                    ? 'Expandir subcategorias de ' + catName
                    : 'Expandir subcategorias';

                if (!$t.length) {
                    $li.append(
                        $('<button>', {
                            type: 'button',
                            'class': 'open-children-toggle navigation__toggle',
                            'aria-label': label,
                            'aria-expanded': 'false'
                        })
                    );
                    return;
                }

                if ($t.prop('tagName') !== 'BUTTON') {
                    $t.replaceWith(
                        $('<button>', {
                            type: 'button',
                            'class': ($t.attr('class') || 'open-children-toggle navigation__toggle'),
                            'aria-label': $t.attr('aria-label') || label,
                            'aria-expanded': $t.attr('aria-expanded') || 'false',
                            'aria-haspopup': $t.attr('aria-haspopup') || 'true',
                            'aria-controls': $t.attr('aria-controls') || undefined
                        })
                    );
                    return;
                }

                $t.attr({
                    'type':          'button',
                    'aria-label':    $t.attr('aria-label') || label,
                    'aria-expanded': $t.attr('aria-expanded') || 'false'
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
            let nestedAnimate = !!animateNested;

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
            let opened = $panel.length && $panel.hasClass('opened');

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
                    let opened = $panel.length && $panel.hasClass('opened');

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
            cachedPanelTop = '';
            cachedPanelLeft = '';
            invalidateOverlayBlocksCache();

            if (isDesktop()) {
                /* Clean up mobile accordion + stale Rokanthemes "opened" class */
                getParentItems().each(function () {
                    resetParentItemState($(this), false);
                });
                closeDesktopSiblingSubmenus();

                /* Re-sync list visibility to current state */
                $list.stop(true, true).removeAttr('style');

                if (keepDesktopMenuExpanded()) {
                    openMenu();
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

                let isPinned = $nav.data('vmm-pinned') === true;

                if (isOpen() && isPinned) {
                    $nav.data('vmm-pinned', false);
                    closeMenu();
                } else {
                    $nav.data('vmm-pinned', true);
                    openMenu();
                }
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

        $nav.on('mouseleave' + NS, function (e) {
            let root = $nav.get(0);
            let active = document.activeElement;
            let to = e.relatedTarget;

            if (!isDesktop()) {
                return;
            }

            if (to && isInsidePortaledFlyout(to)) {
                openMenu();
                return;
            }

            if (root && active && root.contains(active)) {
                return;
            }

            if (keepDesktopMenuExpanded() || $nav.data('vmm-pinned') === true) {
                openMenu();
                return;
            }

            closeMenu();
        });

        $nav.on('focusin' + NS, function () {
            if (shouldAutoOpenOnHover() || $nav.data('vmm-pinned') === true) {
                openMenu();
            }
        });

        $nav.on('focusout' + NS, function () {
            if (!isDesktop()) {
                return;
            }

            window.setTimeout(function () {
                let root = $nav.get(0);
                let active = document.activeElement;

                if (active && isInsidePortaledFlyout(active)) {
                    openMenu();
                    return;
                }

                if (root && active && root.contains(active)) {
                    return;
                }

                if (keepDesktopMenuExpanded() || $nav.data('vmm-pinned') === true) {
                    openMenu();
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

            if (keepDesktopMenuExpanded() || $nav.data('vmm-pinned') === true) {
                openMenu();
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

        /* ---- click outside to close pinned menu ------------------- */
        $(document).on('mousedown' + NS, function (e) {
            if (!isDesktop() || keepDesktopMenuExpanded() || $nav.data('vmm-pinned') !== true) {
                return;
            }

            let root = $nav.get(0);
            if (root && !root.contains(e.target)) {
                $nav.data('vmm-pinned', false);
                closeMenu();
            }
        });

        /* ---- deterministic desktop submenu visibility (hover-intent below) */
        $nav.on('focusout' + NS, 'li.ui-menu-item.level0.parent', function () {
            var $item = $(this);

            window.setTimeout(function () {
                let active = document.activeElement;
                let node = $item.get(0);

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
                let expanding = !$p.hasClass('_active');

                if (expanding) {
                    closeSiblingParentItems($p, true);
                }

                $p.toggleClass('_active');
                $t.attr('aria-expanded', expanding ? 'true' : 'false');

                var catName = ($p.children('a').first().find('.navigation__label').text()
                    || $p.children('a').first().text() || '').trim();
                if (catName) {
                    $t.attr('aria-label', expanding
                        ? 'Recolher subcategorias de ' + catName
                        : 'Expandir subcategorias de ' + catName);
                }

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
            let touchStartX = 0;
            let touchStartY = 0;

            $list.on('touchstart' + NS, function (e) {
                let touch = e.originalEvent.changedTouches[0];
                touchStartX = touch.screenX;
                touchStartY = touch.screenY;
            });

            $list.on('touchend' + NS, function (e) {
                let touch = e.originalEvent.changedTouches[0];
                let dx = touch.screenX - touchStartX;
                let dy = Math.abs(touch.screenY - touchStartY);

                if (dx < -60 && dy < 100 && !isDesktop()) {
                    closeMenu();
                }
            });
        })();

        /* Rola para a categoria ativa quando o menu abre no mobile */
        function scrollToActiveItem() {
            var $active = $list.find('.awa-current-cat, .ui-menu-item.level0._active').first();
            let listEl  = $list.get(0);

            if (!$active.length || !listEl || isDesktop()) {
                return;
            }

            window.setTimeout(function () {
                let itemTop = $active.position() ? $active.position().top : 0;
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

            if ($expandLink.data('show-text')) {
                $expandLink.find('span').first().text($expandLink.data('show-text'));
            }

            $expandLink.on('click' + NS, function (e) {
                e.preventDefault();

                var $a        = $(this);
                var $hidden   = $nav.find('.ui-menu-item.level0.orther-link');
                let expanding = !$a.hasClass('expanding');

                $a.toggleClass('expanding', expanding)
                   .closest('.expand-category-link').toggleClass('expanding', expanding);

                if ($a.data('show-text') && $a.data('hide-text')) {
                    $a.find('span').text(expanding ? $a.data('hide-text') : $a.data('show-text'));
                }

                if ($a.data('show-aria') || $a.data('hide-aria')) {
                    $a.attr('aria-label', expanding
                        ? ($a.data('hide-aria') || $a.data('hide-text') || '')
                        : ($a.data('show-aria') || $a.data('show-text') || ''));
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
            $(document).off(NS);
            $(overlaySelector).off(NS);
            $nav.off(NS);
            $title.off(NS);
            $list.off(NS);
            $expandLink.off(NS);
            clearTimeout(staggerTimeout);
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
                let el  = this;
                let obs = new MutationObserver(function (mutations) {
                    let i, m;

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
            let listEl = $list.get(0);

            if (!listEl) {
                return;
            }

            function updateShadowsNow() {
                let scrollTop     = listEl.scrollTop;
                let scrollH       = listEl.scrollHeight;
                let clientH       = listEl.clientHeight;
                let canScrollUp   = scrollTop > 4;
                let canScrollDown = (scrollTop + clientH) < (scrollH - 4);

                $list.toggleClass('vmm-scroll-top', canScrollUp);
                $list.toggleClass('vmm-scroll-bottom', canScrollDown);
            }

            let updateShadows = rafThrottle(updateShadowsNow);

            listEl.addEventListener('scroll', updateShadows, { passive: true });

            let shadowObs = new MutationObserver(function () {
                updateShadows();
            });

            shadowObs.observe(listEl, { childList: true, attributes: true, subtree: false });
            $nav.one('remove' + NS, function () { shadowObs.disconnect(); });

            window.setTimeout(updateShadowsNow, 100);
        }

        /* ============================================================ */
        /*  Current Category Highlight (v2 enhancement)                 */
        /* ============================================================ */

        function highlightCurrentCategory() {
            let currentPath = window.location.pathname.replace(/\/$/, '').toLowerCase();

            if (!currentPath || currentPath === '' || currentPath === '/') {
                return;
            }

            $nav.find('li.ui-menu-item.level0').each(function () {
                var $li = $(this);
                var $a  = $li.children('a.level-top').first();

                if (!$a.length) {
                    return;
                }

                let href = ($a.attr('href') || '').replace(/\/$/, '').toLowerCase();

                if (!href || href === '#' || href === 'javascript:void(0)') {
                    return;
                }

                try {
                    let linkPath = new URL(href, window.location.origin).pathname
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

                let text = ($a.text() || "").trim().toLowerCase();

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

        let staggerTimeout = null;

        function triggerStaggerAnimation() {
            if (!isDesktop() || keepDesktopMenuExpanded()) {
                return;
            }

            if ($list.hasClass('vmm-animate-in') || $nav.data('vmm-stagger-done') === 1) {
                return;
            }

            if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                return;
            }

            $list.addClass('vmm-animate-in');
            $nav.data('vmm-stagger-done', 1);

            clearTimeout(staggerTimeout);
            staggerTimeout = setTimeout(function () {
                $list.removeClass('vmm-animate-in');
            }, 500);
        }

        /* Patch openMenu to trigger stagger */
        let _origOpenMenu = openMenu;
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

            let hoverTimer = null;
            let HOVER_DELAY = parseInt(config && config.hoverDelay, 10);

            if (isNaN(HOVER_DELAY) || HOVER_DELAY < 0) {
                HOVER_DELAY = 240;
            }

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

        /* Branded header for mobile drawer */
        if (!isDesktop() && $list.length && !$list.find('.vmm-mobile-header').length) {
            let logoSrc = $('header .logo img').attr('src');
            let logoAlt = $('header .logo img').attr('alt') || 'AWA Motos';

            if (logoSrc) {
                var $mHeader = $('<div class="vmm-mobile-header"></div>');
                var $logo = $('<img>', {
                    src: logoSrc,
                    alt: logoAlt,
                    class: 'vmm-mobile-logo'
                });
                var $close = $('<button>', {
                    type: 'button',
                    class: 'vmm-mobile-close',
                    'aria-label': 'Fechar departamentos',
                    text: '×'
                });

                $mHeader.append($logo, $close);
                $list.prepend($mHeader);

                $mHeader.find('.vmm-mobile-close').on('click' + NS, function () {
                    closeMenu();
                });
            }
        }

        if (rokanActive) {
            bindRokanMobileBridgeHandlers();
        }

        /* SSR a11y: painel fechado até o init confirmar estado */
        if ($list.attr('aria-hidden') !== 'false') {
            $list.attr('aria-hidden', 'true');
            $list.attr('data-awa-menu-state', 'closed');
        }

        initHeaderOverlayWatcher();

        /* ---- Typeahead search no painel -------------------------------- */
        (function initPanelSearch() {
            var $input = $list.find('[data-role="awa-vmenu-search"]');
            if (!$input.length) { return; }

            var $searchLi = $list.find('[data-role="awa-vmenu-search-row"]');
            var $status   = $nav.find('[data-role="awa-vertical-menu-status"]');
            var searchTimer;

            function getFilterableItems() {
                return $list.children('li.ui-menu-item.level0');
            }

            function getSearchEmptyRow() {
                return $list.find('[data-role="awa-vmenu-search-empty"]');
            }

            function ensureSearchEmptyRow() {
                var $empty = getSearchEmptyRow();

                if ($empty.length) {
                    return $empty;
                }

                $empty = $(
                    '<li class="awa-vmenu-search-empty-li" data-role="awa-vmenu-search-empty" role="none">' +
                        '<div class="awa-vmenu-search-empty" aria-live="polite">' +
                            '<span class="awa-vmenu-search-empty-icon" aria-hidden="true">' +
                                '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" focusable="false" aria-hidden="true">' +
                                    '<circle cx="11" cy="11" r="7"/>' +
                                    '<path d="M21 21l-4.35-4.35"/>' +
                                '</svg>' +
                            '</span>' +
                            '<p class="awa-vmenu-search-empty-text"></p>' +
                        '</div>' +
                    '</li>'
                );

                $searchLi.after($empty);
                return $empty;
            }

            function filterItems(query) {
                var rawQuery = (query || '').trim();
                var q = rawQuery.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
                var $items = getFilterableItems();
                var visibleCount = 0;

                $items.each(function () {
                    var $li = $(this);
                    var label = ($li.find('.navigation__label').text()
                        || $li.find('a.level-top, a.navigation__link').first().text()
                        || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
                    var match = !q || label.indexOf(q) !== -1;
                    $li.toggle(match);
                    if (match) { visibleCount++; }
                });

                /* Hide/show section labels and dividers */
                $list.children('.awa-vmenu__divider, .awa-vmenu__section-label').each(function () {
                    var $el = $(this);
                    if (!q) {
                        $el.show();
                        return;
                    }
                    var $nextVisible = $el.nextAll('li.ui-menu-item.level0:visible').first();
                    $el.toggle($nextVisible.length > 0);
                });

                $list.children('li.expand-category-link').toggle(!q);

                /* DELIGHT: empty state visual quando busca não retorna itens */
                var $empty = getSearchEmptyRow();
                if (q && visibleCount === 0) {
                    $empty = ensureSearchEmptyRow();
                    $empty.find('.awa-vmenu-search-empty-text').html(
                        'Nenhuma categoria para <span class="awa-vmenu-search-empty-query"></span>'
                    );
                    $empty.find('.awa-vmenu-search-empty-query').text(rawQuery);
                    $empty.addClass('is-visible');
                } else if ($empty.length) {
                    $empty.removeClass('is-visible');
                }

                /* ARIA live region announce */
                if (q && $status.length) {
                    $status.text(visibleCount > 0
                        ? visibleCount + ' categoria' + (visibleCount !== 1 ? 's' : '') + ' encontrada' + (visibleCount !== 1 ? 's' : '')
                        : 'Nenhuma categoria para "' + rawQuery + '"');
                } else if ($status.length) {
                    $status.text('');
                }
            }

            $input.on('input', function () {
                clearTimeout(searchTimer);
                var q = this.value;
                searchTimer = window.setTimeout(function () { filterItems(q); }, 120);
            });

            $input.on('keydown', function (e) {
                if (e.key === 'Escape') {
                    if (this.value) {
                        this.value = '';
                        filterItems('');
                        e.stopPropagation();
                    }
                }
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    var $first = getFilterableItems().filter(':visible').first();
                    var $a = $first.find('a.level-top, a.navigation__link').first();
                    if ($a.length) { $a.trigger('focus'); }
                }
            });

            /* Clear search when menu closes */
            $nav.on('awa:menu:closed', function () {
                if ($input.val()) {
                    $input.val('');
                    filterItems('');
                }
            });

            /* Show search only on desktop */
            function toggleSearchVisibility() {
                $searchLi.toggle(isDesktop());
            }

            toggleSearchVisibility();
            $(window).on('resize' + NS, debounce(toggleSearchVisibility, 200));
        })();

        /* Robust boot: delay syncOnResize to ensure all widgets are ready */
        window.setTimeout(function () {
            syncOnResize();
            syncHeaderOverlayState();

            if (keepDesktopMenuExpanded() || (isDesktop() && ($nav.is(':hover') || $title.is(':focus')))) {
                openMenu();
            }
        }, 100);

        fixSectionAriaHidden();

        runWhenIdle(function () {
            initScrollShadows();
            highlightCurrentCategory();
            markAllCategoriesLink();
        }, 800);
    };
});
