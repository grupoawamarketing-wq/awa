/* global define */

define([
    'jquery',
    'rokanthemes/owl'
], function ($) {
    'use strict';

    var INT_KEYS  = ['items', 'slideSpeed', 'paginationSpeed', 'rewindSpeed'];
    var BOOL_KEYS = ['lazyLoad', 'navigation', 'pagination', 'autoPlay', 'stopOnHover', 'scrollPerPage'];

    function resolveBoolean(value, fallback) {
        if (value === undefined || value === null || value === '') {
            return fallback;
        }
        if (typeof value === 'string') {
            return !(value === 'false' || value === '0');
        }
        return !!value;
    }

    function normalizeOptions(rawOptions) {
        var options = rawOptions || {};
        var i, key, v;

        for (i = 0; i < INT_KEYS.length; i++) {
            key = INT_KEYS[i];
            if (options[key] !== undefined) {
                v = parseInt(options[key], 10);
                options[key] = v || options[key];
            }
        }

        for (i = 0; i < BOOL_KEYS.length; i++) {
            key = BOOL_KEYS[i];
            if (options[key] !== undefined) {
                options[key] = resolveBoolean(options[key], options[key]);
            }
        }

        return options;
    }

    function initWhenReady($el, options, attemptsLeft) {
        var remaining = (attemptsLeft !== undefined) ? attemptsLeft : 8;

        if (!$el || !$el.length) {
            return;
        }

        if (typeof $el.owlCarousel === 'function') {
            try {
                $el.owlCarousel(options);
                $el.addClass('owl-loaded');
            } catch (e) {
                $el.removeData('awaOwlElementInit');
            }
            return;
        }

        if (remaining <= 0) {
            $el.removeData('awaOwlElementInit');
            return;
        }

        setTimeout(function () {
            initWhenReady($el, options, remaining - 1);
        }, 150);
    }

    /**
     * Magento initializer for Owl Carousel v1.
     *
     * Pattern A — bind directly to the .owl element:
     *   ".rokan-featuredproduct .owl": {
     *     "js/rokanthemes-owl-element-init": { items: 4, navigation: true, ... }
     *   }
     *
     * Pattern B — bind to container, find carousel inside via carouselSelector:
     *   ".rokan-featuredproduct": {
     *     "js/rokanthemes-owl-element-init": {
     *       "carouselSelector": ".owl",
     *       "owl": { items: 4, navigation: true, ... }
     *     }
     *   }
     */
    return function (config, element) {
        var cfg = config || {};
        var $container = $(element);
        var $el, owlOptions;

        if (!$container.length) {
            return;
        }

        if (cfg.carouselSelector) {
            /* Pattern B */
            $el = $container.find(cfg.carouselSelector).first();
            owlOptions = normalizeOptions(cfg.owl ? $.extend({}, cfg.owl) : {});
        } else {
            /* Pattern A — element IS the carousel */
            $el = $container;
            owlOptions = normalizeOptions($.extend({}, cfg));
        }

        if (!$el.length) {
            return;
        }

        if ($el.data('owlCarousel') || $el.hasClass('owl-loaded') || $el.data('awaOwlElementInit')) {
            return;
        }

        $el.data('awaOwlElementInit', 1);
        initWhenReady($el, owlOptions, 8);
    };
});
