/**
 * Carrinho: pedido mínimo B2B reativo (customer-data) + CTA checkout.
 *
 * @module js/awa-cart-min-order-live
 */
define([
    'jquery',
    'Magento_Customer/js/customer-data'
], function ($, customerData) {
    'use strict';

    var ROOT_SELECTOR = '[data-awa-min-order-progress="static"]';
    var CHECKOUT_SELECTOR = '.cart-summary .checkout-methods-items .action.primary.checkout';

    function formatCurrency(amount) {
        var value = Math.max(0, parseFloat(amount) || 0);

        return 'R$ ' + value.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function setCheckoutEnabled(enabled) {
        var $btn = $(CHECKOUT_SELECTOR);
        var $progress = $(ROOT_SELECTOR);
        var messageId = $progress.find('.awa-b2b-min-order-progress__message').attr('id');
        var $checkoutList = $btn.closest('.checkout-methods-items');

        if (!$btn.length) {
            return;
        }

        $btn.prop('disabled', !enabled);
        $btn.attr('aria-disabled', enabled ? 'false' : 'true');

        if ($checkoutList.length) {
            $checkoutList.toggleClass('awa-checkout-min-order-gated', !enabled);
        }

        if (enabled) {
            $btn.removeAttr('title');
            $btn.removeAttr('aria-describedby');
            return;
        }

        $btn.attr(
            'title',
            'Adicione mais itens para atingir o pedido mínimo B2B antes de finalizar.'
        );

        if (messageId) {
            $btn.attr('aria-describedby', messageId);
        }
    }

    function syncProgressBar($root, subtotal, minAmount) {
        var remaining = Math.max(0, minAmount - subtotal);
        var percent = minAmount > 0 ? Math.min(100, Math.round((subtotal / minAmount) * 100)) : 100;
        var $track = $root.find('.awa-b2b-min-order-progress__track');
        var $message = $root.find('.awa-b2b-min-order-progress__message');
        var messageId = $message.attr('id') || 'awa-b2b-min-order-message';

        if (!$message.attr('id')) {
            $message.attr('id', messageId);
        }

        if (subtotal <= 0 || remaining <= 0.009) {
            $root.attr('hidden', 'hidden');
            $root.attr('aria-hidden', 'true');
            $track.attr('aria-hidden', 'true')
                .removeAttr('role aria-valuenow aria-valuemin aria-valuemax aria-describedby');
            setCheckoutEnabled(true);
            return;
        }

        $root.removeAttr('hidden');
        $root.removeAttr('aria-hidden');
        $root.toggleClass('awa-b2b-min-order-progress--near', percent >= 80);
        $root.find('.awa-b2b-min-order-progress__percent').text(percent + '%');
        $root.find('.awa-b2b-min-order-progress__fill').css('width', percent + '%');
        $message.text(
            'Faltam ' + formatCurrency(remaining) + ' para atingir o pedido mínimo de ' + formatCurrency(minAmount) + '.'
        );

        $track.attr({
            role: 'progressbar',
            'aria-valuemin': '0',
            'aria-valuemax': '100',
            'aria-valuenow': String(percent),
            'aria-describedby': messageId
        });
        $track.removeAttr('aria-hidden');

        setCheckoutEnabled(false);
    }

    return function () {
        if (!document.body.classList.contains('checkout-cart-index')) {
            return;
        }

        var $root = $(ROOT_SELECTOR);
        var minAmount = parseFloat($root.data('min-amount')) || 0;

        if (!$root.length || minAmount <= 0) {
            return;
        }

        function syncFromCart(cart) {
            var subtotal = 0;

            if (cart && cart.subtotalAmount !== undefined && cart.subtotalAmount !== null) {
                subtotal = parseFloat(cart.subtotalAmount) || 0;
            }

            syncProgressBar($root, subtotal, minAmount);
        }

        var cartData = customerData.get('cart');

        cartData.subscribe(syncFromCart);
        syncFromCart(cartData());

        $(document).on('contentUpdated.awaCartMinOrder', function () {
            syncFromCart(cartData());
        });
    };
});
