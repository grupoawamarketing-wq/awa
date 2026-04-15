/**
 * REXIS ML - Real-time Recommendations Module
 *
 * Usage:
 * require(['rexisRecommendations'], function(rexis) {
 *     rexis.load({
 *         container: '#my-recommendations',
 *         classificacao: 'Oportunidade Cross-sell',
 *         limit: 4,
 *         onSuccess: function(data) {
 *             console.log('Loaded', data.total, 'recommendations');
 *         }
 *     });
 * });
 */
define([
    'jquery',
    'mage/url',
    'mage/template',
    'mage/storage'
], function($, urlBuilder, mageTemplate, storage) {
    'use strict';

    var defaultTemplate =
        '<% _.each(recommendations, function(item) { %>' +
        '   <div class="rexis-ajax-item" data-product-id="<%= item.product_id %>">' +
        '       <div class="rexis-ajax-image">' +
        '           <a href="<%= item.url %>">' +
        '               <img src="<%= item.image %>" alt="<%= item.name %>" loading="lazy" />' +
        '           </a>' +
        '       </div>' +
        '       <div class="rexis-ajax-details">' +
        '           <h4><a href="<%= item.url %>"><%= item.name %></a></h4>' +
        '           <div class="rexis-ajax-price"><%= item.price %></div>' +
        '           <% if (item.score >= 85) { %>' +
        '               <div class="rexis-ajax-score"><%= Math.round(item.score) %>% Match</div>' +
        '           <% } %>' +
        '           <button class="action primary rexis-ajax-addtocart" data-sku="<%= item.sku %>">' +
        '               Adicionar ao Carrinho' +
        '           </button>' +
        '       </div>' +
        '   </div>' +
        '<% }); %>';

    return {
        /**
         * Load recommendations via AJAX
         *
         * @param {Object} options
         */
        load: function(options) {
            var settings = $.extend({
                container: '#rexis-recommendations',
                classificacao: null,
                limit: 4,
                minScore: 0.7,
                template: null,
                showLoader: true,
                onSuccess: null,
                onError: null
            }, options);

            var $container = $(settings.container);
            if ($container.length === 0) {
                console.warn('REXIS ML: Container not found -', settings.container);
                return;
            }

            // Show loader
            if (settings.showLoader) {
                $container.html('<div class="rexis-ajax-loader">🔄 Carregando recomendações...</div>');
            }

            // Build URL with params
            var url = urlBuilder.build('rexisml/ajax/getrecommendations');
            var params = {
                limit: settings.limit,
                minScore: settings.minScore
            };

            if (settings.classificacao) {
                params.classificacao = settings.classificacao;
            }

            // AJAX request
            $.ajax({
                url: url,
                type: 'GET',
                data: params,
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.recommendations.length > 0) {
                        var template = settings.template || defaultTemplate;
                        var compiled = mageTemplate(template);
                        var html = compiled({
                            recommendations: response.recommendations
                        });

                        $container.html(html);

                        // Bind add to cart
                        $container.find('.rexis-ajax-addtocart').on('click', function(e) {
                            e.preventDefault();
                            var sku = $(this).data('sku');
                            // Implement add to cart logic
                            console.log('Add to cart:', sku);
                        });

                        if (settings.onSuccess) {
                            settings.onSuccess(response);
                        }
                    } else {
                        $container.html('<div class="rexis-ajax-empty">Nenhuma recomendação disponível no momento.</div>');
                    }
                },
                error: function(xhr, status, error) {
                    $container.html('<div class="rexis-ajax-error">Erro ao carregar recomendações.</div>');

                    if (settings.onError) {
                        settings.onError(error);
                    }

                    console.error('REXIS ML Error:', error);
                }
            });
        },

        /**
         * Refresh recommendations in container
         */
        refresh: function(container) {
            var $container = $(container);
            var options = $container.data('rexis-options') || {};
            options.container = container;
            this.load(options);
        },

        /**
         * Track recommendation view (for analytics)
         */
        trackView: function(productId, score) {
            // Implement tracking logic
            console.log('REXIS ML: Tracked view -', productId, 'Score:', score);
        },

        /**
         * Track recommendation click
         */
        trackClick: function(productId, score) {
            // Implement tracking logic
            console.log('REXIS ML: Tracked click -', productId, 'Score:', score);
        }
    };
});
