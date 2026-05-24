/**
 * Home PSI — adia require config + merged bundle até interação real do usuário.
 */
(function (w, d) {
    'use strict';

    var done = false;
    var events = ['pointerdown', 'keydown', 'touchstart'];

    function cleanup() {
        events.forEach(function (eventName) {
            w.removeEventListener(eventName, onInteract, true);
        });
    }

    function runInline() {
        var inline = d.getElementById('awa-home-bootstrap-inline');

        if (!inline || !inline.textContent) {
            return;
        }

        try {
            new Function(inline.textContent)();
        } catch (e) {
            return;
        }
    }

    function appendMerged() {
        var store = d.getElementById('awa-home-bootstrap-merged');

        if (!store) {
            return;
        }

        var src = store.getAttribute('data-src');

        if (!src || d.querySelector('script[data-awa-merged-bundle="1"]')) {
            return;
        }

        var script = d.createElement('script');

        script.src = src;
        script.type = 'text/javascript';
        script.defer = true;
        script.setAttribute('data-awa-merged-bundle', '1');
        (d.body || d.documentElement).appendChild(script);
    }

    function initHeroSliders() {
        if (typeof w.require !== 'function') {
            return;
        }

        var configs = d.querySelectorAll('script[type="application/json"][id^="awa-hero-slider-config-"]');

        if (!configs.length) {
            return;
        }

        w.require(['js/awa-hero-slider-home5'], function (initHero) {
            configs.forEach(function (node) {
                try {
                    var payload = JSON.parse(node.textContent || '');

                    if (payload && payload.sliderId !== undefined) {
                        initHero(payload);
                    }
                } catch (e) {
                    /* ignore malformed config */
                }
            });
        });
    }

    function boot() {
        if (done) {
            return;
        }

        done = true;
        cleanup();
        runInline();
        appendMerged();

        if ('requestIdleCallback' in w) {
            w.requestIdleCallback(initHeroSliders, { timeout: 4000 });
        } else {
            w.setTimeout(initHeroSliders, 2000);
        }
    }

    function onInteract() {
        boot();
    }

    events.forEach(function (eventName) {
        w.addEventListener(eventName, onInteract, { passive: true, capture: true });
    });
})(window, document);
