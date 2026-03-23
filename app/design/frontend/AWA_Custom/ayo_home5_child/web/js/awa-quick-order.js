/**
 * AWA Quick Order by SKU (2026-03-19)
 *
 * Allows B2B users to bulk-add products by SKU without browsing the catalog.
 * Opens via Ctrl+Q keyboard shortcut or the #awa-quick-order-trigger button.
 *
 * Flow:
 *   1. User enters SKUs (one per line, optional qty after space)
 *   2. "Verificar SKUs" resolves each via Magento REST catalog API
 *   3. Preview table shows name / price / stock status per SKU
 *   4. "Adicionar ao Carrinho" adds each item via AJAX and updates sections
 *
 * No backend changes required. Works for logged-in customers.
 * Guests are redirected to login (B2B gate already enforces this at store level).
 */
define([
    'jquery',
    'mage/url',
    'Magento_Customer/js/customer-data'
], function ($, urlHelper, customerData) {
    'use strict';

    var MODAL_ID   = 'awa-qo-modal';
    var DRAFT_KEY  = 'awa_quick_order_draft';
    var modal      = null;
    var isOpen     = false;
    var resolving  = false;
    var resolvedItems = [];

    /* ---- utilities ---- */

    function getFormKey() {
        return window.FORM_KEY
            || ($('input[name="form_key"]').first().val() || '');
    }

    function fmt(price) {
        return 'R$\u00a0' + Number(price).toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function parseLines(text) {
        return text.split('\n')
            .map(function (l) { return l.trim(); })
            .filter(function (l) { return l.length > 0; })
            .map(function (l) {
                var parts = l.split(/[\s,;]+/);
                var sku   = (parts[0] || '').toUpperCase().trim();
                var qty   = parseInt(parts[1], 10);
                return { sku: sku, qty: (isNaN(qty) || qty < 1) ? 1 : qty };
            })
            .filter(function (i) { return i.sku.length > 0; });
    }

    /* ---- REST catalog lookup ---- */

    function resolveSku(sku) {
        var fields = 'items[id,sku,name,price,status,extension_attributes[stock_item[qty,is_in_stock]]]';
        var url    = '/rest/V1/products'
            + '?searchCriteria[filterGroups][0][filters][0][field]=sku'
            + '&searchCriteria[filterGroups][0][filters][0][value]=' + encodeURIComponent(sku)
            + '&searchCriteria[filterGroups][0][filters][0][conditionType]=eq'
            + '&fields=' + encodeURIComponent(fields);

        return fetch(url, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
        .then(function (data) {
            var items = data.items || [];
            if (!items.length) {
                return { sku: sku, error: 'SKU não encontrado' };
            }
            var p  = items[0];
            var si = ((p.extension_attributes || {}).stock_item) || {};
            return {
                sku:      p.sku,
                id:       p.id,
                name:     p.name,
                price:    p.price,
                inStock:  !!si.is_in_stock,
                stockQty: parseFloat(si.qty) || 0
            };
        })
        .catch(function () {
            return { sku: sku, error: 'Erro ao consultar SKU' };
        });
    }

    /* ---- AJAX add to cart ---- */

    function addOneToCart(productId, qty) {
        return $.ajax({
            url:         urlHelper.build('checkout/cart/add'),
            method:      'POST',
            dataType:    'json',
            data: {
                product:   productId,
                qty:       qty,
                form_key:  getFormKey()
            }
        }).then(function (resp) {
            return { success: !resp.error, message: resp.message || '' };
        }).catch(function () {
            return { success: false, message: 'Erro ao adicionar' };
        });
    }

    /* ---- modal build ---- */

    function buildModal() {
        if (document.getElementById(MODAL_ID)) {
            return document.getElementById(MODAL_ID);
        }

        var draft = '';
        try { draft = sessionStorage.getItem(DRAFT_KEY) || ''; } catch (e) {}

        var el = document.createElement('div');
        el.id = MODAL_ID;
        el.className = 'awa-qo-modal';
        el.setAttribute('role', 'dialog');
        el.setAttribute('aria-modal', 'true');
        el.setAttribute('aria-labelledby', 'awa-qo-title');
        el.setAttribute('aria-hidden', 'true');
        el.innerHTML = [
            '<div class="awa-qo-backdrop"></div>',
            '<div class="awa-qo-dialog" role="document">',
            '  <div class="awa-qo-header">',
            '    <span class="awa-qo-icon" aria-hidden="true">',
            '      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">',
            '        <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/>',
            '      </svg>',
            '    </span>',
            '    <h2 id="awa-qo-title" class="awa-qo-title">Pedido Rápido por SKU</h2>',
            '    <button type="button" class="awa-qo-close" aria-label="Fechar pedido rápido" title="Fechar (Esc)">',
            '      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
            '    </button>',
            '  </div>',
            '  <div class="awa-qo-body">',
            '    <p class="awa-qo-help">',
            '      Um SKU por linha. Para especificar quantidade, adicione o número após o SKU.',
            '      <br><strong>Exemplo:</strong> <code>SKU-001 5</code>',
            '    </p>',
            '    <textarea class="awa-qo-textarea" placeholder="SKU-001 2\nSKU-002 1\nSKU-003 3" rows="6"',
            '              aria-label="Lista de SKUs" spellcheck="false" autocorrect="off" autocapitalize="off">',
            draft ? escAttr(draft) : '',
            '</textarea>',
            '    <div class="awa-qo-results" hidden aria-live="polite" aria-label="Resultados da verificação"></div>',
            '  </div>',
            '  <div class="awa-qo-footer">',
            '    <button type="button" class="awa-qo-btn awa-qo-btn--secondary js-qo-cancel">Cancelar</button>',
            '    <button type="button" class="awa-qo-btn awa-qo-btn--resolve js-qo-resolve">',
            '      <span class="awa-qo-btn-label">Verificar SKUs</span>',
            '    </button>',
            '  </div>',
            '</div>'
        ].join('');

        document.body.appendChild(el);
        return el;
    }

    function escAttr(s) {
        return s.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    /* ---- results table ---- */

    function renderResults(results) {
        var hasErrors = results.some(function (r) { return r.error; });
        var hasOk     = results.some(function (r) { return !r.error; });
        var rows = results.map(function (r) {
            if (r.error) {
                return '<tr class="awa-qo-row awa-qo-row--error">'
                    + '<td class="awa-qo-cell awa-qo-cell--sku"><span class="awa-qo-badge awa-qo-badge--error">✕</span>' + escAttr(r.sku) + '</td>'
                    + '<td class="awa-qo-cell" colspan="3"><span class="awa-qo-error-msg">' + escAttr(r.error) + '</span></td>'
                    + '</tr>';
            }
            var stockBadge = r.inStock
                ? '<span class="awa-qo-badge awa-qo-badge--ok">Em estoque</span>'
                : '<span class="awa-qo-badge awa-qo-badge--warn">Sem estoque</span>';
            return '<tr class="awa-qo-row' + (!r.inStock ? ' awa-qo-row--disabled' : '') + '">'
                + '<td class="awa-qo-cell awa-qo-cell--sku"><span class="awa-qo-badge awa-qo-badge--ok">✓</span>' + escAttr(r.sku) + '</td>'
                + '<td class="awa-qo-cell awa-qo-cell--name">' + escAttr(r.name) + '</td>'
                + '<td class="awa-qo-cell awa-qo-cell--stock">' + stockBadge + '</td>'
                + '<td class="awa-qo-cell awa-qo-cell--price">' + fmt(r.price) + '</td>'
                + '</tr>';
        });

        return '<div class="awa-qo-results-inner">'
            + '<table class="awa-qo-table" aria-label="Produtos encontrados">'
            + '<thead><tr>'
            + '<th class="awa-qo-th">SKU</th><th class="awa-qo-th">Produto</th>'
            + '<th class="awa-qo-th">Estoque</th><th class="awa-qo-th">Preço</th>'
            + '</tr></thead>'
            + '<tbody>' + rows.join('') + '</tbody>'
            + '</table>'
            + (hasOk ? '<p class="awa-qo-results-note">Produtos sem estoque não serão adicionados ao carrinho.</p>' : '')
            + '</div>';
    }

    /* ---- open / close ---- */

    function openModal() {
        if (!modal) {
            modal = buildModal();
            bindModalEvents();
        }
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('awa-qo-open');
        isOpen = true;

        /* reset to input state */
        var ta = modal.querySelector('.awa-qo-textarea');
        var results = modal.querySelector('.awa-qo-results');
        var resolveBtn = modal.querySelector('.js-qo-resolve');
        results.hidden = true;
        resolveBtn.querySelector('.awa-qo-btn-label').textContent = 'Verificar SKUs';
        resolveBtn.disabled = false;
        resolvedItems = [];

        /* focus textarea with slight delay for animation */
        setTimeout(function () {
            if (ta) ta.focus();
        }, 80);
    }

    function closeModal() {
        if (!modal) return;
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('awa-qo-open');
        isOpen = false;
        resolving = false;

        /* save draft */
        var ta = modal.querySelector('.awa-qo-textarea');
        try { sessionStorage.setItem(DRAFT_KEY, ta ? ta.value : ''); } catch (e) {}
    }

    /* ---- resolve flow ---- */

    function runResolve() {
        if (resolving) return;
        var ta    = modal.querySelector('.awa-qo-textarea');
        var lines = parseLines(ta ? ta.value : '');

        if (!lines.length) {
            ta && ta.focus();
            return;
        }

        var btn    = modal.querySelector('.js-qo-resolve');
        var label  = btn.querySelector('.awa-qo-btn-label');
        resolving  = true;
        btn.disabled = true;
        label.textContent = 'Verificando…';

        Promise.all(lines.map(function (item) {
            return resolveSku(item.sku).then(function (result) {
                /* carry the user-specified qty into the result */
                result.qty = item.qty;
                return result;
            });
        })).then(function (results) {
            resolvedItems = results;
            resolving = false;
            var resultsEl = modal.querySelector('.awa-qo-results');
            resultsEl.innerHTML = renderResults(results);
            resultsEl.hidden = false;

            var hasOk = results.some(function (r) { return !r.error && r.inStock; });
            btn.disabled = !hasOk;
            label.textContent = hasOk ? 'Adicionar ao Carrinho' : 'Nenhum item disponível';

            if (hasOk) {
                btn.classList.add('awa-qo-btn--primary');
                btn.classList.remove('awa-qo-btn--resolve');
                btn.dataset.qoState = 'add';
            }
        });
    }

    /* ---- add-to-cart flow ---- */

    function runAddToCart() {
        var eligible = resolvedItems.filter(function (r) { return !r.error && r.inStock && r.id; });
        if (!eligible.length) return;

        var btn   = modal.querySelector('.js-qo-resolve');
        var label = btn.querySelector('.awa-qo-btn-label');
        btn.disabled = true;
        label.textContent = 'Adicionando…';

        var chain = eligible.reduce(function (p, item) {
            return p.then(function () { return addOneToCart(item.id, item.qty); });
        }, Promise.resolve());

        chain.then(function () {
            /* refresh minicart via customer-data invalidation */
            customerData.invalidate(['cart']);
            customerData.reload(['cart'], true);

            label.textContent = 'Adicionado! ✓';
            setTimeout(function () { closeModal(); }, 1200);

            /* optional toast if the module is available */
            if (window.awaToast && typeof window.awaToast.show === 'function') {
                var names = eligible.map(function (i) { return i.name; });
                window.awaToast.show({
                    type:    'success',
                    message: eligible.length === 1
                        ? names[0] + ' adicionado ao carrinho.'
                        : eligible.length + ' produtos adicionados ao carrinho.'
                });
            }
        });
    }

    /* ---- event wiring ---- */

    function bindModalEvents() {
        /* backdrop / close button */
        modal.addEventListener('click', function (e) {
            if (e.target.closest('.awa-qo-backdrop') || e.target.closest('.awa-qo-close')) {
                closeModal();
            }
        });

        /* cancel button */
        modal.addEventListener('click', function (e) {
            if (e.target.closest('.js-qo-cancel')) closeModal();
        });

        /* resolve / add-to-cart button */
        modal.addEventListener('click', function (e) {
            var btn = e.target.closest('.js-qo-resolve');
            if (!btn || btn.disabled) return;
            if (btn.dataset.qoState === 'add') {
                runAddToCart();
            } else {
                runResolve();
            }
        });

        /* Ctrl+Enter in textarea → resolve */
        modal.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                var btn = modal.querySelector('.js-qo-resolve');
                if (btn && !btn.disabled) btn.click();
            }
        });

        /* reset state when textarea changes (after resolving) */
        modal.addEventListener('input', function (e) {
            if (!e.target.classList.contains('awa-qo-textarea')) return;
            var btn = modal.querySelector('.js-qo-resolve');
            if (btn && btn.dataset.qoState === 'add') {
                btn.dataset.qoState = '';
                btn.classList.remove('awa-qo-btn--primary');
                btn.classList.add('awa-qo-btn--resolve');
                btn.querySelector('.awa-qo-btn-label').textContent = 'Verificar SKUs';
                btn.disabled = false;
                resolvedItems = [];
                var results = modal.querySelector('.awa-qo-results');
                results.hidden = true;
            }
        });
    }

    function bindGlobalEvents() {
        /* Ctrl+Q to open */
        document.addEventListener('keydown', function (e) {
            var tag = (document.activeElement || {}).tagName || '';
            var isEditable = tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT'
                || (document.activeElement && document.activeElement.isContentEditable);

            if ((e.ctrlKey || e.metaKey) && e.key === 'q' && !isEditable) {
                e.preventDefault();
                isOpen ? closeModal() : openModal();
            }
        });

        /* Esc to close */
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && isOpen) closeModal();
        });

        /* trigger button (rendered by phtml block) */
        document.addEventListener('click', function (e) {
            if (e.target.closest('#awa-quick-order-trigger')) {
                e.preventDefault();
                openModal();
            }
        });
    }

    /* ---- public init ---- */

    return function (config) {
        /* Only render the trigger if the customer can see prices (B2B approved) */
        var trigger = document.getElementById('awa-quick-order-trigger');
        if (trigger) {
            trigger.hidden = false;
            trigger.setAttribute('title', 'Pedido Rápido por SKU (Ctrl+Q)');
        }
        bindGlobalEvents();
    };
});
