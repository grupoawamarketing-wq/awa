/**
 * AWA Motos — Vertical Menu hover tuning wrapper
 *
 * Reuses the existing controller and normalizes desktop hover/focus behavior
 * with a configurable delay inspired by the submenu discipline used in the
 * legacy MegaMenu reference.
 *
 * @module js/vertical-menu-init-hover-tuned
 */
define([
    'jquery',
    'js/vertical-menu-init'
], function ($, baseInit) {
    'use strict';

    function isDesktop(desktopBreakpoint) {
        var breakpoint = parseInt(desktopBreakpoint, 10) || 992;

        if (window.matchMedia) {
            return window.matchMedia('(min-width: ' + breakpoint + 'px)').matches;
        }

        return window.innerWidth >= breakpoint;
    }

    function resolveLevel0SubmenuPanel($item) {
        var $panel = $item.children('.submenu, .level0.submenu, .navigation__submenu').first();
        var menuId;
        var portalNode;

        if ($panel.length) {
            return $panel;
        }

        menuId = $item.attr('data-menu');

        if (!menuId || typeof document === 'undefined') {
            return $();
        }

        portalNode = document.querySelector('.awa-vmf-portal[data-aw-vmf-li-menu="' + menuId + '"]');

        return portalNode ? $(portalNode) : $();
    }

    function setDesktopSubmenuInlineState($item, open, desktopBreakpoint) {
        var $panel;
        var panelNode;

        if (!isDesktop(desktopBreakpoint) || !$item || !$item.length) {
            return;
        }

        $panel = resolveLevel0SubmenuPanel($item);

        if (!$panel.length) {
            return;
        }

        panelNode = $panel.get(0);

        if (open) {
            $item.addClass('vmm-active');
            panelNode.style.setProperty('display', 'grid', 'important');
            panelNode.style.setProperty('visibility', 'visible', 'important');
            panelNode.style.setProperty('opacity', '1', 'important');
            panelNode.style.setProperty('pointer-events', 'auto', 'important');
            return;
        }

        $item.removeClass('vmm-active');
        panelNode.style.removeProperty('display');
        panelNode.style.removeProperty('visibility');
        panelNode.style.removeProperty('opacity');
        panelNode.style.removeProperty('pointer-events');
    }

    function closeDesktopSiblingSubmenus($nav, $activeItem, desktopBreakpoint) {
        var $targets;

        if (!isDesktop(desktopBreakpoint)) {
            return;
        }

        $targets = $nav.find('li.ui-menu-item.level0.parent');

        if ($activeItem && $activeItem.length) {
            $targets = $targets.not($activeItem);
        }

        $targets.each(function () {
            var $item = $(this);
            var $panel = resolveLevel0SubmenuPanel($item);
            var panelNode = $panel.get(0);

            $item.removeClass('vmm-active _active is-open active ui-state-active awa-vmf-active');
            $item.children('a.level-top, > a').attr('aria-expanded', 'false');
            $item.children('.open-children-toggle').attr('aria-expanded', 'false');

            if (!panelNode) {
                return;
            }

            $panel.removeClass('opened');
            panelNode.style.setProperty('display', 'none', 'important');
            panelNode.style.setProperty('visibility', 'hidden', 'important');
            panelNode.style.setProperty('opacity', '0', 'important');
            panelNode.style.setProperty('pointer-events', 'none', 'important');
        });
    }

    return function (config, element) {
        var $nav = $(element);
        var desktopBreakpoint = parseInt(config && config.desktopBreakpoint, 10) || 992;
        var hoverDelay = parseInt(config && config.hoverDelay, 10);
        var hoverTimer = null;
        var selector = 'li.ui-menu-item.level0.parent';
        var namespace = '.awaVMHoverTuned';

        if ($.isFunction(baseInit)) {
            baseInit(config, element);
        }

        if (!$nav.length) {
            return;
        }

        if (isNaN(hoverDelay) || hoverDelay < 0) {
            hoverDelay = 240;
        }

        $nav.off('mouseenter', selector);
        $nav.off('mouseleave', selector);
        $nav.off('focusin', selector);
        $nav.off('mouseenter' + namespace, selector);
        $nav.off('mouseleave' + namespace, selector);
        $nav.off('focusin' + namespace, selector);

        $nav.on('mouseenter' + namespace, selector, function () {
            var $item = $(this);

            if (!isDesktop(desktopBreakpoint)) {
                return;
            }

            clearTimeout(hoverTimer);
            hoverTimer = window.setTimeout(function () {
                closeDesktopSiblingSubmenus($nav, $item, desktopBreakpoint);
                setDesktopSubmenuInlineState($item, true, desktopBreakpoint);
            }, hoverDelay);
        });

        $nav.on('mouseleave' + namespace, selector, function () {
            var $item = $(this);

            if (!isDesktop(desktopBreakpoint)) {
                return;
            }

            clearTimeout(hoverTimer);
            setDesktopSubmenuInlineState($item, false, desktopBreakpoint);
        });

        $nav.on('focusin' + namespace, selector, function () {
            var $item = $(this);

            if (!isDesktop(desktopBreakpoint)) {
                return;
            }

            clearTimeout(hoverTimer);
            closeDesktopSiblingSubmenus($nav, $item, desktopBreakpoint);
            setDesktopSubmenuInlineState($item, true, desktopBreakpoint);
        });
    };
});
