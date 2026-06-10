/**
 * Checkout error-processor — mensagens PT-BR e fallback legível.
 */
define([
    'mage/url',
    'Magento_Ui/js/model/messageList',
    'mage/translate'
], function (url, globalMessageList, $t) {
    'use strict';

    var GENERIC_KEY = 'Something went wrong with your request. Please try again later.';
    var GENERIC_PT = 'Não foi possível concluir esta etapa. Tente novamente em instantes.';

    return function (errorProcessor) {
        errorProcessor.process = function (response, messageContainer) {
            var error;

            messageContainer = messageContainer || globalMessageList;

            if (response && response.status === 401) {
                errorProcessor.redirectTo(url.build('customer/account/login/'));
                return;
            }

            try {
                error = JSON.parse(response.responseText);
            } catch (exception) {
                error = {
                    message: $t(GENERIC_KEY)
                };
            }

            if (!error.message || error.message === GENERIC_KEY) {
                error.message = GENERIC_PT;
            }

            messageContainer.addErrorMessage(error);
        };

        return errorProcessor;
    };
});
