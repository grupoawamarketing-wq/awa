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

    /* Só executa em desktop e na home */
    if (window.innerWidth < 992) return;
    if (!(
        document.body.classList.contains('cms-index-index') ||
        document.body.classList.contains('cms-home') ||
        document.body.classList.contains('cms-homepage_ayo_home5')
    )) return;

    var PORTAL_CLASS = 'awa-vmf-portal';
    var ACTIVE_CLASS = 'awa-vmf-active';
    var Z_INDEX      = 99990;
    var OFFSET_LEFT  = 0;   /* px além do right do LI */
    var OFFSET_TOP   = 0;   /* px além do top do LI */

    /* Aguarda o menu ser renderizado */
    function init() {
        var menu = document.querySelector(
            '.menu_left_home1 .navigation.verticalmenu.side-verticalmenu'
        );
        if (!menu) return;

        /* Observe mutations para quando o togge-menu abrir */
        var ul = menu.querySelector('ul.togge-menu.list-category-dropdown');
        if (!ul) return;

        var observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (m) {
                if (m.type === 'attributes' && m.attributeName === 'class') {
                    var isOpen = ul.classList.contains('vmm-open') ||
                                 ul.classList.contains('menu-open') ||
                                 window.getComputedStyle(ul).display !== 'none';
                    if (!isOpen) detachAll();
                }
            });
        });
        observer.observe(ul, { attributes: true });

        /* Eventos nos LIs de nível 0 */
        ul.addEventListener('mouseenter', function (e) {
            var li = e.target.closest('li.level0.parent, li.level0.navigation__item--parent');
            if (!li) return;
            attachFlyout(li);
        }, true);

        ul.addEventListener('mouseleave', function (e) {
            var li = e.target.closest('li.level0');
            if (!li) return;
            var to = e.relatedTarget;
            /* Se saiu para o flyout portal, não remove */
            if (to && to.classList && (to.classList.contains(PORTAL_CLASS) || to.closest('.' + PORTAL_CLASS))) return;
            detachFlyout(li);
        }, true);

        /* Ao sair do flyout portal, fecha */
        document.addEventListener('mouseout', function (e) {
            var portal = e.target.closest('.' + PORTAL_CLASS);
            if (!portal) return;
            var to = e.relatedTarget;
            var liId = portal.dataset.awVmfLiMenu;
            var li = liId && document.querySelector('li.level0[data-menu="' + liId + '"]');
            /* Se o mouse foi para o LI, fica aberto */
            if (to && li && (li.contains(to) || li === to)) return;
            /* Se foi para outro portal — fecha o atual */
            detachPortal(portal);
        });
    }

    function attachFlyout(li) {
        var sub = li.querySelector(':scope > .submenu, :scope > .vmm-empty-submenu');
        if (!sub) return;

        /* Verifica se já está portado */
        if (sub.dataset.awVmfPortaled === '1') {
            positionPortal(li, sub);
            return;
        }

        /* Calcula posição baseada no LI */
        var liRect = li.getBoundingClientRect();
        var subStyle = getSubStyle(li, liRect);

        /* Salva referência para devolver depois */
        var placeholder = document.createElement('span');
        placeholder.className = 'awa-vmf-placeholder';
        placeholder.style.cssText = 'display:none;';
        li.insertBefore(placeholder, sub);

        sub.dataset.awVmfPortaled = '1';
        sub.dataset.awVmfLiMenu   = li.dataset.menu || '';
        sub._awVmfPlaceholder     = placeholder;

        /* Move para BODY */
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

        /* Devolve ao LI */
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
        var style  = getSubStyle(li, liRect);
        portal.style.cssText = style;
    }

    function getSubStyle(li, liRect) {
        var isFullwidth = li.classList.contains('fullwidth');
        var top   = liRect.top  + OFFSET_TOP;
        var left  = liRect.right + OFFSET_LEFT;
        var maxW  = Math.min(isFullwidth ? 890 : 540, window.innerWidth - left - 8);
        if (maxW < 360) {
            /* Abre para a esquerda se não há espaço */
            left = liRect.left - maxW;
            if (left < 4) left = 4;
        }

        return [
            'position:fixed',
            'top:' + top.toFixed(1) + 'px',
            'left:' + left.toFixed(1) + 'px',
            'width:' + maxW.toFixed(0) + 'px',
            'z-index:' + Z_INDEX,
            'visibility:visible',
            'opacity:1',
            'overflow:hidden',
            'pointer-events:auto',
        ].join('!important;') + '!important;';
    }

    /* Aguarda DOM ready */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            setTimeout(init, 300);
        });
    } else {
        setTimeout(init, 300);
    }

    /* Re-init ao resize acima de 992 */
    var resizeTimer;
    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            detachAll();
            if (window.innerWidth >= 992) init();
        }, 200);
    });
})();
