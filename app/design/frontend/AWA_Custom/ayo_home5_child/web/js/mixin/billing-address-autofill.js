/**
 * Compat: template OPC referencia autoFillAddress; componente Magento padrão não define o método.
 */
define([
    'jquery',
    'mage/translate'
], function ($, $t) {
    'use strict';

    return function (Component) {
        return Component.extend({
            /**
             * No-op seguro no checkout padrão; OPC/Rokanthemes sobrescreve com implementação completa.
             *
             * @param {HTMLElement|string} element
             */
            autoFillAddress: function (element) {
                var root = typeof element === 'string' ? document.getElementById(element) : element;

                if (!root) {
                    return;
                }

                var companyData = (window.checkoutConfig || {}).b2bCompanyData;

                if (!companyData) {
                    return;
                }

                if (companyData.company) {
                    $(root).find('[name="company"]').filter(function () {
                        return !this.value;
                    }).val(companyData.company).trigger('change');
                }

                if (companyData.vatId) {
                    $(root).find('[name="vat_id"]').filter(function () {
                        return !this.value;
                    }).val(companyData.vatId).trigger('change');
                }
            }
        });
    };
});
