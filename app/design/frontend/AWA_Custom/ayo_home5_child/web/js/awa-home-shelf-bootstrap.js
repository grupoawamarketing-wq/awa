/**
 * Home shelf bootstrap:
 * - carrega o runtime do carrossel (awa-scroll-carousel.js, scroll-snap nativo)
 *   automaticamente nas vitrines da home
 * - mantém interação explícita como atalho de prioridade
 * - tolera falha de parse no JSON de config
 * - não espera jQuery/Owl/Swiper para liberar as prateleiras
 */
(function (window, document) {
    'use strict';

    var configNode = document.getElementById('awa-home-shelf-bootstrap-config');
    var parsedConfig = null;
    var booted = false;
    var intentEvents = ['pointerdown', 'keydown', 'touchstart'];

    if (configNode && configNode.textContent) {
        try {
            parsedConfig = JSON.parse(configNode.textContent);
        } catch (error) {
            parsedConfig = null;
        }
    }

    function ensureShelfHeaderNavSlots() {
        document.querySelectorAll('.awa-carousel-section > .container').forEach(function (container) {
            var header = container.querySelector(':scope > .awa-section-header, :scope > header.awa-section-header');
            var placeholder;

            if (!header || header.querySelector('.awa-owl-nav')) {
                return;
            }

            placeholder = document.createElement('div');
            placeholder.className = 'awa-owl-nav awa-owl-nav--header-slot';
            placeholder.setAttribute('aria-hidden', 'true');
            placeholder.innerHTML =
                '<span class="awa-owl-nav__btn awa-owl-nav__btn--prev" aria-hidden="true"></span>' +
                '<span class="awa-owl-nav__btn awa-owl-nav__btn--next" aria-hidden="true"></span>';
            header.appendChild(placeholder);
        });
    }

    function dispatchBootstrapReady() {
        if (window.__awaBootstrapReady) {
            return;
        }
        window.__awaBootstrapReady = true;
        document.dispatchEvent(new CustomEvent('awa-bootstrap-ready'));
    }

    function getScriptSrc() {
        if (parsedConfig && typeof parsedConfig.jsSrc === 'string' && parsedConfig.jsSrc) {
            return parsedConfig.jsSrc;
        }
        var link = document.querySelector('link[data-awa-shelf-js]');
        return link ? (link.getAttribute('data-awa-shelf-js') || '') : '';
    }

    function appendShelfScript() {
        if (document.querySelector('script[data-awa-shelf-carousel-js="1"]')) {
            dispatchBootstrapReady();
            return true;
        }
        var src = getScriptSrc();
        if (!src) {
            return false;
        }
        var script = document.createElement('script');
        script.src = src;
        script.defer = true;
        script.setAttribute('data-awa-shelf-carousel-js', '1');
        script.onload = function () {
            dispatchBootstrapReady();
            var tries = 0;
            (function pollNavMount() {
                if (typeof window.__awaMountShelfNavInHeaders === 'function') {
                    window.__awaMountShelfNavInHeaders();
                    return;
                }
                tries += 1;
                if (tries < 40) {
                    window.setTimeout(pollNavMount, 100);
                }
            }());
        };
        script.onerror = dispatchBootstrapReady;
        (document.body || document.documentElement).appendChild(script);
        dispatchBootstrapReady();
        return true;
    }

    function runHomeBootstrapDefer(reason) {
        if (typeof window.__awaHomeBootstrapBoot !== 'function') {
            return;
        }
        // Carrossel da home só libera RequireJS/merged bundle quando há intenção acionável.
        window.__awaHomeBootstrapBoot(true);
    }

    function isMeaningfulIntent(event) {
        if (!event) {
            return false;
        }

        if (event.type === 'keydown') {
            return event.key === 'Enter' || event.key === ' ' || event.key === 'Spacebar';
        }

        return !!(event.target && event.target.closest && event.target.closest(
            'a, button, input, select, textarea, label, summary, [role="button"], [role="link"], .minicart-wrapper, .awa-header-account-prompt, #search_mini_form, .awa-hero-swiper__nav, .swiper-pagination-bullet, .awa-category-carousel__item, .awa-owl-nav__btn, .awa-carousel__viewport, .awa-shelf--carousel, .product-item, .item-product'
        ));
    }

    function boot(reason) {
        if (booted) {
            return;
        }
        booted = true;
        appendShelfScript();
        if (reason === 'intent') {
            runHomeBootstrapDefer(reason);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            ensureShelfHeaderNavSlots();
            boot('dom-ready');
        }, { once: true });
    } else {
        ensureShelfHeaderNavSlots();
        boot('already-ready');
    }

    intentEvents.forEach(function (eventName) {
        window.addEventListener(eventName, function (event) {
            if (!isMeaningfulIntent(event)) {
                return;
            }

            boot('intent');
        }, { passive: eventName !== 'keydown', capture: true, once: true });
    });
})(window, document);
