/**
 * AWA Motos — mixin para mage/apply/main
 *
 * Adia processamento de x-magento-init e data-mage-init até
 * a primeira interação do usuário (ou 8s de fallback).
 *
 * Mecanismo: ao detectar a primeira interação (touchstart, pointerdown,
 * keydown, scroll, click, mousemove), libera imediatamente todos os
 * applies enfileirados — o usuário nunca percebe o adiamento.
 *
 * @module js/mixin-awa-apply-main
 */
define([], function () {
    'use strict';

    let RELEASE_DELAY = 8000;
    let _queue  = [];
    let _released = false;

    let GATE_EVENTS = ['touchstart', 'pointerdown', 'keydown', 'scroll', 'click', 'mousemove'];

    function release() {
        if (_released) {
            return;
        }

        _released = true;

        GATE_EVENTS.forEach(function (evt) {
            document.removeEventListener(evt, release, true);
        });

        let pending = _queue;
        _queue = null;
        pending.forEach(function (fn) {
            fn();
        });
    }

    GATE_EVENTS.forEach(function (evt) {
        document.addEventListener(evt, release, { capture: true, passive: true, once: true });
    });

    window.setTimeout(release, RELEASE_DELAY);

    return function (targetModule) {
        let originalApply = targetModule.apply.bind(targetModule);

        targetModule.apply = function (context) {
            if (_released) {
                return originalApply(context);
            }

            _queue.push(function () {
                originalApply(context);
            });
        };

        return targetModule;
    };
});
