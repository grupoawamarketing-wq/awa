(function () {
    'use strict';

    if (window.__awaRound2HomeOwlTabsUiInit) {
        return;
    }
    window.__awaRound2HomeOwlTabsUiInit = true;

    function setAttributeIfMissing(element, attributeName, value) {
        if (!element || !value || element.getAttribute(attributeName)) {
            return;
        }

        element.setAttribute(attributeName, value);
    }

    function normalizeHeroFallback(root) {
        var scope = root || document;
        var carousels = scope.querySelectorAll('.top-home-content .banner-slider.banner-slider2 .wrapper_slider .owl');
        var i;
        var carousel;
        var wrapper;
        var slides;
        var isOwlReady;
        var slideIndex;
        var slide;
        var image;
        var isPrimary;

        for (i = 0; i < carousels.length; i += 1) {
            carousel = carousels[i];
            wrapper = carousel.closest('.wrapper_slider');
            slides = carousel.querySelectorAll('.banner_item');
            isOwlReady = carousel.classList.contains('owl-carousel') ||
                carousel.classList.contains('owl-loaded') ||
                !!carousel.querySelector('.owl-wrapper, .owl-stage');

            if (!slides.length) {
                continue;
            }

            carousel.setAttribute('data-awa-hero-carousel', 'true');
            carousel.classList.toggle('awa-hero-fallback-ready', !isOwlReady);

            if (wrapper) {
                wrapper.classList.toggle('awa-hero-fallback-active', !isOwlReady);
            }

            for (slideIndex = 0; slideIndex < slides.length; slideIndex += 1) {
                slide = slides[slideIndex];
                image = slide.querySelector('img');
                isPrimary = slideIndex === 0;

                slide.classList.toggle('awa-hero-fallback-primary', isPrimary);
                slide.classList.toggle('awa-hero-fallback-secondary', !isPrimary);
                slide.setAttribute('aria-hidden', (isOwlReady || isPrimary) ? 'false' : 'true');

                if (!image) {
                    continue;
                }

                if (isPrimary) {
                    image.setAttribute('loading', 'eager');
                    image.setAttribute('fetchpriority', 'high');
                    image.setAttribute('decoding', 'sync');
                } else if (!isOwlReady) {
                    if (!image.getAttribute('loading')) {
                        image.setAttribute('loading', 'lazy');
                    }
                    if (!image.getAttribute('decoding')) {
                        image.setAttribute('decoding', 'async');
                    }
                }
            }
        }
    }

    function normalizeTabsAccessibility(root) {
        var scope = root || document;
        var tabTriggers = scope.querySelectorAll(
            '.list-tab-product .tab-title-link, .tab_product .tab-title-item, .tab_product ul.tabs li'
        );
        var i;
        var trigger;
        var label;

        for (i = 0; i < tabTriggers.length; i += 1) {
            trigger = tabTriggers[i];
            label = (trigger.textContent || '').replace(/\s+/g, ' ').trim();

            if (!label) {
                continue;
            }

            setAttributeIfMissing(trigger, 'title', label);

            if (!trigger.getAttribute('aria-label')) {
                trigger.setAttribute('aria-label', 'Selecionar aba ' + label);
            }

            if (trigger.tagName !== 'A' && !trigger.getAttribute('tabindex')) {
                trigger.setAttribute('tabindex', '0');
            }
        }
    }

    function normalizeOwlNavButtons(root) {
        var scope = root || document;
        var navButtons = scope.querySelectorAll('.list-tab-product .owl-nav button, .tab_product .owl-nav button');
        var i;
        var button;
        var isPrev;
        var isNext;
        var label;

        for (i = 0; i < navButtons.length; i += 1) {
            button = navButtons[i];
            isPrev = button.classList.contains('owl-prev');
            isNext = button.classList.contains('owl-next');

            if (!isPrev && !isNext) {
                continue;
            }

            label = isPrev ? 'Ver produtos anteriores' : 'Ver proximos produtos';
            setAttributeIfMissing(button, 'title', label);

            if (!button.getAttribute('aria-label')) {
                button.setAttribute('aria-label', label);
            }

            button.setAttribute('aria-disabled', button.classList.contains('disabled') ? 'true' : 'false');
        }
    }

    function applyHomeAdjustments(root) {
        normalizeHeroFallback(root);
        normalizeTabsAccessibility(root);
        normalizeOwlNavButtons(root);
    }

    // AWA PERF v8: window.load only (Owl/Swiper guaranteed ready by then).
    // Split into 3 separate setTimeout(fn, 0) so each runs in its own macrotask.
    // Each piece stays < 50ms → zero TBT contribution per task.
    // normalizeHeroFallback scoped to .top-home-content (avoids full-DOM traversal).
    window.addEventListener('load', function () {
        var b = document.body;

        if (!b ||
            (!b.classList.contains('cms-index-index') &&
             !b.classList.contains('cms-home') &&
             !b.classList.contains('cms-homepage_ayo_home5'))) {
            return;
        }

        setTimeout(function () { normalizeHeroFallback(document); }, 0);
        setTimeout(function () { normalizeTabsAccessibility(document); }, 0);
        setTimeout(function () { normalizeOwlNavButtons(document); }, 0);
    }, { once: true });
}());
