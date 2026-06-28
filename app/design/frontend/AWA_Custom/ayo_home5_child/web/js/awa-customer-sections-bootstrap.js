/**
 * Home: inicializa section-config + customer-data após interação no header ou 2,5s.
 * window.__awaCustomerSectionsReady — Promise para o header aguardar antes do runtime.
 */
define(function () {
    'use strict';

    function execute(payload) {
        return new Promise(function (resolve) {
            require([
                'Magento_Customer/js/section-config',
                'Magento_Customer/js/customer-data'
            ], function (sectionConfigModule, customerDataModule) {
                sectionConfigModule['Magento_Customer/js/section-config'](payload.section);
                customerDataModule['Magento_Customer/js/customer-data'](payload.customer);
                customerDataModule.getInitCustomerData().done(resolve);
            });
        });
    }

    return function bootstrapCustomerSections(payload, options) {
        options = options || {};

        if (!payload || !payload.section || !payload.customer) {
            return Promise.resolve();
        }

        if (window.__awaCustomerSectionsReady) {
            return window.__awaCustomerSectionsReady;
        }

        if (window.__awaCustomerSectionsBootstrapStarted) {
            return window.__awaCustomerSectionsReady || Promise.resolve();
        }

        window.__awaCustomerSectionsBootstrapStarted = true;

        if (options.immediate) {
            window.__awaCustomerSectionsReady = execute(payload);
            return window.__awaCustomerSectionsReady;
        }

        var headerSelectors = [
            '.minicart-wrapper',
            '.awa-header-account-prompt',
            '#search',
            '#search_mini_form',
            '[data-awa-header-right]'
        ];

        if (document.body.classList.contains('catalog-category-view')
            || document.body.classList.contains('catalogsearch-result-index')) {
            headerSelectors.push(
                'form[data-role=tocart-form]',
                'button.tocart',
                '.action.tocart',
                '[data-role=quickview-button]'
            );
        }

        if (document.body.classList.contains('catalog-product-view')) {
            headerSelectors.push(
                '#product_addtocart_form',
                '.box-tocart',
                '#product-addtocart-button',
                'button.action.tocart',
                '.swatch-option',
                'select.super-attribute-select',
                '[id^="super_attribute"]',
                '#awa-pdp-sticky-add',
                '.awa-pdp-sticky-bar',
                '.fotorama__stage'
            );
        }

        window.__awaCustomerSectionsReady = new Promise(function (resolve) {
            var settled = false;

            var intentEvents = ['pointerdown', 'touchstart', 'keydown'];

            function isMeaningfulIntent(evt) {
                if (!evt) {
                    return false;
                }

                if (evt.type === 'keydown') {
                    return evt.key === 'Enter' || evt.key === ' ' || evt.key === 'Spacebar';
                }

                return true;
            }

            function go() {
                if (settled) {
                    return;
                }
                settled = true;
                intentEvents.forEach(function (evtName) {
                    window.removeEventListener(evtName, onIntent);
                });
                execute(payload).then(resolve);
            }

            function onIntent(evt) {
                if (!isMeaningfulIntent(evt)) {
                    return;
                }

                var target = evt && evt.target;
                if (!target || !target.closest) {
                    return;
                }
                var hit = headerSelectors.some(function (sel) {
                    return target.closest(sel);
                });
                if (hit) {
                    go();
                }
            }

            intentEvents.forEach(function (evtName) {
                window.addEventListener(evtName, onIntent, { passive: true });
            });

            var sectionsDelay = document.cookie.indexOf('private_content_version') !== -1 ? 4500 : 7000;
            window.setTimeout(go, sectionsDelay);
        });

        return window.__awaCustomerSectionsReady;
    };
});
