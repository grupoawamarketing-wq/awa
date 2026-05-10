define([], function () {
    'use strict';

    function emit(root, type, detail) {
        if (!root || typeof root.dispatchEvent !== 'function') {
            return;
        }

        root.dispatchEvent(new CustomEvent(type, {
            bubbles: true,
            detail: detail || {}
        }));
    }

    function copyWithFallback(text) {
        return new Promise(function (resolve, reject) {
            let helper = document.createElement('textarea');

            helper.value = text;
            helper.setAttribute('readonly', 'readonly');
            helper.style.position = 'fixed';
            helper.style.top = '-9999px';
            helper.style.left = '-9999px';

            document.body.appendChild(helper);
            helper.focus();
            helper.select();

            try {
                if (document.execCommand('copy')) {
                    resolve();
                    return;
                }
            } catch (error) {
                reject(error);
                return;
            } finally {
                document.body.removeChild(helper);
            }

            reject(new Error('copy-failed'));
        });
    }

    function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }

        return copyWithFallback(text);
    }

    return function (config, element) {
        let root = element;
        let options = config || {};
        let selectors = options.selectors || {};
        let messages = options.messages || {};
        let flags = options.flags || {};
        let source = root ? root.querySelector(selectors.source || '[data-awa-role="copy-source"]') : null;
        let trigger = root ? root.querySelector(selectors.trigger || '[data-awa-role="copy-trigger"]') : null;
        let label = trigger ? trigger.querySelector(selectors.label || '[data-awa-role="copy-label"]') : null;
        let status = root ? root.querySelector(selectors.status || '[data-awa-role="copy-status"]') : null;
        let defaultLabel = label ? label.textContent : '';
        let resetDelay = Number.parseInt(flags.resetDelay, 10) || 2000;

        if (!root || root.nodeType !== 1 || root.getAttribute('data-awa-copy-bound') === 'true') {
            return;
        }

        root.setAttribute('data-awa-copy-bound', 'true');
        root.setAttribute('data-awa-component', 'awa-copy-control');

        if (!source || !trigger || !label) {
            emit(root, 'awa:copy-control:error', {
                reason: 'missing-node'
            });
            return;
        }

        trigger.addEventListener('click', function (event) {
            let value = source.textContent ? source.textContent.trim() : '';

            event.preventDefault();

            if (!value) {
                if (status) {
                    status.textContent = messages.error || messages.unsupported || '';
                }

                emit(root, 'awa:copy-control:error', {
                    reason: 'empty-value'
                });
                return;
            }

            copyText(value).then(function () {
                label.textContent = messages.copied || defaultLabel;
                trigger.setAttribute('data-state', 'copied');

                if (status) {
                    status.textContent = messages.copied || defaultLabel;
                }

                window.setTimeout(function () {
                    label.textContent = messages.copy || defaultLabel;
                    trigger.setAttribute('data-state', 'idle');
                }, resetDelay);
            }).catch(function () {
                if (status) {
                    status.textContent = messages.unsupported || messages.error || '';
                }

                emit(root, 'awa:copy-control:error', {
                    reason: 'copy-failed'
                });
            });
        });

        emit(root, 'awa:copy-control:ready');
    };
});
