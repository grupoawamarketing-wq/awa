define(['Magento_Customer/js/customer-data'], function (customerData) {
    'use strict';

    return function initHeaderCustomerRuntime() {
        if (window.__awaHeaderCustomerRuntimeInit) {
            return;
        }

        window.__awaHeaderCustomerRuntimeInit = true;

        function isLoggedIn(data) {
            if (!data || typeof data !== 'object') {
                return false;
            }

            return !!(
                data.firstname
                || data.fullname
                || data.email
                || data.id
                || data.entity_id
                || data.websiteId !== undefined
            );
        }

        function updateRightCol(data) {
            var accountNav = document.querySelector('[data-awa-account-nav]');
            var rightCol = document.querySelector('[data-awa-header-right]');
            var customerLoggedIn = isLoggedIn(data);

            if (customerLoggedIn) {
                if (accountNav) {
                    accountNav.style.removeProperty('display');
                }
                if (rightCol) {
                    rightCol.classList.add('awa-header-right--logged');
                }
                return;
            }

            if (accountNav) {
                accountNav.style.setProperty('display', 'none', 'important');
            }
            if (rightCol) {
                rightCol.classList.remove('awa-header-right--logged');
            }
        }

        function updateMcpDashboardLink(data) {
            var link = document.getElementById('awa-mcp-dashboard-link');
            if (!link) {
                return;
            }

            if (isLoggedIn(data)) {
                link.hidden = false;
                link.setAttribute('aria-hidden', 'false');
                link.style.setProperty('display', 'inline-flex', 'important');
                link.classList.add('is-visible');
                return;
            }

            link.hidden = true;
            link.setAttribute('aria-hidden', 'true');
            link.style.setProperty('display', 'none', 'important');
            link.classList.remove('is-visible');
        }

        function syncCustomerUi(data) {
            updateRightCol(data);
            updateMcpDashboardLink(data);
        }

        var customer = customerData.get('customer');
        syncCustomerUi(customer());
        customer.subscribe(syncCustomerUi);
    };
});
