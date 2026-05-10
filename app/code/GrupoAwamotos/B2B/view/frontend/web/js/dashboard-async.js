/**
 * B2B Dashboard — Async data loader (M20)
 * Fetches orders, quotes, credit data via AJAX after initial render
 */
define(['jquery'], function ($) {
    'use strict';

    return function (config) {
        let baseUrl = config.ajaxUrl || '/b2b/account/dashboardData';

        function escapeHtml(str) {
            if (!str) return '';
            let div = document.createElement('div');
            div.appendChild(document.createTextNode(String(str)));
            return div.innerHTML;
        }

        function renderOrders(data) {
            var $container = $('[data-dashboard-section="orders"]');
            if (!$container.length || !data.items || !data.items.length) return;

            let html = '';
            data.items.forEach(function (order) {
                let url = escapeHtml(order.view_url || '#');
                let id = escapeHtml(order.increment_id);
                let status = escapeHtml(order.status);
                let total = escapeHtml(order.grand_total);
                html += '<tr>' +
                    '<td><a href="' + url + '">#' + id + '</a></td>' +
                    '<td>' + formatDate(order.created_at) + '</td>' +
                    '<td><span class="status-badge">' + status + '</span></td>' +
                    '<td>' + total + '</td></tr>';
            });
            var $tbody = $container.find('tbody');
            if ($tbody.length && !$tbody.children().length) {
                $tbody.html(html);
            }
        }

        function renderQuotes(data) {
            var $badge = $('[data-dashboard-section="quotes"] .pending-count');
            if ($badge.length && data.pending_count > 0) {
                $badge.text(data.pending_count).css('display', 'inline-flex');
            }
        }

        function renderCredit(data) {
            var $container = $('[data-dashboard-section="credit"]');
            if (!$container.length || !data.available) return;
            // Credit already rendered server-side; async just validates freshness
        }

        function formatDate(dateStr) {
            if (!dateStr) return '\u2014';
            try { return new Date(dateStr).toLocaleDateString('pt-BR'); }
            catch (e) { return dateStr; }
        }

        function loadAll() {
            $.ajax({
                url: baseUrl,
                data: { section: 'all' },
                type: 'GET',
                dataType: 'json',
                success: function (resp) {
                    if (resp.orders) renderOrders(resp.orders);
                    if (resp.quotes) renderQuotes(resp.quotes);
                    if (resp.credit) renderCredit(resp.credit);
                },
                error: function (xhr) {
                    if (xhr.status === 401) {
                        window.location.reload();
                    }
                }
            });
        }

        $(loadAll);
    };
});
