/**
 * AWA Motos — Home Category Carousel
 * RequireJS widget: scroll nav, swipe, dots, keyboard, entrance animation.
 * Inicializado via data-mage-init no #awa-cat-carousel track element.
 */
define([], function () {
    'use strict';

    function prefersReducedMotion() {
        return window.matchMedia &&
            window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    function scrollBehavior() {
        return prefersReducedMotion() ? 'auto' : 'smooth';
    }

    return function (config, track) {
        var root;
        var prev;
        var next;
        var dotsWrap;
        var items;
        var startX = 0;
        var startScrollLeft = 0;
        var isDragging = false;
        var resizeTimer;

        if (!track || track.dataset.awaCategoryCarouselInit === '1') {
            return;
        }

        track.dataset.awaCategoryCarouselInit = '1';

        root = track.closest('.top-home-content--category-carousel') || document;
        prev = root.querySelector('.awa-category-carousel__prev');
        next = root.querySelector('.awa-category-carousel__next');
        dotsWrap = root.querySelector('#awa-cat-dots');
        items = track.querySelectorAll('.awa-category-carousel__item');

        if (!items.length) {
            return;
        }

        function getScrollAmount() {
            var item = items[0];
            var style;
            var gap;

            if (!item) {
                return 280;
            }

            style = getComputedStyle(track);
            gap = parseInt(style.gap, 10) || 16;

            return item.offsetWidth + gap;
        }

        function buildDots() {
            var trackW;
            var scrollW;
            var pages;
            var i;

            if (!dotsWrap) {
                return;
            }

            dotsWrap.innerHTML = '';
            trackW = track.offsetWidth;
            scrollW = track.scrollWidth;

            if (scrollW <= trackW) {
                dotsWrap.style.display = 'none';
                return;
            }

            dotsWrap.style.display = '';
            pages = Math.ceil(scrollW / trackW);

            for (i = 0; i < pages; i++) {
                (function (pageIndex) {
                    var dot = document.createElement('button');
                    var isActive = pageIndex === 0;

                    dot.className = 'awa-category-carousel__dot';
                    dot.type = 'button';
                    dot.setAttribute('aria-label', 'Ir para página ' + (pageIndex + 1) + ' de ' + pages);
                    dot.setAttribute('aria-pressed', isActive ? 'true' : 'false');

                    if (isActive) {
                        dot.classList.add('active');
                    }

                    dot.addEventListener('click', function () {
                        track.scrollTo({left: pageIndex * trackW, behavior: scrollBehavior()});
                    });

                    dotsWrap.appendChild(dot);
                })(i);
            }
        }

        function updateDots() {
            var dots;
            var trackW;
            var currentPage;

            if (!dotsWrap) {
                return;
            }

            dots = dotsWrap.querySelectorAll('.awa-category-carousel__dot');
            if (!dots.length) {
                return;
            }

            trackW = track.offsetWidth;
            currentPage = Math.round(track.scrollLeft / trackW);

            dots.forEach(function (dot, idx) {
                var isActive = idx === currentPage;

                dot.classList.toggle('active', isActive);
                dot.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });
        }

        if (prev) {
            prev.addEventListener('click', function () {
                track.scrollBy({left: -getScrollAmount(), behavior: scrollBehavior()});
            });
        }

        if (next) {
            next.addEventListener('click', function () {
                track.scrollBy({left: getScrollAmount(), behavior: scrollBehavior()});
            });
        }

        track.addEventListener('touchstart', function (event) {
            startX = event.touches[0].pageX;
            startScrollLeft = track.scrollLeft;
            isDragging = true;
        }, {passive: true});

        track.addEventListener('touchmove', function (event) {
            if (!isDragging) {
                return;
            }

            track.scrollLeft = startScrollLeft - (event.touches[0].pageX - startX);
        }, {passive: true});

        track.addEventListener('touchend', function () {
            isDragging = false;
        }, {passive: true});

        track.setAttribute('tabindex', '0');
        track.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowRight') {
                track.scrollBy({left: getScrollAmount(), behavior: scrollBehavior()});
                event.preventDefault();
            }

            if (event.key === 'ArrowLeft') {
                track.scrollBy({left: -getScrollAmount(), behavior: scrollBehavior()});
                event.preventDefault();
            }
        });

        track.addEventListener('scroll', updateDots, {passive: true});
        buildDots();

        window.addEventListener('resize', function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(buildDots, 200);
        }, {passive: true});

        if ('IntersectionObserver' in window && !prefersReducedMotion()) {
            var animItems = track.querySelectorAll('.awa-category-carousel__item');

            animItems.forEach(function (el) {
                el.classList.add('awa-carousel-hidden');
            });

            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        var cards = entry.target.querySelectorAll('.awa-carousel-hidden');

                        cards.forEach(function (card, i) {
                            setTimeout(function () {
                                card.classList.remove('awa-carousel-hidden');
                                card.classList.add('awa-carousel-visible');
                            }, i * 80);
                        });
                        io.unobserve(entry.target);
                    }
                });
            }, {threshold: 0.15});

            io.observe(track);
        }
    };
});
