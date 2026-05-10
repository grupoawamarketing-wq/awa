/**
 * AWA Motos — Vertical Menu Mobile Drill-Down (v1)
 *
 * Transforma a navegação mobile do menu vertical de accordion em
 * drill-down por nível: clicar em uma categoria com sub-itens
 * desliza o painel atual para a esquerda e exibe o sub-nível.
 *
 * Ativa APENAS em viewport < desktopBreakpoint (padrão 992px).
 * Em desktop, comporta-se como no-op para não interferir no flyout.
 *
 * @module js/vertical-menu-drill
 */
define([
    'jquery'
], function ($) {
    'use strict';

    var DEFAULTS = {
        desktopBreakpoint: 992,
        animDuration: 240,
        animEasing: 'swing'
    };

    function debounce(fn, ms) {
        var t;
        return function () {
            clearTimeout(t);
            var ctx  = this;
            var args = arguments;
            t = setTimeout(function () { fn.apply(ctx, args); }, ms || 120);
        };
    }

    return function (config, element) {
        var cfg  = $.extend({}, DEFAULTS, config);
        var $nav = $(element);

        if ($nav.data('awaDrillInit')) { return; }
        $nav.data('awaDrillInit', 1);

        var NS = '.awaDrill-' + Math.random().toString(36).slice(2);
        var mql = window.matchMedia
            ? window.matchMedia('(min-width: ' + cfg.desktopBreakpoint + 'px)')
            : null;

        function isMobile() {
            return mql ? !mql.matches : window.innerWidth < cfg.desktopBreakpoint;
        }

        var $list = $nav.find('.togge-menu').first();
        if (!$list.length) { return; }

        var drillStack  = [];
        var isBuilt     = false;
        var isAnimating = false;

        /* ---- Monta estrutura de painéis ---- */
        function buildDrillStage() {
            if (isBuilt) { return; }
            isBuilt = true;

            var $stage = $('<div class="vmm-drill-stage" aria-live="polite"></div>');
            $list.prepend($stage);

            var $root = $('<ul class="vmm-drill-panel" role="menu" data-level="0" aria-label="Categorias"></ul>');
            $list.children('li').not('.vmm-drill-back-bar').appendTo($root);
            $stage.append($root);

            var $backBar = $('<div class="vmm-drill-back-bar" style="display:none">' +
                '<button type="button" class="vmm-drill-back-btn" aria-label="Voltar">' +
                    '<span class="vmm-drill-back-icon" aria-hidden="true">&#8592;</span>' +
                    '<span class="vmm-drill-back-label">Voltar</span>' +
                '</button>' +
                '<span class="vmm-drill-breadcrumb"></span>' +
            '</div>');
            $list.prepend($backBar);
            $backBar.find('.vmm-drill-back-btn').on('click' + NS, drillBack);

            drillStack = [{ $panel: $root, label: 'Categorias' }];
        }

        function buildSubPanel(level, label, $li) {
            var $panel = $('<ul class="vmm-drill-panel" role="menu"' +
                ' data-level="' + level + '"' +
                ' aria-label="' + $('<div>').text(label).html() + '"' +
            '></ul>');

            var $subList = $li.children('.submenu, ul.level0, .subchildmenu').first();
            if ($subList.length) {
                var parentHref = ($li.children('a').first().attr('href') || '#');
                var parentText = ($li.children('a').first().text() || label).trim();

                var $allLi = $('<li class="ui-menu-item vmm-drill-see-all" role="menuitem">' +
                    '<a href="' + $('<div>').text(parentHref).html() + '" class="level-top vmm-drill-see-all-link">' +
                        'Ver todos em ' + $('<div>').text(parentText).html() +
                    '</a>' +
                '</li>');
                $panel.append($allLi);

                /* A estrutura e: submenu > .row > UL.subchildmenu > LI */
                var $innerUl = $subList.find('ul.subchildmenu, ul').first();
                var $liSource = $innerUl.length ? $innerUl.children('li') : $subList.children('li');
                $liSource.each(function () {
                    $panel.append($(this).clone(true, true));
                });
            }
            return $panel;
        }

        /* ---- Drill In: desliza para sub-nível ---- */
        function drillIn($li) {
            if (isAnimating) { return; }

            var $subList = $li.children('.submenu, ul.level0, .subchildmenu').first();
            if (!$subList.length) { return; }

            var label     = ($li.children('a').first().text() || '').trim();
            var $stage    = $list.find('.vmm-drill-stage');
            var $current  = drillStack[drillStack.length - 1].$panel;
            var $newPanel = buildSubPanel(drillStack.length, label, $li);

            $stage.append($newPanel);
            $stage.css({ position: 'relative', overflow: 'hidden', height: $current.outerHeight() });
            $newPanel.css({ position: 'absolute', top: 0, left: '100%', width: '100%' });

            isAnimating = true;

            /* posicionar $current como absolute para animação left */
            $current.css({ position: 'absolute', top: 0, left: '0', width: '100%' });

            $current.animate({ left: '-100%' }, cfg.animDuration, cfg.animEasing);
            $newPanel.animate({ left: '0%' }, cfg.animDuration, cfg.animEasing, function () {
                $current.css({ display: 'none', left: '0' });
                $newPanel.css({ position: 'relative', top: '', left: '', width: '' });
                $stage.css({ height: '' });
                isAnimating = false;
                $newPanel.find('a').first().trigger('focus');
            });

            drillStack.push({ $panel: $newPanel, label: label });
            updateBackBar();
        }

        /* ---- Drill Back: volta ao nível anterior ---- */
        function drillBack() {
            if (isAnimating || drillStack.length <= 1) { return; }

            var $stage   = $list.find('.vmm-drill-stage');
            var $current = drillStack[drillStack.length - 1].$panel;
            var $prev    = drillStack[drillStack.length - 2].$panel;

            $prev.css({ display: '', position: 'absolute', top: 0, left: '-100%', width: '100%' });
            $stage.css({ position: 'relative', overflow: 'hidden', height: $current.outerHeight() });

            isAnimating = true;

            /* posicionar $current como absolute para animação left */
            $current.css({ position: 'absolute', top: 0, left: '0', width: '100%' });

            $current.animate({ left: '100%' }, cfg.animDuration, cfg.animEasing);
            $prev.animate({ left: '0%' }, cfg.animDuration, cfg.animEasing, function () {
                $current.remove();
                $prev.css({ position: 'relative', top: '', left: '', width: '' });
                $stage.css({ height: '' });
                isAnimating = false;
                $prev.find('a').first().trigger('focus');
            });

            drillStack.pop();
            updateBackBar();
        }

        function updateBackBar() {
            var $backBar = $list.find('.vmm-drill-back-bar');
            if (drillStack.length <= 1) { $backBar.hide(); return; }
            $backBar.show();
            var crumbs = drillStack.map(function (s) { return s.label; });
            crumbs.shift();
            $backBar.find('.vmm-drill-breadcrumb').text(crumbs.join(' \u203a '));
        }

        /* ---- Intercepta cliques em itens-pai (apenas mobile) ---- */
        function bindDrillHandlers() {
            $list.on('click' + NS,
                '.vmm-drill-panel li.parent > a, ' +
                '.vmm-drill-panel li.level0.parent > a, ' +
                '.vmm-drill-panel li.parent .open-children-toggle, ' +
                '.vmm-drill-panel li.level0.parent .open-children-toggle',
                function (e) {
                    if (!isMobile()) { return; }
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    drillIn($(this).closest('li'));
                }
            );
        }

        /* ---- Reset ao voltar para desktop ---- */
        function resetDrill() {
            if (!isBuilt || drillStack.length <= 1) { return; }
            while (drillStack.length > 1) { drillStack.pop().$panel.remove(); }
            drillStack[0].$panel.css({ position: '', top: '', left: '', width: '', display: '' });
            $list.find('.vmm-drill-stage').css({ height: '' });
            updateBackBar();
        }

        /* ---- Teclado: Escape volta ---- */
        function bindKeyHandlers() {
            $list.on('keydown' + NS, function (e) {
                if (!isMobile()) { return; }
                if ((e.key === 'Escape' || e.key === 'Esc') && drillStack.length > 1) {
                    e.preventDefault();
                    e.stopPropagation();
                    drillBack();
                }
            });
        }

        /* ---- Swipe direita → voltar ---- */
        function bindSwipe() {
            var touchStartX = 0;
            $list.on('touchstart' + NS, function (e) {
                touchStartX = e.originalEvent.touches[0].clientX;
            });
            $list.on('touchend' + NS, function (e) {
                var dx = e.originalEvent.changedTouches[0].clientX - touchStartX;
                if (dx > 60 && drillStack.length > 1) { drillBack(); }
            });
        }

        function activate() {
            if (!isMobile()) { return; }
            buildDrillStage();
            bindDrillHandlers();
            bindKeyHandlers();
            bindSwipe();
        }

        $(window).on('resize' + NS, debounce(function () {
            if (!isMobile()) { resetDrill(); }
        }, 200));

        $nav.on('vmm:opened' + NS, function () {
            if (isMobile() && !isBuilt) { activate(); }
            if (isMobile()) { resetDrill(); }
        });

        activate();

        $nav.on('remove' + NS, function () {
            $(window).off(NS);
            $list.off(NS);
            $nav.off(NS);
        });
    };
});
