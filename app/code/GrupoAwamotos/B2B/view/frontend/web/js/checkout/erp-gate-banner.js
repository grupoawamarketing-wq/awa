/**
 * ERP purchase gate — banner + disable place-order when customer pending Sectra validation.
 *
 * @module GrupoAwamotos_B2B/js/checkout/erp-gate-banner
 */
define(['jquery'], function ($) {
    'use strict';

    var GATE_SELECTOR = '.awa-b2b-checkout-erp-gate';
    var CART_GATE_SELECTOR = GATE_SELECTOR + '[data-awa-component="b2b-erp-gate-notice"]';
    var PLACE_ORDER_SELECTOR = [
        '#opc-sidebar .action.primary.checkout',
        '.actions-toolbar .action.checkout',
        '.actions-toolbar .btn-placeorder',
        'button[data-role="review-save"]',
        '.payment-method-content .action.primary.checkout',
        '.checkout-methods-items .action.primary.checkout',
        '.cart-summary .action.primary.checkout'
    ].join(', ');

    var scheduled = false;
    var clickBound = false;

    function getConfig() {
        return window.checkoutConfig || {};
    }

    function isBlockedOnCheckout() {
        var cfg = getConfig();
        return cfg.b2bCheckoutBlocked === true || cfg.b2bCheckoutBlocked === 1;
    }

    function isBlockedOnCart() {
        return $(CART_GATE_SELECTOR).length > 0;
    }

    function isBlocked() {
        return isBlockedOnCheckout() || isBlockedOnCart();
    }

    function getMessage() {
        var cfg = getConfig();
        var $cartGate = $(CART_GATE_SELECTOR);

        if ($cartGate.length) {
            return $.trim($cartGate.find('.awa-b2b-checkout-erp-gate__text').text());
        }

        return $.trim(cfg.b2bCheckoutBlockMessage || '') ||
            'Seu cadastro B2B está em análise. Assim que aprovado, seus pedidos serão liberados.';
    }

    function buildGateHtml() {
        return '<section class="awa-b2b-checkout-erp-gate" role="alert" aria-live="assertive" data-awa-component="b2b-erp-gate-banner">' +
            '<div class="awa-b2b-checkout-erp-gate__icon" aria-hidden="true">' +
            '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
            '<circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/>' +
            '</svg></div>' +
            '<div class="awa-b2b-checkout-erp-gate__body">' +
            '<h3 class="awa-b2b-checkout-erp-gate__title">Finalização indisponível</h3>' +
            '<p class="awa-b2b-checkout-erp-gate__text"></p>' +
            '<p class="awa-b2b-checkout-erp-gate__note">Entre em contato com o departamento comercial para acelerar a liberação da sua conta.</p>' +
            '</div></section>';
    }

    function bindClickGuard() {
        if (clickBound) {
            return;
        }

        clickBound = true;
        $(document).on('click.awaB2bErpGate', PLACE_ORDER_SELECTOR, function (event) {
            if (!isBlocked()) {
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();
        });
    }

    function clearGate() {
        $(document.body).removeClass('awa-b2b-checkout-erp-blocked');
        $(GATE_SELECTOR + '[data-awa-component="b2b-erp-gate-banner"]').remove();
        $(PLACE_ORDER_SELECTOR).prop('disabled', false).removeAttr('aria-disabled');
    }

    function applyCheckoutGate() {
        if (!isBlockedOnCheckout()) {
            return;
        }

        var $sidebar = $('#opc-sidebar, .opc-sidebar, .opc-block-summary').first();
        var $target = $sidebar.length ? $sidebar : $('.checkout-container, .page-main .columns').first();

        if (!$target.length) {
            return;
        }

        var $gate = $target.children(GATE_SELECTOR + '[data-awa-component="b2b-erp-gate-banner"]');
        if (!$gate.length) {
            $target.prepend(buildGateHtml());
            $gate = $target.children(GATE_SELECTOR + '[data-awa-component="b2b-erp-gate-banner"]');
        }

        $gate.find('.awa-b2b-checkout-erp-gate__text').text(getMessage());
    }

    function applyGate() {
        if (!isBlocked()) {
            clearGate();
            return;
        }

        if (isBlockedOnCheckout()) {
            applyCheckoutGate();
        }

        $(document.body).addClass('awa-b2b-checkout-erp-blocked');
        $(PLACE_ORDER_SELECTOR).prop('disabled', true).attr('aria-disabled', 'true');
        bindClickGuard();
    }

    function scheduleApply() {
        if (scheduled) {
            return;
        }
        scheduled = true;
        window.requestAnimationFrame(function () {
            scheduled = false;
            applyGate();
        });
    }

    function isRelevantPage() {
        if (!document.body) {
            return false;
        }

        return document.body.classList.contains('checkout-cart-index') ||
            document.body.classList.contains('checkout-index-index') ||
            document.body.classList.contains('rokanthemes-onepagecheckout') ||
            document.body.classList.contains('onepagecheckout-index-index');
    }

    return function () {
        if (!isRelevantPage()) {
            return;
        }

        scheduleApply();

        if (window.MutationObserver && !window.__awaB2bErpGateObserver) {
            window.__awaB2bErpGateObserver = new window.MutationObserver(scheduleApply);
            window.__awaB2bErpGateObserver.observe(document.body, {
                childList: true,
                subtree: true
            });
        }
    };
});
