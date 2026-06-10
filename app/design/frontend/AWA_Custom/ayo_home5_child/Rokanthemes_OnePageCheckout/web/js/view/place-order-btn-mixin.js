/**
 * OPC place-order: billing por método + CTA sidebar + placeOrder direto no renderer.
 */
define([
    'ko',
    'jquery',
    'uiRegistry',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/action/select-billing-address',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Checkout/js/model/payment/additional-validators',
    'Magento_Checkout/js/model/shipping-service',
    'Magento_Checkout/js/action/select-shipping-method',
    'Rokanthemes_OnePageCheckout/js/action/validate-shipping-information',
    'underscore'
], function (
    ko,
    $,
    registry,
    quote,
    selectBillingAddressAction,
    fullScreenLoader,
    additionalValidators,
    shippingService,
    selectShippingMethodAction,
    validateShippingInformationAction,
    _
) {
    'use strict';

    /**
     * @param {Object|null} address
     * @returns {boolean}
     */
    function isBillingAddressUsable(address) {
        var streetLine = '';

        if (!address) {
            return false;
        }

        if (!_.isUndefined(address.street) && address.street !== null) {
            streetLine = Array.isArray(address.street) ?
                String(address.street[0] || '').trim() :
                String(address.street).trim();
        }

        return Boolean(
            String(address.firstname || '').trim() &&
            String(address.lastname || '').trim() &&
            String(address.city || '').trim() &&
            String(address.postcode || '').trim() &&
            String(address.telephone || '').trim() &&
            String(address.countryId || '').trim() &&
            streetLine
        );
    }

    /**
     * @returns {void}
     */
    function ensureBillingFromShipping() {
        var shippingAddress = quote.shippingAddress();

        if (quote.isVirtual() || !shippingAddress) {
            return;
        }

        if (!isBillingAddressUsable(quote.billingAddress())) {
            selectBillingAddressAction(shippingAddress);
        }
    }

    function ensureShippingMethodFromRates() {
        var shippingMethod = quote.shippingMethod();
        var rates;
        var validRates;

        if (shippingMethod && shippingMethod.carrier_code && shippingMethod.method_code) {
            return;
        }

        rates = shippingService.getShippingRates()();
        validRates = Array.isArray(rates) ? rates.filter(function (rate) {
            return rate && rate.carrier_code && rate.method_code && !rate.error_message;
        }) : [];

        if (validRates.length) {
            selectShippingMethodAction(validRates[0]);
        }
    }

    /**
     * @returns {boolean}
     */
    function readCanPlaceOrder() {
        if (quote.isVirtual()) {
            return quote.paymentMethod() != null && isBillingAddressUsable(quote.billingAddress());
        }

        return quote.paymentMethod() != null &&
            quote.shippingMethod() != null &&
            isBillingAddressUsable(quote.billingAddress());
    }

    /**
     * @param {Function} target
     * @returns {void}
     */
    function syncPlaceOrderAllowed(target) {
        ensureBillingFromShipping();
        ensureShippingMethodFromRates();
        target(readCanPlaceOrder());
    }

    /**
     * Alinhado ao OPC original (billing != null), com sync prévia shipping → billing.
     *
     * @returns {boolean}
     */
    function canPlaceOrder() {
        ensureBillingFromShipping();
        ensureShippingMethodFromRates();

        return readCanPlaceOrder();
    }

    /**
     * @returns {Object|null}
     */
    function resolveBillingAddressComponent() {
        var paymentMethod = quote.paymentMethod(),
            code = paymentMethod && paymentMethod.method,
            candidates = [
                'checkout.steps.billing-step.payment.payments-list.billing-address-form-shared'
            ],
            i;

        if (code) {
            candidates.unshift(
                'checkout.steps.billing-step.payment.payments-list.' + code + '-form'
            );
        }

        for (i = 0; i < candidates.length; i++) {
            try {
                return registry.get(candidates[i]);
            } catch (e) {
                // uiRegistry throws when component is missing
            }
        }

        return null;
    }

    /**
     * @param {boolean} isBusy
     * @returns {void}
     */
    function setPlaceOrderBusyState(isBusy) {
        $('.btn-placeorder')
            .attr('aria-busy', isBusy ? 'true' : 'false')
            .toggleClass('is-processing', isBusy);
    }

    /**
     * @returns {Object|null}
     */
    function resolvePaymentRenderer() {
        var code = quote.paymentMethod() && quote.paymentMethod().method;

        if (!code) {
            return null;
        }

        try {
            return registry.get('checkout.steps.billing-step.payment.payments-list.' + code);
        } catch (e) {
            return null;
        }
    }

    return function (Component) {
        return Component.extend({
            /** @inheritdoc */
            initialize: function () {
                this._super();
                this._isPlacingOrder = false;

                var forceHidden = ko.observable(false);
                var previousVisible = this.isVisible;

                if (ko.isObservable(previousVisible)) {
                    previousVisible.subscribe(function (visible) {
                        forceHidden(!visible);
                    });
                    forceHidden(!previousVisible());
                }

                this.isVisible = ko.pureComputed(function () {
                    return !forceHidden();
                });

                var allowed = ko.observable(false);
                var syncAllowed = function () {
                    syncPlaceOrderAllowed(allowed);
                };

                quote.billingAddress.subscribe(syncAllowed);
                quote.paymentMethod.subscribe(syncAllowed);
                quote.shippingMethod.subscribe(syncAllowed);
                quote.shippingAddress.subscribe(syncAllowed);
                shippingService.getShippingRates().subscribe(syncAllowed);
                syncAllowed();

                this.isPlaceOrderActionAllowed = allowed;

                return this;
            },

            /**
             * @returns {void}
             */
            releasePlaceOrderLock: function () {
                this._isPlacingOrder = false;
                setPlaceOrderBusyState(false);
                syncPlaceOrderAllowed(this.isPlaceOrderActionAllowed);
            },

            /** @inheritdoc */
            placeOrder: function (data, event) {
                var self = this;
                var shippingAddressComponent;

                if (self._isPlacingOrder) {
                    return false;
                }

                ensureBillingFromShipping();
                ensureShippingMethodFromRates();

                if (!canPlaceOrder()) {
                    return false;
                }

                if (!additionalValidators.validate()) {
                    return false;
                }

                self._isPlacingOrder = true;
                self.isPlaceOrderActionAllowed(false);
                setPlaceOrderBusyState(true);
                fullScreenLoader.startLoader();

                if (event) {
                    event.preventDefault();
                }

                if (quote.isVirtual()) {
                    return self._super(data, event);
                }

                if (typeof window.shippingAddress !== 'undefined' && !$.isEmptyObject(window.shippingAddress)) {
                    return self._super(data, event);
                }

                try {
                    shippingAddressComponent = registry.get('checkout.steps.shipping-step.shippingAddress');
                } catch (ignore) {
                    fullScreenLoader.stopLoader();
                    setPlaceOrderBusyState(false);
                    self.releasePlaceOrderLock();
                    return false;
                }

                if (!shippingAddressComponent.validateShippingInformation()) {
                    fullScreenLoader.stopLoader();
                    setPlaceOrderBusyState(false);
                    self.releasePlaceOrderLock();
                    return false;
                }

                self.placeOrderContinue();

                return false;
            },

            /** @inheritdoc */
            placeOrderContinue: function () {
                var self = this;

                if (self._isPlacingOrder && self._placeOrderContinueStarted) {
                    return;
                }

                self._placeOrderContinueStarted = true;
                self._isPlacingOrder = true;
                self.isPlaceOrderActionAllowed(false);

                var billingAddressComponent = resolveBillingAddressComponent();

                ensureBillingFromShipping();
                ensureShippingMethodFromRates();

                if (billingAddressComponent &&
                    typeof billingAddressComponent.isAddressSameAsShipping === 'function' &&
                    billingAddressComponent.isAddressSameAsShipping()) {
                    fullScreenLoader.startLoader();
                    selectBillingAddressAction(quote.shippingAddress());
                }

                validateShippingInformationAction().done(function () {
                    var paymentRenderer = resolvePaymentRenderer();
                    var invoked = false;

                    if (paymentRenderer && typeof paymentRenderer.placeOrder === 'function') {
                        paymentRenderer.placeOrder(null, null);
                        invoked = true;
                    }

                    if (!invoked) {
                        $('input#' + self.getCode())
                            .closest('.payment-method')
                            .find('.payment-method-content .actions-toolbar button.action.checkout')
                            .first()
                            .trigger('click');
                    }
                }).fail(function () {
                    fullScreenLoader.stopLoader();
                    setPlaceOrderBusyState(false);
                    self._placeOrderContinueStarted = false;
                    self.releasePlaceOrderLock();
                });
            }
        });
    };
});
