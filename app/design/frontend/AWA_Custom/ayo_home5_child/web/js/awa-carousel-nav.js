/**
 * AWA Motos — Carousel Navigation Injector & OWL v1 Initializer
 *
 * 1. Initializes OWL v1 on .rokan-bestseller carousels where the
 *    Rokanthemes JS failed (broken MARKER_SRC selector bug).
 * 2. Injects prev/next arrows into OWL v1 carousels that were initialized
 *    without navigation (hot-deal-slide, productTabContent, bestseller).
 * 3. Adds progress bar indicator below multi-item carousels.
 *
 * OWL v1 API: $el.trigger('owl.prev'), $el.trigger('owl.next')
 */
(function () {
    'use strict';

    if (window.__awaCarouselNavInit) {
        return;
    }
    window.__awaCarouselNavInit = true;

    var SVG_PREV = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>';
    var SVG_NEXT = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 6 15 12 9 18"/></svg>';

    function buildNavHtml() {
        return (
            '<div class="awa-owl-nav">' +
            '<button type="button" class="awa-owl-nav__btn awa-owl-nav__btn--prev" aria-label="Anterior">' + SVG_PREV + '</button>' +
            '<button type="button" class="awa-owl-nav__btn awa-owl-nav__btn--next" aria-label="Próximo">' + SVG_NEXT + '</button>' +
            '</div>'
        );
    }

    function buildProgressHtml() {
        return '<div class="awa-owl-progress"><div class="awa-owl-progress__bar"></div></div>';
    }

    function injectNav(carouselEl, anchorEl) {
        if (!carouselEl || !anchorEl) {
            return;
        }
        if (anchorEl.querySelector('.awa-owl-nav')) {
            return;
        }

        var navFrag = document.createElement('div');
        navFrag.innerHTML = buildNavHtml();
        var nav = navFrag.firstChild;

        anchorEl.appendChild(nav);

        nav.querySelector('.awa-owl-nav__btn--prev').addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (typeof jQuery !== 'undefined') {
                jQuery(carouselEl).trigger('owl.prev');
            }
        });

        nav.querySelector('.awa-owl-nav__btn--next').addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (typeof jQuery !== 'undefined') {
                jQuery(carouselEl).trigger('owl.next');
            }
        });
    }

    function injectProgress(carouselEl, anchorEl) {
        if (!carouselEl || !anchorEl) {
            return;
        }
        if (anchorEl.querySelector('.awa-owl-progress')) {
            return;
        }

        var progFrag = document.createElement('div');
        progFrag.innerHTML = buildProgressHtml();
        var progress = progFrag.firstChild;

        anchorEl.appendChild(progress);

        updateProgress(carouselEl, progress);
    }

    function updateProgress(carouselEl, progressEl) {
        if (!carouselEl || !progressEl) {
            return;
        }

        var bar = progressEl.querySelector('.awa-owl-progress__bar');
        if (!bar) {
            return;
        }

        var wrapper = carouselEl.querySelector('.owl-wrapper');
        var items = carouselEl.querySelectorAll('.owl-item');
        if (!wrapper || !items.length) {
            return;
        }

        var containerWidth = carouselEl.querySelector('.owl-wrapper-outer');
        if (!containerWidth) {
            return;
        }

        var visibleWidth = containerWidth.offsetWidth;
        var totalWidth = wrapper.scrollWidth || wrapper.offsetWidth;
        if (totalWidth <= visibleWidth) {
            bar.style.width = '100%';
            return;
        }

        var transform = wrapper.style.transform || wrapper.style.webkitTransform || '';
        var match = transform.match(/translate(?:3d)?\(\s*(-?[\d.]+)/);
        var scrollLeft = match ? Math.abs(parseFloat(match[1])) : 0;

        var pct = Math.min(100, Math.max(5, ((scrollLeft + visibleWidth) / totalWidth) * 100));
        bar.style.width = pct + '%';
    }

    /**
     * Equalize min-height of all .product-item cards in a carousel so cards
     * in the same row have consistent heights regardless of product name length.
     */
    function equalizeCardHeights(carouselEl) {
        if (!carouselEl) { return; }
        var cards = carouselEl.querySelectorAll('.owl-item .product-item');
        if (!cards.length) { return; }
        var i;
        for (i = 0; i < cards.length; i++) { cards[i].style.minHeight = ''; }
        var maxH = 0;
        for (i = 0; i < cards.length; i++) {
            var h = cards[i].offsetHeight;
            if (h > maxH) { maxH = h; }
        }
        if (maxH > 0) {
            for (i = 0; i < cards.length; i++) { cards[i].style.minHeight = maxH + 'px'; }
        }
    }

    /**
     * Main init — initialize broken carousels, scan and inject nav.
     */
    function init() {
        var i, carousel, anchor;

        // Step 1: Initialize OWL on .rokan-bestseller carousels.
        // Rokanthemes bestseller.phtml has a broken jQuery selector
        // (".rokan-bestseller MARKER_SRC_77 .owl" — uses tag selector
        // that never matches) so OWL never inits on these elements.
        if (typeof jQuery !== 'undefined' && jQuery.fn.owlCarousel) {
            var uninitOwls = document.querySelectorAll('.rokan-bestseller ul.owl:not(.owl-carousel)');
            for (i = 0; i < uninitOwls.length; i++) {
                jQuery(uninitOwls[i]).owlCarousel({
                    lazyLoad: true,
                    autoPlay: false,
                    items: 4,
                    itemsDesktop: [1199, 4],
                    itemsDesktopSmall: [980, 3],
                    itemsTablet: [768, 2],
                    itemsMobile: [479, 1],
                    slideSpeed: 500,
                    paginationSpeed: 500,
                    rewindSpeed: 500,
                    navigation: false,
                    stopOnHover: true,
                    pagination: false,
                    scrollPerPage: true
                });
            }
        }

        // Step 2: Inject nav on all carousel types (deferred for OWL DOM).
        setTimeout(function () {
            var j, el, anchor2;

            // Bestseller carousels
            var bsCarousels = document.querySelectorAll('.rokan-bestseller .owl-carousel');
            for (j = 0; j < bsCarousels.length; j++) {
                el = bsCarousels[j];
                anchor2 = el.closest('.rokan-bestseller') || el.parentElement;
                if (anchor2) {
                    anchor2.style.position = 'relative';
                    injectNav(el, anchor2);
                    injectProgress(el, anchor2);
                    equalizeCardHeights(el);
                }
            }

            // Newproduct carousels
            var npCarousels = document.querySelectorAll('.rokan-newproduct .owl-carousel');
            for (j = 0; j < npCarousels.length; j++) {
                el = npCarousels[j];
                anchor2 = el.closest('.rokan-newproduct') || el.parentElement;
                if (anchor2) {
                    anchor2.style.position = 'relative';
                    injectNav(el, anchor2);
                    injectProgress(el, anchor2);
                    equalizeCardHeights(el);
                }
            }
        }, 200);

        // Product Tabs carousel — deferred to 1500ms because Rokanthemes tab JS
        // initialises OWL after bestseller/newproduct, causing waitForOwl to fire
        // init() before productTabContent is ready.
        setTimeout(function () {
            var tabCarousels = document.querySelectorAll('.productTabContent.owl-carousel');
            for (var t = 0; t < tabCarousels.length; t++) {
                var tabEl = tabCarousels[t];
                var tabAnchor = tabEl.closest('.tab_content') || tabEl.closest('.list-tab-product') || tabEl.parentElement;
                if (tabAnchor && !tabAnchor.querySelector('.awa-owl-nav')) {
                    tabAnchor.style.position = 'relative';
                    injectNav(tabEl, tabAnchor);
                    injectProgress(tabEl, tabAnchor);
                }
            }

            // Hot Deals carousel — deferred 1500ms same as productTabContent (OWL late init)
            var hdCarousels = document.querySelectorAll('.hot-deal-slide.owl-carousel');
            for (var hd = 0; hd < hdCarousels.length; hd++) {
                var hdEl = hdCarousels[hd];
                var hdAnchor = hdEl.closest('.hot-deal-tab-slider') || hdEl.closest('.hot-deal') || hdEl.parentElement;
                if (hdAnchor && !hdAnchor.querySelector('.awa-owl-nav')) {
                    hdAnchor.style.position = 'relative';
                    injectNav(hdEl, hdAnchor);
                    injectProgress(hdEl, hdAnchor);
                }
            }
        }, 1500);

        // Listen for OWL v1 transitions to update progress bars
        if (typeof jQuery !== 'undefined') {
            jQuery('.hot-deal-slide.owl-carousel, .productTabContent.owl-carousel, .rokan-bestseller .owl-carousel, .rokan-newproduct .owl-carousel').on('owl.afterMove owl.afterUpdate', function () {
                var owlEl = this;
                var container = owlEl.closest('.hot-deal-tab-slider') || owlEl.closest('.hot-deal') || owlEl.closest('.tab_content') || owlEl.closest('.list-tab-product') || owlEl.closest('.rokan-bestseller') || owlEl.closest('.rokan-newproduct') || owlEl.parentElement;
                if (container) {
                    var progressBar = container.querySelector('.awa-owl-progress');
                    if (progressBar) {
                        updateProgress(owlEl, progressBar);
                    }
                }
            });
        }
    }

    function waitForOwl(maxAttempts) {
        var attempts = 0;
        var timer = setInterval(function () {
            attempts++;
            var ready = document.querySelector('.hot-deal-slide .owl-wrapper-outer') ||
                        document.querySelector('.productTabContent .owl-wrapper-outer') ||
                        document.querySelector('.rokan-newproduct .owl-wrapper-outer') ||
                        (typeof jQuery !== 'undefined' && jQuery.fn.owlCarousel);
            if (ready || attempts >= maxAttempts) {
                clearInterval(timer);
                init();
            }
        }, 500);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            waitForOwl(20);
        });
    } else {
        waitForOwl(20);
    }
})();
