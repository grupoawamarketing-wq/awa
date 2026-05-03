define([], function () {
    'use strict';

    function text(value) {
        return String(value || '').replace(/\s+/g, ' ').trim();
    }

    function hasAccessibleName(link) {
        return Boolean(
            text(link.textContent) ||
            text(link.getAttribute('aria-label')) ||
            text(link.getAttribute('aria-labelledby')) ||
            text(link.getAttribute('title'))
        );
    }

    function normalizeLink(link) {
        if (hasAccessibleName(link)) {
            return;
        }

        var img = link.querySelector('img[alt]');
        if (!img) {
            return;
        }

        var alt = text(img.getAttribute('alt'));
        if (!alt) {
            return;
        }

        link.setAttribute('aria-label', alt);

        if (!text(link.getAttribute('title'))) {
            link.setAttribute('title', alt);
        }
    }

    function normalizeScope(root) {
        var scope = root || document;
        var links = scope.querySelectorAll('a:not([aria-hidden="true"])');

        links.forEach(normalizeLink);
    }

    function boot() {
        normalizeScope(document);

        if (typeof MutationObserver === 'undefined') {
            return;
        }

        new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                mutation.addedNodes.forEach(function (node) {
                    if (node && node.nodeType === 1) {
                        normalizeScope(node);
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
