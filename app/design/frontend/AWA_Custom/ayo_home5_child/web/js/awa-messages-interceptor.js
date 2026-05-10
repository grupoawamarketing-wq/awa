/**
 * AWA Messages Interceptor (2026-03-19)
 *
 * Intercepts Magento's native page messages and surfaces them as toasts.
 * Covers both:
 *   1. Session flash messages rendered server-side in .page.messages
 *   2. AJAX-delivered messages via customerData 'messages' section
 *
 * Cart "added to cart" messages are skipped — awa-toast.js already
 * handles those via the cart customer-data subscription.
 *
 * Loaded globally via x-magento-init in awa-custom-js-loader.phtml.
 */
define([
    'js/awa-toast',
    'Magento_Customer/js/customer-data'
], function (toast, customerData) {
    'use strict';

    /* Messages that are already handled by awa-toast.js cart subscription */
    let CART_PATTERNS = [
        /adicionado ao carrinho/i,
        /added .+ to your shopping cart/i,
        /você adicionou/i
    ];

    function isCartMessage(text) {
        return CART_PATTERNS.some(function (re) { return re.test(text); });
    }

    /* Map Magento message types to toast types */
    function normalizeType(type) {
        if (type === 'notice') return 'info';
        if (type === 'success' || type === 'error' || type === 'warning' || type === 'info') {
            return type;
        }
        return 'info';
    }

    function showIfNotCart(type, text) {
        if (!text || isCartMessage(text)) return;
        toast.show({
            type:     normalizeType(type),
            message:  text,
            duration: type === 'error' ? 8000 : 5000
        });
    }

    /* --- 1. Server-rendered DOM messages (session flashes) ---
     * Magento renders these synchronously into .page.messages before
     * KnockoutJS runs. We show them as toasts and then hide the originals.
     */
    function interceptDomMessages() {
        let selectors = [
            '.messages .message-success',
            '.messages .message-error',
            '.messages .message-warning',
            '.messages .message-notice',
            '.messages .message-info',
            /* Magento also uses shortened classes */
            '.messages .success',
            '.messages .error',
            '.messages .warning',
            '.messages .notice'
        ];

        selectors.forEach(function (sel) {
            document.querySelectorAll(sel).forEach(function (el) {
                if (el.dataset.awaIntercepted) return;
                el.dataset.awaIntercepted = '1';

                /* Extract text — KO data-bind may not have run yet, so check both */
                let textEl = el.querySelector('[data-bind], .inner, p, span, div');
                let text   = (textEl ? textEl.textContent : el.textContent).trim();

                if (!text) return;

                /* Determine type from classes */
                let type = 'info';
                if (el.classList.contains('message-success') || el.classList.contains('success')) type = 'success';
                if (el.classList.contains('message-error')   || el.classList.contains('error'))   type = 'error';
                if (el.classList.contains('message-warning') || el.classList.contains('warning')) type = 'warning';

                showIfNotCart(type, text);

                /* Collapse the native message element (keeps DOM for a11y fallback) */
                el.setAttribute('aria-hidden', 'true');
                el.style.cssText = 'height:0;overflow:hidden;margin:0;padding:0;border:none';
            });
        });

        /* Hide the container if all children are intercepted */
        document.querySelectorAll('.messages').forEach(function (container) {
            let visible = container.querySelectorAll('[data-bind]:not([data-awa-intercepted="1"])');
            if (!visible.length) {
                container.style.cssText = 'display:none';
            }
        });
    }

    let customerDataMessagesBound = false;
    let domObserverBound = false;
    let initialDomScanScheduled = false;
    let seenThisLoad = new Set();
    let initialDone = false;

    /* --- 2. customer-data 'messages' section (AJAX-delivered) --- */
    function bindCustomerDataMessages() {
        if (customerDataMessagesBound) {
            return;
        }
        customerDataMessagesBound = true;

        let messagesSection = customerData.get('messages');

        messagesSection.subscribe(function (data) {
            let msgs = (data && data.messages) || [];

            /* Skip the initial population (page already rendered them) */
            if (!initialDone) {
                initialDone = true;
                msgs.forEach(function (m) { seenThisLoad.add(m.type + '|' + m.text); });
                return;
            }

            msgs.forEach(function (m) {
                let key = m.type + '|' + m.text;
                if (seenThisLoad.has(key)) return;
                seenThisLoad.add(key);
                showIfNotCart(m.type, m.text);
            });
        });
    }

    /* --- 3. MutationObserver for KO-rendered messages ---
     * KnockoutJS renders messages asynchronously. Observe .page.messages
     * for additions after DOM load.
     */
    function bindDomObserver() {
        if (domObserverBound) {
            return;
        }

        let target = document.querySelector('.page.messages, .messages-container');
        if (!target || !window.MutationObserver) {
            return;
        }

        let observer = new MutationObserver(function () {
            interceptDomMessages();
        });

        observer.observe(target, { childList: true, subtree: true });
        domObserverBound = true;
    }

    /* Run initial DOM scan after a brief delay to allow KO first render */
    function scheduleInitialDomScan() {
        if (initialDomScanScheduled) {
            return;
        }

        initialDomScanScheduled = true;
        setTimeout(interceptDomMessages, 150);
    }

    function init() {
        bindCustomerDataMessages();
        bindDomObserver();
        scheduleInitialDomScan();
    }

    let api = {
        init: init
    };

    function initializer() {
        init();
        return api;
    }

    initializer.init = init;
    initializer.interceptDomMessages = interceptDomMessages;

    init();

    return initializer;
});
