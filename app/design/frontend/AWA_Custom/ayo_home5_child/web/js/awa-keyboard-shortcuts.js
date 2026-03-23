/**
 * AWA Keyboard Shortcuts Overlay (2026-03-19)
 *
 * Press '?' (without modifier, outside inputs) to show a modal listing
 * all available keyboard shortcuts across AWA custom modules.
 *
 * Also contains the PDP print helper: sets data-print-date and
 * data-product-url attributes before calling window.print().
 *
 * Loaded globally via x-magento-init in awa-custom-js-loader.phtml.
 */
define([], function () {
    'use strict';

    /* ---- Shortcut registry ---- */

    var SHORTCUTS = [
        { key: '/',        modifier: '',       description: 'Focar barra de busca',      scope: 'Global' },
        { key: 'Ctrl + Q', modifier: 'ctrl',   description: 'Abrir Pedido Rápido (SKU)', scope: 'Global' },
        { key: '?',        modifier: '',       description: 'Mostrar atalhos de teclado', scope: 'Global' },
        { key: '↑ ↓',      modifier: '',       description: 'Navegar buscas recentes',   scope: 'Busca' },
        { key: 'Esc',      modifier: '',       description: 'Fechar painel / modal',      scope: 'Qualquer modal' },
        { key: 'Enter',    modifier: '',       description: 'Confirmar ação focada',      scope: 'Qualquer modal' },
        { key: 'Ctrl + ↵', modifier: 'ctrl',   description: 'Resolver + Adicionar ao carrinho', scope: 'Pedido Rápido' },
        { key: 'Tab',      modifier: '',       description: 'Navegar campos / botões',   scope: 'Acessibilidade' },
    ];

    /* ---- Modal DOM ---- */

    var MODAL_ID = 'awa-shortcuts-modal';

    function buildModal() {
        var existing = document.getElementById(MODAL_ID);
        if (existing) { return existing; }

        /* Group by scope */
        var groups = {};
        SHORTCUTS.forEach(function (s) {
            if (!groups[s.scope]) { groups[s.scope] = []; }
            groups[s.scope].push(s);
        });

        var rows = Object.keys(groups).map(function (scope) {
            var items = groups[scope].map(function (s) {
                return '<tr>' +
                    '<td class="awa-ks-key"><kbd>' + escapeHtml(s.key) + '</kbd></td>' +
                    '<td class="awa-ks-desc">' + escapeHtml(s.description) + '</td>' +
                    '</tr>';
            }).join('');

            return '<tbody>' +
                '<tr><th colspan="2" class="awa-ks-scope">' + escapeHtml(scope) + '</th></tr>' +
                items +
                '</tbody>';
        }).join('');

        var modal = document.createElement('div');
        modal.id = MODAL_ID;
        modal.className = 'awa-ks-modal';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('aria-label', 'Atalhos de teclado');
        modal.setAttribute('aria-hidden', 'true');
        modal.innerHTML = '<div class="awa-ks-backdrop" data-awa-ks-close></div>' +
            '<div class="awa-ks-dialog">' +
            '<header class="awa-ks-header">' +
            '<h2 class="awa-ks-title">Atalhos de Teclado</h2>' +
            '<button type="button" class="awa-ks-close" data-awa-ks-close aria-label="Fechar">&#x2715;</button>' +
            '</header>' +
            '<table class="awa-ks-table">' + rows + '</table>' +
            '<p class="awa-ks-hint">Pressione <kbd>?</kbd> ou <kbd>Esc</kbd> para fechar</p>' +
            '</div>';

        document.body.appendChild(modal);
        return modal;
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    /* ---- open / close ---- */

    var isOpen = false;

    function openModal() {
        var modal = buildModal();
        modal.setAttribute('aria-hidden', 'false');
        modal.classList.add('is-open');
        isOpen = true;

        /* Focus dialog for a11y */
        var dialog = modal.querySelector('.awa-ks-dialog');
        if (dialog) { dialog.setAttribute('tabindex', '-1'); dialog.focus(); }

        /* Click delegation */
        if (modal.dataset.awaKsBound !== '1') {
            modal.addEventListener('click', function (e) {
                if (e.target.closest('[data-awa-ks-close]')) { closeModal(); }
            });
            modal.dataset.awaKsBound = '1';
        }
    }

    function closeModal() {
        var modal = document.getElementById(MODAL_ID);
        if (!modal) { return; }
        modal.setAttribute('aria-hidden', 'true');
        modal.classList.remove('is-open');
        isOpen = false;
    }

    /* ---- Global key handler ---- */

    function isEditable(el) {
        if (!el) { return false; }
        var tag = el.tagName.toLowerCase();
        return tag === 'input' || tag === 'textarea' || tag === 'select' || el.isContentEditable;
    }

    function handleGlobalKeydown(e) {
        /* '?' → toggle */
        if (e.key === '?' && !e.ctrlKey && !e.metaKey && !e.altKey) {
            if (isEditable(document.activeElement)) { return; }
            e.preventDefault();
            isOpen ? closeModal() : openModal();
            return;
        }

        /* Esc → close */
        if (e.key === 'Escape' && isOpen) {
            closeModal();
        }
    }

    var keydownBound = false;
    function bindGlobalKeyHandler() {
        if (keydownBound) {
            return;
        }

        document.addEventListener('keydown', handleGlobalKeydown);
        keydownBound = true;
    }

    /* ================================================================
       PDP PRINT HELPER
       Inject metadata attributes before printing and trigger window.print().
       Called from the "Imprimir Ficha" button on product pages.
       ================================================================ */

    function initPdpPrint() {
        var btn = document.getElementById('awa-pdp-print-btn');
        if (!btn) { return; }

        btn.addEventListener('click', function () {
            /* Set data-print-date on .page-main (used by CSS ::before) */
            var pageMain = document.querySelector('.page-main');
            if (pageMain) {
                var now = new Date();
                var formatted = now.toLocaleDateString('pt-BR', {
                    day: '2-digit', month: '2-digit', year: 'numeric'
                });
                pageMain.setAttribute('data-print-date', formatted);
            }

            /* Set data-product-url on .product-info-main */
            var infoMain = document.querySelector('.product-info-main');
            if (infoMain) {
                infoMain.setAttribute('data-product-url', window.location.href);
            }

            window.print();
        });
    }

    /* ================================================================
       FIRST-VISIT HINT
       Shows a pill "Pressione ? para ver atalhos" for 4s on first pageload.
       Stored in sessionStorage so it shows once per session, not every page.
       ================================================================ */

    function showFirstVisitHint() {
        if (sessionStorage.getItem('awa_ks_hint_shown')) { return; }
        sessionStorage.setItem('awa_ks_hint_shown', '1');

        var hint = document.createElement('div');
        hint.className = 'awa-ks-trigger-hint';
        hint.setAttribute('aria-hidden', 'true');
        hint.innerHTML = 'Pressione <kbd>?</kbd> para ver atalhos de teclado';
        document.body.appendChild(hint);

        /* Show after 1.5s (page is settled), hide after 4s total */
        setTimeout(function () { hint.classList.add('is-visible'); }, 1500);
        setTimeout(function () {
            hint.classList.remove('is-visible');
            setTimeout(function () {
                if (hint.parentNode) { hint.parentNode.removeChild(hint); }
            }, 400);
        }, 5000);
    }

    var domInitDone = false;
    function initDomFeatures() {
        if (domInitDone) {
            return;
        }

        domInitDone = true;
        initPdpPrint();
        showFirstVisitHint();
    }

    function init() {
        bindGlobalKeyHandler();

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initDomFeatures, { once: true });
        } else {
            initDomFeatures();
        }
    }

    var api = {
        open: openModal,
        close: closeModal,
        init: init
    };

    function initializer() {
        init();
        return api;
    }

    initializer.open = openModal;
    initializer.close = closeModal;
    initializer.init = init;

    init();

    return initializer;
});
