/**
 * AWA Checkout UX — progresso OPC, trust strip, scroll de erro, CTA loading.
 */
define([
    'jquery',
    'ko',
    'mage/translate',
    'Magento_Checkout/js/model/quote',
    'domReady!'
], function ($, ko, $t, quote) {
    'use strict';

    var TRUST_SELECTOR = '.awa-opc-trust-strip';
    var TRUST_ATTR = 'data-awa-opc-trust';
    var STEP_INDICATOR_SELECTOR = '.awa-opc-step-indicator';
    var STEP_INDICATOR_ATTR = 'data-awa-opc-step-indicator';
    var LIVE_REGION_ATTR = 'data-awa-checkout-live';
    var PLACE_ORDER_META_SELECTOR = '.awa-place-order-toolbar__meta';
    var PLACE_ORDER_AMOUNT_SELECTOR = '.awa-place-order-toolbar__amount';
    var GRAND_TOTAL_SELECTOR = '.opc-block-summary .grand.totals .amount .price, ' +
        '.opc-block-summary .grand.totals .amount strong, ' +
        '.opc-block-summary .grand.totals .amount';
    var MOBILE_MAX = 767;
    var lastIndicatorState = '';
    var lastGrandTotalLabel = '';
    var syncScheduled = false;

    function isCheckoutPage() {
        if (!document.body) {
            return false;
        }

        return document.body.classList.contains('checkout-index-index') ||
            document.body.classList.contains('rokanthemes-onepagecheckout') ||
            document.body.classList.contains('onepagecheckout-index-index');
    }

    function isOpcPage() {
        return document.body.classList.contains('rokanthemes-onepagecheckout') ||
            document.body.classList.contains('onepagecheckout-index-index');
    }

    function isMobileViewport() {
        return window.matchMedia('(max-width: ' + MOBILE_MAX + 'px)').matches;
    }

    function findPlaceOrderToolbar() {
        return $('.awa-place-order-toolbar, #opc-sidebar .actions-toolbar, .opc-sidebar .actions-toolbar').first();
    }

    function readGrandTotalLabel() {
        var $amount = $(GRAND_TOTAL_SELECTOR).first();

        if (!$amount.length) {
            return '';
        }

        return $.trim($amount.text());
    }

    function syncMobilePlaceOrderTotal() {
        var $toolbar = findPlaceOrderToolbar();
        var $meta = $toolbar.find(PLACE_ORDER_META_SELECTOR);
        var $amount = $toolbar.find(PLACE_ORDER_AMOUNT_SELECTOR);
        var totalLabel;

        if (!$toolbar.length || !$meta.length || !$amount.length) {
            return;
        }

        if (!isMobileViewport()) {
            $meta.attr('aria-hidden', 'true');
            $amount.text('');

            return;
        }

        totalLabel = readGrandTotalLabel();

        if (!totalLabel) {
            $meta.attr('aria-hidden', 'true');
            $amount.text('');

            return;
        }

        $meta.attr('aria-hidden', 'false');

        if (lastGrandTotalLabel !== totalLabel) {
            lastGrandTotalLabel = totalLabel;
            $amount.text(totalLabel);
        }
    }

    function hasLegacyCssTrustPseudo() {
        var summary = document.querySelector('.opc-block-summary');

        if (!summary) {
            return false;
        }

        var afterContent = window.getComputedStyle(summary, '::after').content;

        return afterContent &&
            afterContent !== 'none' &&
            afterContent !== 'normal' &&
            afterContent.indexOf('protegidos') !== -1;
    }

    function hasExistingTrustMessage() {
        var $sidebar = $('#opc-sidebar, .opc-sidebar').first();

        if (!$sidebar.length) {
            return false;
        }

        if ($sidebar.find(TRUST_SELECTOR).length) {
            return true;
        }

        if (hasLegacyCssTrustPseudo()) {
            return true;
        }

        var sidebarText = ($sidebar.text() || '').toLowerCase();

        return sidebarText.indexOf('protegidos') !== -1 ||
            sidebarText.indexOf('dados estão') !== -1;
    }

    function ensureTrustStrip() {
        if (hasExistingTrustMessage()) {
            return;
        }

        var $toolbar = findPlaceOrderToolbar();

        if (!$toolbar.length || $toolbar.siblings(TRUST_SELECTOR).length || $toolbar.children(TRUST_SELECTOR).length) {
            return;
        }

        var $strip = $(
            '<p class="awa-opc-trust-strip" role="note" ' + TRUST_ATTR + '="1">' +
            '<svg class="awa-opc-trust-strip__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">' +
            '<rect x="5" y="11" width="14" height="10" rx="2"/>' +
            '<path d="M8 11V8a4 4 0 0 1 8 0v3"/>' +
            '</svg>' +
            '<span>' + $t('Seus dados estão protegidos') + '</span>' +
            '</p>'
        );

        $toolbar.after($strip);
    }

    /**
     * @param {Object|null} address
     * @returns {boolean}
     */
    function isAddressUsable(address) {
        var streetLine = '';

        if (!address) {
            return false;
        }

        if (address.street !== undefined && address.street !== null) {
            streetLine = Array.isArray(address.street) ?
                String(address.street[0] || '').trim() :
                String(address.street).trim();
        }

        return Boolean(
            String(address.firstname || '').trim() &&
            String(address.lastname || '').trim() &&
            String(address.city || '').trim() &&
            String(address.postcode || '').trim() &&
            String(address.telephone || '').trim() &&
            String(address.countryId || '').trim() &&
            streetLine
        );
    }

    /**
     * @returns {Array<{id: string, label: string, complete: boolean}>}
     */
    function getOpcProgressSteps() {
        return [
            {
                id: 'shipping',
                label: $t('Endereço'),
                complete: quote.isVirtual() || isAddressUsable(quote.shippingAddress())
            },
            {
                id: 'opc-shipping_method',
                label: $t('Frete'),
                complete: quote.isVirtual() || Boolean(
                    quote.shippingMethod() &&
                    quote.shippingMethod().carrier_code &&
                    quote.shippingMethod().method_code
                )
            },
            {
                id: 'payment',
                label: $t('Pagamento'),
                complete: Boolean(quote.paymentMethod() && quote.paymentMethod().method)
            }
        ];
    }

    function getCheckoutSteps() {
        return $('#checkoutSteps > li').filter(function () {
            return $(this).css('display') !== 'none' && !$(this).attr('hidden');
        });
    }

    function getActiveStepIndex($steps) {
        var activeIndex = 0;

        $steps.each(function (index) {
            if ($(this).hasClass('_active')) {
                activeIndex = index;
            }
        });

        return activeIndex;
    }

    function getStepTitle($step) {
        var $title = $step.find('.step-title').first();

        if ($title.length) {
            return $.trim($title.text());
        }

        return '';
    }

    function removeStepIndicator() {
        $(STEP_INDICATOR_SELECTOR + '[' + STEP_INDICATOR_ATTR + ']').remove();
    }

    function ensureLiveRegion() {
        var $region = $('[' + LIVE_REGION_ATTR + ']');

        if (!$region.length) {
            $region = $(
                '<div class="awa-checkout-live visually-hidden" ' +
                LIVE_REGION_ATTR + '="1" aria-live="polite" aria-atomic="true"></div>'
            );
            $('body').append($region);
        }

        return $region;
    }

    /**
     * @param {string} message
     * @returns {void}
     */
    function announce(message) {
        if (!message) {
            return;
        }

        var $region = ensureLiveRegion();

        $region.text('');
        window.requestAnimationFrame(function () {
            $region.text(message);
        });
    }

    function enforceOpcLayoutLock() {
        var steps = document.getElementById('checkoutSteps');
        var shipping = document.getElementById('shipping');
        var shipFloat = '';
        var stepsDisplay = '';
        var needsFix = false;
        var id;
        var el;

        if (!isOpcPage() || !steps) {
            return null;
        }

        shipFloat = shipping ? window.getComputedStyle(shipping).float : '';
        stepsDisplay = window.getComputedStyle(steps).display;
        needsFix = shipFloat === 'left' || shipFloat === 'right' || stepsDisplay !== 'grid';

        if (needsFix) {
            steps.style.setProperty('display', 'grid', 'important');
            steps.style.setProperty('grid-template-columns', 'minmax(0, 1fr)', 'important');
            steps.style.setProperty('grid-auto-flow', 'row', 'important');

            ['shipping', 'opc-shipping_method', 'payment'].forEach(function (stepId) {
                el = document.getElementById(stepId);

                if (!el) {
                    return;
                }

                el.style.setProperty('float', 'none', 'important');
                el.style.setProperty('clear', 'both', 'important');
                el.style.setProperty('width', '100%', 'important');
                el.style.setProperty('grid-column', '1 / -1', 'important');
            });

            document.body.setAttribute('data-awa-opc-layout-fix', 'applied');
        } else {
            document.body.setAttribute('data-awa-opc-layout-fix', 'ok');
        }

        return {
            needsFix: needsFix,
            shipFloat: shipFloat,
            stepsDisplay: stepsDisplay
        };
    }

    function syncStepCompletionClasses() {
        var steps;
        var i;
        var step;
        var $el;

        if (!isOpcPage()) {
            return;
        }

        steps = getOpcProgressSteps();

        for (i = 0; i < steps.length; i++) {
            step = steps[i];
            $el = $('#' + step.id);

            if ($el.length) {
                $el.toggleClass('awa-checkout-step--complete', Boolean(step.complete));
            }
        }
    }

    function ensureOpcStepIndicator() {
        var $wrapper = $('.opc-wrapper').first();
        var stepsMeta;
        var total;
        var currentIndex;
        var current;
        var $indicator;
        var stepTitle;
        var label;
        var dotsHtml;
        var pillsHtml;
        var i;
        var isMobile = isMobileViewport();

        if (!$wrapper.length) {
            return;
        }

        if (isOpcPage()) {
            stepsMeta = getOpcProgressSteps();
            total = stepsMeta.length;
            currentIndex = 0;

            for (i = 0; i < stepsMeta.length; i++) {
                if (!stepsMeta[i].complete) {
                    currentIndex = i;
                    break;
                }

                if (i === stepsMeta.length - 1) {
                    currentIndex = i;
                }
            }

            current = currentIndex + 1;
            stepTitle = stepsMeta[currentIndex].label;
        } else {
            var $steps = getCheckoutSteps();

            total = $steps.length;

            if (total < 2) {
                removeStepIndicator();
                return;
            }

            currentIndex = getActiveStepIndex($steps);
            current = currentIndex + 1;
            stepTitle = getStepTitle($steps.eq(currentIndex));
            stepsMeta = null;
        }

        $indicator = $wrapper.children(STEP_INDICATOR_SELECTOR);

        if (!$indicator.length) {
            $indicator = $(
                '<nav class="awa-opc-step-indicator" aria-label="' + $t('Progresso do checkout') + '" ' +
                STEP_INDICATOR_ATTR + '="1">' +
                '<span class="awa-opc-step-indicator__label"></span>' +
                '<span class="awa-opc-step-indicator__pills" aria-hidden="true"></span>' +
                '<span class="awa-opc-step-indicator__dots" aria-hidden="true"></span>' +
                '</nav>'
            );
            $wrapper.prepend($indicator);
        }

        $indicator.toggleClass('awa-opc-step-indicator--desktop', !isMobile && isOpcPage());

        var stateKey = String(isMobile) + '|' + String(current) + '/' + String(total) + '|' + stepTitle;

        if (lastIndicatorState === stateKey) {
            return;
        }

        if (!isMobile && isOpcPage() && stepsMeta) {
            pillsHtml = '';

            stepsMeta.forEach(function (step, index) {
                var pillClass = 'awa-opc-step-indicator__pill';

                if (index === currentIndex) {
                    pillClass += ' is-active';
                } else if (step.complete) {
                    pillClass += ' is-complete';
                }

                pillsHtml += '<button type="button" class="' + pillClass + '" data-step-target="' +
                    step.id + '"' +
                    (index === currentIndex ? ' aria-current="step"' : '') +
                    '>' + step.label + '</button>';
            });

            label = $t('Finalizar compra');
            $indicator.find('.awa-opc-step-indicator__label').text(label);
            $indicator.find('.awa-opc-step-indicator__pills').html(pillsHtml).attr('aria-hidden', 'false');
            $indicator.find('.awa-opc-step-indicator__dots').empty();
            $indicator.attr('aria-label', label + ' — ' + stepTitle);
        } else {
            if (!isMobile) {
                removeStepIndicator();
                return;
            }

            label = $t('Step %1 of %2').replace('%1', String(current)).replace('%2', String(total));

            if (stepTitle && stepTitle.length <= 36) {
                label = label + ': ' + stepTitle;
            }

            dotsHtml = '';

            if (isOpcPage() && stepsMeta) {
                stepsMeta.forEach(function (step, index) {
                    var dotClass = 'awa-opc-step-indicator__dot';

                    if (index === currentIndex) {
                        dotClass += ' is-active';
                    } else if (step.complete) {
                        dotClass += ' is-complete';
                    }

                    dotsHtml += '<span class="' + dotClass + '"></span>';
                });
            } else {
                getCheckoutSteps().each(function (index) {
                    var dotClass = 'awa-opc-step-indicator__dot';

                    if (index === currentIndex) {
                        dotClass += ' is-active';
                    } else if (index < currentIndex) {
                        dotClass += ' is-complete';
                    }

                    dotsHtml += '<span class="' + dotClass + '"></span>';
                });
            }

            $indicator.find('.awa-opc-step-indicator__pills').empty().attr('aria-hidden', 'true');
            $indicator.find('.awa-opc-step-indicator__label').text(label);
            $indicator.find('.awa-opc-step-indicator__dots').html(dotsHtml);
            $indicator.attr('aria-label', label);
        }

        lastIndicatorState = stateKey;
    }

    function scrollToStep($step) {
        if (!$step || !$step.length || !isMobileViewport()) {
            return;
        }

        var node = $step[0];

        if (node && typeof node.scrollIntoView === 'function') {
            node.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    function findFirstErrorTarget() {
        return $('.message-error:visible, .messages .message.error:visible, .field._error:visible, .mage-error:visible')
            .first();
    }

    function scrollToFirstError() {
        var $target = findFirstErrorTarget();

        if (!$target.length) {
            return;
        }

        var $scrollTarget = $target.closest('.field._error, li, .opc-wrapper, .checkout-container').first();

        if (!$scrollTarget.length) {
            $scrollTarget = $target;
        }

        $scrollTarget.addClass('awa-checkout-error-anchor');

        window.setTimeout(function () {
            $scrollTarget[0].scrollIntoView({ behavior: 'smooth', block: 'center' });

            var $focusable = $scrollTarget.find('input:visible, select:visible, textarea:visible, button:visible').first();

            if ($focusable.length) {
                $focusable.trigger('focus');
            }

            announce($t('Revise os campos destacados antes de continuar.'));
        }, 80);
    }

    function getPlaceOrderBlockReason() {
        if (quote.isVirtual()) {
            return quote.paymentMethod() && quote.paymentMethod().method ? '' :
                $t('Selecione uma forma de pagamento para finalizar.');
        }

        if (!isAddressUsable(quote.shippingAddress())) {
            return $t('Informe o endereço de entrega para continuar.');
        }

        if (!quote.shippingMethod() || !quote.shippingMethod().carrier_code) {
            return $t('Selecione uma forma de frete para continuar.');
        }

        if (!quote.paymentMethod() || !quote.paymentMethod().method) {
            return $t('Selecione uma forma de pagamento para finalizar.');
        }

        return '';
    }

    function syncPlaceOrderHint() {
        if (!isOpcPage()) {
            return;
        }

        var $toolbar = findPlaceOrderToolbar();
        var $btn = $toolbar.find('.btn-placeorder').first();
        var $hint = $('#awa-place-order-hint');
        var reason;
        var isDisabled;

        if (!$btn.length) {
            return;
        }

        if (!$hint.length) {
            $hint = $(
                '<p id="awa-place-order-hint" class="awa-place-order-hint visually-hidden" ' +
                'aria-live="polite"></p>'
            );
            $toolbar.append($hint);
        }

        isDisabled = $btn.hasClass('disabled') || $btn.attr('aria-disabled') === 'true';
        reason = isDisabled ? getPlaceOrderBlockReason() : '';

        if (!reason) {
            $hint.text('').addClass('visually-hidden').removeAttr('role');
            $btn.removeAttr('aria-describedby');
            return;
        }

        $hint.text(reason).removeClass('visually-hidden').attr('role', 'status');
        $btn.attr('aria-describedby', 'awa-place-order-hint');
    }

    function scrollToOpcStep(stepId) {
        var $step = $('#' + stepId).first();

        if (!$step.length) {
            return;
        }

        $step[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
        announce($t('Etapa: %1').replace('%1', $.trim($step.find('.step-title').first().text()) || stepId));
    }

    function bindStepPillNavigation() {
        $(document).on(
            'click.awaCheckoutUxPill',
            '.awa-opc-step-indicator__pill[data-step-target]',
            function (event) {
                event.preventDefault();
                scrollToOpcStep(String($(this).data('step-target') || ''));
            }
        );
    }

    function syncPlaceOrderBusyState() {
        var isLoading = $('.loading-mask').is(':visible') ||
            $('body').hasClass('_block-content-loading');

        $('.btn-placeorder').attr('aria-busy', isLoading ? 'true' : 'false')
            .toggleClass('is-processing', isLoading);

        syncPlaceOrderHint();
    }

    function bindStepNavigation() {
        $(document).on('click.awaCheckoutUx', '.opc-wrapper .action.continue, .opc-wrapper .button.action.continue', function () {
            var $step = $(this).closest('#checkoutSteps > li, .checkout-shipping-method, .checkout-payment-method').first();

            if (!$step.length) {
                $step = $(this).closest('.step-content').closest('li');
            }

            window.setTimeout(function () {
                var $active = $('#checkoutSteps > li._active, #checkoutSteps > li[data-role="opc-step"]:visible').last();

                scrollToStep($active.length ? $active : $step);
                ensureOpcStepIndicator();
            }, 320);
        });
    }

    function bindValidationErrorScroll() {
        var pending = false;

        function scheduleErrorScroll() {
            pending = true;
            window.setTimeout(function () {
                if (!pending) {
                    return;
                }

                pending = false;

                if (findFirstErrorTarget().length) {
                    scrollToFirstError();
                }
            }, 420);
        }

        $(document).on(
            'click.awaCheckoutUxError',
            '.btn-placeorder, .opc-wrapper .action.primary.continue, .discount-code .action-apply',
            scheduleErrorScroll
        );
    }

    function bindLoaderObserver() {
        if (!window.MutationObserver) {
            return;
        }

        var loaderTarget = document.querySelector('.loading-mask') || document.body;

        window.__awaCheckoutLoaderObserver = new window.MutationObserver(function () {
            window.requestAnimationFrame(syncPlaceOrderBusyState);
        });

        window.__awaCheckoutLoaderObserver.observe(loaderTarget, {
            attributes: true,
            attributeFilter: ['style', 'class'],
            subtree: true,
            childList: true
        });

        syncPlaceOrderBusyState();
    }

    function bindQuoteProgress() {
        if (!isOpcPage()) {
            return;
        }

        quote.shippingAddress.subscribe(function () {
            ensureOpcStepIndicator();
            syncPlaceOrderHint();
        });
        quote.shippingMethod.subscribe(function (method) {
            ensureOpcStepIndicator();
            syncPlaceOrderHint();

            if (method && method.carrier_title) {
                announce($t('Frete selecionado: %1').replace('%1', String(method.carrier_title)));
            }
        });
        quote.paymentMethod.subscribe(function (method) {
            ensureOpcStepIndicator();
            syncPlaceOrderHint();

            if (method && method.title) {
                announce($t('Pagamento selecionado: %1').replace('%1', String(method.title)));
            }
        });

        if (typeof quote.totals === 'function' && ko.isObservable(quote.totals)) {
            quote.totals.subscribe(syncMobilePlaceOrderTotal);
        }
    }

    function bindViewportSync() {
        var resizeTimer;

        $(window).on('resize.awaCheckoutUx', function () {
            window.clearTimeout(resizeTimer);
            resizeTimer = window.setTimeout(function () {
                ensureOpcStepIndicator();
                syncMobilePlaceOrderTotal();
            }, 120);
        });
    }

    function sync() {
        if (syncScheduled) {
            return;
        }

        syncScheduled = true;

        window.requestAnimationFrame(function () {
            syncScheduled = false;
            ensureTrustStrip();
            ensureOpcStepIndicator();
            syncStepCompletionClasses();
            enforceOpcLayoutLock();
            syncMobilePlaceOrderTotal();
            syncPlaceOrderBusyState();
            syncPlaceOrderHint();
        });
    }

    return function () {
        if (!isCheckoutPage()) {
            return;
        }

        sync();
        bindStepNavigation();
        bindStepPillNavigation();
        bindViewportSync();
        bindValidationErrorScroll();
        bindLoaderObserver();
        bindQuoteProgress();

        if (window.MutationObserver) {
            var target = document.querySelector('#checkoutSteps, .checkout-container, #checkout, .page-main') || document.body;

            if (window.__awaCheckoutUxObserver) {
                var prevTarget = window.__awaCheckoutUxObserverTarget;

                if (!prevTarget || !document.contains(prevTarget)) {
                    window.__awaCheckoutUxObserver.disconnect();
                    window.__awaCheckoutUxObserver = null;
                    window.__awaCheckoutUxObserverTarget = null;
                }
            }

            if (!window.__awaCheckoutUxObserver) {
                window.__awaCheckoutUxObserver = new window.MutationObserver(sync);
                window.__awaCheckoutUxObserver.observe(target, {
                    childList: true,
                    subtree: true,
                    attributes: true,
                    attributeFilter: ['class', 'hidden', 'style']
                });
                window.__awaCheckoutUxObserverTarget = target;
            }
        }
    };
});
