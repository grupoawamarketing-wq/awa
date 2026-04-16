/**
 * AWA Motos — Vertical Menu Promo Carousel
 * Lightweight auto-rotating banner carousel for the vertical menu.
 * Rotates every 5s with crossfade. Pauses on hover.
 */
define([], function () {
    'use strict';

    return function (config, element) {
        var interval = (config && config.interval) || 5000;
        var container = element.querySelector('.vmenu-promo-carousel');
        if (!container) { return; }

        var slides = container.querySelectorAll('.vmenu-promo-slide');
        if (slides.length < 2) { return; }

        var current = 0;
        var timer = null;
        var paused = false;

        function show(index) {
            slides[current].classList.remove('vmenu-promo-slide--active');
            slides[current].style.display = 'none';
            slides[current].style.opacity = '0';

            var nextIndex = index % slides.length;

            slides[nextIndex].style.opacity = '0';
            slides[nextIndex].style.display = 'block';
            // Double-rAF: evita forced reflow síncrono (void offsetHeight).
            // O primeiro rAF garante que o browser processou display:block,
            // o segundo aplica opacity:1 no próximo frame, ativando a transição CSS.
            requestAnimationFrame(function () {
                requestAnimationFrame(function () {
                    current = nextIndex;
                    slides[current].style.opacity = '1';
                    slides[current].classList.add('vmenu-promo-slide--active');
                });
            });
        }

        function next() {
            if (!paused) {
                show(current + 1);
            }
        }

        function start() {
            if (timer) { clearInterval(timer); }
            timer = setInterval(next, interval);
        }

        container.addEventListener('mouseenter', function () { paused = true; });
        container.addEventListener('mouseleave', function () { paused = false; });

        /* Initialize: ensure first slide visible */
        slides[0].classList.add('vmenu-promo-slide--active');
        slides[0].style.display = 'block';
        slides[0].style.opacity = '1';

        start();
    };
});
