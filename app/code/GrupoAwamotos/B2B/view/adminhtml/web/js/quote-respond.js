define([
    'jquery',
    'Magento_Ui/js/modal/confirm'
], function ($, confirm) {
    'use strict';

    let DEFAULT_MESSAGE = 'Tem certeza que deseja rejeitar esta cotação?';

    return function (config, element) {
        var $form = $(element);

        if (!$form.length) {
            return;
        }

        $form.on('click', '[data-action="reject-quote"]', function (event) {
            var $button = $(this);
            let message = $button.data('confirm-message') || config.rejectMessage || DEFAULT_MESSAGE;
            let form = $form.get(0);

            event.preventDefault();

            confirm({
                content: message,
                actions: {
                    confirm: function () {
                        if (form && typeof form.requestSubmit === 'function') {
                            form.requestSubmit($button.get(0));
                            return;
                        }

                        let tempInputName = $button.attr('name');
                        let tempInputValue = $button.val();

                        if (tempInputName) {
                            var $temp = $('<input>', {
                                type: 'hidden',
                                name: tempInputName,
                                value: tempInputValue,
                                'data-temp-action-input': true
                            });

                            $form.find('[data-temp-action-input]').remove();
                            $temp.appendTo($form);
                        }

                        form.submit();
                    }
                }
            });
        });

        $form.on('click', '[type="submit"][name="action"]:not([data-action="reject-quote"])', function () {
            $form.find('[data-temp-action-input]').remove();
        });
    };
});
