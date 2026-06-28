/**
 * Carrinho: mantém contagem peças/referências no título após atualização AJAX.
 *
 * @module js/awa-cart-page-meta-sync
 */
define(['jquery'], function ($) {
    'use strict';

    function readTemplates(meta) {
        return {
            qtyOne: meta.getAttribute('data-awa-qty-one') || '1 peça',
            qtyMany: meta.getAttribute('data-awa-qty-many') || '%1 peças',
            refOne: meta.getAttribute('data-awa-ref-one') || '1 referência',
            refMany: meta.getAttribute('data-awa-ref-many') || '%1 referências'
        };
    }

    function formatTemplate(template, value) {
        return String(template).replace('%1', String(value));
    }

    function countCartStats() {
        var rows = document.querySelectorAll('#shopping-cart-table tbody.cart.item');
        var refs = rows.length;
        var qty = 0;
        var i;
        var input;

        for (i = 0; i < rows.length; i++) {
            input = rows[i].querySelector('[data-role="cart-item-qty"]');

            if (input) {
                qty += parseFloat(String(input.value).replace(',', '.')) || 0;
            }
        }

        return {
            refs: refs,
            qty: Math.round(qty)
        };
    }

    function renderMeta(meta) {
        var stats = countCartStats();
        var tpl = readTemplates(meta);
        var qtyLabel = stats.qty === 1
            ? tpl.qtyOne
            : formatTemplate(tpl.qtyMany, stats.qty);
        var html = '<span class="awa-cart-page-meta__stat">' + qtyLabel + '</span>';

        if (stats.refs !== stats.qty) {
            var refLabel = stats.refs === 1
                ? tpl.refOne
                : formatTemplate(tpl.refMany, stats.refs);

            html += '<span class="awa-cart-page-meta__sep" aria-hidden="true">·</span>'
                + '<span class="awa-cart-page-meta__stat">' + refLabel + '</span>';
        }

        meta.innerHTML = html;
    }

    return function () {
        var meta = document.getElementById('awa-cart-page-meta');

        if (!meta || !document.getElementById('form-validate')) {
            return;
        }

        renderMeta(meta);

        $(document).on('contentUpdated.awaCartPageMeta', function () {
            window.setTimeout(function () {
                renderMeta(meta);
            }, 60);
        });

        document.addEventListener('awa:qty-control:change', function (event) {
            if (!document.getElementById('form-validate').contains(event.target)) {
                return;
            }

            window.setTimeout(function () {
                renderMeta(meta);
            }, 0);
        });
    };
});
