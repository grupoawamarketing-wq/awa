/**
 * Payment Information Mixin
 * Adds Order Notes to payment extension attributes before submission
 * P2-4.2: Order Notes
 *
 * @module GrupoAwamotos_B2B/js/model/payment/order-notes-assigner
 */
define([
    'jquery',
    'mage/utils/wrapper',
    'GrupoAwamotos_B2B/js/model/checkout/order-notes-storage'
], function ($, wrapper, orderNotesStorage) {
    'use strict';

    return function (paymentInformationHandler) {
        if (typeof paymentInformationHandler !== 'function') {
            return paymentInformationHandler;
        }

        return wrapper.wrap(paymentInformationHandler, function (originalAction) {
            var args = Array.prototype.slice.call(arguments, 1);
            var paymentData = args[1];
            var orderNotes = (orderNotesStorage.getOrderNotes() || '').trim();

            if (orderNotes && paymentData && typeof paymentData === 'object') {
                if (!paymentData.extension_attributes || typeof paymentData.extension_attributes !== 'object') {
                    paymentData.extension_attributes = {};
                }

                paymentData.extension_attributes.b2b_order_notes = orderNotes;
            }

            return originalAction.apply(this, args);
        });
    };
});
