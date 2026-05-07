/**
 * AWA B2B PLP Quick Qty
 *
 * Converts hidden [data-awa-role="qty-input"] inputs into a visible ± stepper
 * for B2B customers (detected via body class `b2b-customer`).
 *
 * Works with the existing list.phtml which already inserts a hidden qty input:
 *   <input type="hidden" name="qty" value="1" data-awa-role="qty-input">
 * This module makes it visible and wraps it with ± buttons — no DOM duplication.
 *
 * Activation: x-magento-init with selector "*" on PLP pages.
 */
define(['jquery', 'domReady!'], function ($) {
    'use strict';

    return function (config) {
        // Only activate for B2B approved customers
        if (!document.body.classList.contains('b2b-customer')) {
            return;
        }

        var minQty = config.minQty || 1;
        var maxQty = config.maxQty || 999;

        $('[data-role="tocart-form"]').each(function () {
            var $form = $(this);
            var $hiddenQty = $form.find('[data-awa-role="qty-input"]');

            if (!$hiddenQty.length) {
                return;
            }

            // Already enhanced (idempotent)
            if ($form.find('.awa-plp-qty-wrap').length) {
                return;
            }

            var currentVal = parseInt($hiddenQty.val(), 10) || minQty;

            // Convert hidden → number input with stepper wrapper
            $hiddenQty
                .attr('type', 'number')
                .attr('min', minQty)
                .attr('max', maxQty)
                .attr('step', 1)
                .attr('inputmode', 'numeric')
                .attr('pattern', '[0-9]*')
                .attr('aria-label', 'Quantidade')
                .addClass('awa-plp-qty-input')
                .removeAttr('hidden');

            var $minus = $('<button>', {
                type: 'button',
                class: 'awa-plp-qty-btn awa-plp-qty-minus',
                'aria-label': 'Diminuir',
                tabindex: '-1',
                html: '&minus;'
            });

            var $plus = $('<button>', {
                type: 'button',
                class: 'awa-plp-qty-btn awa-plp-qty-plus',
                'aria-label': 'Aumentar',
                tabindex: '-1',
                text: '+'
            });

            var $wrap = $('<div>', { class: 'awa-plp-qty-wrap' });
            var $label = $('<label>', {
                class: 'awa-plp-qty-label',
                'aria-label': 'Quantidade'
            });

            // Insert wrap before qty input, move qty into it
            $hiddenQty.before($wrap);
            $label.append($minus).append($hiddenQty).append($plus);
            $wrap.append($label);

            function clamp(val) {
                return Math.min(maxQty, Math.max(minQty, isNaN(val) ? minQty : val));
            }

            $minus.on('click', function () {
                $hiddenQty.val(clamp(parseInt($hiddenQty.val(), 10) - 1)).trigger('change');
            });

            $plus.on('click', function () {
                $hiddenQty.val(clamp(parseInt($hiddenQty.val(), 10) + 1)).trigger('change');
            });

            $hiddenQty.on('change blur', function () {
                this.value = clamp(parseInt(this.value, 10));
            });
        });
    };
});
