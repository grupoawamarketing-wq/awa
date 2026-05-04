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

    var DESKTOP_MIN = 992;
    var PORTAL_CLASS = 'awa-vmf-portal';
    var ACTIVE_CLASS = 'awa-vmf-active';
    var Z_INDEX = 99990;
    var OFFSET_LEFT = 0;
    var OFFSET_TOP = 0;

    var _observer = null;
    var _ul = null;
    var _initialized = false;
    var _scrollRaf = 0;
    var resizeTimer;

    function isDesktop() {
        return window.innerWidth >= DESKTOP_MIN;
    }

    /* Handlers nomeados para permitir removeEventListener exato */
    function onMenuMouseenter(e) {
        var li = e.target.closest('li.level0.parent, li.level0.navigation__item--parent');
        if (!li) return;
        attachFlyout(li);
    }

    function onMenuMouseleave(e) {
        var li = e.target.closest('li.level0');
        if (!li) return;
        var to = e.relatedTarget;
        if (to && to.classList && (to.classList.contains(PORTAL_CLASS) || to.closest('.' + PORTAL_CLASS))) return;
        detachFlyout(li);
    }

    function onDocMouseout(e) {
        var portal = e.target.closest('.' + PORTAL_CLASS);
        if (!portal) return;
        var to = e.relatedTarget;
        var liId = portal.dataset.awVmfLiMenu;
        var li = liId && document.querySelector('li.level0[data-menu="' + liId + '"]');
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
                var id = portal.dataset.awVmfLiMenu;
                var li = id && document.querySelector('li.level0[data-menu="' + id + '"]');
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

        var menu = document.querySelector(
            '.menu_left_home1 .navigation.verticalmenu.side-verticalmenu'
        );
        if (!menu) return;

        _ul = menu.querySelector('ul.togge-menu.list-category-dropdown');
        if (!_ul) return;

        _initialized = true;

        _observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (m) {
                if (m.type === 'attributes' && m.attributeName === 'class') {
                    var isOpen = _ul.classList.contains('vmm-open') ||
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
        var sub = li.querySelector(
            ':scope > .submenu, :scope > .level0.submenu, :scope > .navigation__submenu, :scope > .vmm-empty-submenu'
        );
        if (!sub) return;

        if (sub.dataset.awVmfPortaled === '1') {
            positionPortal(li, sub);
            return;
        }

        var liRect = li.getBoundingClientRect();
        var subStyle = getSubStyle(li, liRect);

        var placeholder = document.createElement('span');
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
        var portaled = document.querySelector(
            '.' + PORTAL_CLASS + '[data-aw-vmf-li-menu="' + (li.dataset.menu || '') + '"]'
        );
        if (portaled) detachPortal(portaled);
        li.classList.remove(ACTIVE_CLASS);
    }

    function detachPortal(portal) {
        var placeholder = portal._awVmfPlaceholder;
        if (!placeholder) return;

        portal.style.cssText = '';
        portal.classList.remove(PORTAL_CLASS);
        delete portal.dataset.awVmfPortaled;
        delete portal.dataset.awVmfLiMenu;
        portal._awVmfPlaceholder = null;

        var li = placeholder.parentElement;
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
        var liRect = li.getBoundingClientRect();
        portal.style.cssText = getSubStyle(li, liRect);
    }

    function getSubStyle(li, liRect) {
        var isFullwidth = li.classList.contains('fullwidth');
        var top = liRect.top + OFFSET_TOP;
        var left = liRect.right + OFFSET_LEFT;
        var maxW = Math.min(isFullwidth ? 890 : 540, window.innerWidth - left - 8);
        if (maxW < 360) {
            left = liRect.left - maxW;
            if (left < 4) left = 4;
        }

        var parts = [
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
