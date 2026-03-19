define([
    'jquery',
    'js/vertical-menu-init'
], function ($, baseInit) {
    'use strict';

    function ensureId($el, prefix, index) {
        if (!$el || !$el.length) {
            return '';
        }

        if ($el.attr('id')) {
            return $el.attr('id');
        }

        var id = (prefix || 'awa-vm') + '-' + (index || 0);
        $el.attr('id', id);

        return id;
    }

    function syncMenuState($nav) {
        var $list = $nav.find('.togge-menu').first();
        var $title = $nav.find('.title-category-dropdown').first();
        var open = $list.hasClass('menu-open') || ($list.is(':visible') && !window.matchMedia('(min-width: 992px)').matches);

        $nav.toggleClass('is-open', !!open);
        $list.toggleClass('is-open', !!open);
        $title.toggleClass('is-open', !!open);
        $list.attr('aria-hidden', open ? 'false' : 'true');

        if (window.matchMedia('(max-width: 991px)').matches) {
            $('body').toggleClass('awa-vertical-menu-open', !!open);
        } else {
            $('body').removeClass('awa-vertical-menu-open');
        }
    }

    function syncSubmenuState($nav) {
        $nav.find('.ui-menu-item.parent, .ui-menu-item.level0.parent').each(function (idx) {
            var $item = $(this);
            var $toggle = $item.children('.open-children-toggle').first();
            var $panel = $item.children('.submenu, ul.level0, .subchildmenu').first();
            var linkText = $.trim($item.children('a').first().text()) || 'submenu';
            var isOpen = $item.hasClass('_active') || $item.hasClass('active') || ($panel.length && $panel.hasClass('opened'));

            $item.attr('data-awa-parent-item', 'true').toggleClass('is-open', !!isOpen);

            if ($toggle.length) {
                var panelId = ensureId($panel, 'awa-vm-panel', idx);

                $toggle.attr({
                    'data-awa-toggle': 'submenu',
                    'aria-expanded': isOpen ? 'true' : 'false',
                    'aria-label': $toggle.attr('aria-label') || ('Expandir subcategorias de ' + linkText)
                });

                if (panelId) {
                    $toggle.attr('aria-controls', panelId);
                }
            }

            if ($panel.length) {
                $panel.attr('aria-hidden', isOpen ? 'false' : 'true')
                    .toggleClass('is-open', !!isOpen)
                    .attr('data-awa-panel', 'submenu');
            }
        });
    }

    return function (config, element) {
        var $nav = $(element);
        var observer;

        if (!$nav.length || $nav.data('awaVMCompatInit')) {
            return;
        }

        baseInit(config, element);

        $nav.attr({
            'data-awa-component': $nav.attr('data-awa-component') || ((config && config.componentName) || 'vertical-menu'),
            'data-awa-initialized': 'true'
        }).addClass('is-ready');

        syncSubmenuState($nav);
        syncMenuState($nav);

        $nav.on('click.awaVMCompat keyup.awaVMCompat transitionend.awaVMCompat', function () {
            window.setTimeout(function () {
                syncSubmenuState($nav);
                syncMenuState($nav);
            }, 0);
        });

        $(window).on('resize.awaVMCompat', function () {
            syncSubmenuState($nav);
            syncMenuState($nav);
        });

        if (typeof window.MutationObserver === 'function') {
            observer = new window.MutationObserver(function () {
                syncSubmenuState($nav);
                syncMenuState($nav);
            });

            observer.observe($nav.get(0), {
                subtree: true,
                childList: true,
                attributes: true,
                attributeFilter: ['class', 'style', 'aria-expanded']
            });

            $nav.data('awaVMCompatObserver', observer);
        }

        $nav.data('awaVMCompatInit', 1);
    };
});
