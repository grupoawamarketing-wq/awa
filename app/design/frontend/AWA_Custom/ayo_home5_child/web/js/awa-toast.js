/**
 * AWA Toast — sistema de notificações não-bloqueantes
 *
 * Uso:
 *   require(['js/awa-toast'], function(toast) {
 *       toast.show({ message: 'Produto adicionado!', type: 'success' });
 *   });
 *
 * Tipos: 'success' | 'error' | 'info' | 'warning'
 *
 * Integra automaticamente com o cart customer-data para
 * exibir toast de confirmação ao adicionar produtos.
 */
define(['Magento_Customer/js/customer-data'], function (customerData) {
    'use strict';

    let DEFAULT_DURATION = 4000;
    let CONTAINER_ID     = 'awa-toast-container';
    let container        = null;

    /* ---- container ---- */

    function getContainer() {
        if (!container || !document.body.contains(container)) {
            container = document.getElementById(CONTAINER_ID);

            if (!container) {
                container = document.createElement('div');
                container.id = CONTAINER_ID;
                container.className = 'awa-toast-container';
                container.setAttribute('aria-live', 'polite');
                container.setAttribute('aria-atomic', 'false');
                container.setAttribute('aria-relevant', 'additions');
                document.body.appendChild(container);
            }
        }

        return container;
    }

    /* ---- icon SVGs ---- */

    let ICONS = {
        success: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>',
        error:   '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
        warning: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        info:    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>'
    };

    /* ---- XSS-safe helpers ---- */

    /**
     * Escape HTML special characters before inserting into innerHTML.
     * Converts & < > " ' to their named entities.
     *
     * @param {string} str
     * @returns {string}
     */
    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    /**
     * Validate a URL to prevent javascript: / data: XSS vectors.
     * Only allows http(s) URLs and absolute-path or relative URLs.
     *
     * @param {string} url
     * @returns {string} safe URL or '#' if invalid
     */
    function safeUrl(url) {
        let s = String(url || '').trim();
        /* allow relative paths and http(s) absolute URLs only */
        if (/^(https?:\/\/|\/)/i.test(s)) {
            return s;
        }
        return '#';
    }

    /* ---- dismiss ---- */

    function dismiss(toast, timer) {
        if (timer) {
            clearTimeout(timer);
        }

        toast.classList.remove('awa-toast--visible');
        toast.classList.add('awa-toast--out');

        setTimeout(function () {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 320);
    }

    /* ---- show ---- */

    function show(options) {
        let opts    = options || {};
        let type    = opts.type || 'info';
        let message = String(opts.message || '');
        let action  = opts.action || null;     /* { url, label } */
        let dur     = typeof opts.duration === 'number' ? opts.duration : DEFAULT_DURATION;
        let icon    = ICONS[type] || ICONS.info;

        /* build element */
        let toast = document.createElement('div');
        toast.className  = 'awa-toast awa-toast--' + type;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-atomic', 'true');

        let actionHtml = action
            ? '<a href="' + escHtml(safeUrl(action.url)) + '" class="awa-toast__action">' + escHtml(action.label) + '</a>'
            : '';

        toast.innerHTML =
            '<span class="awa-toast__icon" aria-hidden="true">' + icon + '</span>' +
            '<span class="awa-toast__body">' +
                '<span class="awa-toast__message">' + escHtml(message) + '</span>' +
                actionHtml +
            '</span>' +
            '<button type="button" class="awa-toast__close" aria-label="Fechar notificação">' +
                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>' +
            '</button>';

        getContainer().appendChild(toast);

        /* animate in on next frame */
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                toast.classList.add('awa-toast--visible');
            });
        });

        /* auto-dismiss */
        let timer = null;

        if (dur > 0) {
            timer = setTimeout(function () {
                dismiss(toast, null);
            }, dur);
        }

        /* close button */
        toast.querySelector('.awa-toast__close').addEventListener('click', function () {
            dismiss(toast, timer);
        });

        /* pause on hover */
        toast.addEventListener('mouseenter', function () {
            if (timer) {
                clearTimeout(timer);
                timer = null;
            }
        });

        toast.addEventListener('mouseleave', function () {
            if (dur > 0) {
                timer = setTimeout(function () {
                    dismiss(toast, null);
                }, 1500);
            }
        });

        return { dismiss: dismiss.bind(null, toast, timer) };
    }

    /* ---- cart integration ---- */
    let cartIntegrationBound = false;

    function bindCartIntegration() {
        if (cartIntegrationBound) {
            return;
        }
        cartIntegrationBound = true;

        let cart         = customerData.get('cart');
        let previousData = null;

        cart.subscribe(function (cartData) {
            let prev    = previousData;
            let current = cartData || {};

            previousData = {
                count: current.summary_count || 0,
                subtotal: current.subtotal || ''
            };

            /* First load — don't toast */
            if (prev === null) {
                return;
            }

            let prevCount    = prev.count || 0;
            let currentCount = current.summary_count || 0;

            if (currentCount > prevCount) {
                let added = currentCount - prevCount;
                let msg   = added === 1
                    ? 'Produto adicionado ao carrinho!'
                    : added + ' produtos adicionados ao carrinho!';

                show({
                    type:     'success',
                    message:  msg,
                    action:   { url: '/checkout/cart/', label: 'Ver Carrinho' },
                    duration: 4500
                });
            }
        });
    }

    function init() {
        bindCartIntegration();
    }

    let api = {
        show: show,
        dismiss: dismiss,
        init: init
    };

    function initializer() {
        init();
        return api;
    }

    initializer.show = show;
    initializer.dismiss = dismiss;
    initializer.init = init;

    init();

    return initializer;
});
