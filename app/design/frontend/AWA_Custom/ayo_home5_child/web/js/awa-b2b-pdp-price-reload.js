/**
 * AWA Motos — awa-b2b-pdp-price-reload.js  (v2 — reescrito 2026-04)
 *
 * PROBLEMA: FPC varia por customer_id (HttpContextPlugin), mas quando o
 * cliente está em uma aba do PDP aberta ANTES do login, a aba ainda mostra
 * o HTML cacheado do convidado (.b2b-login-to-see-price visível).
 *
 * SOLUÇÃO: Detectar via customer-data que o cliente está logado mas o preço
 * está escondido e fazer UM reload simples. Após o reload, o browser envia o
 * novo cookie X-Magento-Vary com customer_id correto → FPC serve o HTML certo.
 *
 * NÃO usa ?no_cache=1 (não bypassa o FPC built-in do Magento).
 * Loop prevention: sessionStorage com pathname, max 1 tentativa por URL por tab.
 */
define(['Magento_Customer/js/customer-data'], function (customerData) {
    'use strict';

    let RELOAD_KEY = 'awa_b2b_pdp_reloaded';

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

    function isPriceFenced() {
        let el = document.querySelector('.b2b-login-to-see-price');
        return !!(el && (el.offsetWidth || el.offsetHeight));
    }

    function wasAlreadyReloaded() {
        return sessionStorage.getItem(RELOAD_KEY) === window.location.pathname;
    }

    function hydratePdpPrice() {
        let productInput = document.querySelector('#product_addtocart_form input[name="product"]');
        let fencedPrice = document.querySelector('.product-info-price .b2b-login-to-see-price');

        if (!productInput || !productInput.value || !fencedPrice || typeof window.fetch !== 'function') {
            return Promise.resolve(false);
        }

        return window.fetch('/b2b/ajax/customerPrices?product_ids=' + encodeURIComponent(productInput.value), {
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(function (response) {
            return response.ok ? response.json() : null;
        }).then(function (payload) {
            let item;

            if (!payload || !payload.success || !payload.allowed || !payload.items) {
                return false;
            }

            item = payload.items[String(productInput.value)];
            if (!item || !item.html) {
                return false;
            }

            fencedPrice.outerHTML = item.html;
            return true;
        }).catch(function () {
            return false;
        });
    }

    function tryRefresh(customer) {
        if (!isPriceFenced()) {
            return; // Preco ja visivel — nada a fazer
        }
        if (!isLoggedIn(customer)) {
            return; // Cliente nao logado — mostrar a mensagem de login e-normal
        }
        if (wasAlreadyReloaded()) {
            return; // Ja tentamos reload nesta aba — cliente pode nao ter permissao
        }

        // Marcar ANTES do reload para evitar loop se o reload retornar a pagina errada
        sessionStorage.setItem(RELOAD_KEY, window.location.pathname);
        customerData.invalidate(['customer', 'cart']);
        customerData.reload(['customer', 'cart'], true);
        hydratePdpPrice().then(function (hydrated) {
            if (!hydrated) {
                window.location.reload();
            }
        });
    }

    // Guard: rodar apenas em PDP com preco bloqueado
    if (!document.body.classList.contains('catalog-product-view')) {
        return;
    }
    if (!document.querySelector('.b2b-login-to-see-price')) {
        return;
    }

    // Verificar customer-data imediatamente (pode ja estar em localStorage)
    let initial = customerData.get('customer')();
    if (isLoggedIn(initial)) {
        tryRefresh(initial);
        return;
    }

    // Aguardar carregamento async do customer-data (~500ms)
    let unsubscribe = customerData.get('customer').subscribe(function (customer) {
        tryRefresh(customer);
        // Limpar subscribe apos primeira execucao
        if (typeof unsubscribe === 'function') {
            unsubscribe();
        }
    });
});
