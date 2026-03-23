/**
 * AWA Searchsuite Autocomplete — Discovery + Navigation + Highlight
 * Extends parent autocomplete with:
 *  - Popular keywords (ranked list)
 *  - Recent searches with clear
 *  - Click-to-search on keywords/recents
 *  - Focus-aware discovery panel
 *  - ESC key to close dropdown
 *  - Loading indicator during AJAX search
 *  - CSRF form_key for add-to-cart
 *  - Keyboard navigation (arrows + Enter)
 *  - Highlight matching text in suggestions/products
 *  - Save recent search on form submit
 */
define([
    'jquery',
    'ko',
    'Rokanthemes_SearchSuiteAutocomplete/js/autocomplete',
    'Magento_Customer/js/customer-data',
    'mage/cookies'
], function ($, ko, BaseComponent, customerData) {
    'use strict';

    var POPULAR_KEYWORDS = [
        { term: 'Bagageiro', url: '/catalogsearch/result/?q=bagageiro' },
        { term: 'Bauleto', url: '/catalogsearch/result/?q=bauleto' },
        { term: 'Retrovisor', url: '/catalogsearch/result/?q=retrovisor' },
        { term: 'Protetor de motor', url: '/catalogsearch/result/?q=protetor%20de%20motor' },
        { term: 'Suporte de celular', url: '/catalogsearch/result/?q=suporte%20de%20celular' },
        { term: 'CG 160', url: '/catalogsearch/result/?q=cg%20160' },
        { term: 'Bros 160', url: '/catalogsearch/result/?q=bros%20160' },
        { term: 'XRE 300', url: '/catalogsearch/result/?q=xre%20300' }
    ];

    return BaseComponent.extend({
        defaults: {
            template: 'Rokanthemes_SearchSuiteAutocomplete/autocomplete'
        },

        initialize: function () {
            this._super();

            this.hasQuery = ko.observable(false);
            this.isInputFocused = ko.observable(false);
            this.isSearching = ko.observable(false);
            this.popularKeywords = ko.observableArray(POPULAR_KEYWORDS);
            this.formKey = $.mage.cookies.get('form_key') || '';
            this.navIndex = ko.observable(-1);

            this.shouldShowDiscovery = ko.pureComputed(function () {
                return this.isInputFocused() && !this.hasQuery();
            }, this);

            this._navItemCount = ko.pureComputed(function () {
                if (this.shouldShowDiscovery()) {
                    return this.popularKeywords().length;
                }

                return (this.result && this.result.suggest ? this.result.suggest.data().length : 0) +
                       (this.result && this.result.product ? this.result.product.data().length : 0);
            }, this);

            this._bindDiscoveryEvents();
            this._bindEscKey();
            this._bindKeyboardNav();
            this._patchSearchingState();
            this._bindFormSubmit();

            return this;
        },

        /**
         * Click a popular keyword — fill input and trigger search via AJAX
         */
        clickKeyword: function (keyword, event) {
            if (event) {
                event.preventDefault();
            }
            this._fillAndSearch(keyword.term);
        },

        /**
         * Click a recent search chip — fill input and trigger search via AJAX
         */
        clickRecentSearch: function (recent, event) {
            if (event) {
                event.preventDefault();
            }
            this._fillAndSearch(recent.term);
        },

        /**
         * Clear all saved recent searches
         */
        clearRecentSearches: function () {
            try {
                localStorage.removeItem('awa_recent_searches');
            } catch (e) {
                // localStorage may be unavailable
            }
            this.recentSearches([]);
        },

        /**
         * Close the dropdown and blur input
         */
        closeDropdown: function () {
            this.isInputFocused(false);
            this.showPopup(false);
            this._getInput().blur();
        },

        /**
         * Internal: fill the search input, update state, and fire the dataProvider
         */
        _fillAndSearch: function (term) {
            var input = this._getInput();

            if (!input.length || !term) {
                return;
            }

            input.val(term).trigger('input').trigger('change');
            this.hasQuery(true);
            this.isInputFocused(true);
            this.showPopup(true);
            input.focus();
        },

        /**
         * Get the search input element
         */
        _getInput: function () {
            return $('#search_mini_form #search, #search_mini_form input[name="q"]').first();
        },

        /**
         * Bind ESC key to close the dropdown
         */
        _bindEscKey: function () {
            var self = this;

            $(document).on('keydown.awaSearchEsc', function (e) {
                if (e.key === 'Escape' && self.showPopup()) {
                    self.closeDropdown();
                }
            });
        },

        /**
         * Patch the parent's AJAX mechanism to track searching state
         */
        _patchSearchingState: function () {
            var self = this;

            $(document).ajaxSend(function (event, jqXHR, settings) {
                if (settings.url && settings.url.indexOf('searchsuiteautocomplete') !== -1) {
                    self.isSearching(true);
                }
            });

            $(document).ajaxComplete(function (event, jqXHR, settings) {
                if (settings.url && settings.url.indexOf('searchsuiteautocomplete') !== -1) {
                    self.isSearching(false);
                }
            });
        },

        /**
         * Highlight matching query text in a string (returns safe HTML)
         */
        highlightMatch: function (text) {
            var input = this._getInput(),
                query = input.length ? $.trim(input.val() || '') : '';

            if (!query || !text) {
                return $('<span>').text(text || '').html();
            }

            var escaped = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'),
                regex = new RegExp('(' + escaped + ')', 'gi'),
                safeText = $('<span>').text(text).html();

            return safeText.replace(regex, '<mark class="awa-ac-highlight">$1</mark>');
        },

        /**
         * Keyboard navigation: arrows + Enter within dropdown
         */
        _bindKeyboardNav: function () {
            var self = this;

            $(document).on('keydown.awaSearchNav', function (e) {
                if (!self.showPopup()) {
                    return;
                }

                var key = e.key,
                    idx = self.navIndex(),
                    max = self._navItemCount() - 1;

                if (key === 'ArrowDown') {
                    e.preventDefault();
                    self.navIndex(idx < max ? idx + 1 : 0);
                    self._scrollActiveIntoView();
                    self._updateAriaActiveDescendant();
                } else if (key === 'ArrowUp') {
                    e.preventDefault();
                    self.navIndex(idx > 0 ? idx - 1 : max);
                    self._scrollActiveIntoView();
                    self._updateAriaActiveDescendant();
                } else if (key === 'Enter' && idx >= 0) {
                    e.preventDefault();
                    self._activateNavItem(idx);
                }
            });
        },

        /**
         * Scroll the currently active nav item into view within the dropdown
         */
        _scrollActiveIntoView: function () {
            window.setTimeout(function () {
                var active = document.querySelector('#searchsuite-autocomplete .awa-ac-nav-active');

                if (active && typeof active.scrollIntoView === 'function') {
                    active.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                }
            }, 20);
        },

        /**
         * Update aria-activedescendant on search input
         */
        _updateAriaActiveDescendant: function () {
            var input = this._getInput(),
                idx = this.navIndex();

            if (!input.length) {
                return;
            }

            if (idx >= 0) {
                input.attr('aria-activedescendant', 'awa-ac-item-' + idx);
            } else {
                input.removeAttr('aria-activedescendant');
            }
        },

        /**
         * Activate the currently nav-highlighted item
         */
        _activateNavItem: function (idx) {
            var kwLen = this.popularKeywords().length,
                suggestData, productData, suggestLen;

            if (this.shouldShowDiscovery()) {
                if (idx < kwLen) {
                    this._fillAndSearch(this.popularKeywords()[idx].term);
                }

                return;
            }

            suggestData = this.result && this.result.suggest ? this.result.suggest.data() : [];
            productData = this.result && this.result.product ? this.result.product.data() : [];
            suggestLen = suggestData.length;

            if (idx < suggestLen && suggestData[idx]) {
                window.location.href = suggestData[idx].url;
            } else if (productData[idx - suggestLen]) {
                window.location.href = productData[idx - suggestLen].url;
            }
        },

        /**
         * Save search term to recent searches when form is submitted
         */
        _bindFormSubmit: function () {
            var self = this;

            $('#search_mini_form').on('submit.awaSearchRecent', function () {
                var term = $.trim(self._getInput().val() || '');

                if (term.length > 1 && typeof self.saveRecentSearch === 'function') {
                    self.saveRecentSearch(term);
                }
            });
        },

        /**
         * Bind focus/blur/input events for discovery panel management
         */
        _bindDiscoveryEvents: function () {
            var self = this;
            var selectors = '#search_mini_form #search, #search_mini_form input[name="q"]';

            function updateQueryState() {
                var input = self._getInput();
                var value = input.length ? $.trim(input.val() || '') : '';
                self.hasQuery(value.length > 0);
                self.navIndex(-1);
                self._updateAriaActiveDescendant();
            }

            function closeIfOutside() {
                window.setTimeout(function () {
                    var activeEl = document.activeElement;
                    var popup = document.getElementById('searchsuite-autocomplete');
                    var input = self._getInput().get(0);
                    var insidePopup = popup && activeEl ? popup.contains(activeEl) : false;
                    var isInput = !!(input && activeEl === input);

                    if (!insidePopup && !isInput) {
                        self.isInputFocused(false);
                        if (!self.hasQuery() && !self.anyResultCount()) {
                            self.showPopup(false);
                        }
                    }
                }, 150);
            }

            $(document)
                .off('.awaSearchDiscovery')
                .on('focusin.awaSearchDiscovery', selectors, function () {
                    self.isInputFocused(true);
                    updateQueryState();
                    self.showPopup(true);
                })
                .on('input.awaSearchDiscovery keyup.awaSearchDiscovery change.awaSearchDiscovery', selectors, function () {
                    self.isInputFocused(true);
                    updateQueryState();
                    self.showPopup(true);
                })
                .on('focusout.awaSearchDiscovery', selectors, function () {
                    closeIfOutside();
                })
                .on('mousedown.awaSearchDiscovery touchstart.awaSearchDiscovery',
                    '#searchsuite-autocomplete a, #searchsuite-autocomplete button', function () {
                    self.isInputFocused(true);
                })
                .on('mousedown.awaSearchDiscovery touchstart.awaSearchDiscovery', function (event) {
                    var root = document.querySelector('.header .top-search .block-search');

                    if (root && !root.contains(event.target)) {
                        self.isInputFocused(false);
                        if (!self.hasQuery() && !self.anyResultCount()) {
                            self.showPopup(false);
                        }
                    }
                });

            updateQueryState();
        }
    });
});
