/**
 * Home: adia Magento_Ui/js/core/app do minicart até customer-data estar pronto.
 *
 * Dependência 'ko' adicionada para guard contra double-binding:
 * em páginas não-home, o text/x-magento-init já inicializa o minicart via
 * Magento_Ui/js/core/app; sem o guard, runAppInit chamaria appModule() novamente
 * resultando em "cannot apply bindings multiple times" (knockout.js:3620).
 */
define(['js/awa-customer-sections-gate', 'ko'], function (whenCustomerSectionsReady, ko) {
    'use strict';

    function markContentReadyWhenHydrated() {
        var shell = document.querySelector(
            '[data-awa-header-minicart-shell="true"], .awa-header-minicart[data-awa-header-cart="true"]'
        );

        if (!shell) {
            return;
        }

        function applyReady() {
            shell.setAttribute('data-awa-minicart-content-ready', '1');
            shell.classList.remove('awa-header-minicart--content-pending');
            shell.classList.add('awa-header-minicart--content-ready');
        }

        if (document.querySelector('#minicart-content-wrapper .block-title')) {
            applyReady();
            return;
        }

        var wrapper = document.getElementById('minicart-content-wrapper');
        if (wrapper && typeof MutationObserver === 'function') {
            var observer = new MutationObserver(function () {
                if (!document.querySelector('#minicart-content-wrapper .block-title')) {
                    return;
                }
                observer.disconnect();
                applyReady();
            });
            observer.observe(wrapper, { childList: true, subtree: true });
            window.setTimeout(function () {
                observer.disconnect();
            }, 10000);
            return;
        }

        var attempts = 0;
        var timer = window.setInterval(function () {
            attempts += 1;
            if (document.querySelector('#minicart-content-wrapper .block-title')) {
                window.clearInterval(timer);
                applyReady();
                return;
            }
            if (attempts >= 50) {
                window.clearInterval(timer);
            }
        }, 100);
    }

    var MINICART_TEMPLATE_IDS = [
        'Magento_Checkout/minicart/content',
        'Magento_Checkout/minicart/item/default',
        'Magento_Tax/checkout/minicart/subtotal/totals',
        'Magento_Msrp/checkout/minicart/subtotal/totals'
    ];

    function templateToTextPluginId(templateId) {
        if (!templateId || typeof templateId !== 'string') {
            return '';
        }

        if (templateId.indexOf('template/') !== -1) {
            return 'text!' + templateId.replace(/\.html$/, '') + '.html';
        }

        var slash = templateId.indexOf('/');

        if (slash === -1) {
            return '';
        }

        return 'text!' + templateId.slice(0, slash) + '/template/' + templateId.slice(slash + 1) + '.html';
    }

    function collectTemplateIds(node, bucket) {
        if (!node || typeof node !== 'object') {
            return;
        }

        if (typeof node.template === 'string') {
            bucket[node.template] = true;
        }

        if (node.config && typeof node.config.template === 'string') {
            bucket[node.config.template] = true;
        }

        if (node.children && typeof node.children === 'object') {
            Object.keys(node.children).forEach(function (key) {
                collectTemplateIds(node.children[key], bucket);
            });
        }
    }

    function preloadMinicartTemplates(jsLayout, done) {
        var bucket = Object.create(null);
        var deps;
        var i;

        MINICART_TEMPLATE_IDS.forEach(function (id) {
            bucket[id] = true;
        });

        if (jsLayout && jsLayout.components) {
            Object.keys(jsLayout.components).forEach(function (key) {
                collectTemplateIds(jsLayout.components[key], bucket);
            });
        }

        deps = Object.keys(bucket).map(templateToTextPluginId).filter(Boolean);

        if (!deps.length) {
            done();
            return;
        }

        require(deps, done, done);
    }

    function hydrateDeferredMinicartShell() {
        var trigger = document.querySelector('[data-block="minicart"] [data-awa-minicart-defer="trigger"]');
        var wrapper = document.getElementById('minicart-content-wrapper');

        if (trigger && !trigger.getAttribute('data-bind')) {
            trigger.setAttribute('data-bind', "scope: 'minicart_content'");
            trigger.removeAttribute('data-awa-minicart-defer');

            var counter = trigger.querySelector('.counter.qty');
            var totalNode = trigger.querySelector('.total-mini-cart-item');

            if (counter && totalNode && !totalNode.querySelector('[data-bind]')) {
                counter.setAttribute(
                    'data-bind',
                    "css: { empty: !!getCartParam('summary_count') == false }, blockLoader: isLoading"
                );
                totalNode.innerHTML =
                    '<!-- ko if: getCartParam(\'summary_count\') -->' +
                    '<!-- ko text: getCartParam(\'summary_count\') --><!-- /ko -->' +
                    '<!-- /ko -->' +
                    '<!-- ko if: !getCartParam(\'summary_count\') -->0<!-- /ko -->';
            }
        }

        if (wrapper && wrapper.getAttribute('data-awa-minicart-defer') === 'content') {
            wrapper.removeAttribute('data-awa-minicart-defer');
            wrapper.removeAttribute('aria-hidden');
            wrapper.setAttribute('data-bind', "scope: 'minicart_content'");
            wrapper.innerHTML = '<!-- ko template: getTemplate() --><!-- /ko -->';
        }
    }

    function isVisible(element) {
        return !!element && !!(element.offsetWidth || element.offsetHeight || element.getClientRects().length);
    }

    function syncDropdownState() {
        var shell = document.querySelector(
            '[data-awa-header-minicart-shell="true"], .awa-header-minicart[data-awa-header-cart="true"]'
        );
        var wrapper = document.querySelector('[data-block="minicart"], .minicart-wrapper');
        var trigger = wrapper ? wrapper.querySelector('.showcart, .action.showcart') : null;
        var panel = wrapper ? wrapper.querySelector('[data-role="dropdownDialog"], .block-minicart') : null;
        var panelStyle = panel && window.getComputedStyle ? window.getComputedStyle(panel) : null;
        var panelVisible = !!(
            panel &&
            isVisible(panel) &&
            (!panelStyle || (
                panelStyle.display !== 'none' &&
                panelStyle.visibility !== 'hidden' &&
                panelStyle.opacity !== '0'
            ))
        );
        var expanded = !!(
            panelVisible ||
            (wrapper && (
                wrapper.classList.contains('active') ||
                wrapper.classList.contains('is-open') ||
                wrapper.classList.contains('show')
            )) ||
            (panel && (
                panel.classList.contains('_active') ||
                panel.classList.contains('active') ||
                panel.classList.contains('is-open')
            ))
        );
        var ready = !!(trigger && isVisible(trigger));

        if (wrapper) {
            wrapper.setAttribute('data-awa-minicart-dropdown', '1');
        }

        if (trigger) {
            trigger.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        }

        if (shell) {
            shell.setAttribute('data-awa-minicart-ready', ready ? '1' : '0');
            shell.setAttribute('data-awa-minicart-expanded', expanded ? '1' : '0');
            shell.classList.toggle('awa-header-minicart--ready', ready);
            shell.classList.toggle('awa-header-minicart--expanded', expanded);
        }
    }

    function scheduleDropdownStateSync() {
        [0, 80, 240, 600].forEach(function (delay) {
            window.setTimeout(syncDropdownState, delay);
        });
    }

    function bindDropdownStateSync() {
        if (window.__awaMinicartUiBootstrapStateSyncBound) {
            return;
        }

        window.__awaMinicartUiBootstrapStateSyncBound = true;
        document.addEventListener('click', scheduleDropdownStateSync, true);
        document.addEventListener('keyup', scheduleDropdownStateSync, true);
        document.addEventListener('contentUpdated', scheduleDropdownStateSync, true);
    }

    function openDropdownWithRetry(maxAttempts) {
        var attempts = 0;

        function attemptOpen() {
            attempts += 1;

            require(['jquery', 'dropdownDialog'], function ($) {
                var $block = $('[data-block="minicart"] [data-role="dropdownDialog"]');

                if ($block.length && !$block.data('mageDropdownDialog')) {
                    initDropdownDialog();
                }

                if ($block.length && $block.data('mageDropdownDialog')) {
                    $block.dropdownDialog('open');
                    scheduleDropdownStateSync();
                    window.__awaMinicartOpenAfterInit = false;
                    return;
                }

                if (attempts < maxAttempts) {
                    window.setTimeout(attemptOpen, 75);
                }
            }, function () {
                if (attempts < maxAttempts) {
                    window.setTimeout(attemptOpen, 75);
                }
            });
        }

        attemptOpen();
    }

    function initDropdownDialog() {
        require(['jquery', 'dropdownDialog'], function ($) {
            var $block = $('[data-block="minicart"] [data-role="dropdownDialog"]');
            var raw;
            var parsed;
            var config;

            if (!$block.length || $block.data('mageDropdownDialog')) {
                return;
            }

            raw = $block.attr('data-mage-init');

            if (raw) {
                try {
                    parsed = JSON.parse(raw);
                    config = parsed.dropdownDialog || null;
                } catch (e) {
                    /* ignore malformed mage-init */
                }
            }

            if (!config && document.getElementById('awa-minicart-ui-json')) {
                config = {
                    appendTo: '[data-block=minicart]',
                    triggerTarget: '.showcart',
                    timeout: 0,
                    closeOnMouseLeave: false,
                    closeOnEscape: true,
                    triggerClass: 'is-open',
                    parentClass: 'is-open',
                    buttons: []
                };
            }

            if (config) {
                $block.dropdownDialog(config);
                $block.closest('[data-block="minicart"], .minicart-wrapper').attr('data-awa-minicart-dropdown', '1');
                bindDropdownStateSync();
                scheduleDropdownStateSync();
            }
        });
    }

    function scheduleDropdownDialogInit() {
        [0, 250, 1000, 2500].forEach(function (delay) {
            window.setTimeout(initDropdownDialog, delay);
        });
    }

    function runAppInit(appModule, payload) {
        var minicartEl = document.querySelector('[data-block="minicart"]');

        if (!minicartEl || !payload || !payload.jsLayout) {
            return false;
        }

        // Guard: verifica se o minicart-content-wrapper já foi inicializado pelo KO
        // (ocorre quando text/x-magento-init processou Magento_Ui/js/core/app antes deste bootstrap).
        // Se já está bound, pular appModule() e executar apenas tarefas secundárias.
        var contentWrapper = document.getElementById('minicart-content-wrapper');

        if (contentWrapper && ko.dataFor(contentWrapper)) {
            if (payload.loaderUrl) {
                require(['Magento_Ui/js/block-loader'], function (blockLoader) {
                    blockLoader(payload.loaderUrl);
                });
            }

            scheduleDropdownDialogInit();
            markContentReadyWhenHydrated();
            window.__awaMinicartDeferSuperseded = true;

            return true;
        }

        hydrateDeferredMinicartShell();

        try {
            appModule(payload.jsLayout);
        } catch (err) {
            if (window.console && console.error) {
                console.error('AWA minicart UI init failed', err);
            }
            return false;
        }

        if (payload.loaderUrl) {
            require(['Magento_Ui/js/block-loader'], function (blockLoader) {
                blockLoader(payload.loaderUrl);
            });
        }

        if (document.getElementById('awa-customer-sections-defer-json')) {
            require(['js/awa-minicart-phase3'], function (initMinicartPhase3) {
                initMinicartPhase3({}, minicartEl);
            });
        }

        scheduleDropdownDialogInit();
        markContentReadyWhenHydrated();
        window.__awaMinicartDeferSuperseded = true;

        return true;
    }

    function bootstrapMinicartUi(payload, options) {
        options = options || {};

        if (!payload || !payload.jsLayout) {
            return Promise.resolve();
        }

        var launchDelayMs = typeof options.launchDelayMs === 'number' && options.launchDelayMs >= 0
            ? options.launchDelayMs
            : 600;

        if (options.openAfterInit) {
            window.__awaMinicartOpenAfterInit = true;
        }

        if (window.__awaMinicartUiInit) {
            if (options.openAfterInit) {
                openDropdownWithRetry(16);
            }
            return window.__awaMinicartUiReady || Promise.resolve();
        }

        if (window.__awaMinicartUiBootstrapping) {
            return window.__awaMinicartUiReady || Promise.resolve();
        }

        window.__awaMinicartUiBootstrapping = true;

        window.__awaMinicartUiReady = new Promise(function (resolve) {
            function launch() {
                window.setTimeout(function () {
                    preloadMinicartTemplates(payload.jsLayout, function () {
                        require(['Magento_Ui/js/core/app'], function (appModule) {
                            var ok = runAppInit(appModule, payload);
                            window.__awaMinicartUiInit = ok;
                            window.__awaMinicartUiBootstrapping = false;
                            if (ok && window.__awaMinicartOpenAfterInit) {
                                openDropdownWithRetry(32);
                            }
                            resolve();
                        }, function () {
                            window.__awaMinicartUiBootstrapping = false;
                            resolve();
                        });
                    });
                }, launchDelayMs);
            }

            if (options.skipCustomerGate) {
                launch();
                return;
            }

            whenCustomerSectionsReady(launch);
        });

        return window.__awaMinicartUiReady;
    }

    return bootstrapMinicartUi;
});
