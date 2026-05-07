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
    'GrupoAwamotos_B2B/js/model/checkout/po-number-storage',
    'mage/translate'
], function (Component, ko, customer, poNumberStorage, $t) {
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
         *
         * @returns {string}
         */
        getLabel: function () {
            return $t('Número do pedido de compra (PO)');
        },

        /**
         * @returns {string}
         */
        getOptionalLabel: function () {
            return $t('Opcional');
        },

        /**
         * Get placeholder text
         *
         * @returns {string}
         */
        getPlaceholder: function () {
            return $t('Ex.: PO-2026-00123');
        },

        /**
         * Get help text
         *
         * @returns {string}
         */
        getHelpText: function () {
            return $t('Informe o identificador interno do seu pedido de compra para facilitar conciliação comercial e atendimento pós-venda.');
        },

        /**
         * @returns {string}
         */
        getSupportText: function () {
            return $t('Esse código será associado ao pedido confirmado para consulta futura.');
        },

        /**
         * @returns {string}
         */
        getDescriptionId: function () {
            return 'b2b-po-number-description';
        },

        /**
         * @returns {string}
         */
        getSupportId: function () {
            return 'b2b-po-number-support';
        },

        /**
         * @returns {string}
         */
        getAriaDescribedBy: function () {
            return this.getDescriptionId() + ' ' + this.getSupportId();
        },

        /**
         * Check if field is visible
         *
         * @returns {boolean}
         */
        isFieldVisible: function () {
            return this.isVisible();
        }
    });
});