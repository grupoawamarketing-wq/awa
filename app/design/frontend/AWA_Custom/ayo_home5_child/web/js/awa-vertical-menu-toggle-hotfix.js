define(['jquery', 'domReady!'], function ($) {
    'use strict';

    var DESKTOP_BREAKPOINT = 992;
    var RESIZE_NS = '.awaVMenuToggleHotfix';

    function isDesktop() {
        return window.matchMedia
            ? window.matchMedia('(min-width: ' + DESKTOP_BREAKPOINT + 'px)').matches
            : window.innerWidth >= DESKTOP_BREAKPOINT;
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

    function getTitle($nav) {
        return $nav.find('.title-category-dropdown').first();
    }

    function getList($nav) {
        return $nav.find('ul.togge-menu.list-category-dropdown').first();
    }

    function getState($nav) {
        var state = $nav.data('awaVMenuToggleHotfixState');

        if (!state) {
            state = { pinned: false };
            $nav.data('awaVMenuToggleHotfixState', state);
        }

        return state;
    }

    function isMenuOpen($nav) {
        var $list = getList($nav);

        if (!$list.length) {
            return false;
        }

        return $list.hasClass('menu-open')
            || $list.hasClass('vmm-open')
            || $list.is(':visible');
    }

    function setOpenState($nav, open) {
        var $title = getTitle($nav);
        var $list = getList($nav);
        var listNode = $list.get(0);
        var expanded = open ? 'true' : 'false';

        if (!$list.length || !$title.length) {
            return;
        }

        $nav.toggleClass('menu-open', open).toggleClass('vmm-open', open);
        $list.toggleClass('menu-open', open).toggleClass('vmm-open', open);
        $title.toggleClass('active', open).attr('aria-expanded', expanded);

        if (isDesktop()) {
            if (open) {
                $list.stop(true, true).show();

                if (listNode && listNode.style) {
                    listNode.style.setProperty('display', 'grid', 'important');
                    listNode.style.setProperty('visibility', 'visible', 'important');
                    listNode.style.setProperty('opacity', '1', 'important');
                    listNode.style.setProperty('pointer-events', 'auto', 'important');
                }
            } else {
                $list.stop(true, true).hide();

                if (listNode && listNode.style) {
                    listNode.style.setProperty('display', 'none', 'important');
                    listNode.style.removeProperty('visibility');
                    listNode.style.removeProperty('opacity');
                    listNode.style.removeProperty('pointer-events');
                }
            }
            return;
        }

        if (open) {
            $list.stop(true, true).fadeIn(200);
            return;
        }

        $list.stop(true, true).fadeOut(200);
    }

    function openMenu($nav) {
        setOpenState($nav, true);
    }

    function closeMenu($nav) {
        setOpenState($nav, false);
    }

    function wireTitleCapture($nav) {
        var $title = getTitle($nav);
        var title = $title.get(0);

        if (!title || title.getAttribute('data-awa-vmenu-hotfix-title-bound') === '1') {
            return;
        }

        title.setAttribute('data-awa-vmenu-hotfix-title-bound', '1');

        title.addEventListener('click', function (event) {
            var state;

            if (!isDesktop()) {
                return;
            }

            if (keepDesktopMenuExpanded()) {
                state = getState($nav);
                state.pinned = false;
                openMenu($nav);
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();

            state = getState($nav);

            if (state.pinned && isMenuOpen($nav)) {
                state.pinned = false;
                closeMenu($nav);
                return;
            }

            state.pinned = true;
            openMenu($nav);
        }, true);

        title.addEventListener('keydown', function (event) {
            var state;

            if (!isDesktop()) {
                return;
            }

            if (event.key !== 'Enter' && event.key !== ' ' && event.key !== 'Escape') {
                return;
            }

            if (keepDesktopMenuExpanded() && event.key !== 'Escape') {
                state = getState($nav);
                state.pinned = false;
                openMenu($nav);
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();

            state = getState($nav);

            if (event.key === 'Escape') {
                state.pinned = false;
                closeMenu($nav);
                return;
            }

            if (state.pinned && isMenuOpen($nav)) {
                state.pinned = false;
                closeMenu($nav);
                return;
            }

            state.pinned = true;
            openMenu($nav);
        }, true);
    }

    function preventPinnedAutoClose($nav) {
        var nav = $nav.get(0);

        if (!nav || nav.getAttribute('data-awa-vmenu-hotfix-nav-bound') === '1') {
            return;
        }

        nav.setAttribute('data-awa-vmenu-hotfix-nav-bound', '1');

        ['mouseleave', 'focusout'].forEach(function (eventName) {
            nav.addEventListener(eventName, function (event) {
                var state = getState($nav);

                if (!isDesktop() || keepDesktopMenuExpanded() || !state.pinned) {
                    return;
                }

                event.stopPropagation();
                event.stopImmediatePropagation();
            }, true);
        });
    }

    function bindOne($nav) {
        if (!$nav.length || $nav.attr('data-awa-vmenu-toggle-hotfix-init') === '1') {
            return;
        }

        if (!getTitle($nav).length || !getList($nav).length) {
            return;
        }

        $nav.attr('data-awa-vmenu-toggle-hotfix-init', '1');

        wireTitleCapture($nav);
        preventPinnedAutoClose($nav);

        if (keepDesktopMenuExpanded()) {
            getState($nav).pinned = false;
            openMenu($nav);
        }
    }

    function allMenus() {
        return $('[data-role="awa-vertical-menu"]');
    }

    function bindAll() {
        allMenus().each(function () {
            bindOne($(this));
        });
    }

    function releasePinnedMenus() {
        allMenus().each(function () {
            var $nav = $(this);
            var state = getState($nav);

            if (!state.pinned) {
                return;
            }

            state.pinned = false;
            closeMenu($nav);
        });
    }

    document.addEventListener('mousedown', function (event) {
        if (!isDesktop()) {
            return;
        }

        allMenus().each(function () {
            var $nav = $(this);
            var nav = $nav.get(0);
            var state = getState($nav);

            if (!state.pinned || keepDesktopMenuExpanded()) {
                return;
            }

            if (nav && nav.contains(event.target)) {
                return;
            }

            state.pinned = false;
            closeMenu($nav);
        });
    }, true);

    document.addEventListener('keydown', function (event) {
        if (!isDesktop() || event.key !== 'Escape') {
            return;
        }

        releasePinnedMenus();
    }, true);

    $(window).on('resize' + RESIZE_NS, function () {
        if (!isDesktop()) {
            releasePinnedMenus();
            return;
        }

        bindAll();

        if (keepDesktopMenuExpanded()) {
            allMenus().each(function () {
                var $nav = $(this);
                getState($nav).pinned = false;
                openMenu($nav);
            });
        }
    });

    bindAll();

    (function retryForEsiMenu() {
        var attempts = 0;
        var maxAttempts = 40;
        var timer = window.setInterval(function () {
            attempts += 1;
            bindAll();

            if (attempts >= maxAttempts) {
                window.clearInterval(timer);
            }
        }, 500);
    }());

    return {};
});
