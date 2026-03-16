define([
    'jquery',
    'js/custom-menu-init'
], function ($, baseInit) {
    'use strict';

    function ensureId($el, prefix, index) {
        if (!$el || !$el.length) {
            return '';
        }

        if ($el.attr('id')) {
            return $el.attr('id');
        }

        var id = (prefix || 'awa-menu') + '-' + index;
        $el.attr('id', id);

        return id;
    }

    function syncMenuTree($root) {
        var $menuList = $root.children('ul').first();

        $root.attr({
            'data-awa-component': $root.attr('data-awa-component') || 'main-nav',
            'data-awa-initialized': 'true'
        }).addClass('is-ready');

        if ($menuList.length) {
            $menuList.attr({
                'data-awa-panel': 'main-nav-list',
                'role': $menuList.attr('role') || 'menubar'
            });
        }

        $root.find('li').each(function (idx) {
            var $item = $(this);
            var $toggle = $item.children('.open-children-toggle').first();
            var $submenu = $item.children('.submenu, .groupmenu, .subchildmenu').first();
            var isOpen = $item.hasClass('active') || $item.hasClass('_active') || ($submenu.length && ($submenu.hasClass('opened') || $submenu.hasClass('active') || $submenu.is(':visible')));

            if (!$submenu.length) {
                return;
            }

            $item.attr('data-awa-parent-item', 'true').toggleClass('is-open', !!isOpen);
            $submenu.attr({
                'data-awa-panel': 'submenu',
                'aria-hidden': isOpen ? 'false' : 'true'
            }).toggleClass('is-open', !!isOpen);

            if ($toggle.length) {
                $toggle.attr({
                    'data-awa-toggle': 'submenu',
                    'aria-expanded': isOpen ? 'true' : 'false'
                });

                $toggle.attr('aria-controls', ensureId($submenu, 'awa-main-nav-submenu', idx));
            }
        });
    }

    return function (config, element) {
        var $root = $(element);
        var observer;

        if (!$root.length || $root.data('awaMainNavCompatInit')) {
            return;
        }

        baseInit(config, element);
        syncMenuTree($root);

        $root.on('click.awaMainNavCompat keyup.awaMainNavCompat mouseup.awaMainNavCompat', function () {
            window.setTimeout(function () {
                syncMenuTree($root);
            }, 0);
        });

        $(window).on('resize.awaMainNavCompat', function () {
            syncMenuTree($root);
        });

        if (typeof window.MutationObserver === 'function') {
            observer = new window.MutationObserver(function () {
                syncMenuTree($root);
            });

            observer.observe($root.get(0), {
                subtree: true,
                childList: true,
                attributes: true,
                attributeFilter: ['class', 'style', 'aria-expanded']
            });

            $root.data('awaMainNavCompatObserver', observer);
        }

        $root.data('awaMainNavCompatInit', 1);
    };
});
