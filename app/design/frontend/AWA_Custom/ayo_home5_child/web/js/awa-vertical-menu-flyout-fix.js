/**
 * AWA Motos — Vertical Menu Flyout Fix
 *
 * Problema: o submenu do menu vertical (togge-menu) está dentro de uma cadeia
 * de stacking contexts (HEADER w:0/h:0 → page-wrapper z:1) que impede o flyout
 * de aparecer sobre o slider (content-top-home z:0) fora do page-wrapper.
 *
 * Solução: ao hover num li.level0, move o submenu para o <body> com
 * position:fixed + coordenadas calculadas via getBoundingClientRect().
 * Ao mouseleave, devolve o submenu ao LI original.
 *
 * Funciona junto com o Rokanthemes verticalmenu.js e awa-vertical-mega-menu.js
 * sem modificá-los.
 */
(function () {
    'use strict';

    let DESKTOP_MIN = 992;
    let PORTAL_CLASS = 'awa-vmf-portal';
    let ACTIVE_CLASS = 'awa-vmf-active';
    let Z_INDEX = 99990;
    let OFFSET_LEFT = 0;
    let OFFSET_TOP = 0;

    let _observer = null;
    let _ul = null;
    let _initialized = false;
    let _scrollRaf = 0;
    let resizeTimer;

    function isDesktop() {
        return window.innerWidth >= DESKTOP_MIN;
    }

    /* Handlers nomeados para permitir removeEventListener exato */
    function onMenuMouseenter(e) {
        let li = e.target.closest('li.level0.parent, li.level0.navigation__item--parent');
        if (!li) return;
        attachFlyout(li);
    }

    function onMenuMouseleave(e) {
        let li = e.target.closest('li.level0');
        if (!li) return;
        let to = e.relatedTarget;
        if (to && to.classList && (to.classList.contains(PORTAL_CLASS) || to.closest('.' + PORTAL_CLASS))) return;
        detachFlyout(li);
    }

    function onDocMouseout(e) {
        let portal = e.target.closest('.' + PORTAL_CLASS);
        if (!portal) return;
        let to = e.relatedTarget;
        let liId = portal.dataset.awVmfLiMenu;
        let li = liId && document.querySelector('li.level0[data-menu="' + liId + '"]');
        if (to && li && (li.contains(to) || li === to)) return;
        detachPortal(portal);
    }

    function onWinScrollReposition() {
        if (!_initialized || !isDesktop()) return;
        if (_scrollRaf) {
            window.cancelAnimationFrame(_scrollRaf);
        }
        _scrollRaf = window.requestAnimationFrame(function () {
            _scrollRaf = 0;
            document.querySelectorAll('.' + PORTAL_CLASS).forEach(function (portal) {
                let id = portal.dataset.awVmfLiMenu;
                let li = id && document.querySelector('li.level0[data-menu="' + id + '"]');
                if (li) positionPortal(li, portal);
            });
        });
    }

    function teardown() {
        detachAll();
        if (_observer) {
            _observer.disconnect();
            _observer = null;
        }
        if (_ul) {
            _ul.removeEventListener('mouseenter', onMenuMouseenter, true);
            _ul.removeEventListener('mouseleave', onMenuMouseleave, true);
            _ul = null;
        }
        document.removeEventListener('mouseout', onDocMouseout);
        window.removeEventListener('scroll', onWinScrollReposition, true);
        if (_scrollRaf) {
            window.cancelAnimationFrame(_scrollRaf);
            _scrollRaf = 0;
        }
        _initialized = false;
    }

    function tryInit() {
        if (!isDesktop()) {
            teardown();
            return;
        }

        if (_initialized) {
            return;
        }

        let menu = document.querySelector(
            '.menu_left_home1 .navigation.verticalmenu.side-verticalmenu'
        );
        if (!menu) return;

        _ul = menu.querySelector('ul.togge-menu.list-category-dropdown');
        if (!_ul) return;

        _initialized = true;
        /* JS-1 fix: flag para o bootstrap saber que flyout-fix está ativo */
        _ul.setAttribute('data-awa-vmf-active', '1');

        _observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (m) {
                if (m.type === 'attributes' && m.attributeName === 'class') {
                    let isOpen = _ul.classList.contains('vmm-open') ||
                        _ul.classList.contains('menu-open') ||
                        window.getComputedStyle(_ul).display !== 'none';
                    if (!isOpen) detachAll();
                }
            });
        });
        _observer.observe(_ul, { attributes: true });

        _ul.addEventListener('mouseenter', onMenuMouseenter, true);
        _ul.addEventListener('mouseleave', onMenuMouseleave, true);

        document.addEventListener('mouseout', onDocMouseout);
        window.addEventListener('scroll', onWinScrollReposition, true);
    }

    function scheduleInit() {
        setTimeout(function () {
            if (!isDesktop()) {
                teardown();
                return;
            }
            tryInit();
        }, 300);
    }

    function attachFlyout(li) {
        let sub = li.querySelector(
            ':scope > .submenu, :scope > .level0.submenu, :scope > .navigation__submenu, :scope > .vmm-empty-submenu'
        );
        if (!sub) return;

        /* JS-1 fix: limpar classes de nav mobile que possam ter ficado abertas */
        if (document.body) {
            document.body.classList.remove('nav-open', 'nav-before-open');
            document.documentElement.classList.remove('nav-open');
        }

        if (sub.dataset.awVmfPortaled === '1') {
            positionPortal(li, sub);
            return;
        }

        let liRect = li.getBoundingClientRect();
        let subStyle = getSubStyle(li, liRect);

        let placeholder = document.createElement('span');
        placeholder.className = 'awa-vmf-placeholder';
        placeholder.style.cssText = 'display:none;';
        li.insertBefore(placeholder, sub);

        sub.dataset.awVmfPortaled = '1';
        sub.dataset.awVmfLiMenu = li.dataset.menu || '';
        sub._awVmfPlaceholder = placeholder;

        sub.classList.add(PORTAL_CLASS);
        sub.style.cssText = subStyle;
        document.body.appendChild(sub);

        li.classList.add(ACTIVE_CLASS);
    }

    function detachFlyout(li) {
        let portaled = document.querySelector(
            '.' + PORTAL_CLASS + '[data-aw-vmf-li-menu="' + (li.dataset.menu || '') + '"]'
        );
        if (portaled) detachPortal(portaled);
        li.classList.remove(ACTIVE_CLASS);
    }

    function detachPortal(portal) {
        let placeholder = portal._awVmfPlaceholder;
        if (!placeholder) return;

        portal.style.cssText = '';
        portal.classList.remove(PORTAL_CLASS);
        delete portal.dataset.awVmfPortaled;
        delete portal.dataset.awVmfLiMenu;
        portal._awVmfPlaceholder = null;

        let li = placeholder.parentElement;
        if (li) {
            li.insertBefore(portal, placeholder);
            li.classList.remove(ACTIVE_CLASS);
        }
        placeholder.remove();
    }

    function detachAll() {
        document.querySelectorAll('.' + PORTAL_CLASS).forEach(detachPortal);
    }

    function positionPortal(li, portal) {
        let liRect = li.getBoundingClientRect();
        portal.style.cssText = getSubStyle(li, liRect);
    }

    function getSubStyle(li, liRect) {
        let isFullwidth = li.classList.contains('fullwidth');
        let top = liRect.top + OFFSET_TOP;
        let left = liRect.right + OFFSET_LEFT;
        let maxW = Math.min(isFullwidth ? 890 : 540, window.innerWidth - left - 8);
        if (maxW < 360) {
            left = liRect.left - maxW;
            if (left < 4) left = 4;
        }

        let parts = [
            'position:fixed',
            'top:' + top.toFixed(1) + 'px',
            'left:' + left.toFixed(1) + 'px',
            'width:' + maxW.toFixed(0) + 'px',
            'z-index:' + Z_INDEX,
            'visibility:visible',
            'opacity:1',
            'overflow:hidden',
            'pointer-events:auto'
        ];

        return parts.map(function (p) { return p + ' !important'; }).join('; ') + ';';
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scheduleInit);
    } else {
        scheduleInit();
    }

    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            teardown();
            if (isDesktop()) scheduleInit();
        }, 200);
    });
})();
