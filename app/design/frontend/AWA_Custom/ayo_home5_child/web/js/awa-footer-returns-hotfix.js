define([], function () {
    'use strict';

    var LEGACY_PATTERN = /\/trocas-devolucoes\/?$/i;
    var CANONICAL_PATH = '/returns';

    function normalizeReturnsLinks(root) {
        var scope = root || document;
        var links = scope.querySelectorAll('a[href]');

        links.forEach(function (link) {
            var href = link.getAttribute('href') || '';

            if (!LEGACY_PATTERN.test(href)) {
                return;
            }

            // Keep protocol/host from current document and normalize to canonical route.
            link.setAttribute('href', CANONICAL_PATH);
            link.setAttribute('data-awa-returns-normalized', '1');
        });
    }

    function boot() {
        normalizeReturnsLinks(document);

        if (typeof MutationObserver === 'undefined') {
            return;
        }

        new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                mutation.addedNodes.forEach(function (node) {
                    if (node && node.nodeType === 1) {
                        normalizeReturnsLinks(node);
                    }
                });
            });
        }).observe(document.body, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
        return;
    }

    boot();
});
