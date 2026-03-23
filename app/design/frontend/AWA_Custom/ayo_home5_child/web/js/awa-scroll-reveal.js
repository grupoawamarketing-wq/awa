/**
 * AWA Scroll Reveal — IntersectionObserver-based reveal animations.
 *
 * Magento 2 AMD module. Loaded via data-mage-init or x-magento-init.
 *
 * Usage:
 *   Add class "awa-reveal" to any element you want to animate on scroll.
 *   Variants: "awa-reveal--scale", "awa-reveal--left"
 *   Stagger children: add "awa-reveal-stagger" to parent.
 *
 * The module also auto-discovers common Magento/Ayo sections and
 * applies reveal if they don't already have it (progressive enhancement).
 */
define([], function () {
    'use strict';

    var REVEALED_CLASS = 'awa-revealed';
    var REVEAL_SELECTOR = '.awa-reveal, .awa-reveal-stagger';
    var THRESHOLD = 0.12;
    var ROOT_MARGIN = '0px 0px -40px 0px';

    /* Sections auto-tagged for reveal (homepage, PLP, footer) */
    var AUTO_REVEAL_SELECTORS = [
        '.awa-home-section',
        '.awa-carousel-section',
        '.awa-footer-trust-bar',
        '.awa-footer-brands',
        '.awa-category-carousel__viewport',
        '.rokan-product-heading',
        '.block_cat',
        '.super-deal',
        '.velaNewsletterFooter'
    ];

    var AUTO_STAGGER_SELECTORS = [
        '.awa-footer-trust-grid',
        '.products-grid .product-items',
        '.awa-category-carousel__track'
    ];

    function prefersReducedMotion() {
        return window.matchMedia &&
            window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    function autoTag() {
        var i, elements, el;

        for (i = 0; i < AUTO_REVEAL_SELECTORS.length; i++) {
            elements = document.querySelectorAll(AUTO_REVEAL_SELECTORS[i]);
            for (var j = 0; j < elements.length; j++) {
                el = elements[j];
                if (!el.classList.contains('awa-reveal') &&
                    !el.classList.contains('awa-reveal-stagger') &&
                    !el.classList.contains(REVEALED_CLASS)) {
                    el.classList.add('awa-reveal');
                }
            }
        }

        for (i = 0; i < AUTO_STAGGER_SELECTORS.length; i++) {
            elements = document.querySelectorAll(AUTO_STAGGER_SELECTORS[i]);
            for (var k = 0; k < elements.length; k++) {
                el = elements[k];
                if (!el.classList.contains('awa-reveal-stagger') &&
                    !el.classList.contains(REVEALED_CLASS)) {
                    el.classList.add('awa-reveal-stagger');
                }
            }
        }
    }

    function initObserver() {
        if (!('IntersectionObserver' in window)) {
            /* Fallback: show all immediately */
            var all = document.querySelectorAll(REVEAL_SELECTOR);
            for (var i = 0; i < all.length; i++) {
                all[i].classList.add(REVEALED_CLASS);
            }
            return;
        }

        var observer = new IntersectionObserver(function (entries) {
            for (var i = 0; i < entries.length; i++) {
                if (entries[i].isIntersecting) {
                    entries[i].target.classList.add(REVEALED_CLASS);
                    observer.unobserve(entries[i].target);
                }
            }
        }, {
            threshold: THRESHOLD,
            rootMargin: ROOT_MARGIN
        });

        var targets = document.querySelectorAll(REVEAL_SELECTOR);
        for (var j = 0; j < targets.length; j++) {
            /* Skip already-visible above-fold elements */
            var rect = targets[j].getBoundingClientRect();
            if (rect.top < window.innerHeight * 0.85 && rect.top >= 0) {
                targets[j].classList.add(REVEALED_CLASS);
            } else {
                observer.observe(targets[j]);
            }
        }
    }

    return function () {
        if (prefersReducedMotion()) {
            /* Show everything immediately, no animations */
            return;
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () {
                autoTag();
                initObserver();
            });
        } else {
            autoTag();
            initObserver();
        }
    };
});
