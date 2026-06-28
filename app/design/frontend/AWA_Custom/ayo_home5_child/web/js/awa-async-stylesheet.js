/**
 * Carrega stylesheets async sem inline event handlers (compatível com CSP).
 */
define([], function () {
    'use strict';

    /**
     * @param {{urls?: string[]}} config
     */
    return function (config) {
        var urls = config && config.urls ? config.urls : [];
        var head = document.head || document.getElementsByTagName('head')[0];

        urls.forEach(function (url) {
            if (!url || typeof url !== 'string') {
                return;
            }

            var link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = url;
            link.media = 'all';
            link.setAttribute('data-awa-async-css', '1');
            head.appendChild(link);
        });
    };
});
