/**
 * Billing Address Company Auto-fill Mixin
 * P2-4.1: Preenche company e vat_id da billing address com dados B2B
 *
 * @module GrupoAwamotos_B2B/js/model/checkout/company-autofill
 */
define([
    'jquery',
    'mage/utils/wrapper',
    'Magento_Checkout/js/model/quote'
], function ($, wrapper, quote) {
    'use strict';

    return function (setBillingAddressAction) {
        if (typeof setBillingAddressAction !== 'function') {
            return setBillingAddressAction;
        }

        return wrapper.wrap(setBillingAddressAction, function (originalAction) {
            var args = Array.prototype.slice.call(arguments, 1);
            var companyData = (window.checkoutConfig || {}).b2bCompanyData || null;

            if (companyData) {
                var billingAddress = quote.billingAddress();

                if (billingAddress) {
                    if (!billingAddress.company && companyData.company) {
                        billingAddress.company = companyData.company;
                    }

                    if (!billingAddress.vatId && companyData.vatId) {
                        billingAddress.vatId = companyData.vatId;
                    }
                }
            }

            return originalAction.apply(this, args);
        });
    };
});
