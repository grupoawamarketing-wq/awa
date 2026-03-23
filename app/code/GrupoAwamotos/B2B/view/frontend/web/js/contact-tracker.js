define([
    'jquery',
    'mage/url'
], function ($, urlBuilder) {
    'use strict';

    return function (config, element) {
        var options = config || {};
        var $root = $(element);
        var eventName = options.eventName || 'Contact';
        var storagePrefix = options.storagePrefix || 'b2b_contact_fired_';
        var dedupeBySession = options.dedupeBySession !== false;
        var funnelStage = options.funnelStage || 'consideration';
        var touchpoint = options.touchpoint || 'b2b_contact';
        var capiUrl = urlBuilder.build('b2b/ajax/trackContact');

        function hasBeenTracked(action) {
            if (!dedupeBySession || !action) {
                return false;
            }

            try {
                return window.sessionStorage.getItem(storagePrefix + action) === '1';
            } catch (error) {
                return false;
            }
        }

        function markTracked(action) {
            if (!dedupeBySession || !action) {
                return;
            }

            try {
                window.sessionStorage.setItem(storagePrefix + action, '1');
            } catch (error) {
                // Ignore browsers where sessionStorage is blocked.
            }
        }

        /**
         * Generates a lightweight unique event ID to allow Meta to deduplicate
         * the browser Pixel event against the server-side CAPI event.
         */
        function generateEventId(action) {
            return 'contact-b2b-' + action + '-' + Date.now();
        }

        /**
         * Fires CAPI via a lightweight server-side AJAX call so the event is
         * captured even when Meta Pixel is blocked by the browser.
         */
        function sendCapi(action, channel, eventId) {
            var formKey = window.FORM_KEY || '';

            if (!formKey) {
                return;
            }

            $.ajax({
                url: capiUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    contact_action: action,
                    contact_channel: channel,
                    funnel_stage: funnelStage,
                    touchpoint: touchpoint,
                    event_id: eventId,
                    form_key: formKey
                }
            });
        }

        if (!$root.length) {
            return;
        }

        $root.on('click', '[data-b2b-contact-track]', function () {
            var $link = $(this);
            var action = $link.attr('data-b2b-contact-track') || '';
            var channel = $link.attr('data-b2b-contact-channel') || 'unknown';

            if (!action || hasBeenTracked(action)) {
                return;
            }

            var eventId = generateEventId(action);

            if (typeof window.fbq === 'function') {
                window.fbq('track', eventName, {
                    lead_type: 'b2b_cnpj',
                    person_type: 'pj',
                    contact_action: action,
                    contact_channel: channel,
                    funnel_stage: funnelStage,
                    touchpoint: touchpoint
                }, {eventID: eventId});
            }

            sendCapi(action, channel, eventId);
            markTracked(action);
        });
    };
});