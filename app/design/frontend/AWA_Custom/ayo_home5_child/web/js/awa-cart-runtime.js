/**
 * Carrinho AWA — runtime único (1 mage-init, 1 árvore RequireJS).
 *
 * @module js/awa-cart-runtime
 */
define([
    'js/awa-cart-summary-polish',
    'js/awa-cart-table-a11y',
    'js/awa-cart-min-order-live',
    'js/awa-cart-form-feedback',
    'js/awa-cart-qty-auto-update',
    'js/awa-cart-mobile-bar',
    'js/awa-cart-page-meta-sync',
    'Magento_Customer/js/customer-data'
], function (summaryPolish, tableA11y, minOrderLive, formFeedback, qtyAutoUpdate, mobileBar, pageMetaSync, customerData) {
    'use strict';

    function clearStaleCartBadges() {
        document.querySelectorAll('.awa-header-cart-link .awa-cart-link-badge').forEach(function (badge) {
            badge.textContent = '';
            badge.style.display = 'none';
            badge.classList.add('awa-badge-hidden');
            badge.setAttribute('aria-hidden', 'true');
        });

        document.querySelectorAll(
            '[data-awa-header-minicart-shell="true"] .counter.qty, .awa-header-minicart[data-awa-header-cart="true"] .counter.qty'
        ).forEach(function (counter) {
            counter.classList.add('empty');
            counter.querySelectorAll('.counter-number, .total-mini-cart-item').forEach(function (node) {
                node.textContent = '0';
            });
        });
    }

    /**
     * Carrinho vazio no servidor mas customer-data/localStorage ainda com itens (badge stale).
     */
    function syncEmptyCartCustomerData() {
        if (window.__awaCartEmptySyncDone) {
            return;
        }

        var emptyNode = document.querySelector('[data-awa-cart-empty="1"]');

        if (!emptyNode) {
            return;
        }

        window.__awaCartEmptySyncDone = true;

        var serverCount = parseInt(emptyNode.getAttribute('data-awa-server-items-count') || '0', 10);

        if (isNaN(serverCount)) {
            serverCount = 0;
        }

        var cartSection = customerData.get('cart')();
        var localCount = parseInt(cartSection.summary_count || 0, 10);

        if (serverCount === 0 && localCount > 0) {
            clearStaleCartBadges();
        }

        if (localCount === serverCount) {
            return;
        }

        customerData.invalidate(['cart']);
        customerData.reload(['cart'], true).done(function () {
            if (serverCount === 0) {
                clearStaleCartBadges();
            }
        });
    }

    return function () {
        if (!document.body.classList.contains('checkout-cart-index')) {
            return;
        }

        summaryPolish();

        if (document.querySelector('[data-awa-cart-empty="1"]')) {
            syncEmptyCartCustomerData();
            return;
        }

        if (document.getElementById('form-validate')) {
            tableA11y();
            minOrderLive();
            formFeedback();
            qtyAutoUpdate();
            mobileBar();
            pageMetaSync();
        }
    };
});
