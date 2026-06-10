var config = {
    map: {
        '*': {
            'Magento_Checkout/template/billing-address/form.html':
                'Magento_Checkout/template/billing-address/form.html'
        }
    },
    config: {
        mixins: {
            'Rokanthemes_OnePageCheckout/js/view/place-order-btn': {
                'Rokanthemes_OnePageCheckout/js/view/place-order-btn-mixin': true
            },
            'Rokanthemes_OnePageCheckout/js/view/shipping': {
                'Rokanthemes_OnePageCheckout/js/view/shipping-autosave-mixin': true
            }
        }
    }
};
