/**
 * B2B RequireJS Configuration
 */
var config = {
    map: {
        '*': {
            inputMask: 'GrupoAwamotos_B2B/js/inputMask',
            b2bHeaderStatusPanel: 'GrupoAwamotos_B2B/js/header-status-panel',
            'Rokanthemes_AjaxSuite/template/authentication-popup.html':
                'GrupoAwamotos_B2B/template/authentication-popup.html'
        }
    },
    config: {
        mixins: {
            // P0-1: Inject PO Number into payment data
            // P2-4.2: Inject Order Notes into payment data
            'Magento_Checkout/js/action/place-order': {
                'GrupoAwamotos_B2B/js/action/place-order-mixin': true
            },
            'Magento_Checkout/js/action/set-payment-information': {
                'GrupoAwamotos_B2B/js/model/payment/po-number-assigner': true,
                'GrupoAwamotos_B2B/js/model/payment/order-notes-assigner': true
            },
            'Magento_Checkout/js/action/set-payment-information-extended': {
                'GrupoAwamotos_B2B/js/model/payment/po-number-assigner': true,
                'GrupoAwamotos_B2B/js/model/payment/order-notes-assigner': true
            },
            // P2-4.1: Auto-fill empresa/IE na billing address
            'Magento_Checkout/js/action/set-billing-address': {
                'GrupoAwamotos_B2B/js/model/checkout/company-autofill': true
            }
        }
    }
};
