(function () {
    'use strict';

    if (window.__awaB2bAnalyticsInit) {
        return;
    }
    window.__awaB2bAnalyticsInit = true;

    function pushEvent(name, payload) {
        window.dataLayer = window.dataLayer || [];
        var data = payload || {};
        data.event = name;
        try {
            window.dataLayer.push(data);
        } catch (e) {
            /* ignore analytics failures */
        }
    }

    function bindCta(selector, eventName, extra) {
        document.querySelectorAll(selector).forEach(function (el) {
            if (el.dataset.awaB2bTracked) {
                return;
            }
            el.dataset.awaB2bTracked = '1';
            el.addEventListener('click', function () {
                pushEvent(eventName, extra || {});
            });
        });
    }

    function init() {
        bindCta('.awa-hero-b2b-cta a[href*="b2b"], .awa-b2b-testimonials-cta a[href*="b2b"]', 'b2b_cta_click', {
            event_category: 'b2b_conversion',
            event_label: 'home_cta'
        });

        bindCta('.b2b-login-to-see-price a, .b2b-login-to-see-price', 'b2b_price_gate_click', {
            event_category: 'b2b_conversion',
            event_label: 'price_gate'
        });

        bindCta('.b2b-quote-clone-form button', 'b2b_quote_clone', {
            event_category: 'b2b_account',
            event_label: 'quote_clone'
        });

        document.querySelectorAll('.js-reorder-form').forEach(function (form) {
            if (form.dataset.awaB2bTracked) {
                return;
            }
            form.dataset.awaB2bTracked = '1';
            form.addEventListener('submit', function () {
                pushEvent('b2b_reorder_submit', {
                    event_category: 'b2b_account',
                    event_label: 'reorder_ajax'
                });
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
