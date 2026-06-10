/**
 * Guards for OPC additional-data when optional sidebar/shipping fields are absent.
 */
define([
    'jquery',
    'uiRegistry',
    'underscore'
], function ($, registry, _) {
    'use strict';

    return function (paymentData) {
        var additionalData = {};
        var shippingAddressComponent = registry.get('checkout.steps.shipping-step.shippingAddress');
        var deliveryDate;
        var deliveryComment;
        var beforeForm;

        if (!_.isEmpty(shippingAddressComponent)) {
            beforeForm = shippingAddressComponent.getChild('before-shipping-method-form');
            if (beforeForm) {
                deliveryDate = beforeForm.getChild('rokanthemes_opc_shipping_delivery_date');
                deliveryComment = beforeForm.getChild('rokanthemes_opc_shipping_delivery_comment');
            }
        }

        var orderComment = registry.get('checkout.sidebar.rokanthemes_opc_order_comment');
        var subscribe = registry.get('checkout.sidebar.subscribe');

        if (!_.isUndefined(deliveryDate) && deliveryDate) {
            additionalData.customer_shipping_date = deliveryDate.value();
        }
        if (!_.isUndefined(deliveryComment) && deliveryComment) {
            additionalData.customer_shipping_comments = deliveryComment.value();
        }
        if (!_.isUndefined(orderComment) && orderComment) {
            additionalData.order_comment = orderComment.value();
        }
        if (!_.isUndefined(subscribe) && subscribe) {
            additionalData.subscribe = subscribe.value();
        }

        if (_.isEmpty(additionalData)) {
            return;
        }

        if (paymentData.extension_attributes === undefined) {
            paymentData.extension_attributes = {};
        }

        paymentData.extension_attributes.rokanthemes_opc = additionalData;
    };
});
