/**
 * AWA Custom — Mixin para Magento_Ui/js/lib/knockout/template/renderer
 *
 * Problema: quando renderer.render(tmplPath) falha (por erro de rede transitório
 * ou qualquer condição intermitente), a promise rejeitada fica cacheada em
 * renderedTemplatePromises. Isso impede re-tentativas e o template nunca carrega,
 * causando o minicart ficar em branco com erro no console:
 * "[ERROR] Failed to load the "X" template requested by "Y""
 *
 * Solução: wrappear o renderer.render() para interceptar rejeições e
 * realizar uma nova tentativa direta (bypassa o cache) antes de desistir.
 * Limita a 2 re-tentativas por template para evitar loops infinitos.
 */
define([
    'Magento_Ui/js/lib/knockout/template/loader',
    'jquery'
], function (loader, $) {
    'use strict';

    /** Contador de re-tentativas por template path */
    var retryAttempts = {};

    /** Máximo de re-tentativas por template antes de desistir */
    var MAX_RETRIES = 2;

    /** Delay (ms) antes de cada re-tentativa */
    var RETRY_DELAY_MS = 300;

    return function (renderer) {
        var originalRender = renderer.render.bind(renderer);

        /**
         * Sobrescreve render() adicionando retry logic.
         * Se a promise original for rejeitada, tenta carregar o template
         * diretamente (sem cache) até MAX_RETRIES vezes.
         *
         * @param {String} tmplPath - Caminho do template (ex: Magento_Checkout/minicart/content)
         * @returns {jQuery.Promise}
         */
        renderer.render = function (tmplPath) {
            var originalPromise = originalRender(tmplPath);
            var deferred = $.Deferred();

            originalPromise.then(function (result) {
                retryAttempts[tmplPath] = 0;
                deferred.resolve(result);
            }).fail(function () {
                var attempts = retryAttempts[tmplPath] || 0;

                if (attempts >= MAX_RETRIES) {
                    // Esgotou as re-tentativas — propaga a falha
                    deferred.reject();
                    return;
                }

                retryAttempts[tmplPath] = attempts + 1;

                // Re-tentativa direta: bypass do cache do renderer
                setTimeout(function () {
                    loader.loadTemplate(tmplPath)
                        .then(renderer.parseTemplate)
                        .then(function (result) {
                            deferred.resolve(result);
                        })
                        .fail(function () {
                            // Outra falha — incrementar e tentar novamente recursivamente
                            // (será tratado na próxima chamada a makeTemplateSource)
                            deferred.reject();
                        });
                }, RETRY_DELAY_MS);
            });

            return deferred.promise();
        };

        return renderer;
    };
});
