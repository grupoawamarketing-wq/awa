define([
    'Magento_Customer/js/customer-data'
], function (customerData) {
    'use strict';

    var hydratedProducts = {};
    var refreshScheduled = false;

    function isLoggedIn(customer) {
        if (!customer || typeof customer !== 'object') {
            return false;
        }

        return !!(
            customer.firstname
            || customer.fullname
            || customer.email
            || customer.id
            || customer.entity_id
            || customer.websiteId !== undefined
        );
    }

    function getCustomerPayload() {
        try {
            return customerData.get('customer')();
        } catch (e) {
            return {};
        }
    }

    function resolveProductId(node) {
        var root;
        var fromDataset;
        var productInput;
        var quickviewTrigger;

        root = node.closest('[data-product-id], .item-product, .product-item, li, .product-info-main, .product-add-form') || node.parentElement;

        fromDataset = root && root.getAttribute ? parseInt(root.getAttribute('data-product-id'), 10) : 0;
        if (fromDataset) {
            return String(fromDataset);
        }

        productInput = root ? root.querySelector('input[name="product"]') : null;
        if (productInput && productInput.value) {
            return String(parseInt(productInput.value, 10));
        }

        quickviewTrigger = root ? root.querySelector('[data-role="quickview-button"][data-id]') : null;
        if (quickviewTrigger) {
            return String(parseInt(quickviewTrigger.getAttribute('data-id'), 10));
        }

        return null;
    }

    function collectTargets() {
        var targets = {};

        document.querySelectorAll('.b2b-login-to-see-price').forEach(function (priceMarker) {
            var productId = resolveProductId(priceMarker);

            if (!productId || hydratedProducts[productId]) {
                return;
            }

            if (!targets[productId]) {
                targets[productId] = [];
            }

            targets[productId].push(priceMarker);
        });

        return targets;
    }

    function replaceTargets(targets, payloadItems) {
        Object.keys(payloadItems).forEach(function (productId) {
            var item = payloadItems[productId];

            if (!item || !item.html || !targets[productId]) {
                return;
            }

            targets[productId].forEach(function (marker) {
                marker.outerHTML = item.html;
            });

            hydratedProducts[productId] = true;
        });
    }

    function hydratePrices() {
        var targets;
        var productIds;

        if (!isLoggedIn(getCustomerPayload()) || typeof window.fetch !== 'function') {
            return;
        }

        targets = collectTargets();
        productIds = Object.keys(targets);

        if (!productIds.length) {
            return;
        }

        window.fetch('/b2b/ajax/customerPrices?product_ids=' + encodeURIComponent(productIds.join(',')), {
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(function (response) {
            return response.ok ? response.json() : null;
        }).then(function (payload) {
            if (!payload || !payload.success || !payload.allowed || !payload.items) {
                return;
            }

            replaceTargets(targets, payload.items);
        }).catch(function () {
            // no-op: placeholder remains until next refresh
        });
    }

    function scheduleHydration() {
        if (refreshScheduled) {
            return;
        }

        refreshScheduled = true;
        window.setTimeout(function () {
            refreshScheduled = false;
            hydratePrices();
        }, 120);
    }

    function init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', scheduleHydration);
        } else {
            scheduleHydration();
        }

        try {
            customerData.get('customer').subscribe(function (customer) {
                if (isLoggedIn(customer)) {
                    scheduleHydration();
                }
            });
        } catch (e) {
            // ignore subscription errors
        }

        new MutationObserver(function (mutations) {
            var shouldRefresh = false;

            mutations.forEach(function (mutation) {
                if (mutation.addedNodes && mutation.addedNodes.length) {
                    shouldRefresh = true;
                }
            });

            if (shouldRefresh) {
                scheduleHydration();
            }
        }).observe(document.body, {childList: true, subtree: true});
    }

    return init;
});
