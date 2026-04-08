/**
 * Curriculo Progress Bar Widget
 * Tracks form completion and updates progress bar in real-time
 */
define([
    'jquery',
    'jquery-ui-modules/widget'
], function ($) {
    'use strict';

    $.widget('ayo.curriculoProgress', {
        options: {
            formSelector: '#curriculo-form',
            progressBarSelector: '#progress-fill',
            progressPercentSelector: '#progress-percent',
            filledFieldsSelector: '#filled-fields',
            totalFieldsSelector: '#total-fields',
            stepSelector: '.curriculo-progress .step',
            requiredWeight: 2, // Required fields count double
            optionalWeight: 1
        },

        _create: function () {
            this.form = $(this.options.formSelector);
            this.progressBar = $(this.options.progressBarSelector);
            this.progressPercent = $(this.options.progressPercentSelector);
            this.filledFields = $(this.options.filledFieldsSelector);
            this.totalFields = $(this.options.totalFieldsSelector);
            this.steps = $(this.options.stepSelector);
            
            this._bindEvents();
            this._updateProgress();
        },

        _bindEvents: function () {
            var self = this;
            
            // Track all form inputs
            this.form.on('input change blur', 'input, select, textarea', function () {
                self._updateProgress();
            });

            // Track file input specifically
            this.form.on('change', 'input[type="file"]', function () {
                self._updateProgress();
            });
        },

        _getFields: function () {
            var fields = [];
            
            this.form.find('input, select, textarea').each(function () {
                var $el = $(this);
                var type = $el.attr('type');
                var name = $el.attr('name');
                
                // Skip hidden fields, form key, honeypot
                if (type === 'hidden' || name === 'form_key' || name === 'hideit') {
                    return;
                }
                
                // Skip checkboxes that are not consent
                if (type === 'checkbox' && name !== 'consent') {
                    return;
                }
                
                var isRequired = $el.prop('required') || $el.hasClass('required-entry') || 
                                 $el.closest('.field').hasClass('required');
                
                fields.push({
                    element: $el,
                    name: name,
                    type: type,
                    required: isRequired,
                    step: $el.closest('[data-progress-step]').data('progress-step') || 1
                });
            });
            
            return fields;
        },

        _isFieldFilled: function (field) {
            var $el = field.element;
            var val = $el.val();
            
            if (field.type === 'checkbox') {
                return $el.is(':checked');
            }
            
            if (field.type === 'file') {
                return $el[0].files && $el[0].files.length > 0;
            }
            
            if (field.type === 'select-one' || $el.is('select')) {
                return val && val !== '';
            }
            
            return val && $.trim(val) !== '';
        },

        _updateProgress: function () {
            var fields = this._getFields();
            var totalWeight = 0;
            var filledWeight = 0;
            var filledCount = 0;
            var stepsFilled = {};
            var stepsTotal = {};
            
            $.each(fields, function (i, field) {
                var weight = field.required ? this.options.requiredWeight : this.options.optionalWeight;
                totalWeight += weight;
                
                // Track steps
                if (!stepsTotal[field.step]) {
                    stepsTotal[field.step] = 0;
                    stepsFilled[field.step] = 0;
                }
                stepsTotal[field.step]++;
                
                if (this._isFieldFilled(field)) {
                    filledWeight += weight;
                    filledCount++;
                    stepsFilled[field.step]++;
                }
            }.bind(this));
            
            var percent = totalWeight > 0 ? Math.round((filledWeight / totalWeight) * 100) : 0;
            
            // Update UI
            this.progressBar.css('width', percent + '%');
            this.progressPercent.text(percent);
            this.filledFields.text(filledCount);
            this.totalFields.text(fields.length);
            
            // Update progress bar color based on percentage
            this.progressBar.removeClass('low medium high complete');
            if (percent < 25) {
                this.progressBar.addClass('low');
            } else if (percent < 50) {
                this.progressBar.addClass('medium');
            } else if (percent < 100) {
                this.progressBar.addClass('high');
            } else {
                this.progressBar.addClass('complete');
            }
            
            // Update steps
            this.steps.each(function () {
                var $step = $(this);
                var stepNum = $step.data('step');
                
                if (stepsTotal[stepNum] && stepsFilled[stepNum]) {
                    var stepComplete = stepsFilled[stepNum] >= stepsTotal[stepNum];
                    var stepStarted = stepsFilled[stepNum] > 0;
                    
                    $step.removeClass('active in-progress complete').removeAttr('aria-current');
                    if (stepComplete) {
                        $step.addClass('complete');
                    } else if (stepStarted) {
                        $step.addClass('in-progress').attr('aria-current', 'step');
                    }
                }
            });
        }
    });

    return $.ayo.curriculoProgress;
});
