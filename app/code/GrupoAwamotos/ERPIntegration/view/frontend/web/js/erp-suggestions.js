/**
 * ERP Suggestions Widget
 *
 * Handles AJAX loading and interactions for customer purchase suggestions
 */
define([
    'jquery',
    'mage/translate',
    'jquery-ui-modules/widget'
], function ($, $t) {
    'use strict';

    $.widget('grupoawamotos.erpSuggestions', {
        options: {
            ajaxUrl: '',
            loadingClass: 'loading',
            errorClass: 'error'
        },

        /**
         * Widget initialization
         */
        _create: function () {
            this._bindEvents();
        },

        /**
         * Bind event handlers
         */
        _bindEvents: function () {
            var self = this;

            // Refresh button click
            this.element.on('click', '.erp-refresh-btn', function (e) {
                e.preventDefault();
                self.refreshData($(this).data('type'));
            });

            // Add to cart from suggestions
            this.element.on('click', '.erp-add-to-cart', function (e) {
                e.preventDefault();
                var productId = $(this).data('product-id');
                var qty = $(this).closest('.erp-product-item').find('.erp-qty-input').val() || 1;
                self.addToCart(productId, qty);
            });

            // Quick reorder
            this.element.on('click', '.erp-quick-reorder', function (e) {
                e.preventDefault();
                var orderId = $(this).data('order-id');
                self.quickReorder(orderId);
            });
        },

        /**
         * Refresh data from ERP
         */
        refreshData: function (type) {
            var self = this;
            var $container = this.element;

            $container.addClass(this.options.loadingClass);

            $.ajax({
                url: this.options.ajaxUrl,
                type: 'GET',
                dataType: 'json',
                data: {
                    type: type || 'all'
                },
                success: function (response) {
                    if (response.success) {
                        self._updateContent(response.data, type);
                    } else {
                        self._showError(response.message || $t('Error loading data'));
                    }
                },
                error: function () {
                    self._showError($t('Connection error. Please try again.'));
                },
                complete: function () {
                    $container.removeClass(self.options.loadingClass);
                }
            });
        },

        /**
         * Add product to cart
         */
        addToCart: function (productId, qty) {
            var self = this;
            var addToCartUrl = window.BASE_URL + 'checkout/cart/add';

            $.ajax({
                url: addToCartUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    product: productId,
                    qty: qty,
                    form_key: $.mage.cookies.get('form_key')
                },
                success: function (response) {
                    if (response.success || !response.error) {
                        // Trigger mini cart update
                        $('[data-block="minicart"]').trigger('contentLoading');
                        self._showSuccess($t('Product added to cart'));
                    } else {
                        self._showError(response.message || $t('Could not add product to cart'));
                    }
                },
                error: function () {
                    self._showError($t('Error adding product to cart'));
                }
            });
        },

        /**
         * Quick reorder - add all items from a previous order
         */
        quickReorder: function (orderId) {
            var self = this;
            var reorderUrl = this.options.ajaxUrl.replace('suggestions', 'reorder');

            $.ajax({
                url: reorderUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    order_id: orderId,
                    form_key: $.mage.cookies.get('form_key')
                },
                beforeSend: function () {
                    self.element.addClass(self.options.loadingClass);
                },
                success: function (response) {
                    if (response.success) {
                        window.location.href = window.BASE_URL + 'checkout/cart';
                    } else {
                        self._showError(response.message || $t('Could not reorder'));
                    }
                },
                error: function () {
                    self._showError($t('Error processing reorder'));
                },
                complete: function () {
                    self.element.removeClass(self.options.loadingClass);
                }
            });
        },

        /**
         * Update content based on response
         */
        _updateContent: function (data, type) {
            if (!data) {
                return;
            }

            // Re-render HTML sections returned by the server
            if (typeof data === 'string') {
                this.element.html(data);
                return;
            }

            // Handle typed section updates
            var sectionMap = {
                suggestions: '.erp-suggestions-list',
                history: '.erp-history-list',
                summary: '.erp-summary-grid'
            };

            var selector = type && sectionMap[type] ? sectionMap[type] : null;

            if (selector && data.html) {
                this.element.find(selector).html(data.html);
            } else if (data.html) {
                this.element.html(data.html);
            }
        },

        /**
         * Show success message
         */
        _showSuccess: function (message) {
            this._showMessage(message, 'success');
        },

        /**
         * Show error message
         */
        _showError: function (message) {
            this._showMessage(message, 'error');
        },

        /**
         * Show message notification
         */
        _showMessage: function (message, type) {
            var $notification = $('<div/>')
                .addClass('erp-notification erp-notification-' + type)
                .text(message)
                .appendTo('body');

            setTimeout(function () {
                $notification.fadeOut(function () {
                    $(this).remove();
                });
            }, 3000);
        }
    });

    return $.grupoawamotos.erpSuggestions;
});
