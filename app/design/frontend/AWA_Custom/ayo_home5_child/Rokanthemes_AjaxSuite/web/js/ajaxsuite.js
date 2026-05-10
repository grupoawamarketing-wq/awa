define([
    'jquery',
    'mage/translate',
    'Magento_Customer/js/customer-data',
    'Rokanthemes_AjaxSuite/js/model/ajaxsuite-popup',
    'mage/validation/validation'
], function ($, $t, customerData, ajaxsuitepopup) {
    'use strict';

    $.widget('rokanthemes.ajaxsuite', {
        options: {
                popupWrapperSelector : '#mb-ajaxsuite-popup-wrapper',
                ajaxCart: {
                    enabled: 0,
                    actionAfterSuccess: 'popup',
                    continueShoppingSelector: '#button_continue_shopping',
                    minicartSelector: '[data-block="minicart"]',
                    messagesSelector: '[data-placeholder="messages"]',
                    initConfig: {
                        'show_success_message': true,
                        'timerErrorMessage': 3000,
                        'addWishlistItemUrl': null
                    },
                    formKey: null,
                    formKeyInputSelector: 'input[name="form_key"]',
                    addToCartButtonSelector: 'button.tocart',
                    addToCartUrl: null,
                    addToCartInWishlistUrl: null,
                    wishlistAddToCartUrl: null,
                    checkoutCartUrl: null,
                    addToCartButtonDisabledClass: 'disabled',
                    addToCartButtonTextWhileAdding: $t('Adding...'),
                    addToCartButtonTextAdded: $t('Added'),
                    addToCartButtonTextDefault: $t('Add to Cart')
                },
                ajaxWishList: {
                    enabled: 0,
                    WishlistUrl: null,
                    wishlistBtnSelector: '[data-action="add-to-wishlist"]',
                    btnCloseSelector: '#ajaxwishlist_btn_close_popup',
                    btnCancelSelector: '#ajaxwishlist_btn_cancel',
                    btnToLoginSelector: '#ajaxwishlist_btn_to_login'
                },
                ajaxCompare: {
                    enabled: 0,
                    compareSelector: '.tocompare',
                    CompareUrl: null,
                },
                quickView: {
                    enabled: 0
                },
                popupSelector: '#ajaxsuite-popup-content',
                loginUrl: null,
                customerId: null

        },
        _create: function() {
            this._bind();
            this.options.popupWrapper = $('<div />', {
                    'id': 'mb-ajaxsuite-popup-wrapper'
                }).appendTo($('#ajaxsuite-popup-content'));
        },
        _hasPopupHtmlContent: function (html) {
            var htmlString = (typeof html === 'string') ? html : '';
            var $probe;
            var text;

            if (!htmlString) {
                return false;
            }

            $probe = $('<div/>').html(htmlString);

            if ($probe.find('#mb-ajaxsuite-popup-wrapper, .wrapper-success, .block-authentication, form, img, button, .action').length) {
                return true;
            }

            text = ($probe.text() || '').replace(/\s+/g, ' ').trim();

            return text.length > 0;
        },
        _renderPopupHtml: function (html) {
            if (!this.options.popupWrapper || !this.options.popupWrapper.length) {
                return false;
            }

            if (!this._hasPopupHtmlContent(html)) {
                this.options.popupWrapper.empty();
                return false;
            }

            this.options.popupWrapper.html(html);
            return true;
        },
        showModal: function (element) {
            var created;

            created = ajaxsuitepopup.createPopUp(element);

            if (created === false) {
                ajaxsuitepopup.hideModal();
                return ajaxsuitepopup;
            }

            ajaxsuitepopup.showModal();
            return ajaxsuitepopup;
        },
        getCustomerData: function()
        {
            var customer = customerData.get('customer');
            var customerInfo = customer();
            if (customerInfo && customerInfo.data_id) {
                return true;
            }
            return false;
        },
        initEventsWishlist: function()
        {
            var self = this;
            var get_customer_data = this.getCustomerData();
            
            if(!get_customer_data){
                //$(self.options.ajaxWishList.wishlistBtnSelector).addClass("trigger-auth-popup").attr('data-action', 'ajax-popup-login').removeAttr("data-post");
            }
            $('body').on('click',self.options.ajaxWishList.wishlistBtnSelector, function (e) {
                if (!get_customer_data) {
                    $(self.options.ajaxWishList.wishlistBtnSelector).addClass("trigger-auth-popup").attr('data-action', 'ajax-popup-login').attr('href', 'javascript:void(0);').removeAttr("data-post");
                    e.preventDefault();
                    return;
                }
				var _this_fixed = $(this);
                _this_fixed.addClass('loading');
                e.preventDefault();
                e.stopPropagation();
                if($(this).data('post'))
                {
                    var params = $(this).data('post').data;
                }else
                {
                    var params = {};
                }
                params['ajax_post'] = true;
                $('body').trigger('processStart');
                $.ajax({
                    url: self.options.ajaxWishList.WishlistUrl,
                    data: params,
                    type: 'post',
                    showLoader: false,
                    dataType: 'json',
                    success: function (res) {
                        ajaxsuitepopup.hideModal();
                        if (res.html_popup) {
                            if (self._renderPopupHtml(res.html_popup)) {
                                self.showModal(self.options.popupWrapper);
                            }
                        }
                        self.reloadCustomerData(['wishlist']);
						_this_fixed.removeClass('loading');
                    },
                    error: function (res) {
                        alert('Error in sending ajax request');
						_this_fixed.removeClass('loading');
                    }
                });
                $('body').trigger('processStop');
            });
        },
        initEventsCompare: function () {
            var self = this;
            $('body').on('click',self.options.ajaxCompare.compareSelector, function (e) {

                e.preventDefault();
                e.stopPropagation();
				var _this_fixed = $(this);
                _this_fixed.addClass('loading');
                var params = $(this).data('post').data;
                if($(this).data('post'))
                {
                    var params = $(this).data('post').data;
                }else
                {
                    var params = {};
                }
                $('body').trigger('processStart');
                $.ajax({
                    url: self.options.ajaxCompare.CompareUrl,
                    data: params,
                    type: 'post',
                    showLoader: false,
                    dataType: 'json',
                    success: function (res) {
                        ajaxsuitepopup.hideModal();
                        if (res.html_popup) {
                            if (self._renderPopupHtml(res.html_popup)) {
                                self.showModal(self.options.popupWrapper);
                            }
                        }
                        self.reloadCustomerData(['compare-products']);
						_this_fixed.removeClass('loading');
                    },
                    error: function (res) {
                        alert('Error in sending ajax request');
						_this_fixed.removeClass('loading');
                    }
                });
                $('body').trigger('processStop');
            });
        },
        initEventsAjaxCart: function()
        {
            var self = this;

            $('body').off('click.awaAjaxsuiteAddToCart', self.options.ajaxCart.addToCartButtonSelector);
            $('body').on('click.awaAjaxsuiteAddToCart', self.options.ajaxCart.addToCartButtonSelector, function (e) {
                var form = $(this).closest('form');
                if(form.length)
                {
                    var action = form.attr('action');
                    if(action.indexOf('checkout/cart/add') != -1)
                    {
                        e.preventDefault();
                        if ($(this).closest('.product-info-main').length) {             //In product details page
                            var dataForm = $(this).closest('form#product_addtocart_form');
                            var validate = dataForm.validation('isValid');
                            if (validate) {
                                self.ajaxCartSubmit(form);
                            }
                            return;
                        }
                        self.ajaxCartSubmit(form);
                    }
                }
            });

            $('body').off('click.awaAjaxsuiteContinueShopping', self.options.ajaxCart.continueShoppingSelector);
            $('body').on('click.awaAjaxsuiteContinueShopping', self.options.ajaxCart.continueShoppingSelector, function () {
                ajaxsuitepopup.hideModal();
            });

            $(document).off('ajaxComplete.awaAjaxsuiteMinicartGuard');
            $(document).on('ajaxComplete.awaAjaxsuiteMinicartGuard', function (event, xhr, settings) {
                var $minicart = $(self.options.ajaxCart.minicartSelector);
                var responseJson = xhr && xhr.responseJSON ? xhr.responseJSON : null;

                if (!settings
                    || !settings.type
                    || !settings.url
                    || !settings.type.match(/get/i)
                    || !settings.url.match(/customer\/section\/load/i)
                    || !responseJson
                    || typeof responseJson !== 'object'
                    || !responseJson.cart
                ) {
                    return;
                }

                if ($minicart.hasClass('ajaxcartcomplete'))
                {
                    $minicart.trigger('contentUpdated');
                    $minicart.trigger('awa:ajaxsuite-cart-updated');
                }

                $minicart.removeClass('ajaxcartcomplete');
            });
        },
        ajaxCartSubmit: function (form) {
            var self = this;
            $(self.options.ajaxCart.minicartSelector).trigger('contentLoading');
			$('body').addClass('ajax-cart');
            self.disableAddToCartButton(form);
            $.ajax({
                url: form.attr('action').replace('checkout/cart', 'ajaxsuite/cart'),
                data: form.serialize(),
                type: 'post',
                showLoader: false,
                dataType: 'json',
                success: function (res) {
                    ajaxsuitepopup.hideModal();
                    if (res.success) {
                        if(self.options.ajaxCart.actionAfterSuccess == 'popup')
                        {
                            if (self._renderPopupHtml(res.success)) {
                                self.showModal(self.options.popupWrapper);
                            } else {
                                $(self.options.ajaxCart.minicartSelector).addClass('ajaxcartcomplete');
                            }
                        }else{
                            $(self.options.ajaxCart.minicartSelector).addClass('ajaxcartcomplete');
                        }
                        self.reloadCustomerData(['cart']);
                        //$(self.options.ajaxCart.minicartSelector + ' a.showcart').trigger('click');
                    }
                    else if (res.error && res.url) {
                        window.location.href = res.url;
                    }else if (res.error && res.content) {
                        if(!form.closest(self.options.popupWrapperSelector).length)
                        {
                            if (self._renderPopupHtml(res.content)) {
                                self.showModal(self.options.popupWrapper);
                            }
                        }
                    }else if(res.error)
                    {
                        if (self._renderPopupHtml(res.error)) {
                            self.showModal(self.options.popupWrapper);
                        }
                        window.location.reload();
                    }
                    self.enableAddToCartButton(form);
                    $(self.options.ajaxCart.minicartSelector).trigger('contentUpdated');
					$('body').removeClass('ajax-cart');
                },
                error: function (xhr, status, error) {
                    console.error('AjaxSuite Cart Error:', status, error);
                    self.enableAddToCartButton(form);
                    $(self.options.ajaxCart.minicartSelector).trigger('contentUpdated');
                    $('body').removeClass('ajax-cart');
                    // Fallback: submit form normally
                    form.off('submit').submit();
                }
            });
        },
        disableAddToCartButton: function (form) {
            var addToCartButton = $(form).find(this.options.ajaxCart.addToCartButtonSelector);
            addToCartButton.addClass(this.options.ajaxCart.addToCartButtonDisabledClass);
            addToCartButton.attr('title', this.options.ajaxCart.addToCartButtonTextWhileAdding);
            addToCartButton.find('span').text(this.options.ajaxCart.addToCartButtonTextWhileAdding);
        },
        enableAddToCartButton: function (form) {
            var self = this, addToCartButton = $(form).find(this.options.ajaxCart.addToCartButtonSelector);
            addToCartButton.find('span').text(this.options.ajaxCart.addToCartButtonTextAdded);
            addToCartButton.attr('title', this.options.ajaxCart.addToCartButtonTextAdded);

            setTimeout(function () {
                addToCartButton.removeClass(self.options.ajaxCart.addToCartButtonDisabledClass);
                addToCartButton.find('span').text(self.options.ajaxCart.addToCartButtonTextDefault);
                addToCartButton.attr('title', self.options.ajaxCart.addToCartButtonTextDefault);
            }, 1000);
        },
        reloadCustomerData: function(sessionName)
        {
            customerData.reload(sessionName, false);
        },
        _bind: function () {
            if(this.options.ajaxCart.enabled)
            {
               this.initEventsAjaxCart();
            }
            if(this.options.ajaxWishList.enabled)
            {
                this.initEventsWishlist();
            }
            if(this.options.ajaxCompare.enabled)
            {
                this.initEventsCompare();
            }
        }

    });

    return $.rokanthemes.ajaxsuite;
});
