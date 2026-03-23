/**
 * Order Notes Storage Model
 * Stores order notes value for checkout payment submission
 * P2-4.2: Order Notes
 *
 * @module GrupoAwamotos_B2B/js/model/checkout/order-notes-storage
 */
define([
    'ko'
], function (ko) {
    'use strict';

    var orderNotes = ko.observable('');

    return {
        /**
         * Get current order notes
         * @returns {string}
         */
        getOrderNotes: function () {
            return orderNotes();
        },

        /**
         * Set order notes
         * @param {string} value
         */
        setOrderNotes: function (value) {
            orderNotes(value);
        },

        /**
         * Observable for order notes
         * @returns {ko.observable}
         */
        orderNotesObservable: orderNotes,

        /**
         * Clear order notes
         */
        clear: function () {
            orderNotes('');
        },

        /**
         * Check if order notes is set
         * @returns {boolean}
         */
        hasOrderNotes: function () {
            return orderNotes() !== '' && orderNotes() !== null;
        }
    };
});
