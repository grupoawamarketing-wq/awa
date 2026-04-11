/**
 * AWA Motos â€” Override do Magento_Persistent customer-data mixin
 *
 * Corrige loop infinito: o mixin original chama this.reload() toda vez que
 * mage-cache-sessid nÃ£o estÃ¡ presente, o que ocorre para TODOS os visitantes
 * sem sessÃ£o ativa. Sem um guard, isso dispara a cada carregamento de pÃ¡gina
 * e pode criar um ciclo de invalidaÃ§Ã£o â†’ reload â†’ invalidaÃ§Ã£o.
 *
 * Fix: adiciona flag de execuÃ§Ã£o Ãºnica por sessÃ£o de pÃ¡gina.
 */
define([
    'jquery',
    'mage/utils/wrapper'
], function ($, wrapper) {
    'use strict';

    var mixin = {

        /**
         * Verifica se a seÃ§Ã£o persistent ainda Ã© vÃ¡lida com base no lifetime.
         *
         * @param {Function} originFn
         * @return {Array}
         */
        getExpiredSectionNames: function (originFn) {
            var expiredSections = originFn(),
                storage = $.initNamespaceStorage('mage-cache-storage').localStorage,
                currentTimestamp = Math.floor(Date.now() / 1000),
                persistentIndex = expiredSections.indexOf('persistent'),
                persistentLifeTime = 0,
                sectionData;

            if (window.persistent !== undefined && window.persistent.expirationLifetime !== undefined) {
                persistentLifeTime = window.persistent.expirationLifetime;
            }

            if (persistentIndex !== -1) {
                sectionData = storage.get('persistent');

                if (typeof sectionData === 'object' &&
                    sectionData['data_id'] + persistentLifeTime >= currentTimestamp
                ) {
                    expiredSections.splice(persistentIndex, 1);
                }
            }

            return expiredSections;
        },

        /**
         * @param {Object} settings
         * @constructor
         */
        'Magento_Customer/js/customer-data': function (originFn) {
            var mageCacheTimeout = new Date($.localStorage.get('mage-cache-timeout')),
                mageCacheSessId = $.cookieStorage.isSet('mage-cache-sessid');

            originFn();

            /*
             * Guard: executa o reload no mÃ¡ximo UMA VEZ por carregamento de pÃ¡gina.
             * Sem isso, a ausÃªncia do cookie mage-cache-sessid (comum em visitantes
             * sem sessÃ£o) dispara um reload a cada ciclo de invalidaÃ§Ã£o de seÃ§Ã£o,
             * travando o browser com "PÃ¡gina sem resposta".
             */
            if (window.persistent !== undefined &&
                !window._awaPersistentReloadExecuted &&
                (mageCacheTimeout < new Date() || !mageCacheSessId)
            ) {
                window._awaPersistentReloadExecuted = true;
                this.reload(['persistent', 'cart'], true);
            }
        }
    };

    return function (target) {
        return wrapper.extend(target, mixin);
    };
});
