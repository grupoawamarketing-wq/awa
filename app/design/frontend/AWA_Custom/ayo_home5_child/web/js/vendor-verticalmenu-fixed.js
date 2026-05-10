/**
 * AWA Motos — verticalmenu fix [A1] [A2]
 *
 * Override do vendor Rokanthemes_VerticalMenu/js/verticalmenu.js.
 *
 * [A1] jQuery .bind()/.unbind() substituídos por .on()/.off() (API moderna).
 * [A2] IIFE não-AMD convertido para define() AMD (sem necessidade de shim).
 *
 * @see app/code/Rokanthemes/VerticalMenu/view/frontend/web/js/verticalmenu.js
 */
define(['jquery'], function ($) {
    'use strict';

    $.fn.VerticalMenu = function () {
        var $nav = $(this);

        // ── Posição inicial dos submenus: fora da tela ─────────────────────
        $nav.find(
            'li.classic .submenu,' +
            'li.staticwidth .submenu,' +
            'li.classic .subchildmenu .subchildmenu'
        ).css({ left: '-9999px', right: 'auto' });

        // ── Submenu de terceiro nível: abre à esq. ou dir. conforme espaço ─
        $nav.find('li.classic .subchildmenu > li.parent').on('mouseover', function () {
            var $popup   = $(this).children('ul.subchildmenu');
            let wWidth   = $(window).innerWidth();
            let pos      = $(this).offset();
            let cWidth   = $popup.outerWidth();

            if (wWidth <= pos.left + $(this).outerWidth() + cWidth) {
                $popup.css({ left: 'auto', right: '100%', borderRadius: '6px 0 6px 6px' });
            } else {
                $popup.css({ left: '100%', right: 'auto', borderRadius: '0 6px 6px 6px' });
            }
        });

        // ── Submenu de nível 0: abre à esq. ou dir. conforme espaço ───────
        $nav.find('li.staticwidth.parent, li.classic.parent').on('mouseover', function () {
            var $popup = $(this).children('.submenu');
            let wWidth = $(window).innerWidth();
            let pos    = $(this).offset();
            let cWidth = $popup.outerWidth();

            if (wWidth <= pos.left + $(this).outerWidth() + cWidth) {
                $popup.css({ left: 'auto', right: '0', borderRadius: '6px 0 6px 6px' });
            } else {
                $popup.css({ left: '0', right: 'auto', borderRadius: '0 6px 6px 6px' });
            }
        });

        // ── Resize: reposiciona submenus ───────────────────────────────────
        $(window).on('resize.verticalmenu', function () {
            $nav.find(
                'li.classic .submenu,' +
                'li.staticwidth .submenu,' +
                'li.classic .subchildmenu .subchildmenu'
            ).css({ left: '-9999px', right: 'auto' });
        });

        // ── Toggle mobile — nível 0 ────────────────────────────────────────
        // .off() antes de .on() evita listeners duplicados (mesmo que .unbind() fazia)
        $('.navigation.verticalmenu li.ui-menu-item > .open-children-toggle')
            .off('click.vmenu-l0')
            .on('click.vmenu-l0', function () {
                var $submenu = $(this).parent().children('.submenu');
                var $link    = $(this).parent().children('a');
                if ($submenu.hasClass('opened')) {
                    $submenu.removeClass('opened');
                    $link.removeClass('ui-state-active');
                } else {
                    $submenu.addClass('opened');
                    $link.addClass('ui-state-active');
                }
            });

        // ── Toggle mobile — sub-nível ─────────────────────────────────────
        $('.navigation.verticalmenu .submenu .subchildmenu li.ui-menu-item > .open-children-toggle')
            .off('click.vmenu-sub')
            .on('click.vmenu-sub', function () {
                var $subchild = $(this).parent().children('.subchildmenu');
                var $link     = $(this).parent().children('a');
                if ($subchild.hasClass('opened')) {
                    $subchild.removeClass('opened').hide();
                    $link.removeClass('ui-state-active');
                } else {
                    $subchild.addClass('opened').show();
                    $link.addClass('ui-state-active');
                }
            });

        return $nav;
    };

    return $.fn.VerticalMenu;
});
