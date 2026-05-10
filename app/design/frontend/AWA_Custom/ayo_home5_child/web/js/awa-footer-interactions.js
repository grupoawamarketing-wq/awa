define([
    'jquery',
    'swiper'
], function ($, Swiper) {
    'use strict';

    return function (config, element) {
        var $root = $(element);
        let mobileBreakpoint = Number(config.mobileBreakpoint) || 768;
        let titleSelector = config.titleSelector || '.footer-container .velaFooterTitle, .footer-container .footer-block-title';
        let panelSelector = config.panelSelector || '.velaContent, .footer-block-content';
        let sliderSelector = config.sliderSelector || '.footer_brand_list_slider';
        let brandSliderInitialized = false;
        let resizeDelay = Number(config.resizeDelay) || 120;
        let resizeTimer = null;

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
                let label = normalizeLabel($element.attr('aria-label') || $element.attr('title') || $element.text());

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
            let isMobile = isMobileViewport();

            $root.find(titleSelector).each(function () {
                var $title = $(this);
                let isExpanded = $title.attr('aria-expanded') === 'true';

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
                let titleId = $title.attr('id') || 'awa-footer-title-' + String(index + 1);

                $title.attr({
                    id: titleId,
                    tabindex: '0'
                });
                // role="button" é inválido em h1-h6 (ARIA 1.2 §6.5).
                // accordion funciona via aria-expanded + aria-controls + tabindex.
                $title.removeAttr('role');

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

                // Only add role="button" if no <button> child exists (e.g. inserted via CMS block or awa-footer-ux.js)
                if (!$element.find('button').length) {
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
                }
            });
        }

        function initBrandSlider() {
            var $slider = $root.find(sliderSelector);
            let slidesCount;
            let maxSlidesPerView;
            let shouldLoop;

            if (!$slider.length || brandSliderInitialized || $slider.data('awaSwiperInit')) {
                return;
            }

            brandSliderInitialized = true;
            $slider.data('awaSwiperInit', 1);
            $slider.attr('data-awa-footer-slider-ready', '1');

            // Wrap children in swiper markup if not already present
            if (!$slider.find('.swiper-wrapper').length) {
                $slider.addClass('swiper');
                $slider.children().wrap('<div class="swiper-slide"></div>');
                $slider.children('.swiper-slide').wrapAll('<div class="swiper-wrapper"></div>');
                $slider.append('<div class="swiper-button-prev" aria-label="Marca anterior"><span aria-hidden="true">&#8249;</span></div>');
                $slider.append('<div class="swiper-button-next" aria-label="Próxima marca"><span aria-hidden="true">&#8250;</span></div>');
            }

            slidesCount = $slider.find('.swiper-slide').length;
            maxSlidesPerView = 6;
            shouldLoop = slidesCount > maxSlidesPerView;

            new Swiper($slider[0], {
                slidesPerView: 2,
                spaceBetween: 0,
                loop: shouldLoop,
                watchOverflow: true,
                navigation: {
                    nextEl: $slider.find('.swiper-button-next')[0],
                    prevEl: $slider.find('.swiper-button-prev')[0]
                },
                autoplay: prefersReducedMotion() ? false : {
                    delay: 3000,
                    disableOnInteraction: false,
                    pauseOnMouseEnter: true
                },
                a11y: {
                    prevSlideMessage: 'Marca anterior',
                    nextSlideMessage: 'Próxima marca'
                },
                breakpoints: {
                    480: { slidesPerView: 3 },
                    768: { slidesPerView: 4 },
                    992: { slidesPerView: 5 },
                    1200: { slidesPerView: 6 }
                }
            });
        }

        function scheduleBrandSliderInit() {
            var $slider = $root.find(sliderSelector);
            let sliderElement;

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


        // === Categorias Expand Toggle ===
        function initCategoriesToggle() {
            var $toggleBtn = $root.closest('.page_footer, .page-footer')
                .find('[data-awa-categories-toggle]');

            if (!$toggleBtn.length) {
                $toggleBtn = $('[data-awa-categories-toggle]');
            }

            $toggleBtn.each(function () {
                var $btn = $(this);
                let panelId = $btn.attr('aria-controls');
                var $panel = panelId ? $('#' + panelId) : $btn.parent().find('.awa-footer-categories-expand__panel');

                $btn.on('click.awaCategories', function () {
                    let expanded = $btn.attr('aria-expanded') === 'true';
                    $btn.attr('aria-expanded', String(!expanded));
                    $panel.attr('aria-hidden', String(expanded));
                    if (expanded) {
                        $panel.slideUp(200);
                    } else {
                        $panel.slideDown(200);
                    }
                });
            });
        }

        initCategoriesToggle();
        ensureFooterSectionAccessibility();
        bindFooterSections();
        syncFooterSections();
        scheduleBrandSliderInit();

        $(window)
            .off('resize.awaFooterSections')
            .on('resize.awaFooterSections', scheduleResizeSync);
    };
});
