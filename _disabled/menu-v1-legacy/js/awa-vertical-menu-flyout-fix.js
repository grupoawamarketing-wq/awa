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

    if (window.__AWA_MENU_V2) {
        return;
    }

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

    function prefersReducedMotion() {
        return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    function animateFlyoutEntrance(portal) {
        if (prefersReducedMotion()) {
            return;
        }

        portal.style.setProperty('opacity', '0', 'important');
        portal.style.setProperty('transform', 'translateX(-8px)', 'important');

        window.requestAnimationFrame(function () {
            window.requestAnimationFrame(function () {
                portal.style.setProperty('opacity', '1', 'important');
                portal.style.setProperty('transform', 'translateX(0)', 'important');
            });
        });
    }

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
            '[data-role="awa-vertical-menu"], .menu_left_home1 .navigation.verticalmenu.side-verticalmenu'
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

    function isCompactFlyout(li) {
        return li.classList.contains('subcategory-first-level')
            || li.classList.contains('classic')
            || li.classList.contains('staticwidth');
    }

    function setLevel0Expanded(li, expanded) {
        if (!li) {
            return;
        }
        let link = li.querySelector(':scope > a.level-top, :scope > a.navigation__link');
        let toggle = li.querySelector(':scope > .open-children-toggle');
        let sub = li.querySelector(
            ':scope > .submenu, :scope > .level0.submenu, :scope > .navigation__submenu'
        );
        if (link) {
            link.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            if (sub && sub.id) {
                link.setAttribute('aria-controls', sub.id);
            }
        }
        if (toggle) {
            toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        }
        if (sub) {
            sub.setAttribute('aria-hidden', expanded ? 'false' : 'true');
        }
    }

    function announceStatus(message) {
        let status = document.querySelector('[data-role="awa-vertical-menu-status"]');
        if (!status || !message) {
            return;
        }
        status.textContent = message;
    }

    function clampFlyoutPosition(liRect, maxW, top, left) {
        let margin = 8;
        let viewportH = window.innerHeight || document.documentElement.clientHeight || 600;
        let viewportW = window.innerWidth || document.documentElement.clientWidth || 1024;
        let maxH = Math.max(160, viewportH - margin * 2);
        let adjustedTop = top;
        let adjustedLeft = left;

        if (adjustedTop + maxH > viewportH - margin) {
            adjustedTop = Math.max(margin, viewportH - margin - maxH);
        }
        if (adjustedTop < margin) {
            adjustedTop = margin;
            maxH = Math.max(120, viewportH - margin * 2);
        }
        if (adjustedLeft + maxW > viewportW - margin) {
            adjustedLeft = Math.max(margin, liRect.left - maxW);
        }
        if (adjustedLeft < margin) {
            adjustedLeft = margin;
            maxW = Math.min(maxW, viewportW - margin * 2);
        }

        return {
            top: adjustedTop,
            left: adjustedLeft,
            maxW: maxW,
            maxH: maxH
        };
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
        if (isCompactFlyout(li)) {
            sub.classList.add('awa-vmf-compact');
            sub.setAttribute('data-awa-vmf-density', 'compact');
        } else {
            sub.classList.remove('awa-vmf-compact');
            sub.removeAttribute('data-awa-vmf-density');
        }
        sub.style.cssText = subStyle;
        sub.querySelectorAll('img:not([loading])').forEach(function (img) {
            img.setAttribute('loading', 'lazy');
            img.setAttribute('decoding', 'async');
        });
        document.body.appendChild(sub);
        animateFlyoutEntrance(sub);

        li.classList.add(ACTIVE_CLASS);
        setLevel0Expanded(li, true);
        let link = li.querySelector(':scope > a.level-top, :scope > a.navigation__link');
        if (link) {
            let label = link.querySelector('.navigation__label') || link;
            let name = (label.textContent || '').trim();
            if (name) {
                announceStatus('Submenu de ' + name + ' aberto. Use as setas para navegar.');
            }
        }
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
            setLevel0Expanded(li, false);
            let link = li.querySelector(':scope > a.level-top, :scope > a.navigation__link');
            if (link) {
                let labelEl = link.querySelector('.navigation__label') || link;
                let name = (labelEl.textContent || '').trim();
                if (name) {
                    announceStatus('Submenu de ' + name + ' fechado.');
                }
            }
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
        let compact = isCompactFlyout(li);
        let top = liRect.top + OFFSET_TOP;
        let left = liRect.right + OFFSET_LEFT;
        let maxW;
        if (compact) {
            maxW = Math.min(400, window.innerWidth - left - 12);
        } else if (isFullwidth) {
            maxW = Math.min(680, window.innerWidth - left - 12);
        } else {
            maxW = Math.min(540, window.innerWidth - left - 12);
        }
        if (maxW < 260) {
            left = liRect.left - maxW;
            if (left < 4) {
                left = 4;
            }
        }

        let clamped = clampFlyoutPosition(liRect, maxW, top, left);
        top = clamped.top;
        left = clamped.left;
        maxW = clamped.maxW;

        let parts = [
            'position:fixed',
            'top:' + top.toFixed(1) + 'px',
            'left:' + left.toFixed(1) + 'px',
            'width:' + maxW.toFixed(0) + 'px',
            'max-height:' + clamped.maxH.toFixed(0) + 'px',
            'overflow-x:hidden',
            'overflow-y:auto',
            'overscroll-behavior:contain',
            'z-index:' + Z_INDEX,
            'visibility:visible',
            'opacity:1',
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

    window.AwaVerticalMenuFlyout = {
        closeAll: detachAll,
        closeForMenuId: function (menuId) {
            let li = menuId && document.querySelector('li.level0[data-menu="' + menuId + '"]');
            if (li) {
                detachFlyout(li);
            }
        },
        isDesktop: isDesktop
    };
})();
