/**
 * AWA Menu Controller v2 — vanilla runtime (Departamentos, flyout, mobile drawer, horizontal nav).
 *
 * @module awa-menu-controller
 */
define([
    'domReady!',
    'js/vendor/floating-ui.amd'
], function (domReady, FloatingUIDOM) {
    'use strict';

    var DESKTOP_MIN = 992;
    var PORTAL_CLASS = 'awa-vmf-portal';
    var ACTIVE_CLASS = 'awa-vmf-active';
    var DOC_BOOTED = false;
    var FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

    function isDesktop() {
        return window.matchMedia
            ? window.matchMedia('(min-width: ' + DESKTOP_MIN + 'px)').matches
            : window.innerWidth >= DESKTOP_MIN;
    }

    function isMobile() {
        return !isDesktop();
    }

    function rafThrottle(fn) {
        var scheduled = 0;
        return function () {
            var ctx = this;
            var args = arguments;
            if (scheduled) {
                return;
            }
            scheduled = window.requestAnimationFrame(function () {
                scheduled = 0;
                fn.apply(ctx, args);
            });
        };
    }

    function getFocusables(root) {
        if (!root) {
            return [];
        }
        return Array.prototype.slice.call(root.querySelectorAll(FOCUSABLE)).filter(function (el) {
            return el.offsetParent !== null || el === document.activeElement;
        });
    }

    function resolveDrawerShell() {
        return document.querySelector('[data-awa-nav-shell="true"]')
            || document.getElementById('awa-category-navigation')
            || document.getElementById('awa-primary-navigation')
            || document.querySelector('.section-items.nav-sections.category-dropdown-items.awa-header-primary-nav')
            || document.querySelector('.sections.nav-sections');
    }

    /* ── FlyoutPortal ─────────────────────────────────────────────────── */
    function FlyoutPortal(root) {
        var self = this;
        this.root = root;
        this.portals = [];
        this._onEnter = function (e) {
            var li = e.target.closest('li.level0.parent, li.level0.navigation__item--parent');
            if (li && self.root.contains(li)) {
                self.attach(li);
            }
        };
        this._onLeave = function (e) {
            var li = e.target.closest('li.level0');
            if (!li || !self.root.contains(li)) {
                return;
            }
            var to = e.relatedTarget;
            if (to && to.closest && to.closest('.' + PORTAL_CLASS)) {
                return;
            }
            self.detach(li);
        };
        this._reposition = rafThrottle(function () {
            if (!isDesktop()) {
                return;
            }
            document.querySelectorAll('.' + PORTAL_CLASS).forEach(function (portal) {
                var id = portal.dataset.awVmfLiMenu;
                var li = id && self.root.querySelector('li.level0[data-menu="' + id + '"]');
                if (li) {
                    self.position(li, portal);
                }
            });
        });
    }

    FlyoutPortal.prototype.mount = function () {
        if (!this.root || this.root.dataset.awaFlyoutMounted === '1') {
            return;
        }
        this.root.dataset.awaFlyoutMounted = '1';
        this.root.addEventListener('mouseenter', this._onEnter, true);
        this.root.addEventListener('mouseleave', this._onLeave, true);
        window.addEventListener('scroll', this._reposition, { passive: true });
        window.addEventListener('resize', this._reposition, { passive: true });
    };

    FlyoutPortal.prototype.findSubmenu = function (li) {
        return li.querySelector(':scope > .submenu, :scope > .level0.submenu, :scope > .navigation__submenu');
    };

    FlyoutPortal.prototype.attach = function (li) {
        if (!isDesktop()) {
            return;
        }
        var submenu = this.findSubmenu(li);
        if (!submenu || submenu.dataset.awVmfPortaled === '1') {
            return;
        }
        var portal = document.createElement('div');
        portal.className = PORTAL_CLASS;
        portal.dataset.awVmfLiMenu = li.getAttribute('data-menu') || '';
        portal.appendChild(submenu);
        document.body.appendChild(portal);
        submenu.dataset.awVmfPortaled = '1';
        li.classList.add(ACTIVE_CLASS);
        li.setAttribute('data-awa-submenu-open', 'true');
        this.position(li, portal);
        this.portals.push(portal);
    };

    FlyoutPortal.prototype.position = function (li, portal) {
        if (!FloatingUIDOM || typeof FloatingUIDOM.computePosition !== 'function') {
            portal.style.position = 'fixed';
            portal.style.zIndex = '99990';
            return;
        }
        FloatingUIDOM.computePosition(li, portal, {
            placement: 'right-start',
            middleware: [
                FloatingUIDOM.offset({ mainAxis: 0, crossAxis: 0 }),
                FloatingUIDOM.flip(),
                FloatingUIDOM.shift({ padding: 8 })
            ]
        }).then(function (data) {
            Object.assign(portal.style, {
                position: 'fixed',
                left: data.x + 'px',
                top: data.y + 'px',
                zIndex: '99990'
            });
        });
    };

    FlyoutPortal.prototype.detach = function (li) {
        var menuId = li.getAttribute('data-menu') || '';
        var portal = document.querySelector(
            '.' + PORTAL_CLASS + '[data-aw-vmf-li-menu="' + menuId + '"]'
        );
        var submenu = portal
            ? portal.querySelector('.submenu, .level0.submenu, .navigation__submenu')
            : this.findSubmenu(li);

        if (portal && submenu) {
            li.appendChild(submenu);
            portal.remove();
        }
        if (submenu) {
            submenu.dataset.awVmfPortaled = '';
        }
        li.classList.remove(ACTIVE_CLASS);
        li.removeAttribute('data-awa-submenu-open');
    };

    FlyoutPortal.prototype.teardown = function () {
        document.querySelectorAll('.' + PORTAL_CLASS).forEach(function (p) { p.remove(); });
    };

    /* ── DeptMenu ─────────────────────────────────────────────────────── */
    function DeptMenu(nav, config) {
        this.nav = nav;
        this.config = config || {};
        this.trigger = nav.querySelector('[data-role="awa-vertical-menu-trigger"]');
        this.panel = nav.querySelector('[data-role="awa-vertical-menu-panel"]');
        this.status = nav.querySelector('[data-role="awa-vertical-menu-status"]');
        this.isOpen = false;
        this.hoverTimer = null;
    }

    DeptMenu.prototype.syncAria = function (open) {
        if (this.trigger) {
            this.trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
            this.trigger.classList.toggle('active', open);
        }
        if (this.panel) {
            this.panel.setAttribute('aria-hidden', open ? 'false' : 'true');
            this.panel.setAttribute('data-awa-menu-state', open ? 'open' : 'closed');
            this.panel.classList.toggle('vmm-open', open);
            this.panel.classList.toggle('menu-open', open);
            if (open) {
                this.panel.style.setProperty('display', 'block', 'important');
                this.panel.style.setProperty('visibility', 'visible', 'important');
                this.panel.style.setProperty('opacity', '1', 'important');
            } else {
                this.panel.style.removeProperty('display');
                this.panel.style.removeProperty('visibility');
                this.panel.style.removeProperty('opacity');
            }
        }
        if (this.status) {
            this.status.textContent = open
                ? 'Menu de departamentos aberto.'
                : 'Menu de departamentos fechado. Pressione Enter para abrir.';
        }
        document.body.classList.toggle('awa-menu-dept-open', open);
    };

    DeptMenu.prototype.open = function () {
        if (this.isOpen) {
            return;
        }
        this.isOpen = true;
        this.syncAria(true);
    };

    DeptMenu.prototype.close = function () {
        if (!this.isOpen) {
            return;
        }
        this.isOpen = false;
        this.syncAria(false);
    };

    DeptMenu.prototype.toggle = function () {
        if (this.isOpen) {
            this.close();
        } else {
            this.open();
        }
    };

    DeptMenu.prototype.bind = function () {
        var self = this;
        if (!this.trigger || !this.panel) {
            return;
        }

        if (
            document.body.classList.contains('awa-menu-dept-open')
            || this.panel.getAttribute('data-awa-menu-state') === 'open'
            || this.trigger.getAttribute('aria-expanded') === 'true'
        ) {
            this.isOpen = true;
            this.syncAria(true);
        }

        this.trigger.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            self.toggle();
        });

        if (isDesktop()) {
            this.nav.addEventListener('mouseenter', function () {
                clearTimeout(self.hoverTimer);
                self.hoverTimer = window.setTimeout(function () { self.open(); }, self.config.hoverDelay || 100);
            });
            this.nav.addEventListener('mouseleave', function (e) {
                var to = e.relatedTarget;
                if (to && self.nav.contains(to)) {
                    return;
                }
                clearTimeout(self.hoverTimer);
                self.close();
            });
        }

        document.addEventListener('click', function (e) {
            if (!self.isOpen || self.nav.contains(e.target)) {
                return;
            }
            self.close();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && self.isOpen) {
                self.close();
                self.trigger.focus();
            }
        });

        this.panel.querySelectorAll('.open-children-toggle').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var li = btn.closest('li');
                if (!li) {
                    return;
                }
                var sub = li.querySelector(':scope > .subchildmenu, :scope > .submenu');
                if (!sub) {
                    return;
                }
                var opened = sub.classList.toggle('opened');
                btn.setAttribute('aria-expanded', opened ? 'true' : 'false');
                sub.style.display = opened ? '' : 'none';
            });
        });

        this.bindPanelSearch();
    };

    DeptMenu.prototype.bindPanelSearch = function () {
        var self = this;
        var input = this.panel && this.panel.querySelector('[data-role="awa-vmenu-search"]');
        if (!input) {
            return;
        }

        var searchRow = this.panel.querySelector('[data-role="awa-vmenu-search-row"]');
        var searchTimer = null;

        function normalize(text) {
            return (text || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
        }

        function getFilterableItems() {
            return Array.prototype.filter.call(
                self.panel.querySelectorAll(':scope > li.ui-menu-item.level0'),
                function (li) {
                    return !li.classList.contains('awa-vmenu-search-li')
                        && !li.classList.contains('awa-vmenu-search-empty-li');
                }
            );
        }

        function ensureSearchEmptyRow() {
            var existing = self.panel.querySelector('[data-role="awa-vmenu-search-empty"]');
            if (existing) {
                return existing.closest('li');
            }
            if (!searchRow) {
                return null;
            }
            var li = document.createElement('li');
            li.className = 'awa-vmenu-search-empty-li';
            li.setAttribute('data-role', 'awa-vmenu-search-empty');
            li.setAttribute('role', 'none');
            li.innerHTML = '<div class="awa-vmenu-search-empty" aria-live="polite">' +
                '<span class="awa-vmenu-search-empty-icon" aria-hidden="true">' +
                '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" focusable="false" aria-hidden="true">' +
                '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg></span>' +
                '<p class="awa-vmenu-search-empty-text"></p></div>';
            searchRow.insertAdjacentElement('afterend', li);
            return li;
        }

        function filterItems(query) {
            var rawQuery = (query || '').trim();
            var q = normalize(rawQuery);
            var visibleCount = 0;

            getFilterableItems().forEach(function (li) {
                var labelEl = li.querySelector('.navigation__label')
                    || li.querySelector('a.level-top, a.navigation__link');
                var label = normalize(labelEl ? labelEl.textContent : '');
                var match = !q || label.indexOf(q) !== -1;
                li.style.display = match ? '' : 'none';
                if (match) {
                    visibleCount += 1;
                }
            });

            self.panel.querySelectorAll(':scope > .awa-vmenu__divider, :scope > .awa-vmenu__section-label').forEach(function (el) {
                if (!q) {
                    el.style.display = '';
                    return;
                }
                var nextVisible = Array.prototype.find.call(
                    getFilterableItems(),
                    function (item) { return item.style.display !== 'none'; }
                );
                el.style.display = nextVisible ? '' : 'none';
            });

            var expandLink = self.panel.querySelector(':scope > li.expand-category-link');
            if (expandLink) {
                expandLink.style.display = q ? 'none' : '';
            }

            var emptyLi = self.panel.querySelector('[data-role="awa-vmenu-search-empty"]');
            emptyLi = emptyLi ? emptyLi.closest('li') : null;
            if (q && visibleCount === 0) {
                emptyLi = ensureSearchEmptyRow();
                if (emptyLi) {
                    var textEl = emptyLi.querySelector('.awa-vmenu-search-empty-text');
                    if (textEl) {
                        textEl.innerHTML = 'Nenhuma categoria para <span class="awa-vmenu-search-empty-query"></span>';
                        var queryEl = textEl.querySelector('.awa-vmenu-search-empty-query');
                        if (queryEl) {
                            queryEl.textContent = rawQuery;
                        }
                    }
                    emptyLi.classList.add('is-visible');
                    emptyLi.style.display = '';
                }
            } else if (emptyLi) {
                emptyLi.classList.remove('is-visible');
                emptyLi.style.display = 'none';
            }

            if (self.status) {
                if (q) {
                    self.status.textContent = visibleCount > 0
                        ? visibleCount + ' categoria' + (visibleCount !== 1 ? 's' : '') + ' encontrada' + (visibleCount !== 1 ? 's' : '')
                        : 'Nenhuma categoria para "' + rawQuery + '"';
                } else if (self.isOpen) {
                    self.status.textContent = 'Menu de departamentos aberto.';
                }
            }
        }

        input.addEventListener('input', function () {
            clearTimeout(searchTimer);
            var value = input.value;
            searchTimer = window.setTimeout(function () {
                filterItems(value);
            }, 120);
        });

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && input.value) {
                input.value = '';
                filterItems('');
                e.stopPropagation();
            }
        });
    };

    /* ── MobileDrawer ───────────────────────────────────────────────── */
    var mobileDrawer = {
        toggle: null,
        shell: null,
        overlay: null,
        lastFocus: null,
        open: false,

        ensureOverlay: function () {
            if (this.overlay) {
                return this.overlay;
            }
            this.overlay = document.querySelector('.awa-mobile-drawer-overlay');
            if (this.overlay) {
                return this.overlay;
            }
            this.overlay = document.createElement('button');
            this.overlay.type = 'button';
            this.overlay.className = 'awa-mobile-drawer-overlay';
            this.overlay.setAttribute('aria-label', 'Fechar menu');
            this.overlay.setAttribute('aria-hidden', 'true');
            this.overlay.setAttribute('tabindex', '-1');
            document.body.appendChild(this.overlay);
            return this.overlay;
        },

        syncShell: function (isOpen) {
            var targets = [];
            var shell = this.shell;
            if (shell) {
                targets.push(shell);
            }
            ['awa-primary-navigation', 'awa-category-navigation'].forEach(function (id) {
                var el = document.getElementById(id);
                if (el) {
                    targets.push(el);
                }
            });
            targets.forEach(function (target) {
                target.classList.toggle('is-awa-mobile-open', isOpen);
                if (isOpen) {
                    target.style.setProperty('display', 'block', 'important');
                    target.style.setProperty('visibility', 'visible', 'important');
                    target.style.setProperty('opacity', '1', 'important');
                    target.style.setProperty('pointer-events', 'auto', 'important');
                } else {
                    ['display', 'visibility', 'opacity', 'pointer-events', 'position', 'width', 'height', 'z-index'].forEach(function (p) {
                        target.style.removeProperty(p);
                    });
                }
            });
        },

        openDrawer: function () {
            if (!isMobile() || this.open) {
                return;
            }
            this.lastFocus = document.activeElement;
            this.open = true;
            document.body.classList.add('nav-open', 'awa-menu-drawer-open');
            document.body.classList.remove('awa-nav-preflight');
            this.syncShell(true);
            if (this.toggle) {
                this.toggle.setAttribute('aria-expanded', 'true');
            }
            var ov = this.ensureOverlay();
            ov.classList.add('is-active');
            ov.setAttribute('aria-hidden', 'false');
            var focusables = getFocusables(this.shell);
            if (focusables.length) {
                focusables[0].focus();
            }
        },

        closeDrawer: function () {
            if (!this.open) {
                return;
            }
            this.open = false;
            document.body.classList.remove('nav-open', 'awa-menu-drawer-open', 'awa-mobile-drawer-open', 'nav-before-open');
            this.syncShell(false);
            if (this.toggle) {
                this.toggle.setAttribute('aria-expanded', 'false');
            }
            if (this.overlay) {
                this.overlay.classList.remove('is-active');
                this.overlay.setAttribute('aria-hidden', 'true');
            }
            if (this.lastFocus && typeof this.lastFocus.focus === 'function') {
                this.lastFocus.focus();
            } else if (this.toggle) {
                this.toggle.focus();
            }
            this.lastFocus = null;
        },

        bind: function () {
            var self = this;
            this.toggle = document.querySelector('[data-awa-nav-toggle="true"]');
            this.shell = resolveDrawerShell();
            if (!this.toggle) {
                return;
            }

            if (
                isMobile()
                && (
                    document.body.classList.contains('nav-open')
                    || document.body.classList.contains('awa-nav-preflight')
                )
                && !this.open
            ) {
                this.open = true;
                document.body.classList.add('awa-menu-drawer-open');
                document.body.classList.remove('awa-nav-preflight');
                this.syncShell(true);
                if (this.toggle) {
                    this.toggle.setAttribute('aria-expanded', 'true');
                }
            }

            this.toggle.addEventListener('click', function (e) {
                if (!isMobile()) {
                    return;
                }
                e.preventDefault();
                if (self.open) {
                    self.closeDrawer();
                } else {
                    self.openDrawer();
                }
            });

            this.ensureOverlay().addEventListener('click', function () {
                self.closeDrawer();
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && self.open) {
                    self.closeDrawer();
                }
            });
        }
    };

    /* ── HorizontalNav ──────────────────────────────────────────────── */
    function HorizontalNav() {
        this.root = document.querySelector('.navigation.custommenu.main-nav');
    }

    HorizontalNav.prototype.bind = function () {
        if (!this.root) {
            return;
        }
        this.root.setAttribute('role', 'navigation');
        this.root.querySelectorAll('a.level-top').forEach(function (link) {
            if (!link.getAttribute('tabindex')) {
                link.setAttribute('tabindex', '0');
            }
        });
        this.root.addEventListener('keydown', function (e) {
            if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') {
                return;
            }
            var links = Array.prototype.slice.call(this.querySelectorAll('a.level-top, .top-menu > li > a'));
            var idx = links.indexOf(document.activeElement);
            if (idx < 0) {
                return;
            }
            e.preventDefault();
            var next = e.key === 'ArrowRight' ? idx + 1 : idx - 1;
            if (links[next]) {
                links[next].focus();
            }
        }.bind(this.root));
    };

    /* ── Widget entry (per vertical menu nav) ───────────────────────── */
    return function (config, element) {
        if (!window.__AWA_MENU_V2) {
            return;
        }

        var nav = element && element.nodeType === 1
            ? element
            : document.querySelector('[data-role="awa-vertical-menu"]');
        if (!nav) {
            return;
        }

        var dept = new DeptMenu(nav, config || {});
        dept.bind();
        var flyout = new FlyoutPortal(nav.querySelector('[data-role="awa-vertical-menu-panel"]') || nav);
        flyout.mount();

        if (!DOC_BOOTED) {
            DOC_BOOTED = true;
            mobileDrawer.bind();
            (new HorizontalNav()).bind();
            document.body.classList.add('awa-menu-v2-ready');
        }

        nav.dataset.awaMenuControllerReady = '1';
    };
});
