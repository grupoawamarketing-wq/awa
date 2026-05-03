define([
    'jquery',
    'mage/mage',
    'rokanthemes/owl'
], function ($) {
    'use strict';

    var accordionEnabled = null;
    var expandedAccordionKeys = {};
    var isInitialized = false;

    function initBrandCarousel() {
        var $carousel = $('.block-content.brandowl-play > ul');

        if (!$carousel.length || typeof $carousel.owlCarousel !== 'function') {
            return;
        }

        $carousel.owlCarousel({
            lazyLoad: true,
            items: 7,
            itemsDesktop: [1366, 5],
            itemsDesktopSmall: [991, 3],
            itemsTablet: [767, 2],
            itemsMobile: [479, 1],
            navigation: true,
            afterAction: function () {
                this.$owlItems.removeClass('first-active');
                this.$owlItems.eq(this.currentItem).addClass('first-active');
            }
        });
    }

    function getFooterTitleText($title) {
        return ($title.text() || '').replace(/\s+/g, ' ').trim();
    }

    function updateFooterTitleAriaLabel($title, expanded) {
        var titleText = getFooterTitleText($title);
        if (!titleText) {
            titleText = 'seção do rodapé';
        }

        $title.attr('aria-label', (expanded ? 'Recolher ' : 'Expandir ') + titleText);
        $title.data('footerAddedAriaLabel', 1);
    }

    function clearFooterTitleAriaLabelIfAdded($title) {
        if ($title.data('footerAddedAriaLabel')) {
            $title.removeAttr('aria-label');
            $title.removeData('footerAddedAriaLabel');
        }
    }

    function prefersReducedMotion() {
        if (!window.matchMedia) {
            return false;
        }

        return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    function applyA11y($title, $content, idx) {
        var contentId = $content.attr('id');
        var titleId = $title.attr('id');

        if (!contentId) {
            contentId = 'footer-mobile-content-' + idx;
            $content.attr('id', contentId);
        }

        if (!titleId) {
            titleId = 'footer-mobile-title-' + idx;
            $title.attr('id', titleId);
        }

        // ARIA 1.2 §6.5: role="button" não é permitido em heading elements (h1-h6).
        // Acordeão funciona via aria-expanded + aria-controls + tabindex + keydown.
        var tagName = ($title.prop('tagName') || '').toLowerCase();
        if (!$title.attr('role') && !/^h[1-6]$/.test(tagName)) {
            $title.attr('role', 'button');
        }

        if (!$title.attr('tabindex')) {
            $title.attr('tabindex', '0');
        }

        $title.attr({
            'aria-controls': contentId,
            'aria-expanded': 'false'
        });

        $content.attr({
            'aria-hidden': 'true',
            'role': 'region',
            'aria-labelledby': titleId
        });
    }

    function setExpandedState($title, $content, expanded) {
        $title.toggleClass('active', expanded);
        $title.attr('aria-expanded', expanded ? 'true' : 'false');
        $content.attr('aria-hidden', expanded ? 'false' : 'true');
    }

    function enableAccordion() {
        var $titles = $('.velaFooterMenu .velaFooterTitle');
        var reduceMotion = prefersReducedMotion();

        $titles.each(function (idx) {
            var $title = $(this);
            var $content = $title.closest('.velaFooterMenu').find('.velaContent').first();

            if (!$content.length) {
                return;
            }

            applyA11y($title, $content, idx);

            var key = $title.attr('aria-controls') || String(idx);
            var shouldExpand = !!expandedAccordionKeys[key];

            setExpandedState($title, $content, shouldExpand);
            updateFooterTitleAriaLabel($title, shouldExpand);

            $content.stop(true, true);
            if (shouldExpand) {
                $content.show();
            } else {
                $content.hide();
            }
        });

        $titles.off('.accordionFooter')
            .on('click.accordionFooter', function (e) {
                var $title = $(this);
                var $content = $title.closest('.velaFooterMenu').find('.velaContent').first();

                if (!$content.length) {
                    return;
                }

                e.preventDefault();

                var isExpanded = $title.attr('aria-expanded') === 'true';
                var nextExpanded = !isExpanded;
                var key = $title.attr('aria-controls') || String($titles.index($title));

                setExpandedState($title, $content, nextExpanded);
                updateFooterTitleAriaLabel($title, nextExpanded);

                expandedAccordionKeys[key] = nextExpanded;

                $content.stop(true, true);
                if (reduceMotion) {
                    if (nextExpanded) {
                        $content.show();
                    } else {
                        $content.hide();
                    }
                } else {
                    $content.slideToggle(220);
                }
            })
            .on('keydown.accordionFooter', function (e) {
                var key = e.key || e.keyCode;

                if (key === 'Enter' || key === 13 || key === ' ' || key === 'Spacebar' || key === 32) {
                    e.preventDefault();
                    $(this).trigger('click');
                }
            });
    }

    function disableAccordion() {
        var $titles = $('.velaFooterMenu .velaFooterTitle');

        $titles.each(function () {
            var $title = $(this);
            var $content = $title.closest('.velaFooterMenu').find('.velaContent').first();

            $title.removeClass('active');
            $title.off('.accordionFooter');
            clearFooterTitleAriaLabelIfAdded($title);

            if ($content.length) {
                $content.stop(true, true).show();
                $content.attr('aria-hidden', 'false');
            }
        });
    }

    function isNavOpen() {
        var docEl = document.documentElement;
        var htmlOpen = !!(docEl && docEl.classList && docEl.classList.contains('nav-open'));

        return htmlOpen || $('body').hasClass('nav-open');
    }

    function ensureNavSectionsId() {
        var $navSections = $('.nav-sections').first();

        if (!$navSections.length) {
            return null;
        }

        if (!$navSections.attr('id')) {
            $navSections.attr('id', 'nav-sections');
        }

        return $navSections.attr('id');
    }

    function syncFooterMenuToggleState($toggle) {
        if (!$toggle || !$toggle.length) {
            return;
        }

        var navSectionsId = ensureNavSectionsId();
        if (navSectionsId) {
            $toggle.attr('aria-controls', navSectionsId);
        }

        if (!$toggle.attr('aria-haspopup')) {
            $toggle.attr('aria-haspopup', 'true');
        }

        var open = isNavOpen();
        $toggle.attr('aria-expanded', open ? 'true' : 'false');

        var currentLabel = ($toggle.attr('aria-label') || '').trim();
        if (!currentLabel) {
            $toggle.attr('aria-label', open ? 'Fechar menu' : 'Abrir menu');
        } else if (currentLabel === 'Abrir menu' || currentLabel === 'Fechar menu') {
            $toggle.attr('aria-label', open ? 'Fechar menu' : 'Abrir menu');
        }
    }

    function bindFixedBottomMenu() {
        var $toggle = $('.fixed-bottom .toggle-nav-footer');

        if (!$toggle.length) {
            return;
        }

        $toggle.each(function () {
            var $el = $(this);

            if (!$el.attr('role')) {
                $el.attr('role', 'button');
            }

            if (!$el.attr('tabindex')) {
                $el.attr('tabindex', '0');
            }

            if (!$el.attr('aria-haspopup')) {
                $el.attr('aria-haspopup', 'true');
            }

            if (!$el.attr('aria-label')) {
                $el.attr('aria-label', 'Abrir menu');
            }
        });

        var navSectionsId = ensureNavSectionsId();
        if (navSectionsId && !$toggle.attr('aria-controls')) {
            $toggle.attr('aria-controls', navSectionsId);
        }

        syncFooterMenuToggleState($toggle);

        if (window.MutationObserver) {
            var navObserver = new MutationObserver(function () {
                syncFooterMenuToggleState($toggle);
            });

            try {
                navObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
            } catch (e) {
                // Ignore observer failures
            }

            try {
                navObserver.observe(document.body, { attributes: true, attributeFilter: ['class'] });
            } catch (e2) {
                // Ignore observer failures
            }
        }

        $toggle.off('.fixedBottom')
            .on('click.fixedBottom', function (e) {
                e.preventDefault();

                var wasOpen = isNavOpen();

                setTimeout(function () {
                    var isOpenNow = isNavOpen();

                    if (isOpenNow === wasOpen) {
                        var $navToggle = $('.action.nav-toggle, .nav-toggle').first();
                        if ($navToggle.length) {
                            $navToggle.trigger('click');
                        }
                    }

                    syncFooterMenuToggleState($toggle);
                }, 60);
            })
            .on('keydown.fixedBottom', function (e) {
                var key = e.key || e.keyCode;

                if (key === 'Enter' || key === 13 || key === ' ' || key === 'Spacebar' || key === 32) {
                    e.preventDefault();
                    $(this).trigger('click');
                }
            });
    }

    function updateFixedBottomPadding() {
        var shouldEnable = $(window).width() <= 767;
        var $fixedBottom = $('.fixed-bottom').first();

        if (shouldEnable && $fixedBottom.length) {
            $('body').addClass('has-fixed-bottom');

            var fixedHeight = $fixedBottom.outerHeight();
            if (!fixedHeight || fixedHeight < 1) {
                fixedHeight = 70;
            }

            try {
                document.body.style.setProperty('--fixed-bottom-height', fixedHeight + 'px');
            } catch (e) {
                // Ignore style set failures
            }
        } else {
            $('body').removeClass('has-fixed-bottom');

            try {
                document.body.style.removeProperty('--fixed-bottom-height');
            } catch (e2) {
                // Ignore style remove failures
            }
        }
    }

    function responsiveResize() {
        var shouldEnable = $(window).width() <= 767;
        var breakpointChanged = (accordionEnabled !== shouldEnable);

        if (breakpointChanged) {
            accordionEnabled = shouldEnable;

            if (shouldEnable) {
                enableAccordion();
            } else {
                disableAccordion();
            }
        }

        updateFixedBottomPadding();
    }

    return function () {
        $(function () {
            if (isInitialized) {
                return;
            }

            isInitialized = true;

            initBrandCarousel();
            bindFixedBottomMenu();
            updateFixedBottomPadding();

            responsiveResize();

            $(window)
                .on('resize.footerMobile', responsiveResize)
                .on('orientationchange.footerMobile', function () {
                    responsiveResize();
                })
                .on('load.footerMobile', function () {
                    updateFixedBottomPadding();
                });
        });
    };
});
