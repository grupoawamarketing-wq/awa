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

    let REDUCED_MOTION_QUERY = '(prefers-reduced-motion: reduce)';

    function prefersReducedMotion() {
        return !!(window.matchMedia && window.matchMedia(REDUCED_MOTION_QUERY).matches);
    }

    function owlSpeedOptions() {
        if (prefersReducedMotion()) {
            return {
                slideSpeed: 0,
                paginationSpeed: 0,
                rewindSpeed: 0,
                autoPlay: false
            };
        }

        return {
            slideSpeed: 500,
            paginationSpeed: 500,
            rewindSpeed: 500,
            autoPlay: false
        };
    }

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
     * Equalize min-height of visible row cards (.content-item-product AWA template).
     */
    function equalizeCardHeights(carouselEl) {
        if (!carouselEl) { return; }

        let cards = carouselEl.querySelectorAll(
            '.owl-item.active .content-item-product, .owl-item:not(.cloned) .content-item-product'
        );

        if (!cards.length) {
            cards = carouselEl.querySelectorAll(
                '.owl-item .content-item-product, .owl-item .awa-carousel-card-slot .content-item-product'
            );
        }

        if (!cards.length) { return; }

        let i;
        let outer = carouselEl.querySelector('.owl-wrapper-outer');
        let viewLeft = 0;
        let viewRight = outer ? outer.offsetWidth + 100 : 0;

        if (outer) {
            viewLeft = outer.getBoundingClientRect().left - 80;
            viewRight = outer.getBoundingClientRect().right + 80;
        }

        let visible = [];
        for (i = 0; i < cards.length; i++) {
            let card = cards[i];
            card.style.minHeight = '';
            if (!outer) {
                visible.push(card);
                continue;
            }
            let rect = card.getBoundingClientRect();
            if (rect.width > 0 && rect.right >= viewLeft && rect.left <= viewRight) {
                visible.push(card);
            }
        }

        if (!visible.length) { visible = Array.prototype.slice.call(cards, 0, 8); }

        let maxH = 0;
        for (i = 0; i < visible.length; i++) {
            let h = visible[i].offsetHeight;
            if (h > maxH) { maxH = h; }
        }

        if (maxH > 0) {
            for (i = 0; i < visible.length; i++) {
                visible[i].style.minHeight = maxH + 'px';
            }
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

    let OWL_RAIL_OPTIONS = {
        lazyLoad: true,
        items: 2,
        itemsDesktop: [1440, 5],
        itemsDesktopSmall: [1024, 4],
        itemsTablet: [768, 3],
        itemsMobile: [479, 2],
        navigation: false,
        stopOnHover: true,
        pagination: false,
        scrollPerPage: true
    };

    function containerHasWidth(el, minWidth) {
        if (!el) {
            return false;
        }
        return el.getBoundingClientRect().width >= (minWidth || 200);
    }

    /**
     * Executa callback quando o container tem largura útil (ResizeObserver; fallback poll).
     */
    function whenContainerReady(el, minWidth, callback, timeoutMs) {
        let min = minWidth || 200;
        let timeout = timeoutMs || 5000;

        if (!el || typeof callback !== 'function') {
            return;
        }

        if (containerHasWidth(el, min)) {
            callback();
            return;
        }

        if (typeof ResizeObserver === 'undefined') {
            let attempts = 0;
            let poll = setInterval(function () {
                attempts++;
                if (containerHasWidth(el, min) || attempts > 25) {
                    clearInterval(poll);
                    if (containerHasWidth(el, min)) {
                        callback();
                    }
                }
            }, 200);
            return;
        }

        let settled = false;
        let finish = function () {
            if (settled || !containerHasWidth(el, min)) {
                return;
            }
            settled = true;
            observer.disconnect();
            clearTimeout(fallbackTimer);
            callback();
        };
        let observer = new ResizeObserver(finish);
        let fallbackTimer = setTimeout(function () {
            if (settled) {
                return;
            }
            settled = true;
            observer.disconnect();
            if (containerHasWidth(el, min)) {
                callback();
            }
        }, timeout);

        observer.observe(el);
        finish();
    }

    let repairRailsTimer = null;

    function scheduleRepairNarrowOwlRails() {
        clearTimeout(repairRailsTimer);
        repairRailsTimer = setTimeout(repairNarrowOwlRails, 150);
    }

    function reloadOwlCarousel(owlUl) {
        if (typeof jQuery === 'undefined' || !owlUl) {
            return;
        }
        let api = jQuery(owlUl).data('owlCarousel');
        if (api && typeof api.reload === 'function') {
            api.reload();
            jQuery(owlUl).trigger('owl.update').addClass('owl-loaded');
            scheduleEqualize(owlUl);
        }
    }

    function getCarouselSectionLabel(anchor) {
        if (!anchor) {
            return 'Carrossel de produtos';
        }

        let titleEl = anchor.querySelector(
            '.awa-section-header__title, .rokan-product-heading h2, header.awa-section-header h2, .group-title1 h2'
        );
        let text = titleEl && titleEl.textContent ? titleEl.textContent.trim() : '';

        return text ? text.slice(0, 80) : 'Carrossel de produtos';
    }

    function announceActiveCarouselItem(owlUl, liveEl) {
        if (!owlUl || !liveEl) {
            return;
        }

        let item = owlUl.querySelector('.owl-item.active') ||
            owlUl.querySelector('.owl-item.synced') ||
            owlUl.querySelector('.owl-item');

        if (!item) {
            return;
        }

        let nameEl = item.querySelector('.product-name, .product-item-name, .product-name a');
        let label = nameEl && nameEl.textContent ? nameEl.textContent.trim().replace(/\s+/g, ' ') : '';

        if (label) {
            liveEl.textContent = label.slice(0, 120);
        }
    }

    function setupCarouselA11y(owlUl, anchor) {
        if (!owlUl || owlUl.dataset.awaA11y === '1') {
            return;
        }

        owlUl.dataset.awaA11y = '1';
        owlUl.setAttribute('role', 'region');
        owlUl.setAttribute('aria-roledescription', 'carrossel');
        owlUl.setAttribute('aria-label', getCarouselSectionLabel(anchor));

        let live = anchor.querySelector('.awa-carousel-live');
        if (!live) {
            live = document.createElement('div');
            live.className = 'awa-carousel-live';
            live.setAttribute('aria-live', 'polite');
            live.setAttribute('aria-atomic', 'true');
            anchor.appendChild(live);
        }

        let cards = owlUl.querySelectorAll('.content-item-product, .item-product');
        for (let i = 0; i < cards.length; i++) {
            cards[i].classList.add('awa-carousel-ready');
        }
        owlUl.classList.add('awa-carousel-ready');

        announceActiveCarouselItem(owlUl, live);

        if (typeof jQuery !== 'undefined') {
            jQuery(owlUl).off('owl.afterMove.awaA11y').on('owl.afterMove.awaA11y', function () {
                announceActiveCarouselItem(owlUl, live);
                updateProgress(owlUl, anchor.querySelector('.awa-owl-progress'));
            });
        }
    }

    function wireRailChrome(owlUl, options) {
        let opts = options || {};
        let anchor2 = owlUl.closest(opts.anchorClosest || '.rokan-bestseller, .rokan-newproduct') ||
            owlUl.parentElement;

        if (!anchor2 || owlUl.dataset.awaRailChrome === '1') {
            return;
        }

        anchor2.style.position = 'relative';
        owlUl.dataset.awaRailChrome = '1';

        if (opts.skipNavIfOwlNav && owlUl.querySelector('.owl-prev')) {
            injectProgress(owlUl, anchor2);
            setupCarouselA11y(owlUl, anchor2);
            if (opts.equalize !== false) {
                scheduleEqualize(owlUl);
            }
            return;
        }

        if (!anchor2.querySelector('.awa-owl-nav')) {
            injectNav(owlUl, anchor2);
        }
        injectProgress(owlUl, anchor2);
        setupCarouselA11y(owlUl, anchor2);
        if (opts.equalize !== false) {
            scheduleEqualize(owlUl);
        }
    }

    /**
     * Injeta nav/progress quando a secção entra no viewport (substitui timeouts fixos).
     */
    function whenSectionVisible(section, callback, rootMargin) {
        if (!section) {
            return;
        }

        let fired = false;

        function runOnce() {
            if (fired) {
                return;
            }
            fired = true;
            if (observer) {
                observer.disconnect();
            }
            clearTimeout(fallbackTimer);
            callback();
        }

        if (typeof IntersectionObserver === 'undefined') {
            setTimeout(runOnce, 300);
            return;
        }

        let margin = rootMargin || '120px 0px';
        let observer = null;
        let fallbackTimer = setTimeout(runOnce, 6500);

        observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) {
                    return;
                }
                runOnce();
            });
        }, { rootMargin: margin, threshold: 0.01 });

        observer.observe(section);
    }

    function scanCarouselsInSection(section, carouselSelector, wireOptions) {
        if (!section) {
            return;
        }

        let carousels = section.querySelectorAll(carouselSelector);

        for (let c = 0; c < carousels.length; c++) {
            wireRailChrome(carousels[c], wireOptions);
        }
    }

    function bindDeferredCarouselChrome() {
        document.querySelectorAll('.awa-carousel-section').forEach(function (section) {
            whenSectionVisible(section, function () {
                scanCarouselsInSection(section, 'ul.owl.owl-carousel, .owl-carousel', { equalize: true });
            }, '100px 0px');
        });

        document.querySelectorAll('.rokan-bestseller').forEach(function (section) {
            whenSectionVisible(section, function () {
                scanCarouselsInSection(section, '.owl-carousel', { equalize: true });
            }, '80px 0px');
        });

        document.querySelectorAll('.rokan-newproduct').forEach(function (section) {
            whenSectionVisible(section, function () {
                scanCarouselsInSection(section, '.owl-carousel', {
                    skipNavIfOwlNav: true,
                    equalize: true
                });
            });
        });

        document.querySelectorAll('.list-tab-product, .categorytab-container').forEach(function (section) {
            whenSectionVisible(section, function () {
                scanCarouselsInSection(section, '.productTabContent.owl-carousel', {
                    anchorClosest: '.tab_content, .list-tab-product, .categorytab-container',
                    equalize: true
                });
            });
        });

        document.querySelectorAll('.hot-deal, .hot-deal-tab-slider').forEach(function (section) {
            whenSectionVisible(section, function () {
                scanCarouselsInSection(section, '.hot-deal-slide.owl-carousel', {
                    anchorClosest: '.hot-deal-tab-slider, .hot-deal',
                    equalize: true
                });
            });
        });

        document.querySelectorAll('.awa-carousel-section--super-offers').forEach(function (section) {
            whenSectionVisible(section, function () {
                scanCarouselsInSection(section, '.awa-super-offers-carousel .owl-carousel, .hot-deal-slide.owl-carousel', {
                    anchorClosest: '.awa-super-offers-carousel, .hot-deal-tab-slider, .hot-deal, .awa-carousel-section--super-offers',
                    equalize: true
                });
            }, '80px 0px');
        });

        document.querySelectorAll('.top-home-content--trust-and-offers').forEach(function (section) {
            whenSectionVisible(section, function () {
                scanCarouselsInSection(section, '.hot-deal-slide.owl-carousel', {
                    anchorClosest: '.hot-deal-tab-slider, .hot-deal, .top-home-content--trust-and-offers',
                    equalize: true
                });
            }, '80px 0px');
        });
    }

    /**
     * Init OWL only when the rail container has real width (evita slides de ~2px).
     */
    function owlItemWidth(owlUl) {
        let item = owlUl && owlUl.querySelector('.owl-item');
        return item ? item.getBoundingClientRect().width : 0;
    }

    function repairNarrowOwlRails() {
        let rails = document.querySelectorAll(
            '.rokan-bestseller ul.owl.owl-carousel, .rokan-newproduct ul.owl.owl-carousel'
        );
        for (let r = 0; r < rails.length; r++) {
            if (owlItemWidth(rails[r]) < 80) {
                reloadOwlCarousel(rails[r]);
            }
        }
    }

    function initProductRailOwl(owlUl) {
        if (!owlUl) {
            return;
        }
        if (typeof jQuery === 'undefined' || !jQuery.fn.owlCarousel) {
            return;
        }

        if (owlUl.classList.contains('owl-carousel')) {
            if (owlItemWidth(owlUl) < 80) {
                reloadOwlCarousel(owlUl);
            }
            return;
        }

        let anchor = owlUl.closest('.rokan-bestseller, .rokan-newproduct') || owlUl.parentElement;
        let widthRoot = anchor || owlUl;

        function runInit() {
            if (owlUl.classList.contains('owl-carousel')) {
                reloadOwlCarousel(owlUl);
                return;
            }
            jQuery(owlUl).owlCarousel(Object.assign({}, OWL_RAIL_OPTIONS, owlSpeedOptions()));
            jQuery(owlUl).addClass('owl-loaded');
            setTimeout(function () { wireRailChrome(owlUl); }, 50);
        }

        if (containerHasWidth(widthRoot, 200)) {
            runInit();
            return;
        }

        if (typeof IntersectionObserver !== 'undefined' && anchor) {
            let observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) {
                        return;
                    }
                    observer.disconnect();
                    whenContainerReady(widthRoot, 200, runInit);
                });
            }, { rootMargin: '80px 0px', threshold: 0.05 });
            observer.observe(anchor);
            return;
        }

        whenContainerReady(widthRoot, 200, runInit);
    }

    /**
     * Main init — initialize broken carousels, scan and inject nav.
     */
    function init() {
        let i, carousel, anchor;

        // Step 1: OWL nos rails Mais Vendidos / Lançamentos (Rokan selector quebrado no template).
        if (typeof jQuery !== 'undefined' && jQuery.fn.owlCarousel) {
            let uninitOwls = document.querySelectorAll(
                '.awa-carousel-section .rokan-bestseller ul.owl:not(.owl-carousel), ' +
                '.awa-carousel-section .rokan-newproduct ul.owl:not(.owl-carousel)'
            );
            for (i = 0; i < uninitOwls.length; i++) {
                initProductRailOwl(uninitOwls[i]);
            }
        }

        if (window.requestAnimationFrame) {
            window.requestAnimationFrame(function () {
                window.requestAnimationFrame(repairNarrowOwlRails);
            });
        } else {
            setTimeout(repairNarrowOwlRails, 0);
        }
        setTimeout(repairNarrowOwlRails, 800);

        bindDeferredCarouselChrome();

        if (typeof MutationObserver !== 'undefined') {
            let chromeRoot = document.querySelector('.content-top-home, .ayo-home5-wrapper, main#maincontent');

            if (chromeRoot) {
                let chromeMoTimer = null;
                let chromeObserver = new MutationObserver(function () {
                    clearTimeout(chromeMoTimer);
                    chromeMoTimer = setTimeout(bindDeferredCarouselChrome, 250);
                });
                chromeObserver.observe(chromeRoot, { childList: true, subtree: true });
            }
        }

        // Listen for OWL v1 transitions to update progress bars.
        // Delegated on document so it captures carousels OWL-initialized
        // after init() runs (newproduct/hotdeal/productTab have 1200-1500ms delays).
        if (typeof jQuery !== 'undefined') {
            let progressRaf = null;
            jQuery(document).on('owl.afterMove owl.afterUpdate', '.owl-carousel', function () {
                let owlEl = this;

                if (owlItemWidth(owlEl) < 80) {
                    scheduleRepairNarrowOwlRails();
                }

                let container = owlEl.closest('.hot-deal-tab-slider') || owlEl.closest('.hot-deal') || owlEl.closest('.tab_content') || owlEl.closest('.list-tab-product') || owlEl.closest('.rokan-bestseller') || owlEl.closest('.rokan-newproduct') || owlEl.parentElement;
                if (!container) {
                    return;
                }
                let progressBar = container.querySelector('.awa-owl-progress');
                if (!progressBar) {
                    return;
                }
                if (progressRaf !== null) {
                    return;
                }
                progressRaf = window.requestAnimationFrame(function () {
                    progressRaf = null;
                    updateProgress(owlEl, progressBar);
                });
            });
        }
    }

    function waitForDeps(maxAttempts) {
        let attempts = 0;
        let max = maxAttempts || 25;

        function tick() {
            attempts++;
            if (typeof jQuery !== 'undefined' && jQuery.fn && jQuery.fn.owlCarousel) {
                init();
                return;
            }
            if (attempts < max) {
                setTimeout(tick, 100);
            }
        }

        tick();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            waitForDeps(30);
        });
    } else {
        waitForDeps(30);
    }
})();
