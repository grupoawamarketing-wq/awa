define([
    'awa-header-sticky',
    'awa-vertical-menu-focus-trap',
    'awa-header-nav-runtime',
    'awa-header-customer-runtime'
], function (
    initStickyHeader,
    initFocusTrap,
    initHeaderNavRuntime,
    initHeaderCustomerRuntime
) {
    'use strict';

    return function bootstrapHeaderRuntime() {
        if (window.__awaHeaderRuntimeBootstrapInit) {
            return;
        }

        window.__awaHeaderRuntimeBootstrapInit = true;

        if (typeof initStickyHeader === 'function') {
            initStickyHeader();
        }
        if (typeof initFocusTrap === 'function') {
            initFocusTrap();
        }
        if (typeof initHeaderNavRuntime === 'function') {
            initHeaderNavRuntime();
        }
        if (typeof initHeaderCustomerRuntime === 'function') {
            initHeaderCustomerRuntime();
        }
    };
});
