define([
    'jquery'
], function ($) {
    'use strict';

    var SEARCH_FORM_SELECTOR = 'form.form.minisearch, #search_mini_form';
    var SEARCH_BOOT_INIT_KEY = '__awaSearchCompatBootInit';
    var SEARCH_OBSERVER_KEY = '__awaSearchCompatBootObserver';
    var SEARCH_SCHEDULED_KEY = '__awaSearchCompatBootScheduled';
    var B2B_BOOT_INIT_KEY = '__awaB2bCheckoutCompatBootInit';
    var HOME_CATEGORY_BOOT_INIT_KEY = '__awaHomeCategoryCompatBootInit';

    function toBool(value) {
        return value === true || value === 1 || value === '1' || value === 'true';
    }

    function onReady(callback) {
        if (document.readyState === 'loading') {
            $(callback);
            return;
        }

        callback();
    }

    function runOnce(key, callback) {
        if (window[key]) {
            return;
        }

        window[key] = true;
        callback();
    }

    function nodeContainsSearchForm(node) {
        var $node;

        if (!node || node.nodeType !== 1) {
            return false;
        }

        $node = $(node);

        return $node.is(SEARCH_FORM_SELECTOR) || $node.find(SEARCH_FORM_SELECTOR).length > 0;
    }

    function mutationsContainSearchForm(mutations) {
        var i;
        var j;
        var mutation;
        var addedNodes;
        var removedNodes;

        if (!mutations || !mutations.length) {
            return false;
        }

        for (i = 0; i < mutations.length; i += 1) {
            mutation = mutations[i];

            if (!mutation) {
                continue;
            }

            addedNodes = mutation.addedNodes || [];
            for (j = 0; j < addedNodes.length; j += 1) {
                if (nodeContainsSearchForm(addedNodes[j])) {
                    return true;
                }
            }

            removedNodes = mutation.removedNodes || [];
            for (j = 0; j < removedNodes.length; j += 1) {
                if (nodeContainsSearchForm(removedNodes[j])) {
                    return true;
                }
            }
        }

        return false;
    }

    function initSearchCompat() {
        runOnce(SEARCH_BOOT_INIT_KEY, function () {
            require(['js/awa-search-autocomplete-compat'], function (initAwaSearchCompat) {
                function boot() {
                    $(SEARCH_FORM_SELECTOR).each(function () {
                        initAwaSearchCompat({}, this);
                    });
                }

                function scheduleBoot() {
                    if (window[SEARCH_SCHEDULED_KEY]) {
                        return;
                    }

                    window[SEARCH_SCHEDULED_KEY] = true;
                    function flush() {
                        window[SEARCH_SCHEDULED_KEY] = false;
                        boot();
                    }

                    if (typeof window.requestAnimationFrame === 'function') {
                        window.requestAnimationFrame(flush);
                        return;
                    }

                    window.setTimeout(flush, 0);
                }

                onReady(function () {
                    scheduleBoot();

                    $(document).on('contentUpdated.awaSearchCompatBootstrap', function (event) {
                        if (!event || !event.target || nodeContainsSearchForm(event.target)) {
                            scheduleBoot();
                        }
                    });

                    if (window.MutationObserver && document.body && !window[SEARCH_OBSERVER_KEY]) {
                        window[SEARCH_OBSERVER_KEY] = new window.MutationObserver(function (mutations) {
                            if (!mutationsContainSearchForm(mutations)) {
                                return;
                            }

                            scheduleBoot();
                        });

                        window[SEARCH_OBSERVER_KEY].observe(document.body, {
                            childList: true,
                            subtree: true
                        });
                    }
                });
            });
        });
    }

    function initB2bCheckoutCompat() {
        runOnce(B2B_BOOT_INIT_KEY, function () {
            require(['js/awa-custom-b2b-cart-checkout-compat'], function (initAwaB2bCartCheckoutCompat) {
                onReady(function () {
                    initAwaB2bCartCheckoutCompat();
                });
            });
        });
    }

    function initHomeCategoryCompat() {
        runOnce(HOME_CATEGORY_BOOT_INIT_KEY, function () {
            require(['js/awa-custom-home-category-compat'], function (initAwaHomeCategoryCompat) {
                onReady(function () {
                    initAwaHomeCategoryCompat();
                });
            });
        });
    }

    return function (config) {
        var options = config || {};

        if (toBool(options.load_search_compat_js)) {
            initSearchCompat();
        }

        if (toBool(options.load_b2b_checkout_compat_js)) {
            initB2bCheckoutCompat();
        }

        if (toBool(options.load_home_category_compat_js)) {
            initHomeCategoryCompat();
        }
    };
});
