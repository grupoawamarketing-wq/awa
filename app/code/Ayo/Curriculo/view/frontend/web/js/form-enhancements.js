/**
 * AWA Motos - Form Enhancements
 * Otimizações e validações de formulários
 * 
 * @version 1.0.0
 * @author Grupo Awamotos
 */
define([
    'jquery',
    'domReady!'
], function ($) {
    'use strict';

    return function (config) {
        var settings = $.extend({
            selectors: {
                form: 'form',
                input: 'input, select, textarea',
                submit: 'button[type="submit"], input[type="submit"]',
                qty: '.qty-wrapper, .control.qty',
                password: 'input[type="password"]',
                cpf: 'input[name*="cpf"], input[name*="taxvat"], .cpf-input',
                cnpj: 'input[name*="cnpj"], .cnpj-input',
                cep: 'input[name*="postcode"], input[name*="cep"], .cep-input',
                phone: 'input[name*="telephone"], input[name*="phone"], .phone-input',
                url: 'input[type="url"], .url-input',
                file: 'input[type="file"]',
                counter: '[data-show-counter="true"]',
                status: '.form-status',
                successPanel: '.curriculo-success-panel'
            },
            options: {
                autoFormat: true,
                showPasswordToggle: true,
                floatingLabels: false,
                liveValidation: true,
                urlAutoFix: false,
                docValidation: false,
                phoneValidation: false,
                formStatus: false,
                fileValidation: false,
                charCounter: false,
                successScroll: false,
                successParam: 'success',
                cleanSuccessParam: false,
                analytics: false,
                analyticsForm: 'form',
                trackFillTime: false,
                draftSave: false,
                draftKey: null,
                draftTtlHours: 168,
                draftRestoreMode: 'prompt',
                draftExcludeSelector: '[data-no-draft="true"], input[type="file"], input[type="password"], input[type="hidden"]',
                submitLock: false,
                submitLockText: 'Enviando...',
                submitLoading: true,
                debounceDelay: 300
            }
        }, config || {});

        /**
         * Máscara de CPF (000.000.000-00)
         */
        function maskCPF(value) {
            return value
                .replace(/\D/g, '')
                .replace(/(\d{3})(\d)/, '$1.$2')
                .replace(/(\d{3})(\d)/, '$1.$2')
                .replace(/(\d{3})(\d{1,2})/, '$1-$2')
                .replace(/(-\d{2})\d+?$/, '$1');
        }

        /**
         * Máscara de CNPJ (00.000.000/0000-00)
         */
        function maskCNPJ(value) {
            return value
                .replace(/\D/g, '')
                .replace(/(\d{2})(\d)/, '$1.$2')
                .replace(/(\d{3})(\d)/, '$1.$2')
                .replace(/(\d{3})(\d)/, '$1/$2')
                .replace(/(\d{4})(\d{1,2})/, '$1-$2')
                .replace(/(-\d{2})\d+?$/, '$1');
        }

        /**
         * Máscara de CEP (00000-000)
         */
        function maskCEP(value) {
            return value
                .replace(/\D/g, '')
                .replace(/(\d{5})(\d)/, '$1-$2')
                .replace(/(-\d{3})\d+?$/, '$1');
        }

        /**
         * Máscara de telefone ((00) 00000-0000)
         */
        function maskPhone(value) {
            var digits = value.replace(/\D/g, '');
            
            if (digits.length <= 10) {
                // Telefone fixo
                return digits
                    .replace(/(\d{2})(\d)/, '($1) $2')
                    .replace(/(\d{4})(\d)/, '$1-$2')
                    .replace(/(-\d{4})\d+?$/, '$1');
            } else {
                // Celular
                return digits
                    .replace(/(\d{2})(\d)/, '($1) $2')
                    .replace(/(\d{5})(\d)/, '$1-$2')
                    .replace(/(-\d{4})\d+?$/, '$1');
            }
        }

        /**
         * Aplicar máscaras brasileiras
         */
        function applyBrazilianMasks() {
            // CPF
            $(settings.selectors.cpf).on('input', function () {
                this.value = maskCPF(this.value);
            });

            // CNPJ
            $(settings.selectors.cnpj).on('input', function () {
                this.value = maskCNPJ(this.value);
            });

            // CEP
            $(settings.selectors.cep).on('input', function () {
                this.value = maskCEP(this.value);
            });

            // Telefone
            $(settings.selectors.phone).on('input', function () {
                this.value = maskPhone(this.value);
            });
        }

        /**
         * Toggle de visibilidade de senha
         */
        function setupPasswordToggle() {
            $(settings.selectors.password).each(function () {
                var $input = $(this);
                var $wrapper = $input.parent();
                
                // Não adicionar se já existe
                if ($wrapper.find('.password-toggle').length) return;
                
                // Criar botão toggle
                var $toggle = $('<button type="button" class="password-toggle" aria-label="Mostrar senha">' +
                    '<span class="show-icon">👁</span>' +
                    '<span class="hide-icon" style="display:none;">👁‍🗨</span>' +
                    '</button>');
                
                // Estilo inline para posicionamento
                $wrapper.css('position', 'relative');
                $toggle.css({
                    'position': 'absolute',
                    'right': '12px',
                    'top': '50%',
                    'transform': 'translateY(-50%)',
                    'background': 'none',
                    'border': 'none',
                    'cursor': 'pointer',
                    'padding': '5px',
                    'font-size': '16px'
                });
                $input.css('padding-right', '45px');
                
                // Evento de toggle
                $toggle.on('click', function () {
                    var type = $input.attr('type') === 'password' ? 'text' : 'password';
                    $input.attr('type', type);
                    
                    $toggle.find('.show-icon').toggle(type === 'password');
                    $toggle.find('.hide-icon').toggle(type === 'text');
                    $toggle.attr('aria-label', type === 'password' ? 'Mostrar senha' : 'Ocultar senha');
                });
                
                $wrapper.append($toggle);
            });
        }

        /**
         * Controles de quantidade (+/-)
         */
        function setupQuantityControls() {
            $(settings.selectors.qty).each(function () {
                var $wrapper = $(this);
                var $input = $wrapper.find('input[type="number"], input.qty');
                
                // Verificar se já tem botões
                if ($wrapper.find('.qty-decrease').length) return;
                
                var min = parseInt($input.attr('min'), 10) || 1;
                var max = parseInt($input.attr('max'), 10) || 9999;
                var step = parseInt($input.attr('step'), 10) || 1;
                
                // Criar botões
                var $decrease = $('<button type="button" class="qty-decrease" aria-label="Diminuir quantidade">−</button>');
                var $increase = $('<button type="button" class="qty-increase" aria-label="Aumentar quantidade">+</button>');
                
                $decrease.on('click', function () {
                    var val = parseInt($input.val(), 10) || min;
                    if (val > min) {
                        $input.val(val - step).trigger('change');
                    }
                });
                
                $increase.on('click', function () {
                    var val = parseInt($input.val(), 10) || min;
                    if (val < max) {
                        $input.val(val + step).trigger('change');
                    }
                });
                
                // Inserir botões
                $input.before($decrease);
                $input.after($increase);
            });
        }

        /**
         * Floating labels
         */
        function setupFloatingLabels() {
            if (!settings.options.floatingLabels) return;
            
            $(settings.selectors.form).find('.field').each(function () {
                var $field = $(this);
                var $input = $field.find('input, select, textarea');
                var $label = $field.find('label');
                
                if (!$input.length || !$label.length) return;
                
                $field.addClass('floating-label');
                
                // Verificar se tem valor inicial
                if ($input.val()) {
                    $field.addClass('has-value');
                }
                
                $input.on('focus', function () {
                    $field.addClass('is-focused');
                });
                
                $input.on('blur', function () {
                    $field.removeClass('is-focused');
                    $field.toggleClass('has-value', !!$(this).val());
                });
            });
        }

        /**
         * Validação em tempo real
         */
        function setupLiveValidation() {
            if (!settings.options.liveValidation) return;
            
            var debounceTimer;
            
            $(settings.selectors.input).on('input change', function () {
                var $input = $(this);
                var $field = $input.closest('.field, .form-group');
                
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(function () {
                    validateField($input, $field);
                }, settings.options.debounceDelay);
            });
        }

        /**
         * Validar campo individual
         */
        function validateField($input, $field) {
            var value = $input.val();
            var isValid = true;
            var errorMessage = '';
            
            // Required
            if ($input.prop('required') && !value) {
                isValid = false;
                errorMessage = 'Este campo é obrigatório';
            }
            
            // Email
            if ($input.attr('type') === 'email' && value) {
                var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(value)) {
                    isValid = false;
                    errorMessage = 'Email inválido';
                }
            }
            
            // CPF
            if ($input.is(settings.selectors.cpf) && value) {
                if (!validateCPF(value)) {
                    isValid = false;
                    errorMessage = 'CPF inválido';
                }
            }
            
            // CNPJ
            if ($input.is(settings.selectors.cnpj) && value) {
                if (!validateCNPJ(value)) {
                    isValid = false;
                    errorMessage = 'CNPJ inválido';
                }
            }
            
            // CEP
            if ($input.is(settings.selectors.cep) && value) {
                var cepClean = value.replace(/\D/g, '');
                if (cepClean.length !== 8) {
                    isValid = false;
                    errorMessage = 'CEP deve ter 8 dígitos';
                }
            }
            
            // Atualizar UI
            $field.removeClass('_error _valid has-error has-success');
            $field.find('.field-error').remove();
            
            if (value) {
                if (isValid) {
                    $field.addClass('_valid has-success');
                } else {
                    $field.addClass('_error has-error');
                    $field.find('.control').append('<div class="field-error">' + errorMessage + '</div>');
                }
            }
            
            return isValid;
        }

        /**
         * Validar CPF
         */
        function validateCPF(cpf) {
            cpf = cpf.replace(/\D/g, '');
            
            if (cpf.length !== 11) return false;
            if (/^(\d)\1+$/.test(cpf)) return false;
            
            var sum = 0;
            for (var i = 0; i < 9; i++) {
                sum += parseInt(cpf.charAt(i), 10) * (10 - i);
            }
            var remainder = (sum * 10) % 11;
            if (remainder === 10 || remainder === 11) remainder = 0;
            if (remainder !== parseInt(cpf.charAt(9), 10)) return false;
            
            sum = 0;
            for (var j = 0; j < 10; j++) {
                sum += parseInt(cpf.charAt(j), 10) * (11 - j);
            }
            remainder = (sum * 10) % 11;
            if (remainder === 10 || remainder === 11) remainder = 0;
            
            return remainder === parseInt(cpf.charAt(10), 10);
        }

        /**
         * Validar CNPJ
         */
        function validateCNPJ(cnpj) {
            cnpj = cnpj.replace(/\D/g, '');
            
            if (cnpj.length !== 14) return false;
            if (/^(\d)\1+$/.test(cnpj)) return false;
            
            var size = cnpj.length - 2;
            var numbers = cnpj.substring(0, size);
            var digits = cnpj.substring(size);
            var sum = 0;
            var pos = size - 7;
            
            for (var i = size; i >= 1; i--) {
                sum += parseInt(numbers.charAt(size - i), 10) * pos--;
                if (pos < 2) pos = 9;
            }
            
            var result = sum % 11 < 2 ? 0 : 11 - (sum % 11);
            if (result !== parseInt(digits.charAt(0), 10)) return false;
            
            size = size + 1;
            numbers = cnpj.substring(0, size);
            sum = 0;
            pos = size - 7;
            
            for (var j = size; j >= 1; j--) {
                sum += parseInt(numbers.charAt(size - j), 10) * pos--;
                if (pos < 2) pos = 9;
            }
            
            result = sum % 11 < 2 ? 0 : 11 - (sum % 11);
            
            return result === parseInt(digits.charAt(1), 10);
        }

        /**
         * Submit com loading
         */
        function setupSubmitLoading() {
            $(settings.selectors.form).on('submit', function () {
                var $form = $(this);
                var $submit = $form.find(settings.selectors.submit);
                
                // Adicionar estado de loading
                $submit.addClass('loading');
                $submit.prop('disabled', true);
                
                // Timeout para reabilitar (fallback)
                setTimeout(function () {
                    $submit.removeClass('loading');
                    $submit.prop('disabled', false);
                }, 10000);
            });
        }

        /**
         * Busca CEP automática
         */
        function setupCEPLookup() {
            $(settings.selectors.cep).on('blur', function () {
                var $input = $(this);
                var cep = $input.val().replace(/\D/g, '');
                
                if (cep.length !== 8) return;
                
                // Indicar carregamento
                $input.addClass('loading');
                
                // Buscar via ViaCEP
                $.ajax({
                    url: 'https://viacep.com.br/ws/' + cep + '/json/',
                    dataType: 'json',
                    timeout: 5000,
                    success: function (data) {
                        if (data.erro) {
                            console.warn('[AWA] CEP não encontrado:', cep);
                            return;
                        }
                        
                        // Preencher campos
                        var $form = $input.closest('form');
                        
                        $form.find('input[name*="street"][name*="[0]"], input[name="street[]"]:first').val(data.logradouro);
                        $form.find('input[name*="street"][name*="[2]"], input[name="street[]"]:eq(2)').val(data.bairro);
                        $form.find('input[name*="city"]').val(data.localidade);
                        
                        // Selecionar estado
                        var $regionSelect = $form.find('select[name*="region"]');
                        if ($regionSelect.length) {
                            $regionSelect.find('option').each(function () {
                                if ($(this).text().indexOf(data.uf) > -1 || $(this).val() === data.uf) {
                                    $regionSelect.val($(this).val()).trigger('change');
                                }
                            });
                        } else {
                            var $regionInput = $form.find('input[name*="state"], input[name*="region"]');
                            if ($regionInput.length) {
                                $regionInput.val(data.uf);
                            }
                        }
                        
                        console.log('[AWA] CEP preenchido:', data);
                    },
                    error: function () {
                        console.warn('[AWA] Erro ao buscar CEP');
                    },
                    complete: function () {
                        $input.removeClass('loading');
                    }
                });
            });
        }

        /**
         * Auto completar protocolo em URLs
         */
        function setupUrlAutoFix() {
            $(settings.selectors.url).on('blur', function () {
                var $input = $(this);
                var value = ($input.val() || '').trim();

                if (!value) {
                    return;
                }

                if (/^(https?|ftp):\/\//i.test(value)) {
                    $input.val(value);
                    return;
                }

                if (/^www\./i.test(value)) {
                    $input.val('https://' + value);
                    return;
                }

                $input.val('https://' + value);
            });
        }

        /**
         * Validação de arquivos (tamanho/extensão)
         */
        function setupFileValidation() {
            $(settings.selectors.file).on('change', function () {
                var $input = $(this);
                var $field = $input.closest('.field, .form-group');
                var $control = $input.closest('.control');
                var maxSizeMb = parseFloat($input.data('max-size-mb') || 0);
                var allowedExt = String($input.data('allowed-ext') || '')
                    .toLowerCase()
                    .split(',')
                    .map(function (item) { return $.trim(item); })
                    .filter(Boolean);
                var file = this.files && this.files[0] ? this.files[0] : null;

                if (!$control.length) {
                    $control = $field;
                }

                clearFieldError($field, $control, $input);

                if (!file) {
                    return;
                }

                var errors = [];
                if (maxSizeMb > 0 && file.size > (maxSizeMb * 1024 * 1024)) {
                    errors.push('O arquivo excede o tamanho máximo de ' + maxSizeMb + ' MB.');
                }

                if (allowedExt.length) {
                    var parts = file.name.split('.');
                    var ext = parts.length > 1 ? parts.pop().toLowerCase() : '';
                    if (!ext || allowedExt.indexOf(ext) === -1) {
                        errors.push('Formato inválido. Envie: ' + allowedExt.join(', ').toUpperCase() + '.');
                    }
                }

                if (errors.length) {
                    showFieldError($field, $control, errors[0], $input);
                    $input.val('');
                }
            });
        }

        /**
         * Validação de CPF/CNPJ no blur
         */
        function setupDocumentValidation() {
            $(settings.selectors.cpf + ',' + settings.selectors.cnpj).on('blur', function () {
                var $input = $(this);
                var $field = $input.closest('.field, .form-group');
                var $control = $input.closest('.control');
                var value = ($input.val() || '').trim();

                if (!$control.length) {
                    $control = $field;
                }

                clearFieldError($field, $control, $input);

                if (!value) {
                    return;
                }

                if ($input.is(settings.selectors.cpf) && !validateCPF(value)) {
                    showFieldError($field, $control, 'CPF inválido.', $input);
                    return;
                }

                if ($input.is(settings.selectors.cnpj) && !validateCNPJ(value)) {
                    showFieldError($field, $control, 'CNPJ inválido.', $input);
                }
            });
        }

        /**
         * Validação de telefone (10 ou 11 dígitos)
         */
        function setupPhoneValidation() {
            $(settings.selectors.phone).on('blur', function () {
                var $input = $(this);
                var $field = $input.closest('.field, .form-group');
                var $control = $input.closest('.control');
                var value = ($input.val() || '').trim();

                if (!$control.length) {
                    $control = $field;
                }

                clearFieldError($field, $control, $input);

                if (!value) {
                    return;
                }

                var digits = value.replace(/\D/g, '');
                if (digits.length !== 10 && digits.length !== 11) {
                    showFieldError($field, $control, 'Telefone deve ter 10 ou 11 dígitos.', $input);
                }
            });
        }

        /**
         * Contador de caracteres
         */
        function setupCharCounter() {
            $(settings.selectors.counter).each(function () {
                var $input = $(this);
                var maxLength = parseInt($input.attr('maxlength'), 10);
                if (!maxLength) {
                    return;
                }

                var counterId = $input.attr('id') ? ($input.attr('id') + '-counter') : ('counter-' + Math.random().toString(36).slice(2));
                var $counter = $('<div class="input-counter" id="' + counterId + '" aria-live="polite"></div>');
                var $control = $input.closest('.control').length ? $input.closest('.control') : $input.parent();

                $input.attr('aria-describedby', appendDescribedBy($input.attr('aria-describedby'), counterId));
                $control.append($counter);

                var update = function () {
                    var length = ($input.val() || '').length;
                    $counter.text(length + '/' + maxLength);
                };

                $input.on('input', update);
                update();
            });
        }

        function appendDescribedBy(current, id) {
            if (!current) {
                return id;
            }
            var parts = current.split(' ');
            if (parts.indexOf(id) === -1) {
                parts.push(id);
            }
            return parts.join(' ');
        }

        function showFieldError($field, $control, message, $input) {
            $field.addClass('_error has-error');
            $control.find('.mage-error[data-generated="true"]').remove();
            var errorId = null;
            if ($input && $input.length) {
                errorId = $input.data('error-id');
                if (!errorId) {
                    var baseId = $input.attr('id') || $input.attr('name') || ('field-' + Math.random().toString(36).slice(2));
                    errorId = baseId + '-error';
                    $input.data('error-id', errorId);
                }
            } else {
                errorId = 'field-error-' + Math.random().toString(36).slice(2);
            }

            $control.append('<div class="mage-error" id="' + errorId + '" data-generated="true" role="alert">' + message + '</div>');

            if ($input && $input.length) {
                var describedBy = appendDescribedBy($input.attr('aria-describedby'), errorId);
                $input.attr('aria-describedby', describedBy);
                $input.attr('aria-invalid', 'true');
            }
        }

        function clearFieldError($field, $control, $input) {
            $field.removeClass('_error has-error');
            $control.find('.mage-error[data-generated="true"]').remove();
            if ($input && $input.length) {
                var errorId = $input.data('error-id');
                if (errorId) {
                    var describedBy = removeDescribedBy($input.attr('aria-describedby'), errorId);
                    if (describedBy) {
                        $input.attr('aria-describedby', describedBy);
                    } else {
                        $input.removeAttr('aria-describedby');
                    }
                    $input.removeData('error-id');
                }
                $input.removeAttr('aria-invalid');
            }
        }

        function removeDescribedBy(current, id) {
            if (!current) {
                return '';
            }
            var parts = current.split(' ').filter(function (item) {
                return item && item !== id;
            });
            return parts.join(' ');
        }

        /**
         * Status de formulário e foco no primeiro erro
         */
        function setupFormStatus() {
            $(settings.selectors.form).on('invalid-form.validate', function () {
                var $form = $(this);
                var $status = $form.find(settings.selectors.status).first();
                if ($status.length) {
                    $status.text('Revise os campos destacados.');
                }

                var $input = $form.find('.mage-error')
                    .closest('.field, .form-group')
                    .find('input, select, textarea')
                    .first();
                if (!$input.length) {
                    $input = $form.find('.validation-failed').first();
                }
                if ($input.length) {
                    $input.trigger('focus');
                }

                pushDataLayer('form_submit_error', {
                    form: settings.options.analyticsForm,
                    error_count: $form.find('.mage-error').length
                }, $form);

                if (settings.options.submitLock) {
                    unlockSubmit($form);
                }
            });

            $(settings.selectors.form).on('submit', function () {
                var $form = $(this);
                var $status = $form.find(settings.selectors.status).first();
                if ($status.length) {
                    $status.text('');
                }
                pushDataLayer('form_submit_attempt', {
                    form: settings.options.analyticsForm
                }, $form);

                if (settings.options.submitLock) {
                    lockSubmit($form);
                }
            });
        }

        /**
         * Scroll para sucesso e analytics
         */
        function handleSuccessState() {
            var hasSuccessParam = getQueryParam(settings.options.successParam) === '1';
            var $panel = $(settings.selectors.successPanel).first();

            if (hasSuccessParam) {
                pushDataLayer('form_submit_success', {
                    form: settings.options.analyticsForm
                });
                if (settings.options.cleanSuccessParam) {
                    removeQueryParam(settings.options.successParam);
                }
                if (settings.options.draftSave) {
                    clearDraft();
                }
            }

            if (!settings.options.successScroll || !$panel.length) {
                return;
            }

            setTimeout(function () {
                try {
                    $panel[0].scrollIntoView({behavior: 'smooth', block: 'start'});
                } catch (e) {
                    window.scrollTo(0, $panel.offset().top - 20);
                }
                if ($panel.is('[tabindex]')) {
                    $panel.trigger('focus');
                }
            }, 50);
        }

        function removeQueryParam(param) {
            if (!window.history || !window.history.replaceState) {
                return;
            }
            var url = window.location.href;
            var baseUrl = url.split('?')[0];
            var query = window.location.search.substring(1);
            if (!query) {
                return;
            }
            var parts = query.split('&').filter(function (item) {
                return item && decodeURIComponent(item.split('=')[0]) !== param;
            });
            var newUrl = baseUrl + (parts.length ? '?' + parts.join('&') : '') + window.location.hash;
            window.history.replaceState({}, document.title, newUrl);
        }

        function getQueryParam(name) {
            var search = window.location.search || '';
            if (!search) {
                return '';
            }
            var params = search.substring(1).split('&');
            for (var i = 0; i < params.length; i++) {
                var pair = params[i].split('=');
                if (decodeURIComponent(pair[0]) === name) {
                    return decodeURIComponent(pair[1] || '');
                }
            }
            return '';
        }

        function pushDataLayer(eventName, payload, $contextForm) {
            if (!settings.options.analytics) {
                return;
            }
            if ($contextForm && $contextForm.length) {
                var targetId = ($contextForm.attr('id') || '') + '';
                if (targetId && targetId !== settings.options.analyticsForm) {
                    return;
                }
            }
            window.dataLayer = window.dataLayer || [];
            var data = $.extend({
                event: eventName
            }, payload || {});
            window.dataLayer.push(data);
        }

        /**
         * Draft autosave
         */
        function setupDraftSave() {
            if (!storageAvailable('localStorage')) {
                return;
            }

            var $form = $(settings.selectors.form).first();
            if (!$form.length) {
                return;
            }

            var key = getDraftKey($form);
            var draft = loadDraft(key);

            if (draft && draft.values) {
                if (settings.options.draftRestoreMode === 'auto') {
                    applyDraft($form, draft.values);
                } else {
                    showDraftPrompt($form, draft.values, key);
                }
            }

            var saveDraftDebounced = debounce(function () {
                saveDraft($form, key);
            }, settings.options.debounceDelay);

            $form.on('input change', 'input, select, textarea', function () {
                if ($(this).is(settings.options.draftExcludeSelector)) {
                    return;
                }
                saveDraftDebounced();
            });
        }

        function lockSubmit($form) {
            var $submit = $form.find(settings.selectors.submit).first();
            if (!$submit.length || $submit.prop('disabled')) {
                return;
            }
            var originalText = $submit.data('original-text');
            if (!originalText) {
                $submit.data('original-text', $.trim($submit.text()));
            }
            $form.addClass('is-submitting');
            $submit.addClass('is-loading');
            if (settings.options.submitLockText) {
                $submit.find('span').text(settings.options.submitLockText);
            }
            $submit.prop('disabled', true);
        }

        function unlockSubmit($form) {
            var $submit = $form.find(settings.selectors.submit).first();
            if (!$submit.length) {
                return;
            }
            var originalText = $submit.data('original-text');
            $form.removeClass('is-submitting');
            $submit.removeClass('is-loading');
            if (originalText) {
                $submit.find('span').text(originalText);
            }
            $submit.prop('disabled', false);
        }

        function getDraftKey($form) {
            if (settings.options.draftKey) {
                return settings.options.draftKey;
            }
            var formId = $form.attr('id') || 'form';
            return 'draft:' + formId + ':' + window.location.pathname;
        }

        function loadDraft(key) {
            try {
                var raw = window.localStorage.getItem(key);
                if (!raw) {
                    return null;
                }
                var data = JSON.parse(raw);
                if (!data || !data.ts) {
                    return null;
                }
                var ttlMs = (settings.options.draftTtlHours || 0) * 60 * 60 * 1000;
                if (ttlMs && (Date.now() - data.ts > ttlMs)) {
                    window.localStorage.removeItem(key);
                    return null;
                }
                return data;
            } catch (e) {
                return null;
            }
        }

        function saveDraft($form, key) {
            try {
                var payload = {
                    ts: Date.now(),
                    values: collectDraft($form)
                };
                window.localStorage.setItem(key, JSON.stringify(payload));
                showDraftSaved($form);
            } catch (e) {
                // ignore storage errors
            }
        }

        function clearDraft() {
            if (!storageAvailable('localStorage')) {
                return;
            }
            var $form = $(settings.selectors.form).first();
            if (!$form.length) {
                return;
            }
            var key = getDraftKey($form);
            try {
                window.localStorage.removeItem(key);
            } catch (e) {
                // ignore
            }
        }

        function collectDraft($form) {
            var values = {};
            $form.find('input, select, textarea').each(function () {
                var $field = $(this);
                if ($field.is(settings.options.draftExcludeSelector)) {
                    return;
                }
                var key = $field.attr('name') || $field.attr('id');
                if (!key) {
                    return;
                }
                if ($field.is(':checkbox')) {
                    values[key] = $field.is(':checked');
                    return;
                }
                if ($field.is(':radio')) {
                    if ($field.is(':checked')) {
                        values[key] = $field.val();
                    }
                    return;
                }
                if ($field.is('select[multiple]')) {
                    values[key] = $field.val() || [];
                    return;
                }
                values[key] = $field.val();
            });
            return values;
        }

        function applyDraft($form, values) {
            $form.find('input, select, textarea').each(function () {
                var $field = $(this);
                if ($field.is(settings.options.draftExcludeSelector)) {
                    return;
                }
                var key = $field.attr('name') || $field.attr('id');
                if (!key || values[key] === undefined) {
                    return;
                }
                if ($field.is(':checkbox')) {
                    $field.prop('checked', !!values[key]);
                    return;
                }
                if ($field.is(':radio')) {
                    if ($field.val() === values[key]) {
                        $field.prop('checked', true);
                    }
                    return;
                }
                $field.val(values[key]);
            });
        }

        function showDraftPrompt($form, values, key) {
            var $status = $form.find(settings.selectors.status).first();
            if (!$status.length) {
                applyDraft($form, values);
                return;
            }
            var $prompt = $('<div class="draft-prompt" role="region" aria-label="Rascunho encontrado">' +
                '<span>Encontramos um rascunho salvo neste navegador.</span>' +
                '<div class="draft-prompt-actions">' +
                '<button type="button" class="action primary draft-restore">Restaurar</button>' +
                '<button type="button" class="action secondary draft-discard">Descartar</button>' +
                '</div>' +
                '</div>');

            $status.empty().append($prompt);

            $prompt.find('.draft-restore').on('click', function () {
                applyDraft($form, values);
                $status.text('Rascunho restaurado.');
            });

            $prompt.find('.draft-discard').on('click', function () {
                try {
                    window.localStorage.removeItem(key);
                } catch (e) {
                    // ignore
                }
                $status.text('Rascunho descartado.');
            });
        }

        var draftSavedTimer = null;
        function showDraftSaved($form) {
            var $status = $form.find(settings.selectors.status).first();
            if (!$status.length || $status.find('.draft-prompt').length) {
                return;
            }
            $status.text('Rascunho salvo no navegador.');
            if (draftSavedTimer) {
                clearTimeout(draftSavedTimer);
            }
            draftSavedTimer = setTimeout(function () {
                $status.text('');
            }, 2500);
        }

        function debounce(fn, wait) {
            var timer;
            return function () {
                var context = this;
                var args = arguments;
                clearTimeout(timer);
                timer = setTimeout(function () {
                    fn.apply(context, args);
                }, wait);
            };
        }

        function storageAvailable(type) {
            try {
                var storage = window[type];
                var testKey = '__storage_test__';
                storage.setItem(testKey, testKey);
                storage.removeItem(testKey);
                return true;
            } catch (e) {
                return false;
            }
        }

        /**
         * Track tempo de preenchimento (sem dados sensíveis)
         */
        function setupFillTimeTracking() {
            var startTime = null;
            var $form = $(settings.selectors.form).first();
            if (!$form.length) {
                return;
            }

            $form.one('focusin', 'input, select, textarea', function () {
                startTime = Date.now();
            });

            $form.on('submit', function () {
                if (!startTime) {
                    return;
                }
                var elapsedMs = Date.now() - startTime;
                pushDataLayer('form_fill_time', {
                    form: settings.options.analyticsForm,
                    time_ms: elapsedMs
                }, $form);
            });
        }

        /**
         * Inicialização
         */

        /**
         * Normalização progressiva do CTA do formulário de currículo.
         * Necessária porque o tema aplica alturas de botão via CSS layerizado
         * e a rota do currículo precisa manter consistência visual em mobile.
         */
        function normalizeCurriculoSubmitButton() {
            var $form = $(settings.selectors.form).first();
            var applyInlineCtaStyles;

            if (!$form.length || !$form.hasClass('curriculo')) {
                return;
            }

            applyInlineCtaStyles = function () {
                var isMobile = window.matchMedia('(max-width: 767px)').matches;
                var targetHeight = isMobile ? '48px' : '52px';
                var $submit = $form.find('.actions-toolbar .primary > .action.submit.primary').first();
                var $primary;

                if (!$submit.length) {
                    $submit = $form.find(settings.selectors.submit).first();
                }

                if (!$submit.length) {
                    return;
                }

                $primary = $submit.parent('.primary');
                if ($primary.length) {
                    $primary.css({
                        width: isMobile ? '100%' : '',
                        height: targetHeight,
                        minHeight: targetHeight,
                        display: 'flex',
                        alignItems: 'stretch'
                    });
                }

                $submit.css({
                    width: isMobile ? '100%' : '',
                    height: targetHeight,
                    minHeight: targetHeight,
                    maxHeight: 'none',
                    borderRadius: '14px',
                    padding: '0 24px',
                    lineHeight: targetHeight,
                    display: 'inline-flex',
                    alignItems: 'center',
                    alignSelf: 'stretch',
                    justifyContent: 'center'
                });
            };

            applyInlineCtaStyles();
            $(window)
                .off('.curriculoCtaNormalize')
                .on(
                    'resize.curriculoCtaNormalize orientationchange.curriculoCtaNormalize',
                    debounce(applyInlineCtaStyles, settings.options.debounceDelay)
                );
        }

        function init() {
            if (settings.options.autoFormat) {
                applyBrazilianMasks();
            }
            
            if (settings.options.showPasswordToggle) {
                setupPasswordToggle();
            }
            
            setupQuantityControls();
            setupFloatingLabels();
            setupLiveValidation();
            if (settings.options.submitLoading) {
                setupSubmitLoading();
            }
            setupCEPLookup();
            if (settings.options.urlAutoFix) {
                setupUrlAutoFix();
            }
            if (settings.options.docValidation) {
                setupDocumentValidation();
            }
            if (settings.options.phoneValidation) {
                setupPhoneValidation();
            }
            if (settings.options.fileValidation) {
                setupFileValidation();
            }
            if (settings.options.charCounter) {
                setupCharCounter();
            }
            if (settings.options.formStatus) {
                setupFormStatus();
            }
            if (settings.options.successScroll || settings.options.analytics) {
                handleSuccessState();
            }
            if (settings.options.trackFillTime) {
                setupFillTimeTracking();
            }
            if (settings.options.draftSave) {
                setupDraftSave();
            }

            normalizeCurriculoSubmitButton();
            
            console.log('[AWA] Form enhancements loaded');
        }

        // Executar
        $(init);

        // API pública
        return {
            init: init,
            maskCPF: maskCPF,
            maskCNPJ: maskCNPJ,
            maskCEP: maskCEP,
            maskPhone: maskPhone,
            setupUrlAutoFix: setupUrlAutoFix,
            validateCPF: validateCPF,
            validateCNPJ: validateCNPJ
        };
    };
});
