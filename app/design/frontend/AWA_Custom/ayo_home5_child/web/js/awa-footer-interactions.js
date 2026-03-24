define([
    'jquery',
    'rokanthemes/owl'
], function ($) {
    'use strict';

    return function (config, element) {
        var $root = $(element);
        var mobileBreakpoint = Number(config.mobileBreakpoint) || 768;
        var titleSelector = config.titleSelector || '.footer-container .velaFooterTitle, .footer-container .footer-block-title';
        var panelSelector = config.panelSelector || '.velaContent, .footer-block-content';
        var sliderSelector = config.sliderSelector || '.footer_brand_list_slider';
        var brandSliderInitialized = false;
        var resizeDelay = Number(config.resizeDelay) || 120;
        var resizeTimer = null;

        if (!$root.length) {
            return;
        }

        function isMobileViewport() {
            return window.matchMedia('(max-width:' + String(mobileBreakpoint - 1) + 'px)').matches;
        }

        function prefersReducedMotion() {
            return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        }

        function getPanel($title) {
            return $title.next(panelSelector).first();
        }

        function normalizeLabel(value) {
            return String(value || '').replace(/\s+/g, ' ').trim();
        }

        function ensureLabelAttributes($elements) {
            $elements.each(function () {
                var $element = $(this);
                var label = normalizeLabel($element.attr('aria-label') || $element.attr('title') || $element.text());

                if (!label) {
                    return;
                }

                if (!$element.attr('aria-label')) {
                    $element.attr('aria-label', label);
                }

                if (!$element.attr('title')) {
                    $element.attr('title', label);
                }
            });
        }

        function setPanelState($title, shouldExpand) {
            var $panel = getPanel($title);

            $title.toggleClass('active', shouldExpand)
                .attr('aria-expanded', shouldExpand ? 'true' : 'false');

            if (!$panel.length) {
                return;
            }

            $panel.attr('aria-hidden', shouldExpand ? 'false' : 'true')
                .prop('hidden', !shouldExpand);

            if (shouldExpand) {
                $panel.stop(true, true).slideDown(180);
                return;
            }

            $panel.stop(true, true).slideUp(180);
        }

        function syncFooterSections() {
            var isMobile = isMobileViewport();

            $root.find(titleSelector).each(function () {
                var $title = $(this);
                var isExpanded = $title.attr('aria-expanded') === 'true';

                if (!isMobile) {
                    setPanelState($title, true);
                    return;
                }

                setPanelState($title, isExpanded);
            });
        }

        function scheduleResizeSync() {
            if (resizeTimer) {
                window.clearTimeout(resizeTimer);
            }

            resizeTimer = window.setTimeout(syncFooterSections, resizeDelay);
        }

        function bindFooterSections() {
            $root.find(titleSelector)
                .off('.awaFooter')
                .on('click.awaFooter', function (event) {
                    var $title = $(this);

                    if (!isMobileViewport()) {
                        return;
                    }

                    event.preventDefault();
                    setPanelState($title, $title.attr('aria-expanded') !== 'true');
                })
                .on('keydown.awaFooter', function (event) {
                    if (event.key !== 'Enter' && event.key !== ' ') {
                        return;
                    }

                    event.preventDefault();
                    $(this).trigger('click.awaFooter');
                });
        }

        function ensureFooterSectionAccessibility() {
            $root.find(titleSelector).each(function (index) {
                var $title = $(this);
                var $panel = getPanel($title);
                var titleId = $title.attr('id') || 'awa-footer-title-' + String(index + 1);

                $title.attr({
                    id: titleId,
                    role: 'button',
                    tabindex: '0'
                });

                if (!$title.attr('aria-expanded')) {
                    $title.attr('aria-expanded', isMobileViewport() ? 'false' : 'true');
                }

                if (!$panel.length) {
                    return;
                }

                $panel.attr('id', $panel.attr('id') || 'awa-footer-panel-' + String(index + 1));
                $panel.attr('aria-labelledby', titleId);
                $title.attr('aria-controls', $panel.attr('id'));
            });

            ensureLabelAttributes($root.find('a, button'));
            ensureLabelAttributes($('.fixed-right a, .fixed-right button, .fixed-bottom a, .fixed-bottom button, #back-top'));

            $('.fixed-right .fixed-right-ul .scroll-top').each(function () {
                var $element = $(this);

                $element.attr({
                    role: 'button',
                    tabindex: '0'
                });

                if (!$element.attr('aria-label')) {
                    $element.attr('aria-label', 'Voltar ao topo');
                }

                if (!$element.attr('title')) {
                    $element.attr('title', 'Voltar ao topo');
                }
            });
        }

        function initBrandSlider() {
            var $slider = $root.find(sliderSelector);

            if (!$slider.length || brandSliderInitialized || $slider.hasClass('owl-loaded') || typeof $slider.owlCarousel !== 'function') {
                return;
            }

            brandSliderInitialized = true;
            $slider.attr('data-awa-footer-slider-ready', '1');

            $slider.owlCarousel({
                items: 6,
                loop: true,
                margin: 0,
                nav: true,
                dots: false,
                autoplay: !prefersReducedMotion(),
                autoplayTimeout: 3000,
                autoplayHoverPause: true,
                navText: [
                    '<i class="fa fa-angle-left"></i>',
                    '<i class="fa fa-angle-right"></i>'
                ],
                responsive: {
                    0: { items: 2 },
                    480: { items: 3 },
                    768: { items: 4 },
                    992: { items: 5 },
                    1200: { items: 6 }
                }
            });
        }

        function scheduleBrandSliderInit() {
            var $slider = $root.find(sliderSelector);
            var sliderElement;

            if (!$slider.length || brandSliderInitialized) {
                return;
            }

            sliderElement = $slider.get(0);

            if (window.IntersectionObserver && sliderElement) {
                new window.IntersectionObserver(function (entries, observer) {
                    entries.forEach(function (entry) {
                        if (!entry.isIntersecting) {
                            return;
                        }

                        initBrandSlider();
                        observer.disconnect();
                    });
                }, {
                    rootMargin: '160px 0px'
                }).observe(sliderElement);

                return;
            }

            if (typeof window.requestIdleCallback === 'function') {
                window.requestIdleCallback(function () {
                    initBrandSlider();
                }, {
                    timeout: 1200
                });

                return;
            }

            window.setTimeout(initBrandSlider, 250);
        }

        ensureFooterSectionAccessibility();
        bindFooterSections();
        syncFooterSections();
        scheduleBrandSliderInit();

        $(window)
            .off('resize.awaFooterSections')
            .on('resize.awaFooterSections', scheduleResizeSync);
    };
});
