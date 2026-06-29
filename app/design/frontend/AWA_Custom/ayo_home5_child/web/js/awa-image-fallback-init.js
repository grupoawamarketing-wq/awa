/**
 * AWA — fallback para imagens de produto quebradas (404 / CDN).
 */
define([], function () {
    'use strict';

    var PLACEHOLDER = '';

    try {
        if (typeof require !== 'undefined' && typeof require.toUrl === 'function') {
            PLACEHOLDER = require.toUrl('Magento_Catalog/images/product/placeholder/image.jpg');
        }
    } catch (e) {
        PLACEHOLDER = '';
    }

    function disableSecondThumbSwap(img) {
        var second = img.closest('.second-thumb');
        var thumb = img.closest('.product-thumb');

        if (second) {
            second.style.display = 'none';
            second.setAttribute('aria-hidden', 'true');
        }
        if (thumb) {
            thumb.setAttribute('data-no-swap', 'true');
        }
    }

    function applyFallback(img) {
        if (!PLACEHOLDER || !img || img.dataset.awaFallbackApplied === '1') {
            return;
        }

        if (img.src && img.src.indexOf('placeholder') !== -1) {
            return;
        }

        img.dataset.awaFallbackApplied = '1';

        if (img.closest('.second-thumb')) {
            disableSecondThumbSwap(img);
            return;
        }

        if (img.src === PLACEHOLDER) {
            return;
        }

        img.addEventListener('error', function stopFallbackLoop() {
            img.removeEventListener('error', stopFallbackLoop);
        }, { once: true });
        img.src = PLACEHOLDER;
        img.classList.add('awa-no-image');
    }

    function bindImages(root) {
        if (!PLACEHOLDER) {
            return;
        }

        var scope = root || document;
        var imgs = scope.querySelectorAll(
            'img.product-image-photo, .product-item-photo img, .product-thumb img, .fotorama__img'
        );

        for (var i = 0; i < imgs.length; i++) {
            var img = imgs[i];

            if (img.dataset.awaFallbackBound === '1') {
                continue;
            }

            img.dataset.awaFallbackBound = '1';
            img.addEventListener('error', function () {
                applyFallback(this);
            });
        }
    }

    var IMG_SEL = 'img.product-image-photo, .product-item-photo img, .product-thumb img, .fotorama__img';
    var CATALOG_ROOT_SEL = '.page-main, #maincontent, .column.main, .products-grid, .product-items';

    function mutationAddsProductImage(mutations) {
        var i;
        var j;
        var node;

        for (i = 0; i < mutations.length; i++) {
            var added = mutations[i].addedNodes;
            if (!added || !added.length) {
                continue;
            }
            for (j = 0; j < added.length; j++) {
                node = added[j];
                if (node.nodeType !== 1) {
                    continue;
                }
                if (node.matches && node.matches(IMG_SEL)) {
                    return node;
                }
                if (node.querySelector && node.querySelector(IMG_SEL)) {
                    return node;
                }
            }
        }

        return null;
    }

    function resolveCatalogRoot() {
        return document.querySelector(CATALOG_ROOT_SEL);
    }

    return function () {
        bindImages(document);

        if (!('MutationObserver' in window)) {
            return;
        }

        var root = resolveCatalogRoot();
        if (!root) {
            return;
        }

        var bindQueued = false;
        var observer = new MutationObserver(function (mutations) {
            var scope = mutationAddsProductImage(mutations);
            if (!scope) {
                return;
            }
            if (bindQueued) {
                return;
            }
            bindQueued = true;
            window.requestAnimationFrame(function () {
                bindQueued = false;
                bindImages(scope);
            });
        });

        observer.observe(root, { childList: true, subtree: true });
        window.addEventListener('pagehide', function () {
            observer.disconnect();
        }, { once: true });
    };
});
