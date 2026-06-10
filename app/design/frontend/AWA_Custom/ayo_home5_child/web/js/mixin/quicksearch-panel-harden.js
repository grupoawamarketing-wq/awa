/**
 * AWA — Harden Magento quickSearch: sincroniza classes/ARIA após injetar sugestões.
 */
define([
    'jquery'
], function ($) {
    'use strict';

    return function (QuickSearch) {
        $.widget('mage.quickSearch', QuickSearch, {
            /**
             * @inheritdoc
             */
            _create: function () {
                this._awaPromoteTimer = null;
                this._super();
            },

            /**
             * @private
             */
            _awaSchedulePromote: function () {
                var widget = this;

                if (this._awaPromoteTimer) {
                    window.clearTimeout(this._awaPromoteTimer);
                }

                this._awaPromoteTimer = window.setTimeout(function () {
                    widget._awaPromotePanel();
                }, 420);
            },

            /**
             * @private
             */
            _awaPromotePanel: function () {
                var panel = this.autoComplete;
                var value = $.trim(this.element.val() || '');
                var minLen = parseInt(this.options.minSearchLength, 10) || 3;

                if (!panel || !panel.length || value.length < minLen || !panel.children().length) {
                    return;
                }

                panel.addClass('is-open active has-results');
                panel.attr('aria-hidden', 'false');
                panel.removeAttr('hidden');
                this.searchForm.addClass('is-open has-results');
                this.element.attr('aria-expanded', 'true');

                if (document.body) {
                    document.body.classList.add('searchautocomplete__active');
                }
            },

            /**
             * @inheritdoc
             */
            _onPropertyChange: function () {
                this._super();
                this._awaSchedulePromote();
            }
        });

        return $.mage.quickSearch;
    };
});
