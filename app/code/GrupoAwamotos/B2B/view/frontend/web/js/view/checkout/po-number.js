/**
 * PO Number Component for Checkout
 * P0-1: Campo para número de pedido de compra (Purchase Order)
 *
 * @module GrupoAwamotos_B2B/js/view/checkout/po-number
 */
define([
    'uiComponent',
    'ko',
    'Magento_Customer/js/model/customer',
    'GrupoAwamotos_B2B/js/model/checkout/po-number-storage'
], function (Component, ko, customer, poNumberStorage) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'GrupoAwamotos_B2B/checkout/po-number',
            poNumber: '',
            isVisible: true
        },

        /**
         * Initialize component
         */
        initialize: function () {
            this._super();

            // Reuse shared storage observable to survive checkout component re-renders.
            this.poNumber = poNumberStorage.poNumberObservable;
            var config = (window.checkoutConfig || {}).b2bCheckout || {};
            var poNumberConfig = config.poNumber || {};
            var isEnabled = poNumberConfig.enabled === true;

            this.isVisible = ko.computed(function () {
                return customer.isLoggedIn() && isEnabled;
            }, this);

            return this;
        },

        /**
         * Get component label
         * @returns {string}
         */
        getLabel: function () {
            return 'Número do Pedido de Compra (PO)';
        },

        /**
         * Get placeholder text
         * @returns {string}
         */
        getPlaceholder: function () {
            return 'Ex: PO-2026-00123';
        },

        /**
         * Get help text
         * @returns {string}
         */
        getHelpText: function () {
            return 'Opcional. Informe o número do seu pedido de compra interno para referência.';
        },

        /**
         * Check if field is visible
         * @returns {boolean}
         */
        isFieldVisible: function () {
            return this.isVisible();
        }
    });
});
