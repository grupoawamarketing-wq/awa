define([
    'awa-header-sticky',
    'awa-header-nav-runtime',
    'awa-header-customer-runtime'
], function (
    initStickyHeader,
    initHeaderNavRuntime,
    initHeaderCustomerRuntime
) {
    'use strict';

    function initPromoBarDismiss() {
        var bar = document.getElementById('awa-b2b-promo-bar');
        var btn = document.getElementById('awa-b2b-promo-close');
        if (!bar || !btn) {
            return;
        }
        btn.addEventListener('click', function () {
            bar.classList.add('is-dismissing');
            setTimeout(function () {
                bar.style.display = 'none';
            }, 320);
            try {
                localStorage.setItem('awa_b2b_promo_dismissed', '1');
            } catch (storageError) {
                console.warn('[AWA] promo bar dismiss: localStorage unavailable', storageError);
            }
        });
    }

    return function bootstrapHeaderRuntime() {
        if (window.__awaHeaderRuntimeBootstrapInit) {
            return;
        }

        window.__awaHeaderRuntimeBootstrapInit = true;

        if (typeof initStickyHeader === 'function') {
            initStickyHeader();
        }
        if (typeof initHeaderNavRuntime === 'function') {
            initHeaderNavRuntime();
        }
        if (typeof initHeaderCustomerRuntime === 'function') {
            initHeaderCustomerRuntime();
        }

        initPromoBarDismiss();
    };
});
