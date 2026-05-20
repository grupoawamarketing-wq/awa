/* global define, setTimeout */

define([
    'jquery',
    'rokanthemes/owl'
], function ($) {
    'use strict';

    function railWidth($carousel) {
        let $root = $carousel.closest('.rokan-bestseller, .rokan-newproduct, .awa-carousel-section, .row');
        let widths = [$carousel.width(), $root.width()];

        if ($root.length && $root[0].getBoundingClientRect) {
            widths.push($root[0].getBoundingClientRect().width);
        }

        return Math.max.apply(null, widths.filter(function (w) {
            return typeof w === 'number' && !isNaN(w);
        }).concat([0]));
    }

    function firstOwlItemWidth($carousel) {
        let item = $carousel.find('.owl-item').get(0);

        return item ? item.getBoundingClientRect().width : 0;
    }

    function reloadOwlIfNeeded($carousel) {
        let api = $carousel.data('owlCarousel');

        if (!api) {
            return;
        }

        if (typeof api.reload === 'function') {
            api.reload();
        }

        $carousel.trigger('owl.update');
        $carousel.addClass('owl-loaded');
    }

    function applyCarouselA11y($carousel) {
        let sectionHeading = '';
        let $heading = $carousel
            .closest('.rokan-newproduct, .rokan-bestseller, .list-tab-product, .categorytab-container')
            .find('.rokan-product-heading h2')
            .first();
        let $items = $carousel.find('.owl-item');
        let total = $items.length;

        if ($heading.length) {
            sectionHeading = $.trim($heading.text());
        }

        if (!sectionHeading) {
            sectionHeading = 'Produtos em destaque';
        }

        // Improve screen-reader context and touch behavior for Owl v1 rails.
        $carousel.attr({
            role: 'region',
            'aria-roledescription': 'carousel',
            'aria-label': sectionHeading
        });

        $carousel.css('touch-action', 'pan-y');

        $items.attr('role', 'group');
        if (total > 0) {
            $items.each(function (index) {
                $(this).attr('aria-label', (index + 1) + ' de ' + total);
            });
        }

        $carousel.find('.owl-prev').attr({
            role: 'button',
            tabindex: '0',
            'aria-label': 'Slide anterior'
        });

        $carousel.find('.owl-next').attr({
            role: 'button',
            tabindex: '0',
            'aria-label': 'Proximo slide'
        });
    }

    function bindArrowKeyboardSupport($carousel) {
        $carousel.off('keydown.awaOwlA11y').on('keydown.awaOwlA11y', '.owl-prev, .owl-next', function (event) {
            if (event.key === 'Enter' || event.key === ' ' || event.key === 'Spacebar') {
                event.preventDefault();
                $(this).trigger('click');
            }
        });
    }

    function scheduleOwlWidthRepairs($carousel) {
        function repairOnce() {
            if (firstOwlItemWidth($carousel) < 80 && railWidth($carousel) >= 200) {
                reloadOwlIfNeeded($carousel);
            }
            applyCarouselA11y($carousel);
            bindArrowKeyboardSupport($carousel);
        }

        if (window.requestAnimationFrame) {
            window.requestAnimationFrame(function () {
                window.requestAnimationFrame(repairOnce);
            });
        } else {
            setTimeout(repairOnce, 0);
        }

        setTimeout(repairOnce, 600);

        if (typeof ResizeObserver === 'undefined' || $carousel.data('awaOwlRepairObs')) {
            return;
        }

        let root = $carousel.closest('.rokan-bestseller, .rokan-newproduct').get(0);
        if (!root) {
            return;
        }

        let resizeRaf = null;
        let obs = new ResizeObserver(function () {
            if (resizeRaf !== null) {
                return;
            }

            resizeRaf = window.requestAnimationFrame(function () {
                resizeRaf = null;

                if (firstOwlItemWidth($carousel) < 80 && railWidth($carousel) >= 200) {
                    reloadOwlIfNeeded($carousel);
                    applyCarouselA11y($carousel);
                }
            });
        });
        obs.observe(root);
        $carousel.data('awaOwlRepairObs', obs);
    }

    function runOwlInit($carousel, options, dataFlag) {
        try {
            if ($carousel.data('owlCarousel')) {
                reloadOwlIfNeeded($carousel);
            } else {
                $carousel.owlCarousel(options);
            }

            $carousel.addClass('owl-loaded');
            scheduleOwlWidthRepairs($carousel);
        } catch (error) {
            if (dataFlag) {
                $carousel.removeData(dataFlag);
            }
        }
    }

    function railReady($carousel, minWidth) {
        return $carousel && $carousel.length &&
            $carousel.is(':visible') &&
            railWidth($carousel) >= (minWidth || 200);
    }

    function initWhenVisible($carousel, options, attemptsLeft, delayMs, dataFlag) {
        let delay = delayMs || 200;
        let remaining = typeof attemptsLeft === 'number' ? attemptsLeft : 15;
        let minWidth = 200;

        if (!$carousel || !$carousel.length) {
            return;
        }

        if (typeof $carousel.owlCarousel !== 'function') {
            if (remaining <= 0) {
                if (dataFlag) {
                    $carousel.removeData(dataFlag);
                }
                return;
            }

            setTimeout(function () {
                initWhenVisible($carousel, options, remaining - 1, delay, dataFlag);
            }, delay);
            return;
        }

        if (railReady($carousel, minWidth)) {
            runOwlInit($carousel, options, dataFlag);
            return;
        }

        let root = $carousel.closest('.rokan-bestseller, .rokan-newproduct, .awa-carousel-section').get(0);

        if (root && typeof IntersectionObserver !== 'undefined' && !$carousel.data('awaOwlWaitObs')) {
            $carousel.data('awaOwlWaitObs', 1);

            let waitObs = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting || !railReady($carousel, minWidth)) {
                        return;
                    }

                    waitObs.disconnect();
                    $carousel.removeData('awaOwlWaitObs');
                    runOwlInit($carousel, options, dataFlag);
                });
            }, { rootMargin: '100px 0px', threshold: 0.01 });

            waitObs.observe(root);

            setTimeout(function () {
                if ($carousel.data('awaOwlWaitObs')) {
                    waitObs.disconnect();
                    $carousel.removeData('awaOwlWaitObs');
                    if (railReady($carousel, minWidth)) {
                        runOwlInit($carousel, options, dataFlag);
                    } else if (dataFlag) {
                        $carousel.removeData(dataFlag);
                    }
                }
            }, 6000);

            return;
        }

        if (remaining <= 0) {
            if (dataFlag) {
                $carousel.removeData(dataFlag);
            }
            return;
        }

        setTimeout(function () {
            initWhenVisible($carousel, options, remaining - 1, delay, dataFlag);
        }, delay);
    }

    function observeRailVisibility($carousel, options, dataFlag) {
        if (typeof IntersectionObserver === 'undefined' || !$carousel.length) {
            return;
        }

        let root = $carousel.closest('.rokan-bestseller, .rokan-newproduct').get(0);

        if (!root) {
            return;
        }

        let observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) {
                    return;
                }

                if (railWidth($carousel) >= 200) {
                    if (!$carousel.data('owlCarousel')) {
                        initWhenVisible($carousel, options, 12, 200, dataFlag);
                    } else {
                        reloadOwlIfNeeded($carousel);
                        scheduleOwlWidthRepairs($carousel);
                    }
                }

                observer.disconnect();
            });
        }, { rootMargin: '80px 0px', threshold: 0.05 });

        observer.observe(root);
    }

    function resolveBoolean(value, fallback) {
        if (value === undefined || value === null || value === '') {
            return fallback;
        }
        if (typeof value === 'string') {
            return !(value === 'false' || value === '0');
        }
        return !!value;
    }

    return function (config, element) {
        if (window.AWA_SHELF_CAROUSEL && typeof window.AWA_SHELF_CAROUSEL.scan === 'function') {
            window.AWA_SHELF_CAROUSEL.scan(element);
            return;
        }

        var $scope = $(element);
        let carouselSelector = config.carouselSelector || '.owl';
        let owlConfig = config.owl || {};
        let dataFlag = config.dataFlag || 'awaOwlInit';
        let withFirstActive = resolveBoolean(owlConfig.withFirstActive, true);
        let options = {
            lazyLoad: resolveBoolean(owlConfig.lazyLoad, true),
            autoPlay: resolveBoolean(owlConfig.autoPlay, false),
            navigation: resolveBoolean(owlConfig.navigation, true),
            pagination: resolveBoolean(owlConfig.pagination, false),
            stopOnHover: resolveBoolean(owlConfig.stopOnHover, true),
            scrollPerPage: resolveBoolean(owlConfig.scrollPerPage, true),
            items: parseInt(owlConfig.items, 10) || 4,
            itemsDesktop: owlConfig.itemsDesktop || [1199, 4],
            itemsDesktopSmall: owlConfig.itemsDesktopSmall || [980, 3],
            itemsTablet: owlConfig.itemsTablet || [768, 2],
            itemsMobile: owlConfig.itemsMobile || [479, 1],
            slideSpeed: parseInt(owlConfig.slideSpeed, 10) || 500,
            paginationSpeed: parseInt(owlConfig.paginationSpeed, 10) || 500,
            rewindSpeed: parseInt(owlConfig.rewindSpeed, 10) || 500
        };

        if (withFirstActive) {
            options.afterAction = function () {
                if (this.$owlItems && this.$owlItems.length) {
                    this.$owlItems.removeClass('first-active');
                    this.$owlItems.eq(this.currentItem).addClass('first-active');
                }

                if (this.$elem) {
                    applyCarouselA11y($(this.$elem));
                }
            };
        }

        options.afterInit = function () {
            if (this.$elem) {
                applyCarouselA11y($(this.$elem));
                bindArrowKeyboardSupport($(this.$elem));
            }
        };

        $scope.find(carouselSelector).each(function () {
            var $carousel = $(this);

            if ($carousel.data(dataFlag)) {
                return;
            }

            $carousel.data(dataFlag, 1);

            if ($carousel.data('owlCarousel') || $carousel.hasClass('owl-carousel')) {
                if (firstOwlItemWidth($carousel) < 80) {
                    reloadOwlIfNeeded($carousel);
                }
                observeRailVisibility($carousel, options, dataFlag);
                return;
            }

            initWhenVisible($carousel, options, 15, 200, dataFlag);
            observeRailVisibility($carousel, options, dataFlag);
        });
    };
});
