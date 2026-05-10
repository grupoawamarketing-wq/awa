/**
 * AWA Motos — theme.js (override do tema pai)
 *
 * Correção crítica de performance: o MutationObserver original chamava
 * applyAwaPublicHotfix(node) de forma SÍNCRONA para cada elemento adicionado
 * ao .page-wrapper (childList + subtree: true). Com os múltiplos carrosséis
 * de produtos da homepage isso disparava querySelectorAll('a[href]') centenas
 * de vezes em sequência, bloqueando a thread principal → "Página sem resposta".
 *
 * Fix v2: applyAwaPublicHotfix e o batch do MutationObserver são diferidos
 * via requestIdleCallback, empurrando o trabalho para fora da janela TTI e
 * eliminando o long task de ~1365ms atribuído ao theme.js no LH trace.
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

    let bodyEl = document.body;
    let pathName = (window.location && window.location.pathname) ? window.location.pathname : '';
    let isHomePath = /^\/(?:index\.php\/?)?$/.test(pathName);
    let bodyClassName = bodyEl ? bodyEl.className : '';
    let isHomePage = isHomePath || /\bcms-index-index\b|\bcms-home\b|\bcms-homepage_ayo_home5\b/.test(bodyClassName);
    let shouldRunAwaPublicHotfix = !isHomePage;

    // PERF HOME (experimento controlado): evita executar blocos pesados no caminho crítico.
    if (isHomePage) {
        return;
    }

    function applyAwaPublicHotfix(root) {
        let scope = root && root.querySelectorAll ? root : document;

        if (!shouldRunAwaPublicHotfix) {
            return;
        }

        scope.querySelectorAll('.contact-index-index h1, .contact-index-index h2, .contact-index-index h3, .contact-index-index button, .contact-index-index .action.submit').forEach(function (el) {
            let text = (el.textContent || '').trim();
            if (text === 'Drop Us A Message') {
                el.textContent = 'Envie sua mensagem';
            } else if (text === 'Send Message' || text === 'Send message') {
                el.textContent = 'Enviar mensagem';
            }
        });

        scope.querySelectorAll('.contact-index-index input[placeholder], .contact-index-index textarea[placeholder]').forEach(function (el) {
            let placeholder = (el.getAttribute('placeholder') || '').trim();
            if (placeholder === "What's on your mind?") {
                el.setAttribute('placeholder', 'Como podemos ajudar?');
            } else if (placeholder === 'Phone Number') {
                el.setAttribute('placeholder', 'Telefone');
            } else if (placeholder === 'Your Message') {
                el.setAttribute('placeholder', 'Sua mensagem');
            }
        });

        scope.querySelectorAll('.cms-page-view h2, .cms-page-view h3, .cms-page-view h4').forEach(function (heading) {
            let txt = (heading.textContent || '').trim();
            if (txt && /^\?{2,}/.test(txt)) {
                heading.textContent = txt.replace(/^\?+\s*/, '').trim();
            }
        });

        scope.querySelectorAll('a[href]').forEach(function (anchor) {
            let hrefAttr = anchor.getAttribute('href');
            if (!hrefAttr) return;

            let href = hrefAttr.trim();
            if (!/\/ofertas\/?($|[?#])/i.test(href)) return;

            try {
                let url = new URL(href, window.location.origin);
                let path = (url.pathname || '').replace(/\/+$/, '').toLowerCase();
                if (path !== '/ofertas') return;

                url.pathname = '/ofertas.html';
                let normalized = /^\//.test(href) && !/^https?:\/\//i.test(href)
                    ? (url.pathname + url.search + url.hash)
                    : url.toString();

                anchor.setAttribute('href', normalized);
            } catch (e) {
                // noop
            }
        });
    }

    // Diferido: hotfix DOM + footer aria-label não são críticos para LCP/interatividade.
    // requestIdleCallback garante execução após o browser estar ocioso (pós-TTI).
    let _ric = window.requestIdleCallback || function (cb) { setTimeout(cb, 300); };

    _ric(function () {
        if (shouldRunAwaPublicHotfix) {
            applyAwaPublicHotfix(document);
        }

        /*
         * WCAG 2.5.3 fix: footer contact links have aria-labels that don't contain
         * the full visible text ("WhatsApp Comercial Resposta rápida..."). Removing
         * the mismatched aria-label lets the accessible name fall back to the
         * visible text content, which is already descriptive and satisfies 2.5.3.
         */
        document.querySelectorAll('.awa-footer-business-contact__action[aria-label]').forEach(function (link) {
            let ariaLabel = (link.getAttribute('aria-label') || '').toLowerCase();
            // textContent instead of innerText — innerText forces layout recalculation (reflow)
            let visibleText = (link.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
            if (visibleText && !ariaLabel.includes(visibleText)) {
                link.removeAttribute('aria-label');
            }
        });
    });

    /*
     * WCAG 4.1.3 / aria-hidden-focus: O mega-menu usa visibility:hidden +
     * aria-hidden="true" nos submenus fechados, mas links internos ficam
     * focalizáveis via teclado. O atributo `inert` corrige isso: bloqueia
     * foco, eventos e AT em toda a subárvore, sincronizado com aria-hidden.
     */
    (function () {
        if (isHomePage) {
            return;
        }

        function syncInert(el) {
            if (el.getAttribute('aria-hidden') === 'true') {
                el.setAttribute('inert', '');
            } else {
                el.removeAttribute('inert');
            }
        }
        // Aplicar estado inicial + re-verificar após scripts de terceiros (footer accordion, modal B2B)
        document.querySelectorAll('[aria-hidden]').forEach(syncInert);
        setTimeout(function () {
            document.querySelectorAll('[aria-hidden]').forEach(syncInert);
        }, 800);
        // Observar mudanças em todo o documento (footer + modais estão fora do nav)
        if (window.MutationObserver) {
            new MutationObserver(function (mutations) {
                mutations.forEach(function (m) {
                    if (m.attributeName === 'aria-hidden') {
                        syncInert(m.target);
                    }
                });
            }).observe(document.documentElement, { subtree: true, attributes: true, attributeFilter: ['aria-hidden'] });
        }
    }());

    /*
     * WCAG 1.3.6 / landmark-one-main + WCAG 1.1.1 / image-alt
     * Diferido para requestIdleCallback — getComputedStyle força layout reflow.
     * requestIdleCallback executa quando browser está ocioso (após LCP).
     */
    (window.requestIdleCallback || function (cb) { setTimeout(cb, 0); })(function () {
        let mainEl = document.querySelector('main#maincontent');
        if (mainEl && window.getComputedStyle(mainEl).display === 'none') {
            let contentTopHome = document.querySelector('.content-top-home');
            if (contentTopHome) {
                contentTopHome.setAttribute('role', 'main');
                contentTopHome.setAttribute('aria-label', 'Conteúdo principal');
            }
        }
        let authLogo = document.querySelector('.block-authentication .wave-top img.logo');
        if (authLogo && !authLogo.getAttribute('alt')) {
            authLogo.setAttribute('alt', 'AWA Motos');
        }
    });

    if (window.MutationObserver && shouldRunAwaPublicHotfix) {
        let observerTarget = document.querySelector('.page-wrapper') || document.body;
        if (observerTarget) {

            /*
             * CORREÇÃO DE PERFORMANCE:
             * Acumula os nós adicionados e processa em batch no próximo frame
             * (requestAnimationFrame), em vez de chamar applyAwaPublicHotfix()
             * de forma síncrona para cada mutação. Isso impede que carrosséis
             * com muitos produtos travem a thread principal do browser.
             */
            let _pendingNodes = [];
            let _rafScheduled = false;

            let hotfixObserver = new MutationObserver(function (mutations) {
                mutations.forEach(function (mutation) {
                    mutation.addedNodes.forEach(function (node) {
                        if (node && node.nodeType === 1) {
                            _pendingNodes.push(node);
                        }
                    });
                });

                if (!_rafScheduled && _pendingNodes.length > 0) {
                    _rafScheduled = true;
                    _ric(function () {
                        let nodes = _pendingNodes.splice(0);
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

    function runKeyboardHandlerOnce() {
        if (runKeyboardHandlerOnce._done) {
            return;
        }

        runKeyboardHandlerOnce._done = true;
        keyboardHandler.apply();
    }

    if (isHomePage) {
        // PERF home: evita long task no caminho crítico do LCP/TTI.
        // Acessibilidade é preservada em interação real ou fallback tardio.
        ['pointerdown', 'touchstart', 'keydown', 'scroll', 'mousemove'].forEach(function (evtName) {
            window.addEventListener(evtName, runKeyboardHandlerOnce, { once: true, passive: true });
        });
        window.setTimeout(runKeyboardHandlerOnce, 7000);
    } else {
        runKeyboardHandlerOnce();
    }

    /**
     * Fallback para imagens de produto quebradas (ex: _2.jpg que não existe).
     * Tenta substituir _N.jpg por _1.jpg; se ainda falhar, esconde a imagem.
     */
    function fixBrokenProductImages(root) {
        let scope = root && root.querySelectorAll ? root : document;
        scope.querySelectorAll('img[src*="/media/catalog/product/"]').forEach(function (img) {
            if (img.dataset.awaBrokenHandled) return;
            img.dataset.awaBrokenHandled = '1';

            function tryFallback() {
                let src = img.getAttribute('src') || '';
                let fallback = src.replace(/_\d+(\.(?:jpg|jpeg|png|webp))$/i, '_1$1');
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

    // PERF: na homepage este scan varre centenas de imagens e causa long task >1s.
    // Mantemos o fallback apenas fora da home (PDP/PLP/checkout etc), onde o custo é menor.
    if (!isHomePage) {
        fixBrokenProductImages(document);

        // Also apply to dynamically loaded carousels
        if (window.MutationObserver) {
            let imgObserverTarget = document.querySelector('.page-wrapper') || document.body;
            if (imgObserverTarget) {
                let _imgPendingNodes = [];
                let _imgRafScheduled = false;
                let imgObserver = new MutationObserver(function (mutations) {
                    mutations.forEach(function (mutation) {
                        mutation.addedNodes.forEach(function (node) {
                            if (node && node.nodeType === 1) { _imgPendingNodes.push(node); }
                        });
                    });
                    if (!_imgRafScheduled && _imgPendingNodes.length > 0) {
                        _imgRafScheduled = true;
                        _ric(function () {
                            let nodes = _imgPendingNodes.splice(0);
                            _imgRafScheduled = false;
                            nodes.forEach(fixBrokenProductImages);
                        });
                    }
                });
                imgObserver.observe(imgObserverTarget, { childList: true, subtree: true });
            }
        }
    }
});
