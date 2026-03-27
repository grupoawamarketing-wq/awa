define([
    'require'
], function (require) {
    'use strict';

    function shouldRun() {
        return document.body.classList.contains('onepagecheckout-index-index') ||
            document.body.classList.contains('checkout-index-index');
    }

    return function () {
        if (!shouldRun()) {
            return;
        }

        require([
            'Magento_Checkout/js/model/quote',
            'Magento_Checkout/js/model/shipping-service',
            'Magento_Checkout/js/action/select-shipping-method',
            'Magento_Checkout/js/checkout-data'
        ], function (
            quote,
            shippingService,
            selectShippingMethodAction,
            checkoutData
        ) {
            var isNormalizingRates = false;
            var hasPatchedSetShippingRates = false;

            function getRateCode(rate) {
                if (!rate) {
                    return null;
                }

                return rate.carrier_code + '_' + rate.method_code;
            }

            function sanitizeRates(rates) {
                var availableRates = Array.isArray(rates) ? rates.filter(Boolean) : [];
                var validRates = availableRates.filter(function (rate) {
                    return rate && rate.carrier_code && rate.method_code && !rate.error_message;
                });

                return validRates.length ? validRates : availableRates;
            }

            function patchSetShippingRates() {
                var originalSetShippingRates;

                if (hasPatchedSetShippingRates || typeof shippingService.setShippingRates !== 'function') {
                    return;
                }

                originalSetShippingRates = shippingService.setShippingRates.bind(shippingService);
                shippingService.setShippingRates = function (ratesData) {
                    return originalSetShippingRates(sanitizeRates(ratesData));
                };
                hasPatchedSetShippingRates = true;
            }

            function syncSingleRate(rates) {
                var availableRates = sanitizeRates(rates);
                var selectedRate = quote.shippingMethod();

                if (!isNormalizingRates && availableRates.length === 1 && Array.isArray(rates) && availableRates.length !== rates.filter(Boolean).length) {
                    isNormalizingRates = true;
                    shippingService.setShippingRates(availableRates);
                    isNormalizingRates = false;
                }

                if (availableRates.length !== 1) {
                    return;
                }

                if (getRateCode(selectedRate) === getRateCode(availableRates[0])) {
                    return;
                }

                selectShippingMethodAction(availableRates[0]);
                checkoutData.setSelectedShippingRate(getRateCode(availableRates[0]));
            }

            patchSetShippingRates();
            shippingService.getShippingRates().subscribe(syncSingleRate);
            syncSingleRate(shippingService.getShippingRates()());
        });
    };
});
