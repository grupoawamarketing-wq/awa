define([], function () {
    'use strict';

    function emit(root, type, detail) {
        if (!root || typeof root.dispatchEvent !== 'function') {
            return;
        }

        root.dispatchEvent(new CustomEvent(type, {
            bubbles: true,
            detail: detail || {}
        }));
    }

    function getPrecision(step) {
        var normalized = String(step || 1);
        var decimalIndex = normalized.indexOf('.');

        if (decimalIndex === -1) {
            return 0;
        }

        return normalized.slice(decimalIndex + 1).length;
    }

    function normalizeNumber(value, fallback) {
        var parsed = Number.parseFloat(value);

        return Number.isFinite(parsed) ? parsed : fallback;
    }

    return function (config, element) {
        var root = element;
        var options = config || {};
        var selectors = options.selectors || {};
        var flags = options.flags || {};
        var inputSelector = selectors.input || '[data-awa-role="qty-input"]';
        var triggerSelector = selectors.trigger || '[data-awa-role="qty-trigger"]';

        if (!root || root.nodeType !== 1 || root.getAttribute('data-awa-qty-bound') === 'true') {
            return;
        }

        root.setAttribute('data-awa-qty-bound', 'true');
        root.setAttribute('data-awa-component', 'awa-qty-control');

        root.addEventListener('click', function (event) {
            var trigger = event.target.closest(triggerSelector);
            var input;
            var min;
            var step;
            var precision;
            var current;
            var nextValue;
            var direction;

            if (!trigger || !root.contains(trigger)) {
                return;
            }

            input = root.querySelector(inputSelector);
            if (!input) {
                emit(root, 'awa:qty-control:error', {
                    reason: 'missing-input'
                });
                return;
            }

            direction = trigger.getAttribute('data-awa-direction') === 'decrement' ? -1 : 1;
            min = normalizeNumber(input.getAttribute('min'), 0);
            step = normalizeNumber(input.getAttribute('step'), 1);
            precision = getPrecision(step);
            current = normalizeNumber(input.value, min);
            nextValue = current + (direction * step);

            if (nextValue < min) {
                nextValue = min;
            }

            input.value = precision > 0 ? nextValue.toFixed(precision) : String(Math.round(nextValue));

            if (flags.dispatchEvents !== false) {
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }

            emit(root, 'awa:qty-control:change', {
                value: input.value
            });
        });

        emit(root, 'awa:qty-control:ready');
    };
});
