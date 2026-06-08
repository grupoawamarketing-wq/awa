/**
 * AWA Vertical Menu Harden — 2026-05-28
 * Teclado desktop, Escape no flyout portal, tooltips em nomes longos.
 */
(function () {
    'use strict';

    if (window.__AWA_MENU_V2) {
        return;
    }

    let DESKTOP_MIN = 992;
    let PORTAL_SEL = '.awa-vmf-portal';

    function isDesktop() {
        return window.innerWidth >= DESKTOP_MIN;
    }

    function getMenuRoot() {
        return document.querySelector('[data-role="awa-vertical-menu"]');
    }

    function getPanel() {
        let root = getMenuRoot();
        return root ? root.querySelector('[data-role="awa-vertical-menu-panel"]') : null;
    }

    function isPanelOpen() {
        let panel = getPanel();
        return panel && panel.getAttribute('aria-hidden') === 'false';
    }

    function getLevel0Items(panel) {
        return Array.prototype.filter.call(
            panel.querySelectorAll(':scope > li.ui-menu-item.level0'),
            function (li) {
                return !li.classList.contains('expand-category-link')
                    && !li.classList.contains('awa-vmenu-empty')
                    && !li.classList.contains('vertical-bg-img')
                    && !li.classList.contains('awa-vem-extra-li');
            }
        );
    }

    function getItemLink(li) {
        return li.querySelector(':scope > a.level-top, :scope > a.navigation__link');
    }

    function openFlyoutForLi(li) {
        if (!li) {
            return;
        }
        li.dispatchEvent(new MouseEvent('mouseenter', { bubbles: true, cancelable: true }));
    }

    function closeAllFlyouts() {
        if (window.AwaVerticalMenuFlyout && typeof window.AwaVerticalMenuFlyout.closeAll === 'function') {
            window.AwaVerticalMenuFlyout.closeAll();
            return;
        }
        document.querySelectorAll(PORTAL_SEL).forEach(function (portal) {
            portal.dispatchEvent(new MouseEvent('mouseleave', { bubbles: true, cancelable: true }));
        });
    }

    function focusItemLink(li) {
        let link = getItemLink(li);
        if (link) {
            link.focus();
        }
    }

    function applyTruncationTitles(panel) {
        panel.querySelectorAll('a.level-top, a.navigation__link').forEach(function (link) {
            var label = link.querySelector('.navigation__label');
            var text  = label ? (label.textContent || '').trim()
                              : link.getAttribute('data-awa-clean-label') || '';

            /* Remove any title set by Rokanthemes (may include badge text) */
            if (link.hasAttribute('title')) {
                var cur = link.getAttribute('title');
                var hasBadge = !!link.querySelector('.cat-label');
                if (hasBadge || (text && cur !== text)) {
                    link.removeAttribute('title');
                }
            }

            if (!text || !label) {
                return;
            }

            if (label.scrollWidth > label.clientWidth + 1) {
                link.setAttribute('title', text);
            }
        });
    }

    function onPanelKeydown(e) {
        if (!isDesktop() || !isPanelOpen()) {
            return;
        }

        let panel = getPanel();
        if (!panel || !panel.contains(e.target)) {
            return;
        }

        let items = getLevel0Items(panel);
        if (!items.length) {
            return;
        }

        let currentLi = e.target.closest('li.ui-menu-item.level0');
        let idx = currentLi ? items.indexOf(currentLi) : -1;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            let next = items[Math.min(idx < 0 ? 0 : idx + 1, items.length - 1)];
            focusItemLink(next);
            return;
        }

        if (e.key === 'ArrowUp') {
            e.preventDefault();
            let prev = items[Math.max(idx <= 0 ? 0 : idx - 1, 0)];
            focusItemLink(prev);
            return;
        }

        if ((e.key === 'ArrowRight' || e.key === 'Enter' || e.key === ' ') && currentLi) {
            if (currentLi.classList.contains('parent') || currentLi.classList.contains('navigation__item--parent')) {
                e.preventDefault();
                openFlyoutForLi(currentLi);
            }
            return;
        }

        if (e.key === 'ArrowLeft' || e.key === 'Escape') {
            if (document.querySelector(PORTAL_SEL)) {
                e.preventDefault();
                e.stopPropagation();
                closeAllFlyouts();
                if (currentLi) {
                    focusItemLink(currentLi);
                }
            }
        }
    }

    function onDocumentKeydown(e) {
        if (e.key !== 'Escape' || !isDesktop()) {
            return;
        }
        if (!document.querySelector(PORTAL_SEL)) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        closeAllFlyouts();
    }

    function initTruncationObserver() {
        let panel = getPanel();
        if (!panel || !window.ResizeObserver) {
            return;
        }
        applyTruncationTitles(panel);
        let ro = new ResizeObserver(function () {
            applyTruncationTitles(panel);
        });
        ro.observe(panel);
    }

    function bind() {
        let panel = getPanel();
        if (!panel || panel.dataset.awVmenuHarden === '1') {
            return;
        }
        panel.dataset.awVmenuHarden = '1';
        panel.addEventListener('keydown', onPanelKeydown);
        document.addEventListener('keydown', onDocumentKeydown, true);
        initTruncationObserver();
    }

    function scheduleBind() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', bind);
        } else {
            bind();
        }
        window.setTimeout(bind, 600);
        window.setTimeout(bind, 2000);
    }

    scheduleBind();

    window.addEventListener('resize', function () {
        let panel = getPanel();
        if (panel) {
            applyTruncationTitles(panel);
        }
    });
})();
