define(['Magento_Customer/js/customer-data'], function (customerData) {
    'use strict';

    return function initHeaderCustomerRuntime() {
        if (window.__awaHeaderCustomerRuntimeInit) {
            return;
        }

        window.__awaHeaderCustomerRuntimeInit = true;
        var hoverOpenTimer = null;
        var hoverCloseTimer = null;
        var lastCartQty = null;
        var cartLiveRegion = null;

        function isLoggedIn(data) {
            if (!data || typeof data !== 'object') {
                return false;
            }

            return !!(
                data.firstname
                || data.fullname
                || data.email
                || data.id
                || data.entity_id
                || data.websiteId !== undefined
            );
        }

        function updateRightCol(data) {
            var accountNav = document.querySelector('[data-awa-account-nav]');
            var rightCol = document.querySelector('[data-awa-header-right]');
            var customerLoggedIn = isLoggedIn(data);

            if (customerLoggedIn) {
                if (accountNav) {
                    accountNav.style.removeProperty('display');
                }
                if (rightCol) {
                    rightCol.classList.add('awa-header-right--logged');
                }
                return;
            }

            if (accountNav) {
                accountNav.style.setProperty('display', 'none', 'important');
            }
            if (rightCol) {
                rightCol.classList.remove('awa-header-right--logged');
            }
        }

        function resolveCustomerLabel(data) {
            if (!data || typeof data !== 'object') {
                return '';
            }

            return String(
                data.firstname
                || data.fullname
                || data.name
                || data.email
                || ''
            ).trim();
        }

        function updateGreeting(data) {
            var label = resolveCustomerLabel(data);
            var nameNode = document.querySelector('.customer-welcome .customer-name');
            var switchNode = document.querySelector('.customer-welcome .action.switch');

            if (!nameNode && !switchNode) {
                return;
            }

            // 20 chars keeps desktop header from breaking while preserving recognition.
            var truncated = label.length > 20 ? (label.slice(0, 20) + '...') : label;
            var isLong = label.length > 16;
            var rightCol = document.querySelector('[data-awa-header-right]');

            if (rightCol) {
                rightCol.classList.toggle('awa-account-name-long', isLong);
            }

            if (switchNode && label) {
                // Avoid persistent browser tooltips in the header.
                switchNode.removeAttribute('title');
                switchNode.setAttribute('aria-label', 'Conta de ' + label);
            }

            if (nameNode && label) {
                nameNode.textContent = truncated;
                nameNode.removeAttribute('title');
            }
        }

        function enhanceSearchExperience() {
            var input = document.querySelector('#search');
            var form = document.querySelector('#search_mini_form');

            if (!input || !form || form.dataset.awaEnhancedSearch === '1') {
                return;
            }

            form.dataset.awaEnhancedSearch = '1';

            function isSkuLike(value) {
                var v = String(value || '').trim();
                // Common SKU-like patterns: uppercase letters/numbers with dashes/underscores.
                return /^[A-Z0-9][A-Z0-9_-]{2,31}$/i.test(v) && /[0-9]/.test(v);
            }

            function syncSkuState() {
                var active = isSkuLike(input.value);
                form.classList.toggle('awa-search-sku-mode', active);
                form.classList.toggle('awa-search-priority-sku', active);
                input.setAttribute('aria-label', active
                    ? 'Buscar por código SKU'
                    : 'Buscar produtos, marcas ou categorias');
            }

            input.addEventListener('input', syncSkuState);
            input.addEventListener('blur', syncSkuState);
            form.addEventListener('submit', function () {
                syncSkuState();
            });
            syncSkuState();
        }

        function setupSearchShortcuts() {
            if (window.__awaHeaderSearchShortcutInit) {
                return;
            }
            window.__awaHeaderSearchShortcutInit = true;

            document.addEventListener('keydown', function (event) {
                var input = document.querySelector('#search');
                if (!input) {
                    return;
                }

                var target = event.target;
                var tag = target && target.tagName ? target.tagName.toLowerCase() : '';
                var typingInField = tag === 'input' || tag === 'textarea' || target.isContentEditable;

                // "/" to focus search when user is not typing in another field.
                if (event.key === '/' && !typingInField && !event.ctrlKey && !event.metaKey && !event.altKey) {
                    event.preventDefault();
                    input.focus();
                    input.select();
                    return;
                }

                // Ctrl/Cmd + K opens search focus.
                if ((event.ctrlKey || event.metaKey) && String(event.key).toLowerCase() === 'k') {
                    event.preventDefault();
                    input.focus();
                    input.select();
                }
            });
        }

        function setupMinicartFeedback() {
            if (window.__awaHeaderMinicartFeedbackInit) {
                return;
            }
            window.__awaHeaderMinicartFeedbackInit = true;

            function getQty() {
                var qtyNode = document.querySelector('.minicart-wrapper .counter.qty .counter-number')
                    || document.querySelector('.minicart-wrapper .counter.qty');
                if (!qtyNode) {
                    return 0;
                }
                var value = parseInt(String(qtyNode.textContent || '').replace(/\D+/g, ''), 10);
                return Number.isFinite(value) ? value : 0;
            }

            function pulseIfChanged() {
                var qty = getQty();
                var button = document.querySelector('.awa-header-minicart .action.showcart');
                if (!button) {
                    return;
                }

                if (lastCartQty === null) {
                    lastCartQty = qty;
                    return;
                }

                if (qty !== lastCartQty) {
                    button.classList.remove('awa-cart-bump');
                    // reflow to restart animation
                    void button.offsetWidth; // eslint-disable-line no-void
                    button.classList.add('awa-cart-bump');
                    if (cartLiveRegion) {
                        cartLiveRegion.textContent = qty > lastCartQty
                            ? 'Item adicionado ao carrinho. Total: ' + qty
                            : 'Carrinho atualizado. Total: ' + qty;
                    }
                    window.setTimeout(function () {
                        button.classList.remove('awa-cart-bump');
                    }, 500);
                }

                lastCartQty = qty;
            }

            function bindCounterObserver() {
                if (typeof MutationObserver !== 'function') {
                    return false;
                }
                var counter = document.querySelector('.minicart-wrapper .counter.qty');
                if (!counter) {
                    return false;
                }

                var observer = new MutationObserver(function () {
                    // Avoid work when tab is backgrounded.
                    if (document.hidden) {
                        return;
                    }
                    pulseIfChanged();
                });
                observer.observe(counter, {
                    subtree: true,
                    childList: true,
                    characterData: true,
                    attributes: true
                });
                return true;
            }

            // Prefer observer; fallback to lightweight polling if minicart node is not ready yet.
            if (!bindCounterObserver()) {
                window.setInterval(function () {
                    if (!document.hidden) {
                        pulseIfChanged();
                    }
                }, 1200);
            }
            pulseIfChanged();
        }

        function ensureCartLiveRegion() {
            if (cartLiveRegion) {
                return;
            }
            var existing = document.getElementById('awa-cart-live-region');
            if (existing) {
                cartLiveRegion = existing;
                return;
            }
            var region = document.createElement('div');
            region.id = 'awa-cart-live-region';
            region.className = 'awa-sr-live';
            region.setAttribute('aria-live', 'polite');
            region.setAttribute('aria-atomic', 'true');
            document.body.appendChild(region);
            cartLiveRegion = region;
        }

        function setAccountExpanded(expanded) {
            var switchNode = document.querySelector('.customer-welcome .action.switch');
            if (!switchNode) {
                return;
            }
            switchNode.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        }

        function getAccountTrigger() {
            return document.querySelector('.customer-welcome .action.switch');
        }

        function getAccountMenu() {
            return document.querySelector('.customer-welcome .customer-menu');
        }

        function isAccountMenuOpen() {
            var wrap = document.querySelector('.customer-welcome');
            var menu = getAccountMenu();
            if (!menu) {
                return false;
            }

            return (wrap && wrap.classList.contains('is-open'))
                || menu.classList.contains('active')
                || menu.style.display === 'block';
        }

        function getAccountMenuFocusable(menu) {
            if (!menu) {
                return [];
            }

            return Array.prototype.slice.call(
                menu.querySelectorAll(
                    'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])'
                )
            ).filter(function (el) {
                return el.offsetParent !== null;
            });
        }

        function focusFirstAccountMenuItem() {
            var menu = getAccountMenu();
            var items = getAccountMenuFocusable(menu);

            if (!items.length) {
                return;
            }

            window.requestAnimationFrame(function () {
                items[0].focus();
            });
        }

        function openAccountMenu(shouldFocusFirstItem) {
            var wrap = document.querySelector('.customer-welcome');
            var switchNode = getAccountTrigger();
            var menu = getAccountMenu();

            if (wrap) {
                wrap.classList.add('is-open');
            }

            if (menu) {
                menu.classList.add('active');
                menu.style.setProperty('display', 'block');
                menu.setAttribute('aria-hidden', 'false');
            }

            if (switchNode) {
                switchNode.classList.add('active');
            }

            setAccountExpanded(true);

            if (shouldFocusFirstItem) {
                focusFirstAccountMenuItem();
            }
        }

        function closeAccountMenu(keepFocusOnTrigger) {
            var wrap = document.querySelector('.customer-welcome');
            var switchNode = getAccountTrigger();
            var menu = getAccountMenu();

            if (wrap) {
                wrap.classList.remove('is-open');
            }

            if (menu) {
                menu.classList.remove('active');
                menu.style.removeProperty('display');
                menu.setAttribute('aria-hidden', 'true');
            }

            if (switchNode) {
                switchNode.classList.remove('active');
                if (keepFocusOnTrigger) {
                    switchNode.focus();
                }
            }

            setAccountExpanded(false);
        }

        function syncAccountAriaState() {
            var wrap = document.querySelector('.customer-welcome');
            var menu = getAccountMenu();
            if (!menu) {
                return;
            }

            var open = isAccountMenuOpen();
            setAccountExpanded(open);
            menu.setAttribute('aria-hidden', open ? 'false' : 'true');
            if (wrap) {
                wrap.classList.toggle('is-open', open);
            }
        }

        function setupAccountMenuA11y() {
            if (window.__awaHeaderAccountA11yInit) {
                return;
            }
            window.__awaHeaderAccountA11yInit = true;

            document.addEventListener('click', function (event) {
                var trigger = getAccountTrigger();
                var menu = getAccountMenu();

                if (!trigger || !menu) {
                    return;
                }

                var target = event.target;
                var insideTrigger = trigger.contains(target);
                var insideMenu = menu.contains(target);

                if (insideTrigger) {
                    event.preventDefault();
                    if (isAccountMenuOpen()) {
                        closeAccountMenu(true);
                    } else {
                        openAccountMenu(false);
                    }
                    return;
                }

                if (!insideMenu) {
                    closeAccountMenu(false);
                }
            }, true);

            document.addEventListener('keydown', function (event) {
                if (event.key !== 'Escape') {
                    return;
                }
                closeAccountMenu(true);
            });

            document.addEventListener('keydown', function (event) {
                if (event.key !== 'Tab') {
                    return;
                }

                var menu = getAccountMenu();
                if (!menu || !isAccountMenuOpen()) {
                    return;
                }

                var items = getAccountMenuFocusable(menu);
                if (!items.length) {
                    return;
                }

                var first = items[0];
                var last = items[items.length - 1];
                var active = document.activeElement;

                if (!event.shiftKey && active === last) {
                    event.preventDefault();
                    first.focus();
                } else if (event.shiftKey && active === first) {
                    event.preventDefault();
                    last.focus();
                }
            });

            window.addEventListener('resize', function () {
                if (window.innerWidth < 992) {
                    closeAccountMenu(false);
                }
            });

            // Desktop hover support with delay, controlled by same JS state.
            document.addEventListener('mouseover', function (event) {
                if (window.innerWidth < 992) {
                    return;
                }
                var wrap = document.querySelector('.customer-welcome');
                if (!wrap || !wrap.contains(event.target)) {
                    return;
                }
                window.clearTimeout(hoverCloseTimer);
                hoverOpenTimer = window.setTimeout(function () {
                    openAccountMenu(false);
                }, 100);
            });

            document.addEventListener('mouseout', function (event) {
                if (window.innerWidth < 992) {
                    return;
                }
                var wrap = document.querySelector('.customer-welcome');
                if (!wrap || !wrap.contains(event.target)) {
                    return;
                }
                var related = event.relatedTarget;
                if (related && wrap.contains(related)) {
                    return;
                }
                window.clearTimeout(hoverOpenTimer);
                hoverCloseTimer = window.setTimeout(function () {
                    closeAccountMenu(false);
                }, 130);
            });

            // Canonical state is controlled by this runtime; avoid observer churn/flicker.
        }

        function normalizeAccountMenuInitialState() {
            var menu = getAccountMenu();
            if (!menu) {
                return;
            }
            menu.setAttribute('aria-hidden', 'true');
            menu.style.removeProperty('display');
            closeAccountMenu(false);
        }

        function improveAccountA11y() {
            var trigger = getAccountTrigger();
            var menu = getAccountMenu();
            if (!trigger || !menu) {
                return;
            }

            if (!menu.id) {
                menu.id = 'awa-account-dropdown-menu';
            }
            trigger.setAttribute('aria-controls', menu.id);
            trigger.setAttribute('aria-haspopup', 'menu');
            menu.setAttribute('role', 'menu');
        }

        function ensureQuickActionsContainer(menu) {
            var existing = menu.querySelector('.awa-account-quick-actions');
            if (existing) {
                return existing;
            }

            var wrap = document.createElement('div');
            wrap.className = 'awa-account-quick-actions';
            menu.insertBefore(wrap, menu.firstChild);
            return wrap;
        }

        function createQuickAction(href, label, mod) {
            var link = document.createElement('a');
            link.className = 'awa-account-quick-actions__item ' + mod;
            link.href = href;
            link.textContent = label;
            return link;
        }

        function updateAccountQuickActions(data) {
            var menu = getAccountMenu();
            if (!menu) {
                return;
            }

            var logged = isLoggedIn(data);
            var container = ensureQuickActionsContainer(menu);
            container.innerHTML = '';

            if (!logged) {
                container.style.display = 'none';
                return;
            }

            container.style.display = 'grid';
            container.appendChild(createQuickAction('/b2b/account/dashboard', 'Dashboard', 'is-dashboard'));
            container.appendChild(createQuickAction('/sales/order/history', 'Pedidos', 'is-orders'));
            container.appendChild(createQuickAction('/b2b/account/reorder', 'Recompra', 'is-reorder'));
            container.appendChild(createQuickAction('/checkout/cart', 'Carrinho', 'is-cart'));
        }

        function ensureMobileQuickActions(data) {
            var rightCol = document.querySelector('[data-awa-header-right]');
            if (!rightCol) {
                return;
            }
            var logged = isLoggedIn(data);
            var host = document.querySelector('.awa-header-mobile-quick-actions');
            if (!host) {
                host = document.createElement('div');
                host.className = 'awa-header-mobile-quick-actions';
                rightCol.appendChild(host);
            }
            host.innerHTML = '';
            if (!logged) {
                host.style.display = 'none';
                return;
            }
            host.style.display = '';
            host.appendChild(createQuickAction('/sales/order/history', 'Pedidos', 'is-orders'));
            host.appendChild(createQuickAction('/b2b/account/reorder', 'Recompra', 'is-reorder'));
        }

        function updateMcpDashboardLink(data) {
            var link = document.getElementById('awa-mcp-dashboard-link');
            if (!link) {
                return;
            }

            // Keep dashboard helper link out of header; dashboard remains in dropdown menu.
            link.hidden = true;
            link.setAttribute('aria-hidden', 'true');
            link.style.setProperty('display', 'none', 'important');
            link.classList.remove('is-visible');
        }

        function syncCustomerUi(data) {
            updateRightCol(data);
            updateMcpDashboardLink(data);
            updateGreeting(data);
            updateAccountQuickActions(data);
            ensureMobileQuickActions(data);
            syncAccountAriaState();
        }

        var customer = customerData.get('customer');
        setupAccountMenuA11y();
        normalizeAccountMenuInitialState();
        improveAccountA11y();
        setupSearchShortcuts();
        enhanceSearchExperience();
        ensureCartLiveRegion();
        setupMinicartFeedback();
        syncCustomerUi(customer());
        customer.subscribe(syncCustomerUi);
    };
});
