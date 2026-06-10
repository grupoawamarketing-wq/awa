/**
 * Checkout loader — AWA override.
 *
 * Core Magento calls parentNode.removeChild without guards. On OPC, a separate
 * early-removal path can detach #checkout-loader before rjsResolver fires,
 * which throws TypeError and breaks downstream RequireJS callbacks.
 */
define([
    'rjsResolver'
], function (resolver) {
    'use strict';

    var FALLBACK_MS = 2000;

    /**
     * Removes loader element from DOM when still attached.
     *
     * @param {HTMLElement|null} loader
     */
    function hideLoader(loader) {
        if (loader && loader.parentNode) {
            loader.parentNode.removeChild(loader);
        }
    }

    /**
     * @param {Object} config
     * @param {HTMLElement} loader
     */
    function init(config, loader) {
        if (!loader) {
            loader = document.getElementById('checkout-loader');
        }

        if (!loader) {
            return;
        }

        resolver(hideLoader.bind(null, loader));

        // Fallback when resolver stalls during heavy OPC/KO init.
        setTimeout(function () {
            hideLoader(loader);
        }, FALLBACK_MS);
    }

    return init;
});
