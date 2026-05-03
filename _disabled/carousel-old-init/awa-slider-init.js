/**
 * AWA Motos — Slider Init (Owl Carousel)
 * RequireJS widget: inicializa Owl Carousel com animações de texto.
 * Config fornecida via text/x-magento-init (owlConfig + sliderId).
 */
define([
    'jquery',
    'rokanthemes/owl'
], function ($) {
    'use strict';

    function doAnimations(elems) {
        const animEndEv = 'webkitAnimationEnd animationend';

        elems.each(function () {
            const $this = $(this);
            const $animationType = $this.data('animation');

            if ($animationType) {
                $this.addClass($animationType).one(animEndEv, function () {
                    $this.removeClass($animationType);
                });
            }
        });
    }

    return function (config) {
        const sliderId = config.sliderId;
        const owlConfig = $.extend(true, {}, config.owlConfig || {}, {
            afterInit(elem) {
                const $firstSlide = elem.find('.owl-item').eq(0);

                doAnimations($firstSlide.find('.text-banner').find("[data-animation ^= 'animated']"));
            },
            afterMove(elem) {
                const $currentSlide = elem.find('.owl-item').eq(this.currentItem);

                doAnimations($currentSlide.find('.text-banner').find("[data-animation ^= 'animated']"));
            }
        });

        if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            owlConfig.autoPlay = false;
        }

        $(`.slider_${sliderId} .owl`).owlCarousel(owlConfig);
        $(`.slider_${sliderId}_mobile .owl`).owlCarousel(owlConfig);
    };
});
