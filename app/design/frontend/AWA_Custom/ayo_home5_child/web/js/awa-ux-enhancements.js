/**
 * awa-ux-enhancements — Melhorias UX globais v2 (2026-05).
 *
 *  01. fixHashLinks           — corrige âncoras #hash que rolam para cima
 *  02. fixSocialAria          — labels WCAG em ícones de redes sociais
 *  03. injectWhatsApp         — widget flutuante WhatsApp
 *  04. initActiveFiltersBadge — badge de filtros ativos na PLP
 *  05. fixBreadcrumb          — microdata schema no breadcrumb
 *  06. fixFooterEmail         — previne truncamento de email
 *  07. contentVisibilityFix   — desativa content-visibility nos carrosséis
 *  08. initScrollProgress     — barra de progresso de scroll
 *  09. initImageLazyLoad      — lazy loading para imagens off-screen
 *  10. initCartFeedback       — feedback visual ao adicionar ao carrinho
 *  11. fixMobileTouch         — corrige touch targets WCAG no mobile
 *  12. initSearchEnhance      — limpeza do campo de busca
 */
define(['jquery'], function ($) {
    'use strict';

    /* ============================================================
       01. fixHashLinks
    ============================================================ */
    function fixHashLinks() {
        try {
            document.querySelectorAll('a[href="#"]').forEach(function (el) {
                el.addEventListener('click', function (e) { e.preventDefault(); });
            });
        } catch (e) {}
    }

    /* ============================================================
       02. fixSocialAria
    ============================================================ */
    function fixSocialAria() {
        try {
            let socialMap = {
                'facebook':  'Seguir AWA Motos no Facebook',
                'instagram': 'Seguir AWA Motos no Instagram',
                'youtube':   'Canal AWA Motos no YouTube',
                'whatsapp':  'Contato pelo WhatsApp',
                'twitter':   'Seguir AWA Motos no Twitter',
                'linkedin':  'AWA Motos no LinkedIn'
            };
            document.querySelectorAll('.social-links a, .social a, [class*="social"] a').forEach(function (el) {
                if (el.getAttribute('aria-label')) return;
                let href = (el.href || '').toLowerCase();
                let cls  = (el.className || '').toLowerCase();
                let label = '';
                Object.keys(socialMap).forEach(function (key) {
                    if ((href + cls).indexOf(key) !== -1) label = socialMap[key];
                });
                if (label) {
                    el.setAttribute('aria-label', label);
                    el.setAttribute('title', label);
                    let icon = el.querySelector('em, i, svg, [class*="icon"]');
                    if (icon) icon.setAttribute('aria-hidden', 'true');
                }
            });
        } catch (e) {}
    }

    /* ============================================================
       03. injectWhatsApp
    ============================================================ */
    function injectWhatsApp() {
        try {
            let WA_NUMBER = '5516997367588';
            let WA_MSG    = encodeURIComponent('Olá! Vim pelo site awamotos.com e gostaria de saber mais.');
            let WA_URL    = 'https://wa.me/' + WA_NUMBER + '?text=' + WA_MSG;

            if (document.getElementById('awa-whatsapp-fab')) return;

            let fab = document.createElement('a');
            fab.id         = 'awa-whatsapp-fab';
            fab.href       = WA_URL;
            fab.target     = '_blank';
            fab.rel        = 'noopener noreferrer';
            fab.setAttribute('aria-label', 'Falar com AWA Motos pelo WhatsApp');
            fab.setAttribute('title', 'WhatsApp AWA Motos');
            fab.innerHTML  = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="#fff" d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>';

            let style = document.createElement('style');
            style.id   = 'awa-wa-fab-style';
            style.textContent = [
                '#awa-whatsapp-fab{position:fixed;bottom:80px;right:20px;z-index:9998;width:56px;height:56px;',
                'background:#25d366;border-radius:50%;display:flex;align-items:center;justify-content:center;',
                'box-shadow:0 4px 12px rgba(0,0,0,.3);transition:transform .2s,box-shadow .2s;text-decoration:none}',
                '#awa-whatsapp-fab:hover{transform:scale(1.1);box-shadow:0 6px 20px rgba(0,0,0,.4)}',
                '#awa-whatsapp-fab svg{width:32px;height:32px}',
                '@media(max-width:767px){#awa-whatsapp-fab{bottom:64px;right:12px;width:48px;height:48px}',
                '#awa-whatsapp-fab svg{width:26px;height:26px}}'
            ].join('');
            document.head.appendChild(style);
            document.body.appendChild(fab);
        } catch (e) {}
    }

    /* ============================================================
       05. initActiveFiltersBadge
    ============================================================ */
    function initActiveFiltersBadge() {
        try {
            if (!document.body.classList.contains('catalog-category-view') &&
                !document.body.classList.contains('catalogsearch-result-index')) return;

            function updateBadge() {
                let count  = document.querySelectorAll('.filter-current .item, .active-filter-item').length;
                let btn    = document.querySelector('.filter-title strong, .filter-title button, [data-role="filter-button"]');
                if (!btn) return;

                let badge = btn.querySelector('.awa-filter-badge');
                if (count > 0) {
                    if (!badge) {
                        badge = document.createElement('span');
                        badge.className = 'awa-filter-badge';
                        badge.style.cssText = 'display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;background:#A33B3B;color:#fff;border-radius:50%;font-size:11px;font-weight:700;margin-left:6px;vertical-align:middle;';
                        btn.appendChild(badge);
                    }
                    badge.textContent = count;
                    badge.setAttribute('aria-label', count + ' filtros ativos');
                } else if (badge) {
                    badge.remove();
                }
            }

            updateBadge();
            if (typeof MutationObserver !== 'undefined') {
                let filterEl = document.querySelector('.filter-current, .sidebar-filter');
                if (filterEl) new MutationObserver(updateBadge).observe(filterEl, { childList: true, subtree: true });
            }
        } catch (e) {}
    }

    /* ============================================================
       06. fixBreadcrumb — adiciona schema microdata ao breadcrumb
    ============================================================ */
    function fixBreadcrumb() {
        try {
            let bc = document.querySelector('.breadcrumbs, nav.breadcrumbs, [class*="breadcrumb"]');
            if (!bc || bc.hasAttribute('itemscope')) return;

            bc.setAttribute('itemscope', '');
            bc.setAttribute('itemtype', 'https://schema.org/BreadcrumbList');

            let items = bc.querySelectorAll('li, .item');
            items.forEach(function (item, idx) {
                item.setAttribute('itemprop', 'itemListElement');
                item.setAttribute('itemscope', '');
                item.setAttribute('itemtype', 'https://schema.org/ListItem');

                let link = item.querySelector('a');
                if (link) {
                    link.setAttribute('itemprop', 'item');
                    let nameMeta = document.createElement('meta');
                    nameMeta.setAttribute('itemprop', 'name');
                    nameMeta.setAttribute('content', link.textContent.trim());
                    link.appendChild(nameMeta);
                }

                let posMeta = document.createElement('meta');
                posMeta.setAttribute('itemprop', 'position');
                posMeta.setAttribute('content', idx + 1);
                item.appendChild(posMeta);
            });
        } catch (e) {}
    }

    /* ============================================================
       08. fixFooterEmail
    ============================================================ */
    function fixFooterEmail() {
        try {
            document.querySelectorAll('a[href^="mailto:"]').forEach(function (el) {
                el.style.wordBreak = 'break-all';
                el.style.overflowWrap = 'break-word';
                el.style.display = 'inline-block';
                el.style.maxWidth = '100%';
            });
        } catch (e) {}
    }

    /* ============================================================
       09. contentVisibilityFix
    ============================================================ */
    function contentVisibilityFix() {
        try {
            let selectors = [
                '.awa-carousel-section',
                '.awa-home-section',
                '.rokan-newproduct',
                '.rokan-bestseller',
                '.rokan-mostviewed',
                '.awa-home-products'
            ].join(', ');
            document.querySelectorAll(selectors).forEach(function (el) {
                if (window.getComputedStyle(el).contentVisibility === 'auto') {
                    el.style.contentVisibility = 'visible';
                    el.style.containIntrinsicSize = 'unset';
                }
            });
        } catch (e) {}
    }

    /* ============================================================
       10. initScrollProgress
    ============================================================ */
    function initScrollProgress() {
        try {
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
            if (document.getElementById('awa-scroll-progress')) return;

            let bar   = document.createElement('div');
            bar.id    = 'awa-scroll-progress';
            bar.setAttribute('role', 'progressbar');
            bar.setAttribute('aria-label', 'Progresso de leitura');
            bar.setAttribute('aria-valuemin', '0');
            bar.setAttribute('aria-valuemax', '100');
            bar.setAttribute('aria-valuenow', '0');

            let style = document.createElement('style');
            style.id   = 'awa-scroll-style';
            style.textContent = '#awa-scroll-progress{position:fixed;top:0;left:0;width:0%;height:3px;background:linear-gradient(90deg,#A33B3B,#e05a5e);z-index:99999;transition:width .1s linear;pointer-events:none}';
            document.head.appendChild(style);
            document.body.prepend(bar);

            window.addEventListener('scroll', function () {
                let docH = document.documentElement.scrollHeight - window.innerHeight;
                let pct  = docH > 0 ? Math.round((window.scrollY / docH) * 100) : 0;
                bar.style.width = pct + '%';
                bar.setAttribute('aria-valuenow', pct);
            }, { passive: true });
        } catch (e) {}
    }

    /* ============================================================
       11. initImageLazyLoad — lazy loading nativo para imagens
    ============================================================ */
    function initImageLazyLoad() {
        try {
            if (!('IntersectionObserver' in window)) return;

            let imgs = document.querySelectorAll('img:not([loading])');
            imgs.forEach(function (img) {
                let rect = img.getBoundingClientRect();
                if (rect.top > window.innerHeight) {
                    img.setAttribute('loading', 'lazy');
                }
            });
        } catch (e) {}
    }

    /* ============================================================
       12. initCartFeedback — animação ao adicionar produto
    ============================================================ */
    function initCartFeedback() {
        try {
            if (document.getElementById('awa-cart-feedback-style')) return;

            let style = document.createElement('style');
            style.id   = 'awa-cart-feedback-style';
            style.textContent = [
                '.awa-cart-added{animation:awa-cart-pop .4s ease-out}',
                '@keyframes awa-cart-pop{0%{transform:scale(1)}50%{transform:scale(1.12);background:#1a9e4a!important}100%{transform:scale(1)}}'
            ].join('');
            document.head.appendChild(style);

            document.addEventListener('click', function (e) {
                let btn = e.target.closest('[data-action="add-to-cart"], .action.tocart, [class*="add-to-cart"]');
                if (btn) {
                    btn.classList.add('awa-cart-added');
                    btn.addEventListener('animationend', function () {
                        btn.classList.remove('awa-cart-added');
                    }, { once: true });
                }
            });
        } catch (e) {}
    }

    /* ============================================================
       13. fixMobileTouch — touch targets WCAG (min 44x44)
    ============================================================ */
    function fixMobileTouch() {
        try {
            if (window.innerWidth > 991) return;

            let style = document.createElement('style');
            style.id   = 'awa-touch-style';
            style.textContent = [
                '.nav-toggle,.action.showcart,.action.towishlist,',
                '.product-item-actions .action{min-width:44px!important;min-height:44px!important}',
                '.nav-toggle{display:flex!important;align-items:center!important;justify-content:center!important}'
            ].join('');
            document.head.appendChild(style);
        } catch (e) {}
    }

    /* ============================================================
       14. initSearchEnhance — botão limpar busca
    ============================================================ */
    function initSearchEnhance() {
        try {
            let searchInput = document.querySelector('#search, .header-search input[type="text"], .block-search input.input-text');
            if (!searchInput || document.getElementById('awa-search-clear')) return;

            let clearBtn = document.createElement('button');
            clearBtn.id   = 'awa-search-clear';
            clearBtn.type = 'button';
            clearBtn.setAttribute('aria-label', 'Limpar busca');
            clearBtn.textContent = '✕';

            /* Append ao fim do .control — NÃO após o input (quebra $input.next() do Mirasvit). */
            let control = searchInput.closest('.control[data-awa-search-control], .field.search .control, .control')
                || searchInput.parentElement;

            if (control) {
                control.appendChild(clearBtn);
            }

            searchInput.addEventListener('input', function () {
                clearBtn.style.display = this.value ? 'flex' : 'none';
            });
            clearBtn.addEventListener('click', function () {
                searchInput.value = '';
                clearBtn.style.display = 'none';
                searchInput.dispatchEvent(new Event('input', { bubbles: true }));
                searchInput.focus();
            });
        } catch (e) {}
    }

    /* ============================================================
       15. initSearchAutocompletePanelFix — geometria MST + layout mobile (sem alterar Mirasvit)
    ============================================================ */
    function initSearchAutocompletePanelFix() {
        try {
            if (window.__awaSearchAutocompletePanelFixInit) {
                return;
            }

            let root = document.querySelector('.block-search.awa-professional-search');
            let control = root && root.querySelector('.field.search .control[data-awa-search-control], .field.search .control');

            if (!control) {
                return;
            }

            window.__awaSearchAutocompletePanelFixInit = true;

            function syncSearchLayout() {
                let searchRoot = document.querySelector('.block-search.awa-professional-search');
                let searchControl = searchRoot && searchRoot.querySelector('.field.search .control');
                let form = searchRoot && searchRoot.querySelector('#search_mini_form');
                let input = searchRoot && searchRoot.querySelector('#search');
                let panel = searchControl && searchControl.querySelector('.mst-searchautocomplete__autocomplete._active');
                let isMobile = window.matchMedia('(max-width: 991px)').matches;
                let isActive = !!(form && (
                    form.matches(':focus-within')
                    || form.classList.contains('searchautocomplete__active')
                    || panel
                    || (input && input.value && input.value.length > 0)
                ));

                let searchCol = document.querySelector('.awa-header-search-col');
                let field = searchRoot && searchRoot.querySelector('.field.search');
                let blockContent = searchRoot && searchRoot.querySelector('.block-content');

                if (isMobile && searchRoot) {
                    if (searchCol) {
                        searchCol.style.setProperty('width', '100%', 'important');
                        searchCol.style.setProperty('max-width', '100%', 'important');
                        searchCol.style.setProperty('grid-column', '1 / -1', 'important');
                    }

                    searchRoot.style.setProperty('width', '100%', 'important');
                    searchRoot.style.setProperty('max-width', '100%', 'important');

                    if (blockContent) {
                        blockContent.style.setProperty('width', '100%', 'important');
                        blockContent.style.setProperty('max-width', '100%', 'important');
                    }

                    if (field) {
                        field.style.setProperty('display', 'block', 'important');
                        field.style.setProperty('width', '100%', 'important');
                        field.style.setProperty('flex', '1 1 auto', 'important');
                        field.style.setProperty('min-width', '0', 'important');
                    }

                    if (form) {
                        form.style.setProperty('width', '100%', 'important');
                        form.style.setProperty('max-width', '100%', 'important');
                    }
                } else if (searchRoot) {
                    /* Desktop: remover inline mobile — !important inline vence CSS estrutural */
                    if (searchCol) {
                        searchCol.style.removeProperty('width');
                        searchCol.style.removeProperty('max-width');
                        searchCol.style.removeProperty('grid-column');
                    }

                    searchRoot.style.removeProperty('width');
                    searchRoot.style.removeProperty('max-width');

                    if (blockContent) {
                        blockContent.style.removeProperty('width');
                        blockContent.style.removeProperty('max-width');
                    }

                    if (field) {
                        field.style.removeProperty('display');
                        field.style.removeProperty('width');
                        field.style.removeProperty('flex');
                        field.style.removeProperty('min-width');
                    }

                    if (form) {
                        form.style.removeProperty('width');
                        form.style.removeProperty('max-width');
                    }

                    if (input) {
                        input.style.removeProperty('width');
                        input.style.removeProperty('flex');
                        input.style.removeProperty('min-width');
                        input.style.removeProperty('box-sizing');
                    }
                }

                if (isMobile && searchControl) {
                    searchControl.style.setProperty('display', 'block', 'important');
                    searchControl.style.setProperty('width', '100%', 'important');
                    searchControl.style.setProperty('min-width', '0', 'important');
                    searchControl.style.setProperty('overflow', 'visible', 'important');
                }

                if (isMobile && input && isActive) {
                    input.style.setProperty('width', '100%', 'important');
                    input.style.setProperty('flex', 'none', 'important');
                    input.style.setProperty('min-width', '0', 'important');
                    input.style.setProperty('box-sizing', 'border-box', 'important');

                    if (searchControl) {
                        let controlWidth = searchControl.getBoundingClientRect().width;

                        if (controlWidth > 80) {
                            input.style.setProperty('width', Math.round(controlWidth - 36) + 'px', 'important');
                        }
                    }
                }

                if (form && isActive) {
                    form.style.setProperty('overflow', 'visible', 'important');
                }

                if (panel) {
                    panel.style.setProperty('width', '100%', 'important');
                    panel.style.setProperty('left', '0', 'important');
                    panel.style.setProperty('right', '0', 'important');
                    panel.style.setProperty('max-width', '100%', 'important');
                    panel.style.setProperty('min-height', '84px', 'important');
                    panel.style.setProperty('height', 'auto', 'important');
                    panel.style.setProperty('overflow-y', 'auto', 'important');
                }
            }

            if (window.MutationObserver) {
                new window.MutationObserver(function () {
                    syncSearchLayout();
                }).observe(control, {
                    childList: true,
                    subtree: true,
                    attributes: true,
                    attributeFilter: ['class', 'style']
                });
            }

            control.addEventListener('focusin', syncSearchLayout, true);
            document.addEventListener('input', function (event) {
                if (event.target && event.target.id === 'search') {
                    window.requestAnimationFrame(syncSearchLayout);
                }
            }, true);
            document.addEventListener('focusin', function (event) {
                if (event.target && event.target.id === 'search') {
                    syncSearchLayout();
                }
            }, true);
            window.addEventListener('resize', syncSearchLayout, { passive: true });

            syncSearchLayout();
            [400, 1200, 2500, 5000, 9000, 14000].forEach(function (delay) {
                window.setTimeout(syncSearchLayout, delay);
            });
        } catch (e) {}
    }

    /* ============================================================
       INIT
    ============================================================ */
    function init() {
        fixHashLinks();
        fixSocialAria();
        fixFooterEmail();
        contentVisibilityFix();
        fixBreadcrumb();
        fixMobileTouch();
        // injectWhatsApp(); // Removido para evitar duplicidade (Bug #3)
        initActiveFiltersBadge();
        initScrollProgress();
        initImageLazyLoad();
        initCartFeedback();
        initSearchEnhance();
        initSearchAutocompletePanelFix();
    }

    if (document.readyState !== 'loading') {
        init();
    } else {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    }

    window.setTimeout(function () {
        fixSocialAria();
        fixFooterEmail();
        contentVisibilityFix();
        initSearchEnhance();
        initSearchAutocompletePanelFix();
    }, 1500);

    return {};
});