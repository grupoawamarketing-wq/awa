/**
 * Bootstrap AMD da storefront AWA (extraído do requirejs-config.js).
 */
define([
    'jquery',
    'Magento_PageCache/js/form-key-provider',
    'domReady!'
], function ($, initFormKeyProvider) {
    'use strict';

    if (typeof initFormKeyProvider === 'function') {
        initFormKeyProvider({
            isPaginationCacheEnabled: 0
        });
    }

    $.noConflict();

    if (document.body && document.body.classList.contains('catalog-product-view')) {
        require(['awa-b2b-pdp-price-reload']);
    }

    function isCheckoutFlowPage() {
        if (!document.body) {
            return false;
        }

        return document.body.classList.contains('checkout-cart-index') ||
            document.body.classList.contains('checkout-index-index') ||
            document.body.classList.contains('rokanthemes-onepagecheckout') ||
            document.body.classList.contains('onepagecheckout-index-index');
    }

    function loadIdleModules() {
        var modules = ['awa-footer-returns-hotfix'];

        if (!isCheckoutFlowPage()) {
            modules.push('awa-link-a11y-hotfix');
        }

        require(modules);
    }

    var isHome = document.body && document.body.classList.contains('cms-index-index');

    if (!isHome) {
        if ('requestIdleCallback' in window) {
            window.requestIdleCallback(loadIdleModules, { timeout: 3500 });
        } else {
            window.setTimeout(loadIdleModules, 2500);
        }
    }
});
