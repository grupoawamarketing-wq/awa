/**
 * AWA Motos — Back to Top Button
 * RequireJS widget: mostra/esconde botão e scroll suave ao topo.
 */
define([], function () {
    'use strict';

    return function (config, element) {
        const threshold = config.threshold || 600;
        let shown = false;

        function toggle() {
            const y = window.pageYOffset || document.documentElement.scrollTop;

            if (y > threshold && !shown) {
                element.classList.add('is-visible');
                element.setAttribute('aria-hidden', 'false');
                shown = true;
            } else if (y <= threshold && shown) {
                element.classList.remove('is-visible');
                element.setAttribute('aria-hidden', 'true');
                shown = false;
            }
        }

        window.addEventListener('scroll', toggle, {passive: true});

        element.addEventListener('click', () => {
            const prefersReducedMotion = window.matchMedia &&
                window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            window.scrollTo({top: 0, behavior: prefersReducedMotion ? 'auto' : 'smooth'});
        });

        toggle();
    };
});
