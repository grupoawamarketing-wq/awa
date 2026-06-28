/**
 * Carrinho: feedback acessível ao atualizar quantidades (aria-busy + status).
 *
 * @module js/awa-cart-form-feedback
 */
define(['jquery'], function ($) {
    'use strict';

    function getStatusNode() {
        return document.getElementById('awa-cart-form-status');
    }

    function announce(message) {
        var node = getStatusNode();

        if (!node) {
            return;
        }

        node.textContent = message;
    }

    function setBusy(isBusy) {
        var form = document.getElementById('form-validate');

        if (!form) {
            return;
        }

        var busyMessage = form.getAttribute('data-awa-busy-message') || 'Atualizando carrinho…';
        var doneMessage = form.getAttribute('data-awa-done-message') || 'Carrinho atualizado.';

        if (isBusy) {
            form.setAttribute('aria-busy', 'true');
            announce(busyMessage);

            return;
        }

        form.removeAttribute('aria-busy');
        announce(doneMessage);
    }

    return function () {
        if (!document.body.classList.contains('checkout-cart-index')) {
            return;
        }

        var $form = $('#form-validate');

        if (!$form.length) {
            return;
        }

        $form.on('submit.awaCartFeedback', function () {
            setBusy(true);
        });

        $form.on('click.awaCartFeedback', '[data-cart-item-update], [data-cart-empty]', function () {
            setBusy(true);
        });

        $(document).on('contentUpdated.awaCartFeedback', function () {
            window.setTimeout(function () {
                setBusy(false);

                $('#form-validate [data-role="cart-item-qty"]').each(function () {
                    this.setAttribute('data-item-qty', String(this.value));
                });
            }, 120);
        });
    };
});
