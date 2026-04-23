define([], function () {
    'use strict';

    return function initHeaderNavRuntime() {
        if (window.__awaHeaderNavRuntimeInit) {
            return;
        }

        window.__awaHeaderNavRuntimeInit = true;

        /* ── Pre-initialize cart badge from localStorage to avoid flash ── */
        (function () {
            try {
                var cache = JSON.parse(localStorage.getItem('mage-cache-storage') || '{}');
                var count = Number((cache.cart || {}).summary_count || 0);
                if (count > 0) {
                    var badge = document.querySelector('.awa-header-cart-link .awa-cart-link-badge');
                    if (badge) {
                        badge.textContent = count > 99 ? '99+' : String(count);
                        badge.style.cssText = 'display:inline-flex;align-items:center;justify-content:center';
                    }
                }
            } catch (e) {
                // localStorage not available
            }
        }());

        /* ── aria-expanded sync for hamburger (body.nav-open toggled by Magento menu.js) ── */
        (function () {
            function resolveDrawerShell() {
                return document.querySelector('[data-awa-nav-shell="true"]') ||
                    document.getElementById('awa-category-navigation') ||
                    document.querySelector('#awa-primary-navigation.section-items');
            }

            function syncNavAria() {
                var isOpen = document.body.classList.contains('nav-open');
                var toggle = document.querySelector('.awa-header-mobile-toggle[data-awa-nav-toggle="true"]');

                if (toggle) {
                    toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                }

                var drawerShell = resolveDrawerShell();
                if (drawerShell) {
                    drawerShell.classList.toggle('is-awa-mobile-open', isOpen);
                }
            }

            if (window.MutationObserver) {
                new MutationObserver(syncNavAria).observe(document.body, {
                    attributes: true,
                    attributeFilter: ['class']
                });
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', syncNavAria, { once: true });
            } else {
                syncNavAria();
            }
        }());

        function syncBadge() {
            var badge = document.querySelector('.awa-header-cart-link .awa-cart-link-badge');
            if (!badge) {
                return;
            }

            var counter = document.querySelector('.awa-header-minicart .counter.qty');
            if (!counter) {
                badge.style.display = 'none';
                return;
            }

            if (counter.classList.contains('empty')) {
                badge.style.display = 'none';
                return;
            }

            var total = counter.querySelector('.total-mini-cart-item');
            var value = total ? (parseInt((total.textContent || '').replace(/\D/g, ''), 10) || 0) : 0;
            if (value > 0) {
                badge.textContent = value > 99 ? '99+' : String(value);
                badge.style.display = 'inline-flex';
                badge.style.alignItems = 'center';
                badge.style.justifyContent = 'center';
            } else {
                badge.style.display = 'none';
            }
        }

        function bootBadgeSync() {
            syncBadge();

            var counter = document.querySelector('.awa-header-minicart .counter.qty');
            if (counter && window.MutationObserver) {
                new MutationObserver(syncBadge).observe(counter, {
                    childList: true,
                    subtree: true,
                    characterData: true,
                    attributes: true,
                    attributeFilter: ['class']
                });
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', bootBadgeSync, { once: true });
        } else {
            bootBadgeSync();
        }

        /* ── Fix: botão de busca fica disabled quando input já está pré-preenchido no carregamento
           Causa: form-mini.js do Magento inicia com submitBtn.disabled=true e só re-habilita via
           evento 'input', que não dispara quando o valor vem do URL (ex.: /catalogsearch/result/?q=zz).
           Fix: disparar o evento 'input' após o RequireJS inicializar o widget. ── */
        function fixSearchSubmitBtn() {
            var searchInput = document.getElementById('search');
            if (!searchInput || !searchInput.value) {
                return;
            }

            var form = searchInput.closest('form');
            var submitBtn = form ? form.querySelector('button[type="submit"]') : null;

            if (submitBtn && submitBtn.disabled) {
                searchInput.dispatchEvent(new Event('input', { bubbles: true }));
            }
        }

        // Aguarda 500ms para o RequireJS inicializar o form-mini widget antes de disparar o fix
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () {
                setTimeout(fixSearchSubmitBtn, 500);
            }, { once: true });
        } else {
            setTimeout(fixSearchSubmitBtn, 500);
        }
    };
});
