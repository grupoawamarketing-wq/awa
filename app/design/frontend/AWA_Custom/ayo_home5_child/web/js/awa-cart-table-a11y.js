/**
 * Carrinho: landmarks ARIA + tabela mobile (display:block preserva layout).
 */
define([], function () {
    'use strict';

    function enhanceCartTable() {
        var table = document.querySelector(
            'body.checkout-cart-index .cart.table-wrapper table.data.table'
        );
        if (!table || table.getAttribute('data-awa-cart-a11y')) {
            return;
        }

        table.setAttribute('data-awa-cart-a11y', '1');
        table.setAttribute('role', 'table');
        table.setAttribute('aria-label', table.getAttribute('aria-label') || 'Itens do carrinho');

        var thead = table.querySelector('thead');
        if (thead) {
            thead.setAttribute('role', 'rowgroup');
            thead.querySelectorAll('tr').forEach(function (row) {
                row.setAttribute('role', 'row');
                row.querySelectorAll('th').forEach(function (cell) {
                    cell.setAttribute('role', 'columnheader');
                });
            });
        }

        table.querySelectorAll('tbody.cart.item').forEach(function (tbody) {
            tbody.setAttribute('role', 'rowgroup');
            tbody.querySelectorAll('tr.item-info').forEach(function (row) {
                row.setAttribute('role', 'row');
                row.querySelectorAll('td').forEach(function (cell) {
                    cell.setAttribute('role', 'cell');
                });
            });
        });
    }

    function linkPageLandmarks() {
        if (!document.body.classList.contains('checkout-cart-index')) {
            return;
        }

        var pageMeta = document.getElementById('awa-cart-page-meta');
        var pageTitle = document.querySelector('.page-title-wrapper .page-title');

        if (pageMeta && pageTitle && !pageTitle.getAttribute('aria-describedby')) {
            pageTitle.setAttribute('aria-describedby', 'awa-cart-page-meta');
        }

        var summary = document.querySelector('.cart-summary');
        var summaryTitle = summary
            ? summary.querySelector(':scope > .title, :scope .summary.title')
            : null;

        if (summary && summaryTitle) {
            if (!summaryTitle.id) {
                summaryTitle.id = 'awa-cart-summary-title';
            }
            summary.setAttribute('role', 'region');
            summary.setAttribute('aria-labelledby', summaryTitle.id);
        }
    }

    function init() {
        enhanceCartTable();
        linkPageLandmarks();
    }

    return function () {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }

        document.addEventListener('contentUpdated', function () {
            window.setTimeout(init, 100);
        });
    };
});
