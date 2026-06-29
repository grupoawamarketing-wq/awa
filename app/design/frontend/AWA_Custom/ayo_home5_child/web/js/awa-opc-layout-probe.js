/**
 * OPC layout enforcer — carrega síncrono antes do RequireJS.
 */
(function (w, d) {
    'use strict';

    var MAX_PASSES = 16;
    var PASS_MS = 350;
    var DESKTOP_MQ = '(min-width: 1024px)';

    function isCheckoutBody() {
        if (!d.body) {
            return false;
        }

        return d.body.classList.contains('rokanthemes-onepagecheckout') ||
            d.body.classList.contains('onepagecheckout-index-index') ||
            d.body.classList.contains('checkout-index-index');
    }

    function setImportant(el, prop, value) {
        el.style.setProperty(prop, value, 'important');
    }

    function neutralizeFloat(el) {
        var floatVal = w.getComputedStyle(el).float;

        if (floatVal === 'left' || floatVal === 'right') {
            setImportant(el, 'float', 'none');
            setImportant(el, 'clear', 'both');
            setImportant(el, 'width', '100%');
            setImportant(el, 'max-width', '100%');
            return true;
        }

        return false;
    }

    function applyShellLock(container) {
        var fixed = false;
        var wrapper;
        var sidebar;
        var child;
        var i;
        var containerDisplay;

        if (!container) {
            return false;
        }

        containerDisplay = w.getComputedStyle(container).display;

        if (w.matchMedia(DESKTOP_MQ).matches) {
            if (containerDisplay !== 'grid') {
                setImportant(container, 'display', 'grid');
                setImportant(container, 'grid-template-columns', 'minmax(0, 1fr) minmax(300px, 340px)');
                setImportant(container, 'gap', '24px');
                setImportant(container, 'align-items', 'start');
                fixed = true;
            }

            wrapper = container.querySelector('.opc-wrapper');

            if (wrapper) {
                setImportant(wrapper, 'grid-column', '1');
                setImportant(wrapper, 'order', '0');
                setImportant(wrapper, 'min-width', '0');
                setImportant(wrapper, 'position', 'static');
            }

            sidebar = container.querySelector('#opc-sidebar, .opc-sidebar');

            if (sidebar) {
                setImportant(sidebar, 'grid-column', '2');
                setImportant(sidebar, 'order', '0');
                setImportant(sidebar, 'min-width', '0');
                setImportant(sidebar, 'position', 'static');
                setImportant(sidebar, 'top', 'auto');
            }
        } else {
            if (containerDisplay !== 'flex') {
                setImportant(container, 'display', 'flex');
                setImportant(container, 'flex-direction', 'column');
                setImportant(container, 'gap', '16px');
                setImportant(container, 'align-items', 'stretch');
                fixed = true;
            }

            wrapper = container.querySelector('.opc-wrapper');

            if (wrapper) {
                setImportant(wrapper, 'order', '0');
                setImportant(wrapper, 'position', 'static');
            }

            sidebar = container.querySelector('#opc-sidebar, .opc-sidebar');

            if (sidebar) {
                setImportant(sidebar, 'order', '1');
                setImportant(sidebar, 'position', 'static');
                setImportant(sidebar, 'top', 'auto');
            }

            for (i = 0; i < container.children.length; i++) {
                child = container.children[i];

                if (child.classList.contains('opc-wrapper') ||
                    child.id === 'opc-sidebar' ||
                    child.classList.contains('opc-sidebar')) {
                    continue;
                }

                setImportant(child, 'order', '-1');
                setImportant(child, 'width', '100%');
            }
        }

        ['.opc-wrapper', '#opc-sidebar', '.opc-sidebar'].forEach(function (selector) {
            d.querySelectorAll(selector).forEach(function (node) {
                if (neutralizeFloat(node)) {
                    fixed = true;
                }

                setImportant(node, 'min-width', '0');
                setImportant(node, 'max-width', '100%');
            });
        });

        return fixed;
    }

    function applyStepsLock(steps) {
        var fixed = false;
        var id;
        var el;
        var stepsDisplay;
        var shipFloat = '';

        if (!steps) {
            return false;
        }

        stepsDisplay = w.getComputedStyle(steps).display;
        shipFloat = d.getElementById('shipping') ?
            w.getComputedStyle(d.getElementById('shipping')).float : '';

        if (stepsDisplay !== 'grid' || shipFloat === 'left' || shipFloat === 'right') {
            setImportant(steps, 'display', 'grid');
            setImportant(steps, 'grid-template-columns', 'minmax(0, 1fr)');
            setImportant(steps, 'grid-auto-flow', 'row');
            fixed = true;
        }

        ['shipping', 'opc-shipping_method', 'payment'].forEach(function (stepId) {
            el = d.getElementById(stepId);

            if (!el) {
                return;
            }

            if (neutralizeFloat(el)) {
                fixed = true;
            }

            setImportant(el, 'grid-column', '1 / -1');
        });

        return fixed;
    }

    function applyLayoutLock() {
        var steps = d.getElementById('checkoutSteps');
        var container = d.querySelector('.page-wrapper .checkout-container');
        var fixed = false;
        var shipFloat = '';
        var stepsDisplay = '';
        var containerDisplay = '';

        if (!isCheckoutBody()) {
            return null;
        }

        if (container) {
            fixed = applyShellLock(container) || fixed;
            containerDisplay = w.getComputedStyle(container).display;
        }

        if (steps) {
            fixed = applyStepsLock(steps) || fixed;
            stepsDisplay = w.getComputedStyle(steps).display;
            shipFloat = d.getElementById('shipping') ?
                w.getComputedStyle(d.getElementById('shipping')).float : '';
        }

        d.body.setAttribute('data-awa-opc-layout-fix', fixed ? 'applied' : 'ok');

        return {
            fixed: fixed,
            shipFloat: shipFloat,
            stepsDisplay: stepsDisplay,
            containerDisplay: containerDisplay,
            hasSteps: !!steps,
            viewportW: w.innerWidth
        };
    }

    function run(pass) {
        var state = applyLayoutLock();

        if (pass < MAX_PASSES && state && !state.hasSteps) {
            w.setTimeout(function () {
                run(pass + 1);
            }, PASS_MS);
        }
    }

    if (d.readyState === 'loading') {
        d.addEventListener('DOMContentLoaded', function () {
            run(0);
        });
    } else {
        run(0);
    }

    w.addEventListener('resize', function () {
        w.requestAnimationFrame(applyLayoutLock);
    });

    if (w.MutationObserver && d.body) {
        var observer = new w.MutationObserver(function () {
            applyLayoutLock();
        });

        observer.observe(d.body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['class', 'style']
        });
    }
}(window, document));
