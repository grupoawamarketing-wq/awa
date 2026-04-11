/**
 * AWA B2B Header — auth prompt visibility
 * Hides .awa-header-auth-prompt when customer is logged in (FPC-safe via customerData).
 */
define(['Magento_Customer/js/customer-data'], function (customerData) {
    'use strict';

    function updateAuthPrompt(data) {
        var el = document.querySelector('[data-awa-header-auth]');
        if (!el) { return; }
        if (data && data.fullname) {
            el.style.setProperty('display', 'none', 'important');
        } else {
            el.style.removeProperty('display');
        }
    }

    var customer = customerData.get('customer');
    updateAuthPrompt(customer());
    customer.subscribe(updateAuthPrompt);
});
