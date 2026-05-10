/**
 * A11Y-001 — Product Image Links Accessibility Fix
 *
 * Adds aria-label to product photo anchors that have no accessible name.
 * These links (from Rokanthemes widgets) carry only an image child without
 * any text content, resulting in the browser using the href as the SR label.
 *
 * Strategy: for each qualifying anchor, use the contained img[alt] text.
 * Runs once after DOM ready (deferred via requestIdleCallback).
 */
define([], function () {
    'use strict';

    let SELECTORS = [
        '.product-item-photo',
        '.product-thumb-link',
        '.product photo',
        'a.product-item-photo'
    ].join(',');

    function fixProductLinks() {
        let links = document.querySelectorAll(SELECTORS);
        links.forEach(function (link) {
            // Skip if already has accessible name
            if (link.getAttribute('aria-label') ||
                link.getAttribute('aria-labelledby') ||
                link.textContent.trim()) {
                return;
            }
            let img = link.querySelector('img[alt]');
            if (img && img.alt && img.alt.trim()) {
                link.setAttribute('aria-label', img.alt.trim());
            }
        });
    }

    if (typeof requestIdleCallback === 'function') {
        requestIdleCallback(fixProductLinks);
    } else {
        setTimeout(fixProductLinks, 200);
    }

    return {};
});
