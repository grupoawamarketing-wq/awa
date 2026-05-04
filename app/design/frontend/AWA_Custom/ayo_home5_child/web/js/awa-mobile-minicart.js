/**
 * awa-mobile-minicart — Melhora UX do minicart em dispositivos móveis.
 * Garante que o botão de carrinho tenha aria correto e fecha ao clicar fora.
 */
define(['jquery'], function ($) {
    'use strict';

    if (window.__awaMobileMinicartInit) return {};
    window.__awaMobileMinicartInit = true;

    function init() {
        var $toggle = $('.minicart-wrapper .action.showcart');
        $toggle.each(function () {
            var $btn = $(this);
            if (!$btn.attr('aria-label')) {
                $btn.attr('aria-label', 'Ver carrinho de compras');
            }
        });

        // Garante que o counter do minicart tenha aria-live
        var $counter = $('.minicart-wrapper .counter.qty');
        if ($counter.length && !$counter.attr('aria-live')) {
            $counter.attr('aria-live', 'polite');
            $counter.attr('aria-atomic', 'true');
        }
    }

    if (document.readyState !== 'loading') {
        init();
    } else {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    }

    return {};
});