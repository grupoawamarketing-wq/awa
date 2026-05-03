/* global define */

define([
    'jquery',
    'js/rokanthemes-owl-element-init.min'
], function ($, owlShim) {
    'use strict';

    // Polyfill jQuery method so legacy scripts don't crash
    if (!$.fn.owlCarousel) {
        $.fn.owlCarousel = function(options) {
            if (options === 'destroy') return this; // Ignore destroy calls
            return this.each(function() {
                // owlShim accepts (config, element)
                owlShim(options, this);
            });
        };
    }

    return $.fn.owlCarousel;
});
