/**
 * B2B Header Status Panel - JavaScript Component
 * AWA Motos E-commerce B2B
 *
 * Features:
 * - Dropdown toggle with accessibility
 * - Keyboard navigation
 * - Click outside to close
 * - Mobile bottom sheet behavior
 */
define([
    'jquery',
    'mage/translate'
], function ($, $t) {
    'use strict';

    $.widget('grupoawamotos.headerStatusPanel', {
        options: {
            triggerSelector: '.b2b-status-trigger',
            dropdownSelector: '.b2b-status-dropdown',
            focusableSelector: 'a, button, input, select, textarea, [tabindex]:not([tabindex="-1"])',
            closeOnOutsideClick: true,
            closeOnEscape: true,
            animationDuration: 250
        },

        /**
         * Widget constructor
         * @private
         */
        _create: function () {
            this.trigger = this.element.find(this.options.triggerSelector);
            this.dropdown = this.element.find(this.options.dropdownSelector);
            this.isOpen = false;
            this.focusableElements = [];

            this._bindEvents();
            this._initAccessibility();
        },

        /**
         * Bind event handlers
         * @private
         */
        _bindEvents: function () {
            let self = this;

            // Trigger click
            this.trigger.on('click.b2bPanel', function (e) {
                e.preventDefault();
                e.stopPropagation();
                self.toggle();
            });

            // Keyboard navigation on trigger
            this.trigger.on('keydown.b2bPanel', function (e) {
                self._handleTriggerKeydown(e);
            });

            // Keyboard navigation in dropdown
            this.dropdown.on('keydown.b2bPanel', function (e) {
                self._handleDropdownKeydown(e);
            });

            // Click outside to close
            if (this.options.closeOnOutsideClick) {
                $(document).on('click.b2bPanel', function (e) {
                    if (self.isOpen && !self.element[0].contains(e.target)) {
                        self.close();
                    }
                });
            }

            // Escape key to close
            if (this.options.closeOnEscape) {
                $(document).on('keydown.b2bPanel', function (e) {
                    if (e.key === 'Escape' && self.isOpen) {
                        self.close();
                        self.trigger.focus();
                    }
                });
            }

            // Handle window resize for mobile
            $(window).on('resize.b2bPanel', $.proxy(this._handleResize, this));
        },

        /**
         * Initialize accessibility attributes
         * @private
         */
        _initAccessibility: function () {
            this.trigger.attr({
                'aria-expanded': 'false',
                'aria-controls': this.dropdown.attr('id')
            });

            this.dropdown.attr({
                'aria-hidden': 'true',
                'role': 'region',
                'aria-label': $t('B2B Account Panel')
            });
        },

        /**
         * Handle keydown on trigger
         * @private
         */
        _handleTriggerKeydown: function (e) {
            switch (e.key) {
                case 'Enter':
                case ' ':
                    e.preventDefault();
                    this.toggle();
                    break;
                case 'ArrowDown':
                    e.preventDefault();
                    this.open();
                    this._focusFirstElement();
                    break;
            }
        },

        /**
         * Handle keydown in dropdown
         * @private
         */
        _handleDropdownKeydown: function (e) {
            let focusable = this.dropdown.find(this.options.focusableSelector).filter(':visible');
            let currentIndex = focusable.index(document.activeElement);

            switch (e.key) {
                case 'Tab':
                    // Trap focus within dropdown
                    if (e.shiftKey && currentIndex === 0) {
                        e.preventDefault();
                        focusable.last().focus();
                    } else if (!e.shiftKey && currentIndex === focusable.length - 1) {
                        e.preventDefault();
                        focusable.first().focus();
                    }
                    break;

                case 'ArrowDown':
                    e.preventDefault();
                    if (currentIndex < focusable.length - 1) {
                        focusable.eq(currentIndex + 1).focus();
                    }
                    break;

                case 'ArrowUp':
                    e.preventDefault();
                    if (currentIndex > 0) {
                        focusable.eq(currentIndex - 1).focus();
                    } else {
                        this.trigger.focus();
                        this.close();
                    }
                    break;

                case 'Home':
                    e.preventDefault();
                    focusable.first().focus();
                    break;

                case 'End':
                    e.preventDefault();
                    focusable.last().focus();
                    break;
            }
        },

        /**
         * Toggle dropdown state
         */
        toggle: function () {
            if (this.isOpen) {
                this.close();
            } else {
                this.open();
            }
        },

        /**
         * Open dropdown
         */
        open: function () {
            if (this.isOpen) return;

            this.isOpen = true;
            this.trigger.attr('aria-expanded', 'true');
            this.dropdown.attr('aria-hidden', 'false');
            this.element.addClass('is-open');

            // Announce to screen readers
            this._announceState('aberto');

            // Focus first focusable element after animation
            setTimeout($.proxy(this._focusFirstElement, this), this.options.animationDuration);
        },

        /**
         * Close dropdown
         */
        close: function () {
            if (!this.isOpen) return;

            this.isOpen = false;
            this.trigger.attr('aria-expanded', 'false');
            this.dropdown.attr('aria-hidden', 'true');
            this.element.removeClass('is-open');

            // Announce to screen readers
            this._announceState('fechado');
        },

        /**
         * Focus first focusable element in dropdown
         * @private
         */
        _focusFirstElement: function () {
            let first = this.dropdown.find(this.options.focusableSelector).filter(':visible').first();
            if (first.length) {
                first.focus();
            }
        },

        /**
         * Handle window resize
         * @private
         */
        _handleResize: function () {
            // Close dropdown when resizing from mobile to desktop to prevent layout overlap
            if (window.innerWidth > 767 && this.isOpen) {
                this.close();
            }
        },

        /**
         * Announce state change for screen readers
         * @private
         */
        _announceState: function (state) {
            let announcement = $('<div/>', {
                'class': 'sr-only',
                'aria-live': 'polite',
                'aria-atomic': 'true',
                'text': $t('Painel B2B %1').replace('%1', state)
            });

            $('body').append(announcement);
            setTimeout(function () {
                announcement.remove();
            }, 1000);
        },

        /**
         * Destroy widget
         * @private
         */
        _destroy: function () {
            this.trigger.off('.b2bPanel');
            this.dropdown.off('.b2bPanel');
            $(document).off('.b2bPanel');
            $(window).off('.b2bPanel');
        }
    });

    return $.grupoawamotos.headerStatusPanel;
});
