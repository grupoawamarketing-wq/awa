define([], function () {
    'use strict';

    var STORAGE_KEY = 'awa_cookie_consent';
    var COOKIE_NAME = 'awa_cookies_accepted';
    var COOKIE_DAYS = 365;

    function setCookie(name, value, days) {
        var expires = '';
        if (days) {
            var date = new Date();
            date.setTime(date.getTime() + days * 24 * 60 * 60 * 1000);
            expires = '; expires=' + date.toUTCString();
        }
        document.cookie = name + '=' + encodeURIComponent(value) + expires + '; path=/; SameSite=Lax';
    }

    function getCookie(name) {
        var nameEQ = name + '=';
        var cookies = document.cookie.split(';');
        for (var i = 0; i < cookies.length; i++) {
            var c = cookies[i].trim();
            if (c.indexOf(nameEQ) === 0) {
                return decodeURIComponent(c.substring(nameEQ.length));
            }
        }
        return null;
    }

    function hasConsented() {
        try {
            if (localStorage.getItem(STORAGE_KEY)) {
                return true;
            }
        } catch (e) {
            // localStorage bloqueado (modo privado restrito)
        }
        return getCookie(COOKIE_NAME) !== null;
    }

    function saveConsent(value) {
        try {
            localStorage.setItem(STORAGE_KEY, value);
        } catch (e) {
            // silencia erros de localStorage
        }
        setCookie(COOKIE_NAME, value, COOKIE_DAYS);
    }

    function syncBannerHeight() {
        var banner = document.getElementById('awa-cookie-banner');
        if (!banner || !banner.classList.contains('awa-cookie-banner--visible')) {
            document.documentElement.style.removeProperty('--awa-cookie-banner-height');
            return;
        }

        // Defer layout read — evita forced synchronous layout.
        requestAnimationFrame(function () {
            if (banner.classList.contains('awa-cookie-banner--visible')) {
                document.documentElement.style.setProperty('--awa-cookie-banner-height', banner.offsetHeight + 'px');
            }
        });
    }

    function setBannerActive(isActive) {
        if (document.body) {
            document.body.classList.toggle('awa-cookie-banner-active', isActive);
        }

        if (isActive) {
            syncBannerHeight();
            return;
        }

        document.documentElement.style.removeProperty('--awa-cookie-banner-height');
    }

    function hideBanner() {
        var banner = document.getElementById('awa-cookie-banner');
        if (banner) {
            var cleaned = false;
            var cleanup = function () {
                if (cleaned) {
                    return;
                }
                cleaned = true;
                banner.style.display = 'none';
                setBannerActive(false);
            };

            banner.classList.remove('awa-cookie-banner--visible');
            banner.addEventListener('transitionend', cleanup, { once: true });
            setTimeout(cleanup, 500);
        }
    }

    function showBanner() {
        var banner = document.getElementById('awa-cookie-banner');
        if (!banner) {
            return;
        }
        banner.style.display = 'block';
        // Double-rAF: garante paint de display:block antes da animacao CSS — sem forced layout.
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                banner.classList.add('awa-cookie-banner--visible');
                setBannerActive(true);
            });
        });
    }

    function bindEvents() {
        var acceptBtn = document.getElementById('awa-cookie-accept');
        var declineBtn = document.getElementById('awa-cookie-decline');

        if (acceptBtn) {
            acceptBtn.addEventListener('click', function () {
                saveConsent('all');
                hideBanner();
            });
        }

        if (declineBtn) {
            declineBtn.addEventListener('click', function () {
                saveConsent('essential');
                hideBanner();
            });
        }
    }

    return {
        init: function () {
            if (hasConsented()) {
                return;
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function () {
                    bindEvents();
                    window.addEventListener('resize', syncBannerHeight);
                    setTimeout(showBanner, 800);
                });
            } else {
                bindEvents();
                window.addEventListener('resize', syncBannerHeight);
                setTimeout(showBanner, 800);
            }
        }
    };
});
