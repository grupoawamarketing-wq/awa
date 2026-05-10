/**
 * Bootstrap diferido do widget Tawk no tema filho.
 */
define([], function () {
    'use strict';

    return function (config) {
        var settings = config || {};
        var propertyId = typeof settings.propertyId === 'string' ? settings.propertyId : '';
        var widgetId = typeof settings.widgetId === 'string' ? settings.widgetId : '';
        var visitorData = settings.visitorData && typeof settings.visitorData === 'object'
            ? settings.visitorData
            : null;
        var attributes = settings.attributes && typeof settings.attributes === 'object' && !Array.isArray(settings.attributes)
            ? settings.attributes
            : {};
        var tags = Array.isArray(settings.tags) ? settings.tags : [];
        var fallbackDelay = Number(settings.fallbackDelay) > 0 ? Number(settings.fallbackDelay) : 20000;
        var interactionEvents = ['pointerdown', 'keydown', 'touchstart'];
        var hasLoaded = false;
        var timeoutHandle = null;
        var tawkApi;
        var tawkScriptUrl;

        if (!propertyId || !widgetId || window.__awaTawkBootstrapInstalled) {
            return;
        }

        window.__awaTawkBootstrapInstalled = true;
        window.__awaTawkWidgetLoaded = false;
        window.Tawk_API = window.Tawk_API || {};
        window.Tawk_LoadStart = new Date();

        tawkApi = window.Tawk_API;
        tawkScriptUrl = 'https://embed.tawk.to/' + propertyId + '/' + widgetId;

        if (visitorData && visitorData.name && visitorData.email) {
            tawkApi.visitor = {
                name: visitorData.name,
                email: visitorData.email
            };

            if (visitorData.hash) {
                tawkApi.visitor.hash = visitorData.hash;
            }
        }

        tawkApi.onLoad = function () {
            if (typeof window.Tawk_API.setLanguage === 'function') {
                window.Tawk_API.setLanguage('pt-br');
            }

            if (Object.keys(attributes).length > 0 && typeof window.Tawk_API.setAttributes === 'function') {
                window.Tawk_API.setAttributes(attributes, function (error) {
                    if (error && typeof console !== 'undefined' && console && typeof console.warn === 'function') {
                        console.warn('[TawkIntegration] setAttributes error:', error);
                    }
                });
            }

            if (tags.length > 0 && typeof window.Tawk_API.addTags === 'function') {
                window.Tawk_API.addTags(tags, function (error) {
                    if (error && typeof console !== 'undefined' && console && typeof console.warn === 'function') {
                        console.warn('[TawkIntegration] addTags error:', error);
                    }
                });
            }
        };

        tawkApi.onChatStarted = function () {
            if (typeof window.Tawk_API.addEvent !== 'function') {
                return;
            }

            window.Tawk_API.addEvent('chat-iniciado', {
                pagina: window.location.pathname
            }, function (error) {
                if (error && typeof console !== 'undefined' && console && typeof console.warn === 'function') {
                    console.warn('[TawkIntegration] addEvent error:', error);
                }
            });
        };

        if (!window.__awaTawkConsoleGuardInstalled && typeof console !== 'undefined' && console && typeof console.error === 'function') {
            window.__awaTawkConsoleGuardInstalled = true;

            (function installConsoleGuard(originalConsoleError) {
                console.error = function () {
                    try {
                        if (arguments.length === 1 && arguments[0] === true) {
                            var stack = new Error().stack || '';

                            if (stack.indexOf('tawk') !== -1 || stack.indexOf('Tawk') !== -1) {
                                return;
                            }
                        }
                    } catch (guardError) {
                        originalConsoleError('[TawkIntegration] console guard error:', guardError);
                    }

                    return originalConsoleError.apply(console, arguments);
                };
            }(console.error.bind(console)));
        }

        function detachListeners() {
            interactionEvents.forEach(function (eventName) {
                window.removeEventListener(eventName, loadWidget, true);
            });

            if (timeoutHandle !== null) {
                window.clearTimeout(timeoutHandle);
                timeoutHandle = null;
            }
        }

        function appendScript() {
            var existing = Array.prototype.slice.call(document.scripts).find(function (script) {
                return script.src === tawkScriptUrl;
            });
            var script;
            var anchor;
            var parent;

            if (existing) {
                return;
            }

            script = document.createElement('script');
            anchor = document.getElementsByTagName('script')[0];
            parent = anchor && anchor.parentNode
                ? anchor.parentNode
                : (document.head || document.body || document.documentElement);

            script.async = true;
            script.src = tawkScriptUrl;
            script.charset = 'UTF-8';

            if (!parent) {
                return;
            }

            if (anchor && anchor.parentNode) {
                anchor.parentNode.insertBefore(script, anchor);
                return;
            }

            parent.appendChild(script);
        }

        function loadWidget() {
            if (hasLoaded) {
                return;
            }

            hasLoaded = true;
            window.__awaTawkWidgetLoaded = true;
            detachListeners();
            appendScript();
        }

        function scheduleDeferredLoad() {
            if (document.visibilityState === 'hidden') {
                document.addEventListener('visibilitychange', function onVisible() {
                    if (document.visibilityState !== 'hidden') {
                        scheduleDeferredLoad();
                    }
                }, { once: true });
                return;
            }

            timeoutHandle = window.setTimeout(loadWidget, fallbackDelay);
        }

        interactionEvents.forEach(function (eventName) {
            window.addEventListener(eventName, loadWidget, {
                passive: true,
                once: true,
                capture: true
            });
        });

        scheduleDeferredLoad();
    };
});
