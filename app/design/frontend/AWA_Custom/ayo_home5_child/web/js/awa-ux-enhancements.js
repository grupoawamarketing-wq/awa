/**
 * awa-ux-enhancements — Melhorias UX globais v2 (2026-05).
 *
 *  01. fixHashLinks          — corrige âncoras #hash que rolam para cima
 *  02. fixSocialAria         — labels WCAG em ícones de redes sociais
 *  03. injectWhatsApp        — widget flutuante WhatsApp
 *  04. injectTrustSignals    — selos de confiança acima do footer
 *  05. initActiveFiltersBadge — badge de filtros ativos na PLP
 *  06. fixBreadcrumb         — microdata schema no breadcrumb
 *  07. initValueProp         — barra de propostas de valor
 *  08. fixFooterEmail        — previne truncamento de email
 *  09. contentVisibilityFix  — desativa content-visibility nos carrosséis
 *  10. initScrollProgress    — barra de progresso de scroll
 *  11. initImageLazyLoad     — lazy loading para imagens off-screen
 *  12. initCartFeedback      — feedback visual ao adicionar ao carrinho
 *  13. fixMobileTouch        — corrige touch targets WCAG no mobile
 *  14. initSearchEnhance     — limpeza do campo de busca
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
            var socialMap = {
                'facebook':  'Seguir AWA Motos no Facebook',
                'instagram': 'Seguir AWA Motos no Instagram',
                'youtube':   'Canal AWA Motos no YouTube',
                'whatsapp':  'Contato pelo WhatsApp',
                'twitter':   'Seguir AWA Motos no Twitter',
                'linkedin':  'AWA Motos no LinkedIn'
            };
            document.querySelectorAll('.social-links a, .social a, [class*="social"] a').forEach(function (el) {
                if (el.getAttribute('aria-label')) return;
                var href = (el.href || '').toLowerCase();
                var cls  = (el.className || '').toLowerCase();
                var label = '';
                Object.keys(socialMap).forEach(function (key) {
                    if ((href + cls).indexOf(key) !== -1) label = socialMap[key];
                });
                if (label) {
                    el.setAttribute('aria-label', label);
                    el.setAttribute('title', label);
                    var icon = el.querySelector('em, i, svg, [class*="icon"]');
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
            var WA_NUMBER = '5581996070007';
            var WA_MSG    = encodeURIComponent('Olá! Vim pelo site awamotos.com e gostaria de saber mais.');
            var WA_URL    = 'https://wa.me/' + WA_NUMBER + '?text=' + WA_MSG;

            if (document.getElementById('awa-whatsapp-fab')) return;

            var fab = document.createElement('a');
            fab.id         = 'awa-whatsapp-fab';
            fab.href       = WA_URL;
            fab.target     = '_blank';
            fab.rel        = 'noopener noreferrer';
            fab.setAttribute('aria-label', 'Falar com AWA Motos pelo WhatsApp');
            fab.setAttribute('title', 'WhatsApp AWA Motos');
            fab.innerHTML  = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="#fff" d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>';

            var style = document.createElement('style');
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
       04. injectTrustSignals
    ============================================================ */
    function injectTrustSignals() {
        try {
            if (document.getElementById('awa-trust-signals')) return;
            var footer = document.querySelector('.page-footer, .footer.content');
            if (!footer) return;

            var bar = document.createElement('div');
            bar.id = 'awa-trust-signals';
            bar.setAttribute('aria-label', 'Selos de confiança');
            bar.innerHTML = [
                '<div class="awa-trust-inner">',
                '<span class="awa-trust-item">',
                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/></svg>',
                ' Compra Segura</span>',
                '<span class="awa-trust-item">',
                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M20 8h-3V4H3c-1.1 0-2 .9-2 2v11h2c0 1.66 1.34 3 3 3s3-1.34 3-3h6c0 1.66 1.34 3 3 3s3-1.34 3-3h2v-5l-3-4zM6 18.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm13.5-9l1.96 2.5H17V9.5h2.5zm-1.5 9c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/></svg>',
                ' Entrega Rápida</span>',
                '<span class="awa-trust-item">',
                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M21 18v1c0 1.1-.9 2-2 2H5c-1.11 0-2-.9-2-2V5c0-1.1.89-2 2-2h14c1.1 0 2 .9 2 2v1h-9c-1.11 0-2 .9-2 2v8c0 1.1.89 2 2 2h9zm-9-2h10V8H12v8zm4-2.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/></svg>',
                ' Parcelamento</span>',
                '<span class="awa-trust-item">',
                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67V7z"/></svg>',
                ' Prazo Garantido</span>',
                '</div>'
            ].join('');

            var style = document.createElement('style');
            style.id   = 'awa-trust-style';
            style.textContent = [
                '#awa-trust-signals{background:#f8f8f8;border-top:1px solid #e0e0e0;padding:16px 20px;text-align:center}',
                '.awa-trust-inner{display:flex;justify-content:center;align-items:center;gap:24px;flex-wrap:wrap;max-width:1200px;margin:0 auto}',
                '.awa-trust-item{display:inline-flex;align-items:center;gap:6px;font-size:13px;color:#555;font-weight:500}',
                '.awa-trust-item svg{width:18px;height:18px;color:#b73337;flex-shrink:0}',
                '@media(max-width:767px){.awa-trust-inner{gap:12px 20px}.awa-trust-item{font-size:12px}}'
            ].join('');
            document.head.appendChild(style);
            footer.insertAdjacentElement('beforebegin', bar);
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
                var count  = document.querySelectorAll('.filter-current .item, .active-filter-item').length;
                var btn    = document.querySelector('.filter-title strong, .filter-title button, [data-role="filter-button"]');
                if (!btn) return;

                var badge = btn.querySelector('.awa-filter-badge');
                if (count > 0) {
                    if (!badge) {
                        badge = document.createElement('span');
                        badge.className = 'awa-filter-badge';
                        badge.style.cssText = 'display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;background:#b73337;color:#fff;border-radius:50%;font-size:11px;font-weight:700;margin-left:6px;vertical-align:middle;';
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
                var filterEl = document.querySelector('.filter-current, .sidebar-filter');
                if (filterEl) new MutationObserver(updateBadge).observe(filterEl, { childList: true, subtree: true });
            }
        } catch (e) {}
    }

    /* ============================================================
       06. fixBreadcrumb — adiciona schema microdata ao breadcrumb
    ============================================================ */
    function fixBreadcrumb() {
        try {
            var bc = document.querySelector('.breadcrumbs, nav.breadcrumbs, [class*="breadcrumb"]');
            if (!bc || bc.hasAttribute('itemscope')) return;

            bc.setAttribute('itemscope', '');
            bc.setAttribute('itemtype', 'https://schema.org/BreadcrumbList');

            var items = bc.querySelectorAll('li, .item');
            items.forEach(function (item, idx) {
                item.setAttribute('itemprop', 'itemListElement');
                item.setAttribute('itemscope', '');
                item.setAttribute('itemtype', 'https://schema.org/ListItem');

                var link = item.querySelector('a');
                if (link) {
                    link.setAttribute('itemprop', 'item');
                    var nameMeta = document.createElement('meta');
                    nameMeta.setAttribute('itemprop', 'name');
                    nameMeta.setAttribute('content', link.textContent.trim());
                    link.appendChild(nameMeta);
                }

                var posMeta = document.createElement('meta');
                posMeta.setAttribute('itemprop', 'position');
                posMeta.setAttribute('content', idx + 1);
                item.appendChild(posMeta);
            });
        } catch (e) {}
    }

    /* ============================================================
       07. initValueProp — barra de propostas de valor
    ============================================================ */
    function initValueProp() {
        try {
            if (document.getElementById('awa-value-prop-bar')) return;
            if (!document.body.classList.contains('cms-index-index') &&
                !document.body.classList.contains('cms-home')) return;

            var main = document.querySelector('.page-main, main, #maincontent');
            if (!main) return;

            var bar = document.createElement('div');
            bar.id   = 'awa-value-prop-bar';
            bar.setAttribute('aria-label', 'Diferenciais AWA Motos');
            bar.innerHTML = [
                '<div class="awa-vp-inner">',
                '<div class="awa-vp-item"><span class="awa-vp-icon">🏍️</span><span class="awa-vp-text">Especialistas em motos há + de 10 anos</span></div>',
                '<div class="awa-vp-sep" aria-hidden="true">|</div>',
                '<div class="awa-vp-item"><span class="awa-vp-icon">🔧</span><span class="awa-vp-text">Peças originais e reposição com garantia</span></div>',
                '<div class="awa-vp-sep" aria-hidden="true">|</div>',
                '<div class="awa-vp-item"><span class="awa-vp-icon">🚀</span><span class="awa-vp-text">Entrega rápida para todo o Brasil</span></div>',
                '</div>'
            ].join('');

            var style = document.createElement('style');
            style.id   = 'awa-vp-style';
            style.textContent = [
                '#awa-value-prop-bar{background:#1a1a1a;padding:10px 20px;text-align:center}',
                '.awa-vp-inner{display:flex;justify-content:center;align-items:center;gap:20px;flex-wrap:wrap;max-width:1200px;margin:0 auto}',
                '.awa-vp-item{display:inline-flex;align-items:center;gap:8px;font-size:13px;color:rgba(255,255,255,.85)}',
                '.awa-vp-icon{font-size:16px}',
                '.awa-vp-sep{color:rgba(255,255,255,.3);font-weight:300}',
                '@media(max-width:767px){.awa-vp-sep{display:none}.awa-vp-inner{gap:12px}.awa-vp-item{font-size:12px}}'
            ].join('');
            document.head.appendChild(style);
            main.insertAdjacentElement('beforebegin', bar);
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
            var selectors = [
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

            var bar   = document.createElement('div');
            bar.id    = 'awa-scroll-progress';
            bar.setAttribute('role', 'progressbar');
            bar.setAttribute('aria-label', 'Progresso de leitura');
            bar.setAttribute('aria-valuemin', '0');
            bar.setAttribute('aria-valuemax', '100');
            bar.setAttribute('aria-valuenow', '0');

            var style = document.createElement('style');
            style.id   = 'awa-scroll-style';
            style.textContent = '#awa-scroll-progress{position:fixed;top:0;left:0;width:0%;height:3px;background:linear-gradient(90deg,#b73337,#e05a5e);z-index:99999;transition:width .1s linear;pointer-events:none}';
            document.head.appendChild(style);
            document.body.prepend(bar);

            window.addEventListener('scroll', function () {
                var docH = document.documentElement.scrollHeight - window.innerHeight;
                var pct  = docH > 0 ? Math.round((window.scrollY / docH) * 100) : 0;
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

            var imgs = document.querySelectorAll('img:not([loading])');
            imgs.forEach(function (img) {
                var rect = img.getBoundingClientRect();
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

            var style = document.createElement('style');
            style.id   = 'awa-cart-feedback-style';
            style.textContent = [
                '.awa-cart-added{animation:awa-cart-pop .4s ease-out}',
                '@keyframes awa-cart-pop{0%{transform:scale(1)}50%{transform:scale(1.12);background:#1a9e4a!important}100%{transform:scale(1)}}'
            ].join('');
            document.head.appendChild(style);

            document.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-action="add-to-cart"], .action.tocart, [class*="add-to-cart"]');
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

            var style = document.createElement('style');
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
            var searchInput = document.querySelector('#search, .header-search input[type="text"], .block-search input.input-text');
            if (!searchInput || document.getElementById('awa-search-clear')) return;

            var clearBtn = document.createElement('button');
            clearBtn.id   = 'awa-search-clear';
            clearBtn.type = 'button';
            clearBtn.setAttribute('aria-label', 'Limpar busca');
            clearBtn.textContent = '✕';

            var style = document.createElement('style');
            style.id   = 'awa-search-style';
            style.textContent = [
                '#awa-search-clear{position:absolute;right:48px;top:50%;transform:translateY(-50%);',
                'background:none;border:none;cursor:pointer;color:#999;font-size:14px;line-height:1;',
                'padding:4px 8px;display:none;z-index:2}',
                '#awa-search-clear:hover{color:#b73337}',
                '.block-search,.header-search{position:relative}'
            ].join('');
            document.head.appendChild(style);

            if (searchInput.parentElement) {
                searchInput.parentElement.style.position = 'relative';
                searchInput.insertAdjacentElement('afterend', clearBtn);
            }

            searchInput.addEventListener('input', function () {
                clearBtn.style.display = this.value ? 'block' : 'none';
            });
            clearBtn.addEventListener('click', function () {
                searchInput.value = '';
                clearBtn.style.display = 'none';
                searchInput.focus();
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
        injectWhatsApp();
        injectTrustSignals();
        initValueProp();
        initActiveFiltersBadge();
        initScrollProgress();
        initImageLazyLoad();
        initCartFeedback();
        initSearchEnhance();
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
    }, 1500);

    return {};
});