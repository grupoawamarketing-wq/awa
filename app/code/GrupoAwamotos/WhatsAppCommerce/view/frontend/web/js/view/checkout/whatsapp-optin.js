define([
    'uiComponent',
    'ko',
    'Magento_Customer/js/model/customer',
    'jquery',
    'mage/url'
], function (Component, ko, customer, $, urlBuilder) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'GrupoAwamotos_WhatsAppCommerce/checkout/whatsapp-optin'
        },

        isChecked: ko.observable(false),
        isLoggedIn: customer.isLoggedIn,
        isSaving: ko.observable(false),

        initialize: function () {
            this._super();

            this.isChecked.subscribe(function (newValue) {
                this.saveOptin(newValue ? 1 : 0);
            }.bind(this));

            return this;
        },

        saveOptin: function (value) {
            if (this.isSaving()) {
                return;
            }

            this.isSaving(true);

            $.ajax({
                url: urlBuilder.build('whatsappcommerce/checkout/saveoptin'),
                type: 'POST',
                dataType: 'json',
                data: {
                    optin: value,
                    form_key: $.cookie('form_key')
                },
                complete: function () {
                    this.isSaving(false);
                }.bind(this)
            });
        }
    });
});
