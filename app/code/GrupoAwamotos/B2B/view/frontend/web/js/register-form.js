define([
    'jquery',
    'mage/url',
    'mage/validation'
], function ($, urlBuilder) {
    'use strict';

    return function (config, element) {
        var options = config || {};
        var $form = $(element);
        var $container = $form.closest('.b2b-register-container');
        var cnpjValidateUrl = options.cnpjValidateUrl || '';
        var loginUrl = options.loginUrl || '';
        var sendingLabel = options.sendingLabel || 'Enviando cadastro...';
        var leadStorageKey = options.leadStorageKey || 'b2b_register_lead_fired';
        var leadEventName = options.leadEventName || 'Lead';

        var cnpjTimer = null;
        var lastCnpj = '';
        var cnpjValidated = false;
        var rateLimitTimer = null;
        var erpEmailFull = null;
        var erpEmailMasked = null;
        var currentProgressStep = 1;
        var benefitsViewportMedia = window.matchMedia ? window.matchMedia('(max-width: 600px)') : null;
        var lastBenefitsCompactMode = null;
        var lastSectionsCompactMode = null;
        var progressSyncScheduled = false;
        var leadTriggered = false;

        if (!$form.length) {
            return;
        }

        var $progressSteps = $form.find('.b2b-register-progress .progress-step');
        var $progressBar = $form.find('.b2b-register-progress');
        var $stepSections = $form.find('.form-section[data-step]');
        var $benefitsToggle = $container.find('.b2b-benefits-toggle');
        var $benefitsPanel = $container.find('#b2b-register-benefits');
        var $passwordToggles = $form.find('[data-register-password-toggle]');

        function $field(selector) {
            return $form.find(selector);
        }

        function hasLeadBeenTracked() {
            try {
                return window.sessionStorage.getItem(leadStorageKey) === '1';
            } catch (error) {
                return false;
            }
        }

        function markLeadTracked() {
            try {
                window.sessionStorage.setItem(leadStorageKey, '1');
            } catch (error) {
                // Browsers with restricted storage should not break the flow.
            }
        }

        function trackLeadStart() {
            if (leadTriggered || hasLeadBeenTracked()) {
                return;
            }

            var eventId = 'lead-b2b-' + Date.now();

            if (typeof window.fbq === 'function') {
                window.fbq('track', leadEventName, {
                    lead_type: 'b2b_cnpj',
                    person_type: 'pj',
                    funnel_stage: 'start',
                    register_channel: 'b2b_register_form'
                }, {eventID: eventId});
            }

            var formKey = window.FORM_KEY || '';

            if (formKey) {
                $.ajax({
                    url: urlBuilder.build('b2b/ajax/trackLead'),
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        event_id: eventId,
                        funnel_stage: 'start',
                        register_channel: 'b2b_register_form',
                        form_key: formKey
                    }
                });
            }

            leadTriggered = true;
            markLeadTracked();
        }

        function updateRegisterPasswordToggleButton($toggle, isVisible) {
            $toggle.attr('aria-pressed', isVisible ? 'true' : 'false');
            $toggle.attr('aria-label', isVisible ? 'Ocultar senha' : 'Mostrar senha');
            $toggle.text(isVisible ? 'Ocultar' : 'Mostrar');
        }

        function initRegisterPasswordToggles() {
            $passwordToggles.each(function () {
                var $toggle = $(this);
                var targetSelector = $toggle.attr('data-target');
                var $target = targetSelector ? $form.find(targetSelector) : $();

                if (!$target.length) {
                    return;
                }

                updateRegisterPasswordToggleButton($toggle, $target.attr('type') === 'text');

                $toggle.on('click', function (event) {
                    var showPassword;

                    event.preventDefault();
                    showPassword = $target.attr('type') !== 'text';
                    $target.attr('type', showPassword ? 'text' : 'password');
                    updateRegisterPasswordToggleButton($toggle, showPassword);
                    $target.trigger('focus');
                });
            });
        }

        function setHiddenState($element, isHidden) {
            if (!$element || !$element.length) {
                return $element;
            }

            $element.toggleClass('is-hidden', !!isHidden)
                .attr('aria-hidden', isHidden ? 'true' : 'false');

            return $element;
        }

        function showBlock($element) {
            setHiddenState($element, false);
            $element.show();
            return $element;
        }

        function hideBlock($element) {
            $element.hide();
            setHiddenState($element, true);
            return $element;
        }

        function slideShow($element, duration) {
            setHiddenState($element, false);
            return $element.stop(true, true).slideDown(duration || 200);
        }

        function slideHide($element, duration) {
            return $element.stop(true, true).slideUp(duration || 200, function () {
                setHiddenState($(this), true);
            });
        }

        function setActiveProgressStep(stepNumber) {
            var activeStep = parseInt(stepNumber, 10);

            if (!activeStep || !$progressSteps.length) {
                return;
            }

            currentProgressStep = activeStep;

            $progressSteps.each(function (index) {
                var stepIndex = parseInt($(this).attr('data-step-number'), 10) || (index + 1);
                var $step = $(this);
                var isActive = stepIndex === activeStep;

                $step.toggleClass('is-active', isActive);
                if (isActive) {
                    $step.removeAttr('aria-current');
                    $step.attr('aria-current', 'step');
                } else {
                    $step.removeAttr('aria-current');
                }
            });

            updateProgressCompletionState();
            scrollActiveProgressStepIntoView();
        }

        function syncProgressFromFocus($target) {
            var $section = $target.closest('.form-section');
            var stepNumber = $section.data('step');

            if ($section.length && $section.is('[data-step]')) {
                openStepSection($section, true, true);
            }

            if (!stepNumber && $section.hasClass('form-section--terms')) {
                stepNumber = 4;
            }

            if (stepNumber) {
                setActiveProgressStep(stepNumber);
            }
        }

        function scrollActiveProgressStepIntoView() {
            var $activeStep;
            var supportsSmooth = !!(window.CSS && window.CSS.supports && window.CSS.supports('scroll-behavior', 'smooth'));

            if (!$progressBar.length || !isCompactBenefitsViewport()) {
                return;
            }

            $activeStep = $progressSteps.filter('.is-active').first();

            if (!$activeStep.length || !$activeStep.get(0) || typeof $activeStep.get(0).scrollIntoView !== 'function') {
                return;
            }

            try {
                $activeStep.get(0).scrollIntoView({
                    behavior: supportsSmooth ? 'smooth' : 'auto',
                    block: 'nearest',
                    inline: 'center'
                });
            } catch (error) {
                $activeStep.get(0).scrollIntoView();
            }
        }

        function isCompactBenefitsViewport() {
            if (benefitsViewportMedia) {
                return !!benefitsViewportMedia.matches;
            }

            return window.innerWidth <= 600;
        }

        function setBenefitsExpanded(isExpanded, animate) {
            if (!$benefitsToggle.length || !$benefitsPanel.length) {
                return;
            }

            $benefitsToggle.attr('aria-expanded', isExpanded ? 'true' : 'false');

            if (isExpanded) {
                if (animate) {
                    slideShow($benefitsPanel, 180);
                } else {
                    showBlock($benefitsPanel);
                }
                return;
            }

            if (animate) {
                slideHide($benefitsPanel, 150);
            } else {
                hideBlock($benefitsPanel);
            }
        }

        function syncBenefitsDisclosure() {
            var compactMode = isCompactBenefitsViewport();

            if (!$benefitsToggle.length || !$benefitsPanel.length) {
                return;
            }

            if (!compactMode) {
                setBenefitsExpanded(true, false);
                lastBenefitsCompactMode = false;
                return;
            }

            if (lastBenefitsCompactMode === compactMode) {
                return;
            }

            setBenefitsExpanded(false, false);
            lastBenefitsCompactMode = true;
        }

        function getStepSectionBody($section) {
            return $section.children('.form-section__body').first();
        }

        function getStepSectionToggle($section) {
            return $section.children('.form-section__heading').find('.form-section__toggle').first();
        }

        function initStepSectionAccordionMarkup() {
            $stepSections.each(function (index) {
                var $section = $(this);
                var $heading = $section.children('h3').first();
                var $body;
                var $toggle;
                var headingText;
                var sectionId;
                var bodyId;
                var stepNumber;

                if (!$heading.length || $heading.children('.form-section__toggle').length) {
                    return;
                }

                sectionId = $section.attr('id');
                if (!sectionId) {
                    sectionId = 'b2b-register-step-' + (index + 1);
                    $section.attr('id', sectionId);
                }

                bodyId = sectionId + '-body';
                stepNumber = String($section.data('step') || (index + 1));
                headingText = $.trim($heading.text());
                $body = $('<div/>', {
                    'class': 'form-section__body',
                    'id': bodyId,
                    'aria-hidden': 'false'
                });

                $section.children(':not(h3)').appendTo($body);
                $section.append($body);

                $heading.addClass('form-section__heading').empty();
                $toggle = $('<button/>', {
                    type: 'button',
                    'class': 'form-section__toggle',
                    'data-section-toggle': '',
                    'data-step-number': stepNumber,
                    'aria-expanded': 'true',
                    'aria-controls': bodyId
                });

                $('<span/>', {
                    'class': 'form-section__toggle-text',
                    text: headingText
                }).appendTo($toggle);

                $('<span/>', {
                    'class': 'form-section__toggle-icon',
                    'aria-hidden': 'true'
                }).appendTo($toggle);

                $heading.append($toggle);
            });
        }

        function setStepSectionExpanded($section, isExpanded, animate) {
            var $body = getStepSectionBody($section);
            var $toggle = getStepSectionToggle($section);

            if (!$section || !$section.length || !$body.length || !$toggle.length) {
                return;
            }

            $section.toggleClass('is-collapsed', !isExpanded);
            $toggle.attr('aria-expanded', isExpanded ? 'true' : 'false');

            if (isExpanded) {
                if (animate) {
                    slideShow($body, 180);
                } else {
                    showBlock($body);
                }

                return;
            }

            if (animate) {
                slideHide($body, 160);
            } else {
                hideBlock($body);
            }
        }

        function openStepSection($section, animate, collapseSiblings) {
            var shouldCollapseSiblings = collapseSiblings;

            if (!$section || !$section.length || !$stepSections.length) {
                return;
            }

            if (!isCompactBenefitsViewport()) {
                setStepSectionExpanded($section, true, animate);
                return;
            }

            if (typeof shouldCollapseSiblings === 'undefined') {
                shouldCollapseSiblings = true;
            }

            if (shouldCollapseSiblings) {
                $stepSections.not($section).each(function () {
                    setStepSectionExpanded($(this), false, animate);
                });
            }

            setStepSectionExpanded($section, true, animate);
        }

        function expandAllStepSections(animate) {
            if (!$stepSections.length) {
                return;
            }

            $stepSections.each(function () {
                setStepSectionExpanded($(this), true, animate);
            });
        }

        function syncStepSectionsDisclosure() {
            var compactMode = isCompactBenefitsViewport();
            var $activeSection;

            if (!$stepSections.length) {
                return;
            }

            if (!compactMode) {
                expandAllStepSections(false);
                lastSectionsCompactMode = false;
                return;
            }

            if (lastSectionsCompactMode === compactMode) {
                return;
            }

            $activeSection = $stepSections.filter('[data-step="' + currentProgressStep + '"]').first();
            if (!$activeSection.length) {
                $activeSection = $stepSections.first();
            }

            openStepSection($activeSection, false, true);
            lastSectionsCompactMode = true;
        }

        function syncProgressFromViewport() {
            var probeTop;
            var detectedStep = 1;

            if (!$stepSections.length) {
                return;
            }

            probeTop = (window.pageYOffset || document.documentElement.scrollTop || 0) + (isCompactBenefitsViewport() ? 120 : 180);

            $stepSections.each(function () {
                var $section = $(this);
                var stepNumber = parseInt($section.data('step'), 10);
                var sectionTop = $section.offset() ? $section.offset().top : 0;

                if (stepNumber && sectionTop <= probeTop) {
                    detectedStep = stepNumber;
                }
            });

            if (detectedStep !== currentProgressStep) {
                setActiveProgressStep(detectedStep);
            }
        }

        function scheduleProgressViewportSync() {
            if (progressSyncScheduled) {
                return;
            }

            progressSyncScheduled = true;
            window.requestAnimationFrame(function () {
                progressSyncScheduled = false;
                syncProgressFromViewport();
            });
        }

        function fieldHasValue(selector) {
            return $.trim(String($field(selector).val() || '')) !== '';
        }

        function isStepComplete(stepIndex) {
            if (stepIndex === 1) {
                return fieldHasValue('#cnpj') && fieldHasValue('#razao_social') && fieldHasValue('#phone') && cnpjValidated;
            }

            if (stepIndex === 2) {
                return fieldHasValue('#cep') &&
                    fieldHasValue('#logradouro') &&
                    fieldHasValue('#numero') &&
                    fieldHasValue('#bairro') &&
                    fieldHasValue('#municipio') &&
                    fieldHasValue('#uf');
            }

            if (stepIndex === 3) {
                return fieldHasValue('#firstname') && fieldHasValue('#lastname') && fieldHasValue('#email');
            }

            if (stepIndex === 4) {
                var password = String($field('#password').val() || '');
                var confirm = String($field('#password_confirmation').val() || '');
                return password.length >= 6 && confirm.length >= 6 && password === confirm;
            }

            return false;
        }

        function updateProgressCompletionState() {
            if (!$progressSteps.length) {
                return;
            }

            $progressSteps.each(function (index) {
                var $step = $(this);
                var stepIndex = parseInt($step.attr('data-step-number'), 10) || (index + 1);
                var isActive = stepIndex === currentProgressStep;
                var isComplete = isStepComplete(stepIndex);

                $step.toggleClass('is-complete', isComplete && !isActive);
            });
        }

        function refreshFieldAndSectionErrorStates() {
            $form.find('.field').each(function () {
                var $wrapper = $(this);
                var hasError = $wrapper.hasClass('_error') || $wrapper.find('.mage-error:visible').length > 0;

                $wrapper.find('input, select, textarea').attr('aria-invalid', hasError ? 'true' : 'false');
            });

            $form.find('.form-section').each(function () {
                var $section = $(this);
                var hasErrors = $section.find('.field._error, .mage-error:visible').length > 0;
                $section.toggleClass('has-errors', hasErrors);
            });
        }

        function focusFirstFieldInSection($section) {
            var $target;

            if (!$section || !$section.length) {
                return;
            }

            if ($section.is('[data-step]')) {
                openStepSection($section, false, true);
            }

            $target = $section.find('input, select, textarea').filter(':visible').first();

            if ($target.length) {
                window.setTimeout(function () {
                    $target.trigger('focus');
                }, 30);
            }
        }

        function scrollToSection($section) {
            var topOffset;
            var prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            var scrollOffset = isCompactBenefitsViewport() ? 86 : 18;

            if (!$section || !$section.length) {
                return;
            }

            topOffset = Math.max(0, ($section.offset().top || 0) - scrollOffset);

            if (prefersReducedMotion) {
                window.scrollTo(0, topOffset);
                return;
            }

            $('html, body').stop(true).animate({ scrollTop: topOffset }, 240);
        }

        function focusFirstInvalidField() {
            var $firstInvalid = $form.find('.field._error input, .field._error select, .field._error textarea')
                .filter(':visible')
                .first();
            var $invalidSection;

            if (!$firstInvalid.length) {
                return;
            }

            $invalidSection = $firstInvalid.closest('.form-section');
            if ($invalidSection.length && $invalidSection.is('[data-step]')) {
                openStepSection($invalidSection, false, true);
            }

            syncProgressFromFocus($firstInvalid);
            scrollToSection($invalidSection);
            window.setTimeout(function () {
                $firstInvalid.trigger('focus');
            }, 60);
        }

        function maskCnpj(value) {
            var masked = value.replace(/\D/g, '');

            if (masked.length <= 14) {
                masked = masked.replace(/^(\d{2})(\d)/, '$1.$2');
                masked = masked.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
                masked = masked.replace(/\.(\d{3})(\d)/, '.$1/$2');
                masked = masked.replace(/(\d{4})(\d)/, '$1-$2');
            }

            return masked;
        }

        function maskPhone(value) {
            var masked = value.replace(/\D/g, '');

            if (masked.length <= 11) {
                if (masked.length > 2) {
                    masked = '(' + masked.substring(0, 2) + ') ' + masked.substring(2);
                }

                if (masked.length > 10) {
                    masked = masked.substring(0, 10) + '-' + masked.substring(10);
                }
            }

            return masked;
        }

        function maskCep(value) {
            var masked = value.replace(/\D/g, '');

            if (masked.length > 5) {
                masked = masked.substring(0, 5) + '-' + masked.substring(5, 8);
            }

            return masked;
        }

        function setCnpjStatus(type, message) {
            var $status = $field('#cnpj-status');
            var $feedback = $field('#cnpj-feedback');

            $status
                .removeClass('status-loading status-success status-error')
                .addClass('status-' + type);

            if (type === 'loading') {
                $status.html('<span class="cnpj-spinner"></span>');
                showBlock($feedback.html('<span class="feedback-loading">Consultando Receita Federal...</span>'));
                refreshFieldAndSectionErrorStates();
                return;
            }

            if (type === 'success') {
                $status.html('<span class="cnpj-check">&#10003;</span>');
                showBlock($feedback.html('<span class="feedback-success">' + message + '</span>'));
                refreshFieldAndSectionErrorStates();
                return;
            }

            if (type === 'error') {
                $status.html('<span class="cnpj-x">&#10007;</span>');
                showBlock($feedback.html('<span class="feedback-error">' + message + '</span>'));
                refreshFieldAndSectionErrorStates();
            }
        }

        function updateSubmitState() {
            var $submit = $form.find('.actions-toolbar .action.submit.primary');
            var digits = ($field('#cnpj').val() || '').replace(/\D/g, '');

            if (digits.length === 14 && !cnpjValidated) {
                $submit.prop('disabled', true).addClass('cnpj-pending');
                return;
            }

            $submit.prop('disabled', false).removeClass('cnpj-pending');
        }

        function resetCnpjStatus() {
            $field('#cnpj-status')
                .removeClass('status-loading status-success status-error')
                .html('');
            hideBlock($field('#cnpj-feedback').html(''));
            slideHide($field('#cnpj-company-data'), 200);
            hideBlock($field('#erp-email-alert').empty());

            erpEmailFull = null;
            erpEmailMasked = null;
            cnpjValidated = false;

            updateSubmitState();
            updateProgressCompletionState();
            refreshFieldAndSectionErrorStates();
        }

        function showErpEmailAlert() {
            var $alert = $field('#erp-email-alert');
            var currentEmail = ($field('#email').val() || '').trim().toLowerCase();

            if (!erpEmailMasked) {
                hideBlock($alert.empty());
                return;
            }

            if (!currentEmail) {
                $alert.html(
                    '<div class="erp-alert erp-alert-info">' +
                        '<span class="erp-alert-icon">&#128269;</span>' +
                        '<div class="erp-alert-content">' +
                            '<strong>Cliente encontrado no sistema!</strong><br>' +
                            'E-mail cadastrado: <strong>' + erpEmailMasked + '</strong><br>' +
                            '<small>Informe o mesmo e-mail para vincular sua conta automaticamente.</small>' +
                        '</div>' +
                    '</div>'
                );
                slideShow($alert, 300);
                return;
            }

            if (erpEmailFull && currentEmail === erpEmailFull) {
                $alert.html(
                    '<div class="erp-alert erp-alert-success">' +
                        '<span class="erp-alert-icon">&#10004;</span>' +
                        '<div class="erp-alert-content">' +
                            '<strong>E-mail confirmado!</strong> Sua conta sera vinculada automaticamente.' +
                        '</div>' +
                    '</div>'
                );
                slideShow($alert, 200);
                return;
            }

            $alert.html(
                '<div class="erp-alert erp-alert-warning">' +
                    '<span class="erp-alert-icon">&#9888;</span>' +
                    '<div class="erp-alert-content">' +
                        '<strong>Atencao:</strong> O e-mail cadastrado no sistema e <strong>' + erpEmailMasked + '</strong>.<br>' +
                        'O e-mail informado e diferente. Deseja continuar com <strong>' + currentEmail + '</strong>?<br>' +
                        '<small>Se possivel, use o mesmo e-mail para vincular seu historico de compras.</small>' +
                    '</div>' +
                '</div>'
            );
            slideShow($alert, 200);
        }

        function isValidEmail(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        }

        function updateEmailCheckAlert() {
            var email = ($field('#email').val() || '').trim();
            var password = $field('#password').val() || '';
            var passwordConfirmation = $field('#password_confirmation').val() || '';
            var $alert = $field('#email-check-alert');

            if (passwordConfirmation.length >= 6 && password === passwordConfirmation && isValidEmail(email)) {
                $alert.html(
                    '<div class="email-check-alert-box">' +
                        '<span class="email-check-alert-icon">&#9888;</span>' +
                        '<div class="email-check-alert-content">' +
                            '<strong>Confirme seu e-mail antes de criar a conta:</strong><br>' +
                            'A aprovacao e a recuperacao de senha serao enviadas para <strong>' + email + '</strong>.' +
                        '</div>' +
                    '</div>'
                );
                slideShow($alert, 180);
                return;
            }

            slideHide($alert, 120).empty();
        }

        function startRateLimitCountdown(seconds) {
            var remaining = seconds;
            var $cnpj = $field('#cnpj');

            setCnpjStatus('error', 'Muitas consultas. Aguarde <strong>' + remaining + 's</strong>...');
            $cnpj.prop('disabled', true);

            if (rateLimitTimer) {
                clearInterval(rateLimitTimer);
            }

            rateLimitTimer = setInterval(function () {
                remaining -= 1;

                if (remaining <= 0) {
                    clearInterval(rateLimitTimer);
                    rateLimitTimer = null;
                    $cnpj.prop('disabled', false);
                    setCnpjStatus('error', 'Voce pode consultar novamente agora.');

                    var digits = ($cnpj.val() || '').replace(/\D/g, '');

                    if (digits.length === 14) {
                        consultarCnpj(digits);
                    }

                    return;
                }

                setCnpjStatus('error', 'Muitas consultas. Aguarde <strong>' + remaining + 's</strong>...');
            }, 1000);
        }

        function consultarCnpj(cnpj, forceRefresh) {
            if (!cnpjValidateUrl) {
                return;
            }

            setCnpjStatus('loading', '');
            cnpjValidated = false;
            updateSubmitState();

            $.ajax({
                url: cnpjValidateUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    cnpj: cnpj,
                    force_refresh: forceRefresh ? 1 : 0,
                    form_key: $form.find('input[name="form_key"]').val()
                }
            }).done(function (response) {
                var $companyData = $field('#cnpj-company-data');
                var errorMsg;
                var sourceLabel;
                var cepClean;
                var $badge;

                if (response.rate_limited) {
                    startRateLimitCountdown(response.retry_after || 60);
                    return;
                }

                if (response.cnpj_duplicate) {
                    setCnpjStatus(
                        'error',
                        response.message + ' <a href="' + loginUrl + '" class="cnpj-login-link">Fazer login</a>'
                    );
                    slideHide($companyData, 200);
                    cnpjValidated = false;
                    updateSubmitState();
                    return;
                }

                if (response.success) {
                    if (response.api_unavailable) {
                        setCnpjStatus('success', 'CNPJ valido (API indisponivel, preencha manualmente).');
                        slideHide($companyData, 200);
                        cnpjValidated = true;
                        updateSubmitState();
                        updateProgressCompletionState();
                        return;
                    }

                    setCnpjStatus('success', 'CNPJ valido - Empresa ativa');
                    cnpjValidated = true;
                    updateSubmitState();
                    updateProgressCompletionState();

                    if (response.razao_social) {
                        $field('#razao_social').val(response.razao_social).addClass('autofilled');
                    }

                    if (response.nome_fantasia) {
                        $field('#nome_fantasia').val(response.nome_fantasia).addClass('autofilled');
                    }

                    if (response.telefone) {
                        $field('#cnpj-rf-telefone').text(response.telefone);
                        showBlock($field('#cnpj-rf-telefone-row'));
                    } else {
                        $field('#cnpj-rf-telefone').text('Nao informado na RF');
                        showBlock($field('#cnpj-rf-telefone-row'));
                    }

                    if (response.email) {
                        $field('#cnpj-rf-email').text(response.email);
                        showBlock($field('#cnpj-rf-email-row'));
                    } else {
                        $field('#cnpj-rf-email').text('Nao informado na RF');
                        showBlock($field('#cnpj-rf-email-row'));
                    }

                    showBlock($field('#cnpj-rf-contact-note'));

                    if (response.cep) {
                        cepClean = response.cep.replace(/\D/g, '');
                        if (cepClean.length > 5) {
                            cepClean = cepClean.substring(0, 5) + '-' + cepClean.substring(5, 8);
                        }
                        $field('#cep').val(cepClean).addClass('autofilled');
                    }

                    if (response.logradouro) {
                        $field('#logradouro').val(response.logradouro).addClass('autofilled');
                    }

                    if (response.numero) {
                        $field('#numero').val(response.numero).addClass('autofilled');
                    }

                    if (response.complemento) {
                        $field('#complemento').val(response.complemento).addClass('autofilled');
                    }

                    if (response.bairro) {
                        $field('#bairro').val(response.bairro).addClass('autofilled');
                    }

                    if (response.municipio) {
                        $field('#municipio').val(response.municipio).addClass('autofilled');
                    }

                    if (response.uf) {
                        $field('#uf').val(response.uf).addClass('autofilled');
                    }

                    sourceLabel = 'Dados da Receita Federal';
                    if (response.source === 'cache') {
                        sourceLabel = 'Dados da Receita Federal (cache)';
                    }
                    $field('#cnpj-source-badge').text(sourceLabel);

                    if (response.situacao) {
                        $field('#cnpj-situacao')
                            .text(response.situacao)
                            .attr('class', 'cnpj-situacao situacao-' + response.situacao.toLowerCase());
                    }

                    if (response.atividade_principal) {
                        $field('#cnpj-atividade').text(response.atividade_principal);
                    }

                    if (response.cnae_profile && response.cnae_profile_label && response.cnae_profile !== 'off_profile') {
                        $badge = $field('#cnae-profile-badge');
                        $badge.text(response.cnae_profile_label)
                            .removeClass('cnae-direct cnae-adjacent')
                            .addClass('cnae-' + response.cnae_profile)
                            .removeClass('is-hidden')
                            .attr('aria-hidden', 'false')
                            .show();
                    } else {
                        hideBlock($field('#cnae-profile-badge'));
                    }

                    slideShow($companyData, 300);

                    erpEmailFull = null;
                    erpEmailMasked = null;
                    if (response.erp_found && response.erp_email) {
                        erpEmailMasked = response.erp_email;
                        erpEmailFull = response.erp_email_full || null;
                        showErpEmailAlert();
                    } else {
                        hideBlock($field('#erp-email-alert').empty());
                    }

                    return;
                }

                errorMsg = response.message || 'CNPJ invalido';

                if (response.situacao && response.situacao.toUpperCase() !== 'ATIVA') {
                    errorMsg += ' <button type="button" class="cnpj-retry-btn" id="cnpj-force-refresh">' +
                        'Consultar novamente (dados podem estar desatualizados)</button>';
                }

                setCnpjStatus('error', errorMsg);
                slideHide($companyData, 200);
                cnpjValidated = false;
                updateSubmitState();
                updateProgressCompletionState();
                refreshFieldAndSectionErrorStates();
            }).fail(function () {
                setCnpjStatus('error', 'Erro ao consultar. Tente novamente.');
                cnpjValidated = false;
                updateSubmitState();
                updateProgressCompletionState();
                refreshFieldAndSectionErrorStates();
            });
        }

        $field('#cnpj').on('input', function () {
            var $input = $(this);
            var digits;

            trackLeadStart();

            $input.val(maskCnpj($input.val() || ''));
            digits = ($input.val() || '').replace(/\D/g, '');

            if (digits.length === 14 && digits !== lastCnpj) {
                lastCnpj = digits;
                clearTimeout(cnpjTimer);
                cnpjTimer = setTimeout(function () {
                    consultarCnpj(digits);
                }, 500);
                return;
            }

            if (digits.length < 14) {
                lastCnpj = '';
                resetCnpjStatus();
            }
        });

        $field('#phone').on('input', function () {
            var $input = $(this);
            trackLeadStart();
            $input.val(maskPhone($input.val() || ''));
        });

        $field('#cep').on('input', function () {
            var $input = $(this);
            $input.val(maskCep($input.val() || ''));
        });

        $field('#email').on('input blur', function () {
            trackLeadStart();

            if (erpEmailMasked) {
                showErpEmailAlert();
            }

            updateEmailCheckAlert();
        });

        $field('#password, #password_confirmation').on('input blur', updateEmailCheckAlert);

        $field('#razao_social, #nome_fantasia, #inscricao_estadual, #firstname, #lastname')
            .on('input blur', trackLeadStart);

        $form.on('input change blur', 'input, select, textarea', function () {
            updateProgressCompletionState();
            window.setTimeout(refreshFieldAndSectionErrorStates, 0);
        });

        $form.on('focusin', '.form-section :input, .form-section [contenteditable]', function () {
            syncProgressFromFocus($(this));
        });

        $form.on('click', '.b2b-register-progress .progress-step', function (event) {
            var $step = $(this);
            var targetSelector = $step.attr('data-step-target');
            var stepNumber = $step.attr('data-step-number');
            var $section = targetSelector ? $form.find(targetSelector) : $();

            event.preventDefault();

            if (!$section.length) {
                return;
            }

            setActiveProgressStep(stepNumber);
            openStepSection($section, true, true);
            scrollToSection($section);
            focusFirstFieldInSection($section);
        });

        $form.on('click', '.form-section__toggle', function (event) {
            var $toggle = $(this);
            var $section = $toggle.closest('.form-section');
            var stepNumber = parseInt($section.data('step'), 10);

            event.preventDefault();

            if (!$section.length || !isCompactBenefitsViewport()) {
                return;
            }

            if (stepNumber) {
                setActiveProgressStep(stepNumber);
            }

            openStepSection($section, true, true);
            scrollToSection($section);
        });

        if ($benefitsToggle.length && $benefitsPanel.length) {
            $benefitsToggle.on('click', function (event) {
                var isExpanded;

                event.preventDefault();

                if (!isCompactBenefitsViewport()) {
                    return;
                }

                isExpanded = $benefitsToggle.attr('aria-expanded') === 'true';
                setBenefitsExpanded(!isExpanded, true);
            });

            if (benefitsViewportMedia) {
                if (typeof benefitsViewportMedia.addEventListener === 'function') {
                    benefitsViewportMedia.addEventListener('change', function () {
                        syncBenefitsDisclosure();
                        syncStepSectionsDisclosure();
                        scheduleProgressViewportSync();
                    });
                } else if (typeof benefitsViewportMedia.addListener === 'function') {
                    benefitsViewportMedia.addListener(function () {
                        syncBenefitsDisclosure();
                        syncStepSectionsDisclosure();
                        scheduleProgressViewportSync();
                    });
                }
            } else {
                $(window).on('resize.b2bBenefitsDisclosure', function () {
                    syncBenefitsDisclosure();
                    syncStepSectionsDisclosure();
                    scheduleProgressViewportSync();
                });
            }
        }

        $(window).on('scroll.b2bRegisterProgress resize.b2bRegisterProgress', scheduleProgressViewportSync);

        $(document)
            .off('click.b2bRegisterForceRefresh', '#cnpj-force-refresh')
            .on('click.b2bRegisterForceRefresh', '#cnpj-force-refresh', function (event) {
                var digits;

                event.preventDefault();
                digits = ($field('#cnpj').val() || '').replace(/\D/g, '');

                if (digits.length === 14) {
                    consultarCnpj(digits, true);
                }
            });

        $form.find('input').on('focus', function () {
            $(this).removeClass('autofilled');
        });

        $form.on('submit', function (event) {
            var $submitButton;

            if ($form.data('isSubmitting')) {
                event.preventDefault();
                return false;
            }

            if (isCompactBenefitsViewport()) {
                expandAllStepSections(false);
            }

            if (typeof $form.validation === 'function' && !$form.validation('isValid')) {
                window.setTimeout(function () {
                    refreshFieldAndSectionErrorStates();
                    focusFirstInvalidField();
                }, 0);
                return true;
            }

            $form.data('isSubmitting', true).addClass('is-submitting');
            $submitButton = $form.find('.actions-toolbar .action.submit.primary');
            $submitButton.addClass('is-loading').prop('disabled', true);
            $submitButton.find('span').text(sendingLabel);

            return true;
        });

        window.setTimeout(function () {
            $form.find('.benefit-item').each(function () {
                $(this).addClass('benefit-animate');
            });
        }, 100);

        initStepSectionAccordionMarkup();
        initRegisterPasswordToggles();
        setActiveProgressStep(1);
        syncBenefitsDisclosure();
        syncStepSectionsDisclosure();
        updateEmailCheckAlert();
        updateSubmitState();
        updateProgressCompletionState();
        refreshFieldAndSectionErrorStates();
        scheduleProgressViewportSync();
    };
});
