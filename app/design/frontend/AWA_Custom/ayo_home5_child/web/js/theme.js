define([
    'jquery',
    'domReady!'
], function ($) {
    'use strict';

    var bodyEl = document.body;
    var pathName = (window.location && window.location.pathname) ? window.location.pathname : '';
    var isHomePath = /^\/(?:index\.php\/?)?$/.test(pathName);
    var bodyClassName = bodyEl ? bodyEl.className : '';
    var isHomePage = isHomePath || /\bcms-index-index\b|\bcms-home\b|\bcms-homepage_ayo_home5\b/.test(bodyClassName);

    // PERF HOME: não carregar o módulo pesado (mage/mage + keyboard + observers)
    // na homepage para reduzir render delay e long tasks no caminho crítico.
    if (isHomePage) {
        return;
    }

    require(['js/theme-heavy'], function () {
        // no-op: execução ocorre no define() do módulo pesado
    });
});
