/**
 * AWA Motos — Home Category Carousel
 * RequireJS widget: scroll nav, swipe, dots, keyboard, entrance animation.
 * Inicializado via data-mage-init no #awa-cat-carousel track element.
 */
define([], function () {
    'use strict';

    return function (config, track) {
        const prev = document.querySelector('.awa-category-carousel__prev');
        const next = document.querySelector('.awa-category-carousel__next');
        const dotsWrap = document.getElementById('awa-cat-dots');
        const items = track.querySelectorAll('.awa-category-carousel__item');

        if (!items.length) {
            return;
        }

        const mobileBreakpoint = config.mobileBreakpoint || 768;

        function isMobile() {
            return window.matchMedia(`(max-width: ${mobileBreakpoint}px)`).matches;
        }

        function syncControlVisibility() {
            const mobile = isMobile();

            track.setAttribute('tabindex', mobile ? '-1' : '0');

            if (prev) {
                prev.style.display = mobile ? 'none' : '';
                prev.setAttribute('aria-hidden', mobile ? 'true' : 'false');
            }

            if (next) {
                next.style.display = mobile ? 'none' : '';
                next.setAttribute('aria-hidden', mobile ? 'true' : 'false');
            }

            if (dotsWrap) {
                dotsWrap.style.display = mobile ? 'none' : '';
                dotsWrap.setAttribute('aria-hidden', mobile ? 'true' : 'false');
            }
        }

        function getScrollAmount() {
            const item = items[0];

            if (!item) {
                return 280;
            }

            const style = getComputedStyle(track);
            const gap = parseInt(style.gap, 10) || 16;

            return item.offsetWidth + gap;
        }

        /* --- Nav buttons --- */
        if (prev) {
            prev.addEventListener('click', () => {
                if (!isMobile()) {
                    track.scrollBy({left: -getScrollAmount(), behavior: 'smooth'});
                }
            });
        }

        if (next) {
            next.addEventListener('click', () => {
                if (!isMobile()) {
                    track.scrollBy({left: getScrollAmount(), behavior: 'smooth'});
                }
            });
        }

        /* --- Touch swipe --- */
        let startX = 0;
        let startScrollLeft = 0;
        let isDragging = false;

        track.addEventListener('touchstart', (e) => {
            startX = e.touches[0].pageX;
            startScrollLeft = track.scrollLeft;
            isDragging = true;
        }, {passive: true});

        track.addEventListener('touchmove', (e) => {
            if (!isDragging) {
                return;
            }

            track.scrollLeft = startScrollLeft - (e.touches[0].pageX - startX);
        }, {passive: true});

        track.addEventListener('touchend', () => {
            isDragging = false;
        }, {passive: true});

        /* --- Pagination dots --- */
        function buildDots() {
            if (!dotsWrap) {
                return;
            }

            if (isMobile()) {
                dotsWrap.innerHTML = '';
                dotsWrap.style.display = 'none';
                return;
            }

            dotsWrap.innerHTML = '';
            const trackW = track.offsetWidth;
            const scrollW = track.scrollWidth;

            if (scrollW <= trackW) {
                dotsWrap.style.display = 'none';
                return;
            }

            dotsWrap.style.display = '';
            const pages = Math.ceil(scrollW / trackW);

            for (let i = 0; i < pages; i++) {
                const dot = document.createElement('span');

                dot.className = 'awa-category-carousel__dot';

                if (i === 0) {
                    dot.classList.add('active');
                }

                dot.dataset.page = i;
                dot.addEventListener('click', ((page) => {
                    return () => {
                        track.scrollTo({left: page * trackW, behavior: 'smooth'});
                    };
                })(i));
                dotsWrap.appendChild(dot);
            }
        }

        function updateDots() {
            if (!dotsWrap || isMobile()) {
                return;
            }

            const dots = dotsWrap.querySelectorAll('.awa-category-carousel__dot');

            if (!dots.length) {
                return;
            }

            const trackW = track.offsetWidth;
            let currentPage = Math.round(track.scrollLeft / trackW);

            currentPage = Math.max(0, Math.min(currentPage, dots.length - 1));
            dots.forEach((d, idx) => {
                d.classList.toggle('active', idx === currentPage);
            });
        }

        /* --- Keyboard nav --- */
        track.addEventListener('keydown', (e) => {
            if (isMobile()) {
                return;
            }

            if (e.key === 'ArrowRight') {
                track.scrollBy({left: getScrollAmount(), behavior: 'smooth'});
                e.preventDefault();
            }

            if (e.key === 'ArrowLeft') {
                track.scrollBy({left: -getScrollAmount(), behavior: 'smooth'});
                e.preventDefault();
            }
        });

        track.addEventListener('scroll', updateDots, {passive: true});
        syncControlVisibility();
        buildDots();

        let resizeTimer;

        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
                syncControlVisibility();
                buildDots();
            }, 200);
        }, {passive: true});

        /* --- Staggered entrance animation --- */
        if ('IntersectionObserver' in window) {
            const animItems = track.querySelectorAll('.awa-category-carousel__item');

            animItems.forEach((el) => {
                el.classList.add('awa-carousel-hidden');
            });

            const io = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        const cards = entry.target.querySelectorAll('.awa-carousel-hidden');

                        cards.forEach((card, i) => {
                            setTimeout(() => {
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
