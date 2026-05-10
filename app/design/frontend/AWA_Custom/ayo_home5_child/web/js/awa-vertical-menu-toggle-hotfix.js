define(['jquery', 'domReady!'], function ($) {
    'use strict';

    let DESKTOP_BREAKPOINT = 992;
    let RESIZE_NS = '.awaVMenuToggleHotfix';

    function isDesktop() {
        return window.matchMedia
            ? window.matchMedia('(min-width: ' + DESKTOP_BREAKPOINT + 'px)').matches
            : window.innerWidth >= DESKTOP_BREAKPOINT;
    }

    function isHomeContext() {
        let body = document.body;

        if (!body) {
            return false;
        }

        return body.classList.contains('cms-index-index')
            || body.classList.contains('cms-home')
            || body.classList.contains('cms-homepage_ayo_home5')
            || body.classList.contains('cms-homepage_ayo_home5_demo_stage');
    }

    function keepDesktopMenuExpanded() {
        /* Igual Ayo Home 5 / demo: lista de categorias expandida no desktop na homepage. */
        return isDesktop() && isHomeContext();
    }

    function getTitle($nav) {
        return $nav.find('.title-category-dropdown').first();
    }

    function getList($nav) {
        return $nav.find('ul.togge-menu.list-category-dropdown').first();
    }

    function getState($nav) {
        let state = $nav.data('awaVMenuToggleHotfixState');

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
        let listNode = $list.get(0);
        let expanded = open ? 'true' : 'false';

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

    function enforceDesktopHeaderNavVisualState($nav) {
        var $list;
        var $quickWrap;
        var $quickList;
        let quickLinks;
        let quickHtml;
        var $logoImg;
        var $promo;
        var $promoText;
        var $promoClose;
        var $loginLine1;
        var $loginLine2;
        var $cart;
        var $cartCounter;
        var $cartFallback;
        var $cartFallbackIcon;

        if (!isDesktop()) {
            return;
        }

        $list = getList($nav);
        $quickWrap = $('.awa-site-header .header-control.awa-nav-bar .awa-nav-quick-links').first();
        $quickList = $quickWrap.find('.awa-nav-quick-links__list').first();

        /* Homepage desktop (demo Ayo): lista expandida + desbloqueia altura após estilos antigos */
        if ($list.length && keepDesktopMenuExpanded()) {
            getState($nav).pinned = false;
            openMenu($nav);
            $list[0].style.removeProperty('height');
            $list[0].style.removeProperty('max-height');
        }
        /*
         * NÃO fechar nem aplicar display:none !important aqui nas outras páginas.
         * Este enforce corre a cada bind e no intervalo de retry — isso anulava o hover
         * (vertical-menu-init abria e o hotfix fechava de novo). Abrir/fechar fica só
         * com vertical-menu-init.js + CSS.
         */

        if ($quickWrap.length) {
            $quickWrap[0].style.setProperty('display', 'flex', 'important');
            $quickWrap[0].style.setProperty('align-items', 'center', 'important');
        }

        if ($quickList.length) {
            $quickList[0].style.setProperty('display', 'flex', 'important');
            $quickList[0].style.setProperty('align-items', 'center', 'important');
        }

        quickLinks = [
            { name: 'Nossas Marcas', path: '/nossas-marcas/' },
            { name: 'Lançamentos', path: '/ofertas.html' },
            { name: 'Catálogo', path: '/catalogsearch/result/' }
        ];

        if ($quickList.length) {
            quickHtml = quickLinks.map(function (item) {
                return '<li class="awa-nav-quick-links__item">'
                    + '<a href="' + window.location.origin + item.path + '" class="awa-nav-quick-links__link" title="' + item.name + '">'
                    + item.name
                    + '</a></li>';
            }).join('');

            if ($quickList.attr('data-awa-header-links-final') !== '1') {
                $quickList.html(quickHtml);
                $quickList.attr('data-awa-header-links-final', '1');
            }
        }

        $logoImg = $('.awa-site-header .awa-header-brand-cell .logo img').first();
        if ($logoImg.length) {
            $logoImg[0].style.setProperty('width', '161px', 'important');
            $logoImg[0].style.setProperty('height', '92px', 'important');
            $logoImg[0].style.setProperty('max-height', 'none', 'important');
            $logoImg[0].style.setProperty('max-width', '161px', 'important');
        }

        $promo = $('.awa-site-header .top-header.awa-b2b-promo-bar').first();
        $promoText = $promo.find('.awa-b2b-promo-bar__text, .awa-b2b-promo-bar__lead, .awa-b2b-promo-bar__cta, .awa-b2b-promo-bar__tail');
        $promoClose = $promo.find('.awa-b2b-promo-close, #awa-b2b-promo-close');

        if ($promo.length) {
            $promo[0].style.setProperty('background-color', 'var(--awa-red-dark, var(--awa-primary-dark))', 'important');
        }
        $promoText.each(function () {
            this.style.setProperty('font-size', '13px', 'important');
            this.style.setProperty('font-weight', '700', 'important');
        });
        $promoClose.each(function () {
            this.style.setProperty('display', 'none', 'important');
        });

        $loginLine1 = $('.awa-site-header .awa-header-account-prompt__line1').first();
        $loginLine2 = $('.awa-site-header .awa-header-account-prompt__line2').first();
        if ($loginLine1.length) {
            $loginLine1[0].style.setProperty('font-size', '13px', 'important');
            $loginLine1[0].style.setProperty('font-weight', '500', 'important');
        }
        if ($loginLine2.length) {
            $loginLine2[0].style.setProperty('font-size', '14px', 'important');
            $loginLine2[0].style.setProperty('font-weight', '700', 'important');
        }

        $cart = $('.awa-site-header .awa-header-minicart .minicart-wrapper .showcart.header-mini-cart').first();
        $cartCounter = $cart.find('.counter.qty');
        $cartFallback = $('.awa-site-header .awa-header-minicart .awa-header-cart-fallback').first();
        $cartFallbackIcon = $cartFallback.find('.awa-header-cart-fallback__icon');
        if ($cart.length) {
            $cart[0].style.setProperty('width', '36px', 'important');
            $cart[0].style.setProperty('height', '36px', 'important');
            $cart[0].style.setProperty('color', 'var(--awa-primary)', 'important');
            $cart[0].style.setProperty('left', 'auto', 'important');
            $cart[0].style.setProperty('right', '0', 'important');
            $cart[0].style.setProperty('display', 'inline-flex', 'important');
            $cart[0].style.setProperty('visibility', 'visible', 'important');
            $cart[0].style.setProperty('opacity', '1', 'important');
            $cart[0].style.setProperty('pointer-events', 'auto', 'important');
        }
        if ($cartFallback.length) {
            $cartFallback[0].style.setProperty('display', 'none', 'important');
            $cartFallback[0].style.setProperty('visibility', 'hidden', 'important');
            $cartFallback[0].style.setProperty('opacity', '0', 'important');
            $cartFallback[0].style.setProperty('pointer-events', 'none', 'important');
        }
        if ($cartFallbackIcon.length) {
            $cartFallbackIcon[0].style.setProperty('visibility', 'hidden', 'important');
            $cartFallbackIcon[0].style.setProperty('opacity', '0', 'important');
            $cartFallbackIcon[0].style.setProperty('display', 'none', 'important');
        }
        if ($cartCounter.length) {
            $cartCounter[0].style.setProperty('width', '14px', 'important');
            $cartCounter[0].style.setProperty('height', '14px', 'important');
            $cartCounter[0].style.setProperty('border', '1px solid var(--awa-primary)', 'important');
            $cartCounter[0].style.setProperty('background', 'var(--awa-bg)', 'important');
            $cartCounter[0].style.setProperty('color', 'var(--awa-primary)', 'important');
        }
    }

    function wireTitleCapture($nav) {
        var $title = getTitle($nav);
        let title = $title.get(0);

        if (!title || title.getAttribute('data-awa-vmenu-hotfix-title-bound') === '1') {
            return;
        }

        title.setAttribute('data-awa-vmenu-hotfix-title-bound', '1');

        title.addEventListener('click', function (event) {
            let state;

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
            let state;

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
        let nav = $nav.get(0);

        if (!nav || nav.getAttribute('data-awa-vmenu-hotfix-nav-bound') === '1') {
            return;
        }

        nav.setAttribute('data-awa-vmenu-hotfix-nav-bound', '1');

        ['mouseleave', 'focusout'].forEach(function (eventName) {
            nav.addEventListener(eventName, function (event) {
                let state = getState($nav);

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
        }

        /* Sempre aplica header (links rápidos, logo, etc.); estado do menu lista expandida/contraída está dentro. */
        enforceDesktopHeaderNavVisualState($nav);
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
            let state = getState($nav);

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
            let nav = $nav.get(0);
            let state = getState($nav);

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
            return;
        }

        allMenus().each(function () {
            enforceDesktopHeaderNavVisualState($(this));
        });
    });

    bindAll();

    (function retryForEsiMenu() {
        let attempts = 0;
        let maxAttempts = 20;
        let timer = window.setInterval(function () {
            attempts += 1;
            bindAll();
            allMenus().each(function () {
                enforceDesktopHeaderNavVisualState($(this));
            });

            if (attempts >= maxAttempts) {
                window.clearInterval(timer);
            }
        }, 400);
    }());

    return {};
});
