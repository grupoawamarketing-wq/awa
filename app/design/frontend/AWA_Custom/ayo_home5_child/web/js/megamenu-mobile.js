/**
 * Mega Menu Mobile Accordion - AWA Motos
 * 
 * ONDE COLOCAR:
 * app/design/frontend/AWA_Custom/ayo_home5_child/web/js/megamenu-mobile.js
 * 
 * COMO CARREGAR:
 * 1. Adicione no requirejs-config.js do tema:
 *    var config = {
 *        map: { '*': { megamenuMobile: 'js/megamenu-mobile' } }
 *    };
 * 
 * 2. Adicione no template ou via layout XML:
 *    <script type="text/x-magento-init">
 *    { "*": { "megamenuMobile": {} } }
 *    </script>
 */
define(['jquery'], function($) {
    'use strict';

    return function() {
        var breakpoint = 768;

        function initMobileMenu() {
            if ($(window).width() > breakpoint) return;

            var menuSelectors = [
                '.vertical-menu .vertical-menu-content > ul > li',
                '.block-vertical-nav .block-content > ul > li'
            ].join(',');

            // Remove handlers antigos para evitar duplicacao
            $(document).off('click.megamenuMobile');

            $(document).on('click.megamenuMobile', menuSelectors + ' > a', function(e) {
                var $li = $(this).parent();
                var $submenu = $li.find('> ul, > .submenu');

                if ($submenu.length && $(window).width() <= breakpoint) {
                    e.preventDefault();
                    e.stopPropagation();

                    // Fecha outros submenus abertos no mesmo nivel
                    $li.siblings('.active').removeClass('active')
                       .find('> ul, > .submenu').slideUp(200);

                    // Toggle atual
                    $li.toggleClass('active');
                    $submenu.slideToggle(250);
                }
            });
        }

        // Init
        initMobileMenu();

        // Re-init no resize (com debounce)
        var resizeTimer;
        $(window).on('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                // Reset estados no desktop
                if ($(window).width() > breakpoint) {
                    $('.vertical-menu .active, .block-vertical-nav .active')
                        .removeClass('active');
                    $('.vertical-menu .submenu, .block-vertical-nav ul ul')
                        .removeAttr('style');
                }
                initMobileMenu();
            }, 250);
        });
    };
});
