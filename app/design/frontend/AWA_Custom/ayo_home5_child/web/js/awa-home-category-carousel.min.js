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
        var pageOffsets = [0];
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
            // Scroll by almost a full page (track.clientWidth) to match dots logic
            var scroll = track.clientWidth;
            var item = items[0];
            if (item) {
                var style = getComputedStyle(track);
                var gap = parseInt(style.gap, 10) || 16;
                var itemW = item.offsetWidth + gap;
                // Round down to nearest whole item to prevent cutting
                scroll = Math.floor(track.clientWidth / itemW) * itemW;
            }
            return Math.max(scroll, 280);
        }

        function getMaxScroll() {
            return Math.max(0, track.scrollWidth - track.clientWidth);
        }

        function buildPageOffsets() {
            var trackW = track.clientWidth;
            var maxScroll = getMaxScroll();
            var offset = 0;

            pageOffsets = [0];

            if (!trackW || maxScroll <= 0) {
                return;
            }

            while (offset + trackW < maxScroll) {
                offset += trackW;
                pageOffsets.push(offset);
            }

            if (pageOffsets[pageOffsets.length - 1] !== maxScroll) {
                pageOffsets.push(maxScroll);
            }
        }

        function getCurrentPage() {
            var currentScroll = track.scrollLeft;
            var activeIndex = 0;
            var activeDistance = Infinity;

            pageOffsets.forEach(function (offset, idx) {
                var distance = Math.abs(currentScroll - offset);

                if (distance < activeDistance) {
                    activeDistance = distance;
                    activeIndex = idx;
                }
            });

            return activeIndex;
        }

        function updateNavState() {
            var maxScroll = getMaxScroll();
            var atStart = track.scrollLeft <= 4;
            var atEnd = track.scrollLeft >= (maxScroll - 4);

            [prev, next].forEach(function (button, idx) {
                var disabled = idx === 0 ? atStart : atEnd;

                if (!button) {
                    return;
                }

                button.disabled = disabled;
                button.classList.toggle('is-disabled', disabled);
                button.setAttribute('aria-disabled', disabled ? 'true' : 'false');
                button.style.opacity = disabled ? '0.45' : '';
                button.style.pointerEvents = disabled ? 'none' : '';
            });
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
            buildPageOffsets();

            if (scrollW <= trackW) {
                dotsWrap.style.display = 'none';
                dotsWrap.setAttribute('aria-hidden', 'true');
                dotsWrap.setAttribute('inert', '');
                dotsWrap.removeAttribute('aria-label');
                return;
            }

            dotsWrap.style.display = '';
            dotsWrap.removeAttribute('aria-hidden');
            dotsWrap.removeAttribute('inert');
            dotsWrap.setAttribute('aria-label', 'Navegacao do carrossel de categorias');
            pages = pageOffsets.length;

            for (i = 0; i < pages; i++) {
                (function (pageIndex) {
                    var dot = document.createElement('button');
                    var isActive = pageIndex === 0;

                    dot.className = 'awa-category-carousel__dot';
                    dot.type = 'button';
                    dot.setAttribute('aria-label', 'Ir para página ' + (pageIndex + 1) + ' de ' + pages);
                    dot.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                    if (isActive) {
                        dot.setAttribute('aria-current', 'page');
                    }

                    if (isActive) {
                        dot.classList.add('active');
                    }

                    dot.addEventListener('click', function () {
                        track.scrollTo({left: pageOffsets[pageIndex] || 0, behavior: scrollBehavior()});
                    });

                    dotsWrap.appendChild(dot);
                })(i);
            }

            updateNavState();
        }

        function updateDots() {
            var dots;
            var currentPage;

            if (!dotsWrap) {
                return;
            }

            dots = dotsWrap.querySelectorAll('.awa-category-carousel__dot');
            if (!dots.length) {
                return;
            }

            currentPage = getCurrentPage();

            dots.forEach(function (dot, idx) {
                var isActive = idx === currentPage;

                dot.classList.toggle('active', isActive);
                dot.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                if (isActive) {
                    dot.setAttribute('aria-current', 'page');
                } else {
                    dot.removeAttribute('aria-current');
                }
            });

            updateNavState();
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

            /* Reveal items: fires up to 200px before track enters viewport so
               items are never invisible on above-fold / near-fold loads. */
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
            }, {threshold: 0.05, rootMargin: '0px 0px 200px 0px'});

            /* Immediate reveal: if track is already in or very near viewport
               (within 400px of fold), skip animation and show items at once. */
            var trackRect = track.getBoundingClientRect();
            if (trackRect.top < window.innerHeight + 400) {
                animItems.forEach(function (el) {
                    el.classList.remove('awa-carousel-hidden');
                    el.classList.add('awa-carousel-visible');
                });
            } else {
                io.observe(track);
            }
        }
    };
});
