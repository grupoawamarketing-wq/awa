/**
 * Carrinho: aguarda customer-data defer antes de resolver endereço de estimativa.
 * Evita TypeError em customer-data.js (storage.set) quando checkout-data roda cedo demais.
 *
 * IMPORTANTE: _super é injetado pelo wrapper.js apenas durante a execução síncrona.
 * Deve ser capturado ANTES de qualquer chamada assíncrona (Promise/setTimeout).
 */
define([
    'js/awa-customer-sections-gate'
], function (whenCustomerSectionsReady) {
    'use strict';

    return function (Component) {
        return Component.extend({
            /**
             * @inheritdoc
             */
            initialize: function () {
                if (!document.getElementById('awa-customer-sections-defer-json')) {
                    return this._super();
                }

                var self = this;
                // Captura _super enquanto ainda está disponível (síncrono).
                // wrapper.js deleta this._super logo após o return desta função.
                var superInitialize = this._super;

                whenCustomerSectionsReady(function () {
                    superInitialize.call(self);
                });

                return this;
            }
        });
    };
});
