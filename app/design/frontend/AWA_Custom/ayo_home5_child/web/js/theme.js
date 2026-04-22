/**
 * AWA Motos — theme.js (override do tema pai)
 *
 * Correção crítica de performance: o MutationObserver original chamava
 * applyAwaPublicHotfix(node) de forma SÍNCRONA para cada elemento adicionado
 * ao .page-wrapper (childList + subtree: true). Com os múltiplos carrosséis
 * de produtos da homepage isso disparava querySelectorAll('a[href]') centenas
 * de vezes em sequência, bloqueando a thread principal → "Página sem resposta".
 *
 * Fix: as chamadas ao hotfix são batched via requestAnimationFrame, liberando
 * a thread entre mutações e evitando o travamento.
 */
define([
    'jquery',
    'mage/smart-keyboard-handler',
    'mage/mage',
    'domReady!'
], function ($, keyboardHandler) {
    'use strict';

    if ($('body').hasClass('checkout-cart-index')) {
        if ($('#co-shipping-method-form .fieldset.rates').length > 0 && $('#co-shipping-method-form .fieldset.rates :checked').length === 0) {
            $('#block-shipping').on('collapsiblecreate', function () {
                $('#block-shipping').collapsible('forceActivate');
            });
        }
    }

    $('.cart-summary').mage('sticky', {
        container: '#maincontent'
    });

    $('.panel.header > .header.links').clone().appendTo('#store\\.links');

    function applyAwaPublicHotfix(root) {
        var scope = root && root.querySelectorAll ? root : document;

        scope.querySelectorAll('.contact-index-index h1, .contact-index-index h2, .contact-index-index h3, .contact-index-index button, .contact-index-index .action.submit').forEach(function (el) {
            var text = (el.textContent || '').trim();
            if (text === 'Drop Us A Message') {
                el.textContent = 'Envie sua mensagem';
            } else if (text === 'Send Message' || text === 'Send message') {
                el.textContent = 'Enviar mensagem';
            }
        });

        scope.querySelectorAll('.contact-index-index input[placeholder], .contact-index-index textarea[placeholder]').forEach(function (el) {
            var placeholder = (el.getAttribute('placeholder') || '').trim();
            if (placeholder === "What's on your mind?") {
                el.setAttribute('placeholder', 'Como podemos ajudar?');
            } else if (placeholder === 'Phone Number') {
                el.setAttribute('placeholder', 'Telefone');
            } else if (placeholder === 'Your Message') {
                el.setAttribute('placeholder', 'Sua mensagem');
            }
        });

        scope.querySelectorAll('.cms-page-view h2, .cms-page-view h3, .cms-page-view h4').forEach(function (heading) {
            var txt = (heading.textContent || '').trim();
            if (txt && /^\?{2,}/.test(txt)) {
                heading.textContent = txt.replace(/^\?+\s*/, '').trim();
            }
        });

        scope.querySelectorAll('a[href]').forEach(function (anchor) {
            var hrefAttr = anchor.getAttribute('href');
            if (!hrefAttr) return;

            var href = hrefAttr.trim();
            if (!/\/ofertas\/?($|[?#])/i.test(href)) return;

            try {
                var url = new URL(href, window.location.origin);
                var path = (url.pathname || '').replace(/\/+$/, '').toLowerCase();
                if (path !== '/ofertas') return;

                url.pathname = '/ofertas.html';
                var normalized = /^\//.test(href) && !/^https?:\/\//i.test(href)
                    ? (url.pathname + url.search + url.hash)
                    : url.toString();

                anchor.setAttribute('href', normalized);
            } catch (e) {
                // noop
            }
        });
    }

    applyAwaPublicHotfix(document);

    /*
     * WCAG 2.5.3 fix: footer contact links have aria-labels that don't contain
     * the full visible text ("WhatsApp Comercial Resposta rápida..."). Removing
     * the mismatched aria-label lets the accessible name fall back to the
     * visible text content, which is already descriptive and satisfies 2.5.3.
     */
    document.querySelectorAll('.awa-footer-business-contact__action[aria-label]').forEach(function (link) {
        var ariaLabel = (link.getAttribute('aria-label') || '').toLowerCase();
        var visibleText = (link.innerText || link.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
        if (visibleText && !ariaLabel.includes(visibleText)) {
            link.removeAttribute('aria-label');
        }
    });

    /*
     * WCAG 1.3.6 / landmark-one-main: Na homepage, themes5.css oculta o
     * elemento <main> com display:none — todo o conteúdo fica em
     * .content-top-home (div fora do <main>). Adicionamos role="main" ao
     * container visível para que Lighthouse e leitores de tela encontrem
     * exatamente um ponto de referência principal.
     */
    var mainEl = document.querySelector('main#maincontent');
    if (mainEl && window.getComputedStyle(mainEl).display === 'none') {
        var contentTopHome = document.querySelector('.content-top-home');
        if (contentTopHome) {
            contentTopHome.setAttribute('role', 'main');
            contentTopHome.setAttribute('aria-label', 'Conteúdo principal');
        }
    }

    /*
     * WCAG 1.1.1 / image-alt: O img de logo no modal de autenticação B2B
     * não possui atributo alt — o data-bind Knockout define apenas o src.
     * Adicionamos alt manualmente após o DOM estar disponível.
     */
    var authLogo = document.querySelector('.block-authentication .wave-top img.logo');
    if (authLogo && !authLogo.getAttribute('alt')) {
        authLogo.setAttribute('alt', 'AWA Motos');
    }

    if (window.MutationObserver) {
        var observerTarget = document.querySelector('.page-wrapper') || document.body;
        if (observerTarget) {

            /*
             * CORREÇÃO DE PERFORMANCE:
             * Acumula os nós adicionados e processa em batch no próximo frame
             * (requestAnimationFrame), em vez de chamar applyAwaPublicHotfix()
             * de forma síncrona para cada mutação. Isso impede que carrosséis
             * com muitos produtos travem a thread principal do browser.
             */
            var _pendingNodes = [];
            var _rafScheduled = false;

            var hotfixObserver = new MutationObserver(function (mutations) {
                mutations.forEach(function (mutation) {
                    mutation.addedNodes.forEach(function (node) {
                        if (node && node.nodeType === 1) {
                            _pendingNodes.push(node);
                        }
                    });
                });

                if (!_rafScheduled && _pendingNodes.length > 0) {
                    _rafScheduled = true;
                    requestAnimationFrame(function () {
                        var nodes = _pendingNodes.splice(0);
                        _rafScheduled = false;
                        nodes.forEach(applyAwaPublicHotfix);
                    });
                }
            });

            hotfixObserver.observe(observerTarget, {
                childList: true,
                subtree: true
            });
        }
    }

    keyboardHandler.apply();

    /**
     * Fallback para imagens de produto quebradas (ex: _2.jpg que não existe).
     * Tenta substituir _N.jpg por _1.jpg; se ainda falhar, esconde a imagem.
     */
    function fixBrokenProductImages(root) {
        var scope = root && root.querySelectorAll ? root : document;
        scope.querySelectorAll('img[src*="/media/catalog/product/"]').forEach(function (img) {
            if (img.dataset.awaBrokenHandled) return;
            img.dataset.awaBrokenHandled = '1';

            function tryFallback() {
                var src = img.getAttribute('src') || '';
                var fallback = src.replace(/_\d+(\.(?:jpg|jpeg|png|webp))$/i, '_1$1');
                if (fallback !== src) {
                    img.removeEventListener('error', tryFallback);
                    img.addEventListener('error', function () {
                        img.style.visibility = 'hidden';
                    });
                    img.setAttribute('src', fallback);
                } else {
                    img.style.visibility = 'hidden';
                }
            }

            img.addEventListener('error', tryFallback);

            // Handle images already in broken state (complete + naturalWidth === 0)
            // This happens when the error event fired before our listener was attached
            if (img.complete && img.naturalWidth === 0) {
                tryFallback();
            }
        });
    }

    fixBrokenProductImages(document);

    // Also apply to dynamically loaded carousels
    if (window.MutationObserver) {
        var imgObserverTarget = document.querySelector('.page-wrapper') || document.body;
        if (imgObserverTarget) {
            var _imgPendingNodes = [];
            var _imgRafScheduled = false;
            var imgObserver = new MutationObserver(function (mutations) {
                mutations.forEach(function (mutation) {
                    mutation.addedNodes.forEach(function (node) {
                        if (node && node.nodeType === 1) { _imgPendingNodes.push(node); }
                    });
                });
                if (!_imgRafScheduled && _imgPendingNodes.length > 0) {
                    _imgRafScheduled = true;
                    requestAnimationFrame(function () {
                        var nodes = _imgPendingNodes.splice(0);
                        _imgRafScheduled = false;
                        nodes.forEach(fixBrokenProductImages);
                    });
                }
            });
            imgObserver.observe(imgObserverTarget, { childList: true, subtree: true });
        }
    }
});
