/**
 * AWA Product Shelf Carousel — inicializador único para carrosséis de produto
 * Motor: Owl Carousel v1 (Rokanthemes)
 * Equal-height: CSS flex stretch (primary) + JS fallback pós-load de imagens
 */
(function () {
    'use strict';

    if (window.__awaShelfCarouselInit) {
        return;
    }
    window.__awaShelfCarouselInit = true;

    var OWL = {
        lazyLoad: true,
        items: 2,
        itemsDesktop: [1366, 5],
        itemsDesktopSmall: [1024, 4],
        itemsTablet: [768, 3],
        itemsMobile: [479, 2],
        navigation: false,
        stopOnHover: true,
        pagination: false,
        scrollPerPage: true
    };

    var SVG_PREV = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>';
    var SVG_NEXT = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 6 15 12 9 18"/></svg>';

    var SHELF_SELECTORS = [
        '.awa-shelf--carousel',
        '.awa-carousel-section .rokan-bestseller',
        '.awa-carousel-section .rokan-newproduct',
        '.rokan-bestseller.awa-shelf',
        '.rokan-newproduct.awa-shelf',
        '.hot-deal-tab-slider',
        '.awa-super-offers-carousel',
        '.block.related',
        '.block.upsell',
        '.awa-pdp-related',
        '.rokan-mostviewed',
        '.rokan-toprate',
        '.rokan-featured',
        '.rokan-featuredproduct',
        '.rokan-onsale',
        '.rokan-onsaleproduct',
        '.categorytab-container'
    ];

    var equalizeTimers = Object.create(null);

    function speedOptions() {
        var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        return reduced
            ? { slideSpeed: 0, paginationSpeed: 0, rewindSpeed: 0, autoPlay: false }
            : { slideSpeed: 500, paginationSpeed: 500, rewindSpeed: 500, autoPlay: false };
    }

    function qsa(sel, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(sel));
    }

    function containerHasWidth(el, min) {
        return el && el.getBoundingClientRect().width >= (min || 200);
    }

    function whenContainerReady(el, min, cb, timeoutMs) {
        if (!el || typeof cb !== 'function') {
            return;
        }
        if (containerHasWidth(el, min)) {
            cb();
            return;
        }
        var done = false;
        var finish = function () {
            if (done) {
                return;
            }
            done = true;
            if (observer) {
                observer.disconnect();
            }
            clearTimeout(timer);
            cb();
        };
        var observer = typeof ResizeObserver !== 'undefined'
            ? new ResizeObserver(function () {
                if (containerHasWidth(el, min)) {
                    finish();
                }
            })
            : null;
        var timer = setTimeout(finish, timeoutMs || 5000);
        if (observer) {
            observer.observe(el);
        }
    }

    function cardHeight(card) {
        return Math.round(card.getBoundingClientRect().height);
    }

    function getShelfCards(shelf) {
        return qsa(
            '.owl-item:not(.cloned) .awa-product-card, ' +
            '.owl-item:not(.cloned) .content-item-product, ' +
            '.owl-item:not(.cloned) .product-item-info.awa-product-card, ' +
            '.product_row .content-item-product.awa-product-card',
            shelf
        );
    }

    function getShelfSlides(shelf) {
        return qsa('.owl-item:not(.cloned)', shelf);
    }

    /**
     * Fallback JS — Owl items são independentes; equaliza slides + cards quando delta > threshold.
     */
    function equalizeCardsFallback(shelf) {
        if (!shelf) {
            return;
        }

        var cards = getShelfCards(shelf);
        var slides = getShelfSlides(shelf);
        if (!cards.length && !slides.length) {
            return;
        }

        var i;
        var heights = [];
        for (i = 0; i < cards.length; i++) {
            cards[i].style.minHeight = '';
        }
        for (i = 0; i < slides.length; i++) {
            slides[i].style.minHeight = '';
            slides[i].style.height = 'auto';
        }

        for (i = 0; i < slides.length; i++) {
            var slideH = Math.round(slides[i].getBoundingClientRect().height);
            if (slideH > 0) {
                heights.push(slideH);
            }
        }
        if (!heights.length) {
            for (i = 0; i < cards.length; i++) {
                var cardH = cardHeight(cards[i]);
                if (cardH > 0) {
                    heights.push(cardH);
                }
            }
        }

        if (!heights.length) {
            return;
        }

        var maxH = Math.max.apply(null, heights);
        var minH = Math.min.apply(null, heights);
        var threshold = (shelf.classList.contains('awa-shelf--has-countdown') ||
            shelf.classList.contains('hot-deal-tab-slider') ||
            shelf.classList.contains('awa-super-offers-carousel')) ? 0 : 1;

        if (maxH - minH <= threshold) {
            return;
        }

        for (i = 0; i < slides.length; i++) {
            slides[i].style.minHeight = maxH + 'px';
            slides[i].style.height = 'auto';
        }
        for (i = 0; i < cards.length; i++) {
            cards[i].style.minHeight = '100%';
        }
    }

    function scheduleEqualizeFallback(shelf) {
        if (!shelf) {
            return;
        }
        var key = shelf.dataset.awaShelfKey || (shelf.dataset.awaShelfKey = 's' + Math.random().toString(36).slice(2));
        clearTimeout(equalizeTimers[key]);
        equalizeTimers[key] = setTimeout(function () {
            equalizeCardsFallback(shelf);
            if (shelf.classList.contains('awa-shelf--has-countdown') || shelf.classList.contains('hot-deal-tab-slider')) {
                setTimeout(function () {
                    equalizeCardsFallback(shelf);
                }, 250);
            }
        }, 80);
    }

    function scheduleEqualizeAll() {
        qsa('.awa-shelf--carousel, .rokan-bestseller.awa-shelf, .rokan-newproduct.awa-shelf, .block.related.awa-shelf, .block.upsell.awa-shelf, .awa-shelf--has-countdown, .hot-deal-tab-slider').forEach(scheduleEqualizeFallback);
    }

    function markProductCards(root) {
        qsa('.content-item-product, .item-product .content-item-product, .product-item-info, .owl-item .product-item', root).forEach(function (card) {
            card.classList.add('awa-product-card');
        });
    }

    function ensureShelfStructure(root) {
        if (!root || root.dataset.awaShelfStructured === '1') {
            return root;
        }

        if (!root.classList.contains('awa-shelf')) {
            root.classList.add('awa-shelf', 'awa-shelf--carousel');
        }

        if (root.querySelector('.super-deal-countdown, .countdown_block')) {
            root.classList.add('awa-shelf--has-countdown');
            var countdown = root.querySelector('.countdown_block');
            if (countdown && !countdown.closest('.awa-shelf__countdown')) {
                var wrap = document.createElement('div');
                wrap.className = 'awa-shelf__countdown';
                countdown.parentNode.insertBefore(wrap, countdown);
                wrap.appendChild(countdown);
            }
        }

        var track = root.querySelector('ul.owl, .hot-deal-slide, .owl-carousel, ol.product-items');
        if (!track) {
            root.dataset.awaShelfStructured = '1';
            return root;
        }

        var carousel = root.querySelector('.awa-carousel');
        if (!carousel) {
            carousel = document.createElement('div');
            carousel.className = 'awa-carousel';
            track.parentNode.insertBefore(carousel, track);
            carousel.appendChild(track);
        }

        var viewport = carousel.querySelector('.awa-carousel__viewport');
        if (!viewport) {
            viewport = document.createElement('div');
            viewport.className = 'awa-carousel__viewport';
            carousel.insertBefore(viewport, track);
            viewport.appendChild(track);
        } else if (track.parentNode !== viewport) {
            viewport.appendChild(track);
        }

        track.classList.add('awa-carousel__track');
        qsa('.owl-item, .item.product-item, li.item, li.product-item', track).forEach(function (slide) {
            slide.classList.add('awa-carousel__slide');
            slide.style.removeProperty('display');
        });

        markProductCards(root);
        root.dataset.awaShelfStructured = '1';
        bindImageLoadEqualize(root);
        return root;
    }

    function bindImageLoadEqualize(shelf) {
        if (!shelf || shelf.dataset.awaImgBound === '1') {
            return;
        }
        shelf.dataset.awaImgBound = '1';

        shelf.addEventListener('load', function (event) {
            if (event.target && event.target.tagName === 'IMG') {
                scheduleEqualizeFallback(shelf);
            }
        }, true);

        shelf.addEventListener('lazyloaded', function () {
            scheduleEqualizeFallback(shelf);
        }, true);
    }

    function buildNav() {
        var frag = document.createElement('div');
        frag.innerHTML =
            '<div class="awa-owl-nav awa-carousel__nav">' +
            '<button type="button" class="awa-owl-nav__btn awa-carousel__arrow awa-carousel__arrow--prev awa-owl-nav__btn--prev" aria-label="Anterior">' + SVG_PREV + '</button>' +
            '<button type="button" class="awa-owl-nav__btn awa-carousel__arrow awa-carousel__arrow--next awa-owl-nav__btn--next" aria-label="Próximo">' + SVG_NEXT + '</button>' +
            '</div>';
        return frag.firstChild;
    }

    function buildProgress() {
        var frag = document.createElement('div');
        frag.innerHTML = '<div class="awa-owl-progress"><div class="awa-owl-progress__bar"></div></div>';
        return frag.firstChild;
    }

    function updateProgress(owlEl, progressEl) {
        if (!owlEl || !progressEl) {
            return;
        }
        var bar = progressEl.querySelector('.awa-owl-progress__bar');
        var wrapper = owlEl.querySelector('.owl-wrapper');
        var outer = owlEl.querySelector('.owl-wrapper-outer');
        if (!bar || !wrapper || !outer) {
            return;
        }
        var visible = outer.offsetWidth;
        var total = wrapper.scrollWidth || wrapper.offsetWidth;
        if (total <= visible) {
            bar.style.width = '100%';
            return;
        }
        var transform = wrapper.style.transform || wrapper.style.webkitTransform || '';
        var match = transform.match(/translate(?:3d)?\(\s*(-?[\d.]+)/);
        var scrollLeft = match ? Math.abs(parseFloat(match[1])) : 0;
        bar.style.width = Math.min(100, Math.max(5, ((scrollLeft + visible) / total) * 100)) + '%';
    }

    function wireChrome(owlEl, anchor) {
        if (!owlEl || !anchor || owlEl.dataset.awaShelfChrome === '1') {
            return;
        }
        owlEl.dataset.awaShelfChrome = '1';
        var mount = (anchor && anchor.closest && anchor.closest('.awa-shelf')) ||
            owlEl.closest('.awa-shelf') ||
            owlEl.closest('.awa-carousel') ||
            anchor;
        mount.style.position = 'relative';

        if (!mount.querySelector('.awa-owl-nav')) {
            var nav = buildNav();
            mount.appendChild(nav);
            nav.querySelector('.awa-owl-nav__btn--prev').addEventListener('click', function (e) {
                e.preventDefault();
                if (window.jQuery) {
                    window.jQuery(owlEl).trigger('owl.prev');
                }
            });
            nav.querySelector('.awa-owl-nav__btn--next').addEventListener('click', function (e) {
                e.preventDefault();
                if (window.jQuery) {
                    window.jQuery(owlEl).trigger('owl.next');
                }
            });
        }
        if (!mount.querySelector('.awa-owl-progress')) {
            mount.appendChild(buildProgress());
        }
        scheduleEqualizeFallback(anchor);
    }

    function owlItemWidth(owlEl) {
        var item = owlEl && owlEl.querySelector('.owl-item');
        return item ? item.getBoundingClientRect().width : 0;
    }

    function initOwlTrack(track) {
        if (!track || !window.jQuery || !window.jQuery.fn.owlCarousel) {
            return;
        }
        if (track.dataset.awaShelfOwlInit === '1' && track.classList.contains('owl-carousel')) {
            if (owlItemWidth(track) < 80) {
                var apiReload = window.jQuery(track).data('owlCarousel');
                if (apiReload && typeof apiReload.reload === 'function') {
                    apiReload.reload();
                }
            }
            return;
        }

        var shelf = track.closest('.awa-shelf, .rokan-bestseller, .rokan-newproduct, .hot-deal-tab-slider, .block.related, .block.upsell, .rokan-mostviewed, .rokan-toprate, .rokan-featured, .rokan-onsale, .categorytab-container');
        var widthRoot = shelf || track;

        function run() {
            if (track.classList.contains('owl-carousel')) {
                wireChrome(track, shelf || track.parentElement);
                return;
            }
            window.jQuery(track).owlCarousel(Object.assign({}, OWL, speedOptions()));
            window.jQuery(track).addClass('owl-loaded');
            track.dataset.awaShelfOwlInit = '1';
            setTimeout(function () {
                wireChrome(track, shelf || track.parentElement);
                scheduleEqualizeFallback(shelf || track);
            }, 50);
        }

        if (containerHasWidth(widthRoot, 200)) {
            run();
        } else {
            whenContainerReady(widthRoot, 200, run);
        }
    }

    function initCountdown(shelf) {
        if (!shelf || !shelf.classList.contains('awa-shelf--has-countdown')) {
            return;
        }
        var nodes = qsa('.super-deal-countdown', shelf);
        if (!nodes.length || shelf.dataset.awaCountdownInit === '1') {
            return;
        }
        shelf.dataset.awaCountdownInit = '1';

        if (typeof window.require !== 'function') {
            return;
        }

        window.require(['jquery', 'rokanthemes/timecircles'], function ($) {
            nodes.forEach(function (node) {
                if (node.dataset.awaTcInit === '1') {
                    return;
                }
                var endDate = node.getAttribute('data-date');
                if (!endDate || typeof $(node).TimeCircles !== 'function') {
                    return;
                }
                node.dataset.awaTcInit = '1';
                $(node).TimeCircles({
                    fg_width: 0.01,
                    bg_width: 1.2,
                    text_size: 0.07,
                    circle_bg_color: '#ffffff',
                    time: {
                        Days: { show: true, text: 'Dias', color: '#b73337' },
                        Hours: { show: true, text: 'Horas', color: '#b73337' },
                        Minutes: { show: true, text: 'Min', color: '#b73337' },
                        Seconds: { show: true, text: 'Seg', color: '#b73337' }
                    }
                });
            });
        });
    }

    function prepareRelatedBlock(block) {
        if (!block || block.dataset.awaShelfPrepared === '1') {
            return;
        }
        block.classList.add('awa-shelf', 'awa-shelf--carousel');

        var header = block.querySelector('.block-title.title');
        if (header) {
            header.classList.add('awa-shelf__header');
            var title = header.querySelector('strong, h2');
            if (title) {
                title.classList.add('awa-shelf__title');
            }
        }

        var grid = block.querySelector('.products-grid');
        if (grid) {
            grid.classList.remove('awa-grid-auto');
        }

        var list = block.querySelector('ol.product-items, ul.product-items');
        if (!list) {
            return;
        }

        if (!list.closest('.awa-carousel')) {
            var carousel = document.createElement('div');
            carousel.className = 'awa-carousel';
            list.parentNode.insertBefore(carousel, list);
            var viewport = document.createElement('div');
            viewport.className = 'awa-carousel__viewport';
            carousel.appendChild(viewport);
            viewport.appendChild(list);
        }

        list.classList.add('owl', 'awa-carousel__track');
        qsa('li.product-item', list).forEach(function (li) {
            li.classList.add('item', 'awa-carousel__slide');
            li.style.removeProperty('display');
            var info = li.querySelector('.product-item-info');
            if (info) {
                info.classList.add('awa-product-card');
            }
        });

        block.dataset.awaShelfPrepared = '1';
        ensureShelfStructure(block);
    }

    function scanShelf(root) {
        var scope = root && root.nodeType === 1 ? root : document;
        SHELF_SELECTORS.forEach(function (sel) {
            qsa(sel, scope).forEach(function (shelf) {
                if (shelf.matches('.awa-super-offers-carousel') &&
                    shelf.querySelector('.hot-deal-tab-slider.awa-shelf--carousel')) {
                    return;
                }
                if (shelf.matches('.block.related, .block.upsell')) {
                    prepareRelatedBlock(shelf);
                } else if (shelf.querySelector('ul.owl, .owl-carousel') && !shelf.classList.contains('awa-shelf')) {
                    shelf.classList.add('awa-shelf', 'awa-shelf--carousel');
                }
                ensureShelfStructure(shelf);
                initCountdown(shelf);
                var track = shelf.querySelector('.awa-carousel__track, ul.owl, .hot-deal-slide, ol.product-items.owl');
                if (track) {
                    initOwlTrack(track);
                }
            });
        });
    }

    function guardOverflow() {
        var docW = document.documentElement.clientWidth;
        qsa('.awa-shelf, .awa-carousel__viewport, .owl-wrapper-outer').forEach(function (el) {
            var w = el.getBoundingClientRect().width;
            if (w > docW + 2) {
                el.style.maxWidth = '100%';
                el.style.overflow = 'hidden';
            }
        });
    }

    function bindEvents() {
        if (!window.jQuery) {
            return;
        }
        window.jQuery(document).on('owl.afterMove owl.afterUpdate owl.afterInit lazyLoaded', '.awa-carousel__track.owl-carousel, .awa-carousel__track.owl', function () {
            var shelf = this.closest('.awa-shelf, .rokan-bestseller, .rokan-newproduct, .hot-deal-tab-slider, .block.related, .block.upsell');
            if (shelf) {
                scheduleEqualizeFallback(shelf);
                var progress = shelf.querySelector('.awa-owl-progress');
                if (progress) {
                    updateProgress(this, progress);
                }
            }
            if (owlItemWidth(this) < 80) {
                var api = window.jQuery(this).data('owlCarousel');
                if (api && typeof api.reload === 'function') {
                    api.reload();
                }
            }
        });
    }

    function whenVisible(el, cb) {
        if (typeof IntersectionObserver === 'undefined') {
            setTimeout(cb, 200);
            return;
        }
        var obs = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) {
                    return;
                }
                obs.disconnect();
                cb();
            });
        }, { rootMargin: '100px 0px', threshold: 0.01 });
        obs.observe(el);
    }

    function boot() {
        qsa('.awa-carousel-section, .catalog-product-view .block.related, .catalog-product-view .block.upsell, .catalog-product-view .awa-pdp-related, .rokan-bestseller, .rokan-newproduct').forEach(function (section) {
            whenVisible(section, function () {
                scanShelf(section);
                guardOverflow();
            });
        });
        scanShelf(document);
        guardOverflow();
        bindEvents();

        window.addEventListener('load', function () {
            scheduleEqualizeAll();
            guardOverflow();
        }, { once: true });

        if (typeof MutationObserver !== 'undefined') {
            var root = document.querySelector('.page-wrapper') || document.body;
            var timer = null;
            new MutationObserver(function () {
                clearTimeout(timer);
                timer = setTimeout(function () {
                    scanShelf(document);
                    guardOverflow();
                }, 300);
            }).observe(root, { childList: true, subtree: true });
        }

        window.addEventListener('resize', function () {
            clearTimeout(boot._rt);
            boot._rt = setTimeout(function () {
                scheduleEqualizeAll();
                guardOverflow();
            }, 200);
        }, { passive: true });

        document.addEventListener('click', function (event) {
            if (!event.target.closest('ul.tabs li, .tabs-categorytab2 li, .categorytab-container .tabs li')) {
                return;
            }
            clearTimeout(boot._tabTimer);
            boot._tabTimer = setTimeout(function () {
                scanShelf(document);
                scheduleEqualizeAll();
            }, 160);
        }, true);

        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'visible') {
                scheduleEqualizeAll();
            }
        });

        if (window.jQuery) {
            window.jQuery(document).on('ajaxComplete contentUpdated awa:shelf:refresh', function () {
                clearTimeout(boot._ajaxTimer);
                boot._ajaxTimer = setTimeout(function () {
                    scanShelf(document);
                    scheduleEqualizeAll();
                    guardOverflow();
                }, 220);
            });
        }
    }

    window.AWA_SHELF_CAROUSEL = {
        scan: scanShelf,
        OWL: OWL,
        equalize: equalizeCardsFallback,
        scheduleEqualize: scheduleEqualizeFallback,
        scheduleEqualizeAll: scheduleEqualizeAll
    };

    function waitDeps(n) {
        var i = 0;
        (function tick() {
            i++;
            if (window.jQuery && window.jQuery.fn && window.jQuery.fn.owlCarousel) {
                boot();
                return;
            }
            if (i < (n || 40)) {
                setTimeout(tick, 100);
            }
        })();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { waitDeps(40); });
    } else {
        waitDeps(40);
    }
})();
