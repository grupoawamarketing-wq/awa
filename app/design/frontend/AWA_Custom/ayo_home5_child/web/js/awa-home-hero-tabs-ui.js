(function () {
    'use strict';

    if (window.__awaHomeHeroTabsUiInit || window.__awaRound2HomeOwlTabsUiInit) {
        return;
    }
    window.__awaHomeHeroTabsUiInit = true;
    window.__awaRound2HomeOwlTabsUiInit = true;

    function setAttributeIfMissing(element, attributeName, value) {
        if (!element || !value || element.getAttribute(attributeName)) {
            return;
        }

        element.setAttribute(attributeName, value);
    }

    function normalizeHeroFallback(root) {
        var scope = root || document;
        var carousels = scope.querySelectorAll(
            '.top-home-content .banner-slider.banner-slider2 .wrapper_slider .awa-hero-swiper'
        );
        var i;
        var carousel;
        var wrapper;
        var slides;
        var isHeroReady;
        var slideIndex;
        var slide;
        var image;
        var isPrimary;

        for (i = 0; i < carousels.length; i += 1) {
            carousel = carousels[i];
            wrapper = carousel.closest('.wrapper_slider');
            slides = carousel.querySelectorAll('.banner_item');
            isHeroReady = carousel.classList.contains('swiper-initialized') ||
                carousel.classList.contains('awa-hero-swiper-ready');

            if (!slides.length) {
                continue;
            }

            carousel.setAttribute('data-awa-hero-carousel', 'true');
            carousel.classList.toggle('awa-hero-fallback-ready', !isHeroReady);

            if (wrapper) {
                wrapper.classList.toggle('awa-hero-fallback-active', !isHeroReady);
            }

            for (slideIndex = 0; slideIndex < slides.length; slideIndex += 1) {
                slide = slides[slideIndex];
                image = slide.querySelector('img');
                isPrimary = slideIndex === 0;

                slide.classList.toggle('awa-hero-fallback-primary', isPrimary);
                slide.classList.toggle('awa-hero-fallback-secondary', !isPrimary);
                slide.setAttribute('aria-hidden', (isHeroReady || isPrimary) ? 'false' : 'true');

                if (!image) {
                    continue;
                }

                if (isPrimary) {
                    image.setAttribute('loading', 'eager');
                    image.setAttribute('fetchpriority', 'high');
                    image.setAttribute('decoding', 'async');
                } else if (!isHeroReady) {
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

    /** A11y em setas de abas Rokanthemes (markup legado .owl-nav). */
    function normalizeTabNavButtons(root) {
        var scope = root || document;
        var navButtons = scope.querySelectorAll(
            '.list-tab-product .owl-nav button, .tab_product .owl-nav button, ' +
            '.list-tab-product .awa-carousel__nav button, .tab_product .awa-carousel__nav button'
        );
        var i;
        var button;
        var isPrev;
        var isNext;
        var label;

        for (i = 0; i < navButtons.length; i += 1) {
            button = navButtons[i];
            isPrev = button.classList.contains('owl-prev') ||
                button.classList.contains('awa-carousel__arrow--prev');
            isNext = button.classList.contains('owl-next') ||
                button.classList.contains('awa-carousel__arrow--next');

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
        normalizeTabNavButtons(root);
    }

    window.addEventListener('load', function () {
        var body = document.body;

        if (!body ||
            (!body.classList.contains('cms-index-index') &&
             !body.classList.contains('cms-home') &&
             !body.classList.contains('cms-homepage_ayo_home5'))) {
            return;
        }

        setTimeout(function () { applyHomeAdjustments(document); }, 0);
    }, { once: true });
}());
