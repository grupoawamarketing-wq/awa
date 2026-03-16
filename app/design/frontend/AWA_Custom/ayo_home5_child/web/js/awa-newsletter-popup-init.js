define([
    'jquery'
], function ($) {
    'use strict';

    function setCookie(name, value, days) {
        var expires = '';
        var date;

        if (days) {
            date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = '; expires=' + date.toUTCString();
        }

        document.cookie = name + '=' + value + expires + '; path=/; SameSite=Lax';
    }

    function getCookie(name) {
        var nameEq = name + '=';
        var cookies = document.cookie.split(';');
        var index;
        var item;

        for (index = 0; index < cookies.length; index += 1) {
            item = cookies[index].trim();
            if (item.indexOf(nameEq) === 0) {
                return item.substring(nameEq.length);
            }
        }

        return null;
    }

    return function (config, element) {
        var $popup = $(element);
        var cookieName = config.cookieName || 'shownewsletter';
        var cookieDays = parseInt(config.cookieDays, 10) || 1;
        var popupHeight = parseInt(config.popupHeight, 10) || 520;
        var popupSpeed = parseInt(config.popupSpeed, 10) || 450;
        var homeOnly = !!config.homeOnly;
        var pPopup = null;

        if (!$popup.length || $popup.data('awaNewsletterPopupInit')) {
            return;
        }

        $popup.data('awaNewsletterPopupInit', 1);

        function isHomePage() {
            return $('body').hasClass('cms-index-index')
                || $('body').hasClass('cms-home')
                || $('body').hasClass('cms-homepage_ayo_home5');
        }

        function closePopup(event) {
            if (event) {
                event.preventDefault();
            }

            if (pPopup && typeof pPopup.close === 'function') {
                try {
                    pPopup.close();
                } catch (error) {
                    // noop: fallback below guarantees closure.
                }
            }

            $popup
                .removeClass('nl-popup-fallback-open')
                .css('display', 'none');
            $('body').removeClass('nl-popup-body-open');
        }

        function openPopup() {
            var fixedHeight = Math.max(40, ($(window).height() - popupHeight) / 2);

            if (typeof $.fn.bPopup === 'function') {
                pPopup = $popup.bPopup({
                    position: ['auto', fixedHeight],
                    speed: popupSpeed,
                    transition: 'slideDown',
                    onOpen: function () {
                        $('body').addClass('nl-popup-body-open');
                    },
                    onClose: function () {
                        $('body').removeClass('nl-popup-body-open');
                    }
                });
                return;
            }

            $popup
                .addClass('nl-popup-fallback-open')
                .css('display', 'block');
            $('body').addClass('nl-popup-body-open');
        }

        $(document)
            .on('click.awaNewsletterPopup', '.newletter_popup_close', closePopup)
            .on('submit.awaNewsletterPopup', '#newsletter_pop_up form', function () {
                setCookie(cookieName, '1', cookieDays);
            })
            .on('change.awaNewsletterPopup', '#newsletter_popup_dont_show_again', function () {
                setCookie(cookieName, this.checked ? '1' : '0', cookieDays);
            });

        if (homeOnly && !isHomePage()) {
            return;
        }

        if (getCookie(cookieName) === '1') {
            return;
        }

        $(function () {
            openPopup();
        });
    };
});
