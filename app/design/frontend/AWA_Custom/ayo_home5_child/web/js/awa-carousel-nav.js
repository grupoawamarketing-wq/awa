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

    let SVG_PREV = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>';
    let SVG_NEXT = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 6 15 12 9 18"/></svg>';

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

        let navFrag = document.createElement('div');
        navFrag.innerHTML = buildNavHtml();
        let nav = navFrag.firstChild;

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

        let progFrag = document.createElement('div');
        progFrag.innerHTML = buildProgressHtml();
        let progress = progFrag.firstChild;

        anchorEl.appendChild(progress);

        updateProgress(carouselEl, progress);
    }

    function updateProgress(carouselEl, progressEl) {
        if (!carouselEl || !progressEl) {
            return;
        }

        let bar = progressEl.querySelector('.awa-owl-progress__bar');
        if (!bar) {
            return;
        }

        let wrapper = carouselEl.querySelector('.owl-wrapper');
        let items = carouselEl.querySelectorAll('.owl-item');
        if (!wrapper || !items.length) {
            return;
        }

        let containerWidth = carouselEl.querySelector('.owl-wrapper-outer');
        if (!containerWidth) {
            return;
        }

        let visibleWidth = containerWidth.offsetWidth;
        let totalWidth = wrapper.scrollWidth || wrapper.offsetWidth;
        if (totalWidth <= visibleWidth) {
            bar.style.width = '100%';
            return;
        }

        let transform = wrapper.style.transform || wrapper.style.webkitTransform || '';
        let match = transform.match(/translate(?:3d)?\(\s*(-?[\d.]+)/);
        let scrollLeft = match ? Math.abs(parseFloat(match[1])) : 0;

        let pct = Math.min(100, Math.max(5, ((scrollLeft + visibleWidth) / totalWidth) * 100));
        bar.style.width = pct + '%';
    }

    /**
     * Equalize min-height of all .product-item cards in a carousel so cards
     * in the same row have consistent heights regardless of product name length.
     */
    function equalizeCardHeights(carouselEl) {
        if (!carouselEl) { return; }
        let cards = carouselEl.querySelectorAll('.owl-item .product-item');
        if (!cards.length) { return; }
        let i;
        for (i = 0; i < cards.length; i++) { cards[i].style.minHeight = ''; }
        let maxH = 0;
        for (i = 0; i < cards.length; i++) {
            let h = cards[i].offsetHeight;
            if (h > maxH) { maxH = h; }
        }
        if (maxH > 0) {
            for (i = 0; i < cards.length; i++) { cards[i].style.minHeight = maxH + 'px'; }
        }
    }

    /**
     * Defer equalizeCardHeights to idle time — avoids forced layout during critical path.
     * Uses requestIdleCallback with 3s timeout fallback (runs at idle or max 3s after call).
     */
    function scheduleEqualize(carouselEl) {
        let fn = function () { equalizeCardHeights(carouselEl); };
        if (typeof requestIdleCallback === 'function') {
            requestIdleCallback(fn, { timeout: 3000 });
        } else {
            setTimeout(fn, 3000);
        }
    }

    /**
     * Main init — initialize broken carousels, scan and inject nav.
     */
    function init() {
        let i, carousel, anchor;

        // Step 1: Initialize OWL on .rokan-bestseller carousels.
        // Rokanthemes bestseller.phtml has a broken jQuery selector
        // (".rokan-bestseller MARKER_SRC_77 .owl" — uses tag selector
        // that never matches) so OWL never inits on these elements.
        if (typeof jQuery !== 'undefined' && jQuery.fn.owlCarousel) {
            let uninitOwls = document.querySelectorAll('.rokan-bestseller ul.owl:not(.owl-carousel)');
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
            let j, el, anchor2;

            // Bestseller carousels
            let bsCarousels = document.querySelectorAll('.rokan-bestseller .owl-carousel');
            for (j = 0; j < bsCarousels.length; j++) {
                el = bsCarousels[j];
                anchor2 = el.closest('.rokan-bestseller') || el.parentElement;
                if (anchor2) {
                    anchor2.style.position = 'relative';
                    injectNav(el, anchor2);
                    injectProgress(el, anchor2);
                    scheduleEqualize(el);
                }
            }

            // Newproduct carousels — processed after bestseller
        }, 200);

        // Newproduct carousels deferred 1200ms: OWL lazy-inits only when visible,
        // so we need extra time for user to scroll past hero before OWL fires.
        setTimeout(function () {
            let j, el, anchor2;
            let npCarousels = document.querySelectorAll('.rokan-newproduct .owl-carousel');
            for (j = 0; j < npCarousels.length; j++) {
                el = npCarousels[j];
                anchor2 = el.closest('.rokan-newproduct') || el.parentElement;
                if (anchor2) {
                    anchor2.style.position = 'relative';
                    // Only inject awa-owl-nav if OWL has no built-in nav
                    if (!el.querySelector('.owl-prev')) {
                        injectNav(el, anchor2);
                    }
                    // Always inject progress bar if missing
                    injectProgress(el, anchor2);
                    scheduleEqualize(el);
                }
            }
        }, 1200);

        // Product Tabs carousel — deferred to 1500ms because Rokanthemes tab JS
        // initialises OWL after bestseller/newproduct, causing waitForOwl to fire
        // init() before productTabContent is ready.
        setTimeout(function () {
            let tabCarousels = document.querySelectorAll('.productTabContent.owl-carousel');
            for (let t = 0; t < tabCarousels.length; t++) {
                let tabEl = tabCarousels[t];
                let tabAnchor = tabEl.closest('.tab_content') || tabEl.closest('.list-tab-product') || tabEl.parentElement;
                if (tabAnchor && !tabAnchor.querySelector('.awa-owl-nav')) {
                    tabAnchor.style.position = 'relative';
                    injectNav(tabEl, tabAnchor);
                    injectProgress(tabEl, tabAnchor);
                }
            }

            // Hot Deals carousel — deferred 1500ms same as productTabContent (OWL late init)
            let hdCarousels = document.querySelectorAll('.hot-deal-slide.owl-carousel');
            for (let hd = 0; hd < hdCarousels.length; hd++) {
                let hdEl = hdCarousels[hd];
                let hdAnchor = hdEl.closest('.hot-deal-tab-slider') || hdEl.closest('.hot-deal') || hdEl.parentElement;
                if (hdAnchor && !hdAnchor.querySelector('.awa-owl-nav')) {
                    hdAnchor.style.position = 'relative';
                    injectNav(hdEl, hdAnchor);
                    injectProgress(hdEl, hdAnchor);
                }
            }
        }, 1500);

        // Listen for OWL v1 transitions to update progress bars.
        // Delegated on document so it captures carousels OWL-initialized
        // after init() runs (newproduct/hotdeal/productTab have 1200-1500ms delays).
        if (typeof jQuery !== 'undefined') {
            jQuery(document).on('owl.afterMove owl.afterUpdate', '.owl-carousel', function () {
                let owlEl = this;
                let container = owlEl.closest('.hot-deal-tab-slider') || owlEl.closest('.hot-deal') || owlEl.closest('.tab_content') || owlEl.closest('.list-tab-product') || owlEl.closest('.rokan-bestseller') || owlEl.closest('.rokan-newproduct') || owlEl.parentElement;
                if (container) {
                    let progressBar = container.querySelector('.awa-owl-progress');
                    if (progressBar) {
                        requestAnimationFrame(function () { updateProgress(owlEl, progressBar); });
                    }
                }
            });
        }
    }

    function waitForOwl(maxAttempts) {
        let attempts = 0;
        let timer = setInterval(function () {
            attempts++;
            let ready = document.querySelector('.hot-deal-slide .owl-wrapper-outer') ||
                        document.querySelector('.productTabContent .owl-wrapper-outer') ||
                        document.querySelector('.rokan-bestseller .owl-wrapper-outer') ||
                        document.querySelector('.rokan-newproduct .owl-wrapper-outer');
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
