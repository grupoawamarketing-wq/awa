(function () {
    'use strict';

    if (window.__awaRound2HomeOwlTabsUiInit) {
        return;
    }
    window.__awaRound2HomeOwlTabsUiInit = true;

    var scheduled = false;
    var observer;

    function onReady(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
            return;
        }
        callback();
    }

    function isHomePage() {
        var body = document.body;
        if (!body) {
            return false;
        }

        return body.classList.contains('cms-index-index')
            || body.classList.contains('cms-home')
            || body.classList.contains('cms-homepage_ayo_home5');
    }

    function normalizeText(value) {
        return (value || '').replace(/\s+/g, ' ').trim();
    }

    function setAttrIfMissing(el, name, value) {
        if (!el || !value || el.getAttribute(name)) {
            return;
        }

        el.setAttribute(name, value);
    }

    function syncTabLabels(root) {
        var selector = '.list-tab-product .tab-title-link, .tab_product .tab-title-item, .tab_product ul.tabs li';
        root.querySelectorAll(selector).forEach(function (el) {
            var text = normalizeText(el.textContent);
            if (!text) {
                return;
            }

            setAttrIfMissing(el, 'title', text);

            if (!el.getAttribute('aria-label')) {
                el.setAttribute('aria-label', 'Selecionar aba ' + text);
            }

            if (el.tagName !== 'A' && !el.getAttribute('tabindex')) {
                el.setAttribute('tabindex', '0');
            }
        });
    }

    function syncOwlControls(root) {
        root.querySelectorAll('.list-tab-product .owl-nav button, .tab_product .owl-nav button').forEach(function (btn) {
            var isPrev = btn.classList.contains('owl-prev');
            var isNext = btn.classList.contains('owl-next');
            var label;

            if (!isPrev && !isNext) {
                return;
            }

            label = isPrev ? 'Ver produtos anteriores' : 'Ver próximos produtos';

            setAttrIfMissing(btn, 'title', label);
            if (!btn.getAttribute('aria-label')) {
                btn.setAttribute('aria-label', label);
            }

            btn.setAttribute('aria-disabled', btn.classList.contains('disabled') ? 'true' : 'false');
        });
    }

    function syncHeroFallback(root) {
        var heroSelector = '.top-home-content .banner-slider.banner-slider2 .wrapper_slider .owl';

        root.querySelectorAll(heroSelector).forEach(function (owlNode) {
            var wrapper = owlNode.closest('.wrapper_slider');
            var items = owlNode.querySelectorAll('.banner_item');
            var isInitialized = owlNode.classList.contains('owl-carousel')
                || owlNode.classList.contains('owl-loaded')
                || !!owlNode.querySelector('.owl-wrapper, .owl-stage');
            var i;

            if (!items.length) {
                return;
            }

            owlNode.setAttribute('data-awa-hero-carousel', 'true');
            owlNode.classList.toggle('awa-hero-fallback-ready', !isInitialized);

            if (wrapper) {
                wrapper.classList.toggle('awa-hero-fallback-active', !isInitialized);
            }

            for (i = 0; i < items.length; i += 1) {
                var item = items[i];
                var image = item.querySelector('img');
                var isPrimary = i === 0;

                if (!item) {
                    continue;
                }

                item.classList.toggle('awa-hero-fallback-primary', isPrimary);
                item.classList.toggle('awa-hero-fallback-secondary', !isPrimary);
                item.setAttribute('aria-hidden', (!isInitialized && !isPrimary) ? 'true' : 'false');

                if (!image) {
                    continue;
                }

                if (isPrimary) {
                    image.setAttribute('loading', 'eager');
                    image.setAttribute('fetchpriority', 'high');
                    image.setAttribute('decoding', 'sync');
                } else if (!isInitialized) {
                    if (!image.getAttribute('loading')) {
                        image.setAttribute('loading', 'lazy');
                    }
                    if (!image.getAttribute('decoding')) {
                        image.setAttribute('decoding', 'async');
                    }
                }
            }
        });
    }

    function apply(root) {
        syncHeroFallback(root || document);
        syncTabLabels(root || document);
        syncOwlControls(root || document);
    }

    function scheduleApply() {
        if (scheduled) {
            return;
        }

        scheduled = true;
        window.requestAnimationFrame(function () {
            scheduled = false;
            apply(document);
        });
    }

    onReady(function () {
        var pageWrapper;

        if (!isHomePage()) {
            return;
        }

        apply(document);

        if (!window.MutationObserver) {
            return;
        }

        pageWrapper = document.querySelector('.page-wrapper');
        if (!pageWrapper) {
            return;
        }

        observer = new MutationObserver(function () {
            scheduleApply();
        });

        observer.observe(pageWrapper, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['class']
        });
    });
}());
