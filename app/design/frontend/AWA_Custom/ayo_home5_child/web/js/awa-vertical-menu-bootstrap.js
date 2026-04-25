define([
    'jquery',
    'js/vertical-menu-init'
], function ($, initVerticalMenu) {
    'use strict';

    function parseLimit($nav) {
        var $list = $nav.find('.togge-menu').first();
        var limit = 0;

        if (!$list.length) {
            return 0;
        }

        limit = parseInt($list.attr('data-limit-show'), 10);

        if (isNaN(limit) || limit < 0) {
            return 0;
        }

        return limit;
    }

    function bootstrapMenus() {
        $('[data-role="awa-vertical-menu"]').each(function () {
            var $nav = $(this);

            if ($nav.attr('data-awa-vm-bootstrapped') !== '1') {
                initVerticalMenu({
                    desktopBreakpoint: 992,
                    overlaySelector: '.shadow_bkg_show',
                    limitShow: parseLimit($nav)
                }, this);

                $nav.attr('data-awa-vm-bootstrapped', '1');
            }

            wireDesktopHoverFallback(this);
        });
    }

    function wireDesktopHoverFallback(navEl) {
        var items;

        if (!navEl) {
            return;
        }

        items = navEl.querySelectorAll('.togge-menu > li.ui-menu-item.level0.parent');

        items.forEach(function (item) {
            if (item.getAttribute('data-awa-vm-hover-wired') === '1') {
                return;
            }

            item.setAttribute('data-awa-vm-hover-wired', '1');

            function setOpenState(open) {
                var panel;

                if (window.innerWidth < 992) {
                    return;
                }

                panel = item.querySelector(':scope > .submenu, :scope > .level0.submenu');

                if (!panel) {
                    return;
                }

                item.classList.toggle('vmm-active', open);
                panel.style.visibility = open ? 'visible' : 'hidden';
                panel.style.opacity = open ? '1' : '0';
                panel.style.pointerEvents = open ? 'auto' : 'none';
            }

            item.addEventListener('mouseenter', function () {
                setOpenState(true);
            });

            item.addEventListener('mouseleave', function () {
                setOpenState(false);
            });

            item.addEventListener('focusin', function () {
                setOpenState(true);
            });

            item.addEventListener('focusout', function (event) {
                if (event.relatedTarget && item.contains(event.relatedTarget)) {
                    return;
                }

                setOpenState(false);
            });
        });
    }

    $(bootstrapMenus);

    (function retryForEsiMenu() {
        var attempts = 0;
        var maxAttempts = 40;
        var timer = window.setInterval(function () {
            attempts += 1;
            bootstrapMenus();

            if (attempts >= maxAttempts) {
                window.clearInterval(timer);
            }
        }, 500);
    }());

    return {};
});
