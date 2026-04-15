define([
    'jquery'
], function ($) {
    'use strict';

    return function (config, element) {
        var $root = $(element);
        var trackSelector = config.trackSelector || '.rexis-carousel-track';
        var slideSelector = config.slideSelector || '.rexis-carousel-slide';
        var prevSelector = config.prevSelector || '.rexis-prev';
        var nextSelector = config.nextSelector || '.rexis-next';
        var mobileBreakpoint = parseInt(config.mobileBreakpoint, 10) || 480;
        var tabletBreakpoint = parseInt(config.tabletBreakpoint, 10) || 768;
        var desktopBreakpoint = parseInt(config.desktopBreakpoint, 10) || 992;

        var $track = $root.find(trackSelector).first();
        var $slides = $track.find(slideSelector);
        var $prev = $root.find(prevSelector).first();
        var $next = $root.find(nextSelector).first();
        var position = 0;
        var resizeTimer = null;

        if (!$track.length || !$slides.length || $root.data('rexisCarouselInit')) {
            return;
        }

        $root.data('rexisCarouselInit', true);

        function getVisibleSlides() {
            var width = $root.outerWidth();

            if (width <= mobileBreakpoint) {
                return 1;
            }

            if (width <= tabletBreakpoint) {
                return 2;
            }

            if (width <= desktopBreakpoint) {
                return 3;
            }

            return 4;
        }

        function clampPosition(pos) {
            var max = Math.max(0, $slides.length - getVisibleSlides());

            return Math.max(0, Math.min(max, pos));
        }

        function render() {
            var slideWidth = $slides.first().outerWidth(true) || 0;
            var max = Math.max(0, $slides.length - getVisibleSlides());

            position = clampPosition(position);

            if (window.requestAnimationFrame) {
                window.requestAnimationFrame(function () {
                    $track.css('transform', 'translate3d(-' + (position * slideWidth) + 'px, 0, 0)');
                });
            } else {
                $track.css('transform', 'translate3d(-' + (position * slideWidth) + 'px, 0, 0)');
            }

            if ($prev.length) {
                $prev.prop('disabled', position <= 0);
                $prev.attr('aria-disabled', position <= 0 ? 'true' : 'false');
            }

            if ($next.length) {
                $next.prop('disabled', position >= max);
                $next.attr('aria-disabled', position >= max ? 'true' : 'false');
            }
        }

        function move(direction) {
            position = clampPosition(position + direction);
            render();
        }

        $prev.on('click', function () {
            move(-1);
        });

        $next.on('click', function () {
            move(1);
        });

        $root.on('keydown', function (event) {
            if (event.key === 'ArrowLeft') {
                move(-1);
                event.preventDefault();
            }

            if (event.key === 'ArrowRight') {
                move(1);
                event.preventDefault();
            }
        });

        $(window).on('resize.rexisCarousel', function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(render, 160);
        });

        if ('IntersectionObserver' in window) {
            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        observer.disconnect();
                        render();
                    }
                });
            }, {rootMargin: '300px 0px'});

            observer.observe($root.get(0));
        } else {
            render();
        }
    };
});
