define([
    'jquery',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/url-builder',
    'mage/storage',
    'Magento_Checkout/js/model/error-processor',
    'Magento_Customer/js/model/customer',
    'Magento_Checkout/js/model/payment/method-converter',
    'Magento_Checkout/js/model/payment-service'
], function ($, quote, urlBuilder, storage, errorProcessor, customer, methodConverter, paymentService) {
    'use strict';

    function hasConfiguredPaymentMethods() {
        return paymentService.getAvailablePaymentMethods().length > 0 ||
            (window.checkoutConfig &&
                Array.isArray(window.checkoutConfig.paymentMethods) &&
                window.checkoutConfig.paymentMethods.length > 0);
    }

    return function (deferred, messageContainer) {
        var serviceUrl;

        deferred = deferred || $.Deferred();

        if (!customer.isLoggedIn()) {
            serviceUrl = urlBuilder.createUrl('/guest-carts/:cartId/payment-information', {
                cartId: quote.getQuoteId()
            });
        } else {
            serviceUrl = urlBuilder.createUrl('/carts/mine/payment-information', {});
        }

        if (window.isPlaceOrderDispatched) {
            deferred.resolve();
            return deferred.promise();
        }

        window.isPlaceOrderDispatched = false;

        return storage.get(
            serviceUrl,
            false
        ).done(function (response) {
            quote.setTotals(response.totals);
            paymentService.setPaymentMethods(methodConverter(response.payment_methods));
            deferred.resolve();
        }).fail(function (response) {
            if (hasConfiguredPaymentMethods()) {
                deferred.resolve();
                return;
            }

            errorProcessor.process(response, messageContainer);
            deferred.reject();
        });
    };
});
