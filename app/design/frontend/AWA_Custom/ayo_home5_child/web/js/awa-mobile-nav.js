/**
 * awa-mobile-nav — Garante que o drawer mobile do menu funcione corretamente.
 * Complementa o comportamento nativo do RokanThemes para o hamburguer mobile.
 */
define(['jquery'], function ($) {
    'use strict';

    if (window.__awaMobileNavInit) return {};
    window.__awaMobileNavInit = true;

    function init() {

        // Fecha o drawer ao clicar fora (overlay)
        $(document).on('click', '.page-wrapper', function (e) {
            if ($(document.body).hasClass('nav-open') &&
                !$(e.target).closest('.nav-sections, .nav-toggle').length) {
                var $closeBtn = $('.nav-toggle');
                if ($closeBtn.length) $closeBtn.trigger('click');
            }
        });
    }

    if (document.readyState !== 'loading') {
        init();
    } else {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    }

    return {};
});