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

    var RELOAD_KEY = 'awa_b2b_pdp_reloaded';

    function isPriceFenced() {
        var el = document.querySelector('.b2b-login-to-see-price');
        return !!(el && (el.offsetWidth || el.offsetHeight));
    }

    function wasAlreadyReloaded() {
        return sessionStorage.getItem(RELOAD_KEY) === window.location.pathname;
    }

    function tryRefresh(customer) {
        if (!isPriceFenced()) {
            return; // Preco ja visivel — nada a fazer
        }
        if (!customer || !customer.firstname) {
            return; // Cliente nao logado — mostrar a mensagem de login e-normal
        }
        if (wasAlreadyReloaded()) {
            return; // Ja tentamos reload nesta aba — cliente pode nao ter permissao
        }

        // Marcar ANTES do reload para evitar loop se o reload retornar a pagina errada
        sessionStorage.setItem(RELOAD_KEY, window.location.pathname);
        window.location.reload();
    }

    // Guard: rodar apenas em PDP com preco bloqueado
    if (!document.body.classList.contains('catalog-product-view')) {
        return;
    }
    if (!document.querySelector('.b2b-login-to-see-price')) {
        return;
    }

    // Verificar customer-data imediatamente (pode ja estar em localStorage)
    var initial = customerData.get('customer')();
    if (initial && initial.firstname) {
        tryRefresh(initial);
        return;
    }

    // Aguardar carregamento async do customer-data (~500ms)
    var unsubscribe = customerData.get('customer').subscribe(function (customer) {
        tryRefresh(customer);
        // Limpar subscribe apos primeira execucao
        if (typeof unsubscribe === 'function') {
            unsubscribe();
        }
    });
});
