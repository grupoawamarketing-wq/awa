/**
 * Attach B2B checkout extension attributes to place-order payloads.
 */
define([
    'mage/utils/wrapper',
    'GrupoAwamotos_B2B/js/model/checkout/po-number-storage',
    'GrupoAwamotos_B2B/js/model/checkout/order-notes-storage'
], function (wrapper, poNumberStorage, orderNotesStorage) {
    'use strict';

    /**
     * @param {Object} paymentData
     * @returns {Object}
     */
    function ensureExtensionAttributes(paymentData) {
        if (!paymentData || typeof paymentData !== 'object') {
            return paymentData;
        }

        if (!paymentData.extension_attributes || typeof paymentData.extension_attributes !== 'object') {
            paymentData.extension_attributes = {};
        }

        return paymentData;
    }

    /**
     * Resolve payment data for both standard checkout and custom wrappers.
     *
     * Magento_Checkout/js/action/place-order usually receives:
     *  - arg0: paymentData
     *  - arg1: messageContainer
     *
     * Some wrappers may shift arguments, so keep a safe fallback.
     *
     * @param {Array} args
     * @returns {Object|null}
     */
    function resolvePaymentData(args) {
        if (args[0] && typeof args[0] === 'object' && !args[0].hasMessages) {
            return ensureExtensionAttributes(args[0]);
        }

        if (args[1] && typeof args[1] === 'object' && !args[1].hasMessages) {
            return ensureExtensionAttributes(args[1]);
        }

        return null;
    }

    return function (placeOrderAction) {
        if (typeof placeOrderAction !== 'function') {
            return placeOrderAction;
        }

        return wrapper.wrap(placeOrderAction, function (originalAction) {
            var args = Array.prototype.slice.call(arguments, 1);
            var paymentData = resolvePaymentData(args);
            var poNumber = (poNumberStorage.getPoNumber() || '').trim();
            var orderNotes = (orderNotesStorage.getOrderNotes() || '').trim();

            if (paymentData) {
                if (poNumber) {
                    paymentData.extension_attributes.b2b_po_number = poNumber;
                }

                if (orderNotes) {
                    paymentData.extension_attributes.b2b_order_notes = orderNotes;
                }
            }

            return originalAction.apply(this, args);
        });
    };
});
