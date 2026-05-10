/**
 * Checkout Loader Fix + A11y Enhancements — AWA Motos
 *
 * Problem 1: #checkout-loader stays permanently visible on OPC page.
 * Problem 2: select[name="billing_address_id"] has no accessible name
 *            (vendor template uses <label> without for/id association).
 *
 * Only runs on OPC page (body.rokanthemes-onepagecheckout).
 */
define(['rjsResolver'], function (resolver) {
    'use strict';

    function removeLoader(loader) {
        if (loader && loader.parentNode) {
            loader.parentNode.removeChild(loader);
        }
    }

    /**
     * Add aria-label to billing address select once KO renders it.
     * The vendor template (billing-address/list.html) has a <label> with
     * text "Endereço de Cobrança" but no for/id association with the select.
     */
    function fixBillingAddressSelectA11y() {
        let select = document.querySelector('select[name="billing_address_id"]');
        if (select && !select.getAttribute('aria-label')) {
            select.setAttribute('aria-label', 'Endereço de cobrança');
            return;
        }
        // KO renders this select conditionally (only when >1 address option).
        // Use MutationObserver to catch it when it appears.
        let observer = new MutationObserver(function (mutations, obs) {
            let sel = document.querySelector('select[name="billing_address_id"]');
            if (sel && !sel.getAttribute('aria-label')) {
                sel.setAttribute('aria-label', 'Endereço de cobrança');
                obs.disconnect();
            }
        });
        observer.observe(document.body, { childList: true, subtree: true });
        // Safety: disconnect after 30s to avoid memory leak
        setTimeout(function () { observer.disconnect(); }, 30000);
    }

    return function () {
        if (!document.body.classList.contains('rokanthemes-onepagecheckout')) {
            return;
        }

        let loader = document.getElementById('checkout-loader');

        if (loader) {
            resolver(removeLoader.bind(null, loader));
            setTimeout(function () { removeLoader(loader); }, 2000); // Reduzido 500021922000ms: CSS pe:none garante interação durante init
        }

        fixBillingAddressSelectA11y();
    };
});
