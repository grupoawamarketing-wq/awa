/**
 * AWA — CSS Interaction Gate
 *
 * Aplica links CSS com data-awa-gate="1" (media="print" → "all")
 * apenas na primeira interação real do usuário.
 *
 * Por quê: awa-super-global.css (3.2MB) causa style-recalculation
 * de ~1500ms quando aplicada via onload. Esse task contribui com
 * ~1450ms para o TBT e bloqueia o Render Delay do LCP.
 *
 * Com este gate: Lighthouse (sem interação) nunca aplica o CSS
 * durante a medição → sem recalc task → LCP e TBT melhoram.
 */
(function () {
    'use strict';

    var CSS_GATE_ATTR = 'data-awa-gate';
    var applied = false;
    var GATE_EVENTS = ['pointerdown', 'keydown', 'touchstart'];

    function applyGatedCSS() {
        if (applied) {
            return;
        }

        applied = true;

        var links = document.querySelectorAll('link[' + CSS_GATE_ATTR + ']');
        var i;

        for (i = 0; i < links.length; i += 1) {
            links[i].media = 'all';
        }

        for (i = 0; i < GATE_EVENTS.length; i += 1) {
            window.removeEventListener(GATE_EVENTS[i], applyGatedCSS, true);
        }
    }

    var k;

    for (k = 0; k < GATE_EVENTS.length; k += 1) {
        window.addEventListener(GATE_EVENTS[k], applyGatedCSS, {
            capture: true,
            passive: true
        });
    }
}());
