/**
 * B2B Terms and Conditions Component for Checkout
 * Exibe termos específicos para clientes B2B com validação obrigatória
 *
 * @module GrupoAwamotos_B2B/js/view/checkout/b2b-terms
 */
define([
    'uiComponent',
    'ko',
    'jquery',
    'Magento_Customer/js/model/customer',
    'Magento_Checkout/js/model/payment/additional-validators',
    'Magento_Checkout/js/model/quote',
    'mage/translate'
], function (Component, ko, $, customer, additionalValidators, quote, $t) {
    'use strict';

    var checkoutConfig = window.checkoutConfig || {};
    var config = checkoutConfig.b2bCheckout || {};
    var termsConfig = config.terms || {};
    var validatorRegistered = false;

    return Component.extend({
        defaults: {
            template: 'GrupoAwamotos_B2B/checkout/b2b-terms',
            isAccepted: false,
            isVisible: true,
            checkboxText: termsConfig.checkboxText || $t('Li e aceito os Termos de Venda B2B'),
            termsContent: termsConfig.content || '',
            warningTitle: termsConfig.warningTitle || $t('Atenção'),
            warningContent: termsConfig.warningContent || $t('Você deve aceitar os termos e condições para continuar.')
        },

        /**
         * Initialize component
         */
        initialize: function () {
            this._super();

            // Observable for acceptance
            this.isAccepted = ko.observable(false);

            // Modal state
            this.isModalOpen = ko.observable(false);

            // Show only for logged in customers when terms feature is enabled.
            // Terms content is optional; the link is rendered conditionally in template.
            this.isVisible = ko.computed(function () {
                return customer.isLoggedIn() && termsConfig.enabled === true;
            }, this);

            // Register validator only if terms are enabled
            if (termsConfig.enabled && !validatorRegistered) {
                additionalValidators.registerValidator(this);
                validatorRegistered = true;
            }

            return this;
        },

        /**
         * Validate acceptance
         * @returns {boolean}
         */
        validate: function () {
            if (!this.isVisible()) {
                return true;
            }

            if (!this.isAccepted()) {
                this.showWarning();
                return false;
            }

            return true;
        },

        /**
         * Show warning modal
         */
        showWarning: function () {
            var self = this;
            require(['Magento_Ui/js/modal/alert'], function (alert) {
                alert({
                    title: self.warningTitle,
                    content: self.warningContent,
                    buttons: [{
                        text: $t('Entendi'),
                        class: 'action primary',
                        click: function () {
                            this.closeModal(true);
                        }
                    }]
                });
            });
        },

        /**
         * Open terms modal
         */
        openTermsModal: function () {
            this.isModalOpen(true);
        },

        /**
         * Close terms modal
         */
        closeTermsModal: function () {
            this.isModalOpen(false);
        },

        /**
         * Accept terms from modal
         */
        acceptTerms: function () {
            this.isAccepted(true);
            this.closeTermsModal();
        },

        /**
         * Get checkbox label with link
         * @returns {string}
         */
        getCheckboxLabel: function () {
            return this.checkboxText;
        },

        /**
         * Get terms content HTML
         * @returns {string}
         */
        getTermsContent: function () {
            return this.termsContent;
        },

        /**
         * Check if terms link should be shown
         * @returns {boolean}
         */
        hasTermsContent: function () {
            return !!this.termsContent;
        }
    });
});
