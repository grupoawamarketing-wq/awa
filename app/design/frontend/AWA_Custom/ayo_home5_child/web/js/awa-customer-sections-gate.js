/**
 * Home: aguarda o JSON defer e garante um único bootstrap de customer-data.
 */
define(function () {
    'use strict';

    function bootstrapFromNode() {
        var node = document.getElementById('awa-customer-sections-defer-json');
        if (!node || !node.textContent) {
            return null;
        }

        var payload;
        try {
            payload = JSON.parse(node.textContent);
        } catch (e) {
            return null;
        }

        var body = document.body;
        var immediate = !!(body && body.classList.contains('checkout-cart-index'));

        return new Promise(function (resolve) {
            require(['js/awa-customer-sections-bootstrap'], function (bootstrapCustomerSections) {
                Promise.resolve(bootstrapCustomerSections(payload, { immediate: immediate })).then(resolve);
            });
        });
    }

    return function whenCustomerSectionsReady(callback) {
        if (typeof callback !== 'function') {
            return Promise.resolve();
        }

        if (window.__awaCustomerSectionsReady) {
            return window.__awaCustomerSectionsReady.then(callback);
        }

        return new Promise(function (resolveOuter) {
            var settled = false;

            function finish(promise) {
                if (settled) {
                    return;
                }
                settled = true;
                promise.then(callback).then(resolveOuter).catch(function () {
                    callback();
                    resolveOuter();
                });
            }

            function attempt() {
                if (window.__awaCustomerSectionsReady) {
                    finish(window.__awaCustomerSectionsReady);
                    return true;
                }

                var boot = bootstrapFromNode();
                if (boot) {
                    finish(boot);
                    return true;
                }

                return false;
            }

            if (attempt()) {
                return;
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function () {
                    if (!attempt()) {
                        callback();
                        resolveOuter();
                    }
                }, { once: true });
                return;
            }

            var attempts = 0;
            var poll = window.setInterval(function () {
                if (attempt() || ++attempts > 100) {
                    window.clearInterval(poll);
                    if (!settled) {
                        callback();
                        resolveOuter();
                    }
                }
            }, 50);
        });
    };
});
