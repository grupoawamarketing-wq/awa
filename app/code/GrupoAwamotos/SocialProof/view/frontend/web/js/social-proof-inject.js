/**
 * Social Proof PDP — carrega badges de prova social via AJAX.
 *
 * Permite que a PDP seja totalmente cacheável no FPC/Varnish (~30ms TTFB)
 * enquanto os badges continuam exibindo dados reais com cache de 10 minutos.
 *
 * Conformidade com CDC Art. 37 — somente dados reais, sem simulação.
 */
define(['jquery'], function ($) {
    'use strict';

    return function (config) {
        var container = document.getElementById('awa-social-proof-pdp');
        if (!container) {
            return;
        }

        var productId = parseInt(container.getAttribute('data-product-id'), 10);
        if (!productId) {
            return;
        }

        var endpoint = config.baseUrl + 'socialproof/product/data?product_id=' + productId;

        fetch(endpoint, { credentials: 'omit' })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                var html = '';

                if (data.views_today > 0) {
                    html += '<div class="social-proof-badge views-badge" role="note" aria-label="Visualizações do produto">'
                        + '<i class="fa fa-eye" aria-hidden="true"></i>'
                        + '<span class="badge-text"><strong>' + data.views_today + '</strong> '
                        + container.getAttribute('data-label-views') + '</span></div>';
                }

                if (data.low_stock) {
                    html += '<div class="social-proof-badge low-stock-badge urgency" role="note" aria-label="Alerta de baixo estoque">'
                        + '<i class="fa fa-exclamation-triangle" aria-hidden="true"></i>'
                        + '<span class="badge-text"><strong>'
                        + container.getAttribute('data-label-low-stock-pre') + ' ' + data.qty + ' '
                        + container.getAttribute('data-label-low-stock-suf') + '</strong> '
                        + container.getAttribute('data-label-stock-msg') + '</span></div>';
                }

                if (data.is_best_seller) {
                    html += '<div class="social-proof-badge bestseller-badge" role="note" aria-label="Produto mais vendido">'
                        + '<i class="fa fa-star" aria-hidden="true"></i>'
                        + '<span class="badge-text"><strong>'
                        + container.getAttribute('data-label-bestseller') + '</strong></span></div>';
                }

                if (html) {
                    container.innerHTML = html;
                    container.classList.add('awa-sp-pdp');
                }
            })
            .catch(function () {
                // silently fail — social proof is non-critical
            });
    };
});
