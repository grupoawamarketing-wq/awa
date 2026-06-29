/**
 * AWA PDP — copiar SKU para a área de transferência.
 */
define([], function () {
    'use strict';

    return function (config, element) {
        var btn = element;
        if (!btn || !navigator.clipboard) {
            return;
        }

        var textEl = btn.querySelector('.awa-sku-copy-btn__text');
        var feedbackId = btn.getAttribute('data-feedback-id') || 'awa-sku-feedback';
        var feedback = document.getElementById(feedbackId);
        var defaultLabel = textEl ? textEl.textContent : '';

        btn.addEventListener('click', function () {
            var sku = btn.getAttribute('data-sku');
            if (!sku) {
                return;
            }

            navigator.clipboard.writeText(sku).then(function () {
                btn.setAttribute('data-copied', '');
                if (textEl) {
                    textEl.textContent = 'Copiado!';
                }
                if (feedback) {
                    feedback.textContent = 'SKU copiado';
                    feedback.classList.add('visible');
                }
                window.setTimeout(function () {
                    btn.removeAttribute('data-copied');
                    if (textEl) {
                        textEl.textContent = defaultLabel;
                    }
                    if (feedback) {
                        feedback.textContent = '';
                        feedback.classList.remove('visible');
                    }
                }, 2000);
            });
        });
    };
});
