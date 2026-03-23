/**
 * AWA Motos — Quote Modal
 * RequireJS widget: abre/fecha modal de cotação rápida com event delegation.
 * Inicializado via data-mage-init no container #awa-quote-fab.
 */
define([], function () {
    'use strict';

    return function (config, element) {
        const modalId = config.modalId || 'awa-quote-modal';
        const modal = document.getElementById(modalId);

        if (!modal) {
            return;
        }

        function openModal() {
            modal.classList.add('is-open');
            document.body.classList.add('awa-modal-open');
            const firstInput = modal.querySelector('input, textarea');

            if (firstInput) {
                firstInput.focus();
            }
        }

        function closeModal() {
            modal.classList.remove('is-open');
            document.body.classList.remove('awa-modal-open');
        }

        // FAB button opens modal
        element.addEventListener('click', (e) => {
            if (e.target.closest('.awa-quote-fab__btn')) {
                openModal();
            }
        });

        // Backdrop + close button close modal
        modal.addEventListener('click', (e) => {
            if (e.target.classList.contains('awa-quote-modal__backdrop') ||
                e.target.closest('.awa-quote-modal__close')) {
                closeModal();
            }
        });

        // Escape key closes modal
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modal.classList.contains('is-open')) {
                closeModal();
            }
        });
    };
});
