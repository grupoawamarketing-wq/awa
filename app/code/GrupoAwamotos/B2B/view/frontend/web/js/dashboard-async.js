/**
 * B2B Dashboard — Async data loader (M20)
 * Fetches orders, quotes, credit data via AJAX after initial render
 */
define(['jquery'], function ($) {
    'use strict';

    return function (config) {
        var baseUrl = config.ajaxUrl || '/b2b/account/dashboardData';

        function renderOrders(data) {
            var $container = $('[data-dashboard-section="orders"]');
            if (!$container.length || !data.items || !data.items.length) return;

            var html = '';
            data.items.forEach(function (order) {
                html += '<tr>' +
                    '<td><a href="' + (order.view_url || '#') + '">#' + order.increment_id + '</a></td>' +
                    '<td>' + formatDate(order.created_at) + '</td>' +
                    '<td><span class="status-badge">' + order.status + '</span></td>' +
                    '<td>' + order.grand_total + '</td></tr>';
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
                }
            });
        }

        $(loadAll);
    };
});
