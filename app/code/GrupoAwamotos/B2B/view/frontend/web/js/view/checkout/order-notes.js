/**
 * Order Notes Component for Checkout
 * P2-4.2: Campo para observações do pedido
 *
 * @module GrupoAwamotos_B2B/js/view/checkout/order-notes
 */
define([
    'uiComponent',
    'ko',
    'Magento_Customer/js/model/customer',
    'GrupoAwamotos_B2B/js/model/checkout/order-notes-storage'
], function (Component, ko, customer, orderNotesStorage) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'GrupoAwamotos_B2B/checkout/order-notes',
            orderNotes: '',
            isVisible: true
        },

        /**
         * Initialize component
         */
        initialize: function () {
            this._super();

            this.orderNotes = orderNotesStorage.orderNotesObservable;

            var config = (window.checkoutConfig || {}).b2bCheckout || {};
            var orderNotesConfig = config.orderNotes || {};
            var isEnabled = orderNotesConfig.enabled === true;

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
            return 'Observações do Pedido';
        },

        /**
         * Get placeholder text
         * @returns {string}
         */
        getPlaceholder: function () {
            return 'Instruções especiais de entrega, referências, etc.';
        },

        /**
         * Get max length
         * @returns {number}
         */
        getMaxLength: function () {
            return 500;
        }
    });
});
