define([
    'jquery'
], function ($) {
    'use strict';

    var SVG_OPEN = '<svg class="awa-mueller-cat-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">';
    var SVG_CLOSE = '</svg>';

    var ICONS = {
        handlebar: '<path d="M4 14V9a8 8 0 0 1 16 0v5"/><line x1="2" y1="14" x2="6" y2="14"/><line x1="18" y1="14" x2="22" y2="14"/>',
        luggage: '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
        shield: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        wrench: '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
        mirror: '<ellipse cx="14" cy="8" rx="6" ry="5"/><path d="M9 12 5 21"/>',
        package: '<path d="M21 16V8l-9-5-9 5v8l9 5z"/><path d="M3.3 7 12 12l8.7-5"/><line x1="12" y1="22" x2="12" y2="12"/>'
    };

    function resolveIconType(name) {
        var lower = (name || '').toLowerCase();

        if (lower.indexOf('guid') !== -1 || lower.indexOf('barra') !== -1) {
            return 'handlebar';
        }

        if (lower.indexOf('baule') !== -1 || lower.indexOf('bagage') !== -1) {
            return 'luggage';
        }

        if (lower.indexOf('protetor') !== -1 || lower.indexOf('carenagem') !== -1 || lower.indexOf('carter') !== -1) {
            return 'shield';
        }

        if (lower.indexOf('retrovisor') !== -1 || lower.indexOf('lente') !== -1 || lower.indexOf('pisca') !== -1) {
            return 'mirror';
        }

        if (lower.indexOf('adaptador') !== -1 || lower.indexOf('suporte') !== -1 || lower.indexOf('antena') !== -1) {
            return 'wrench';
        }

        return 'package';
    }

    function buildIcon(type) {
        return SVG_OPEN + (ICONS[type] || ICONS.package) + SVG_CLOSE;
    }

    return function (config, element) {
        var $nav = $(element);
        var settings = $.extend({
            desktopBreakpoint: 992,
            openModeDesktop: 'hover',
            openModeMobile: 'click',
            disableOverlay: true,
            limitShow: 0,
            keepOpenOnHomeDesktop: false
        }, config || {});
        var uid = (Date.now().toString(36) + Math.random().toString(36).slice(2, 8)).replace(/[^a-z0-9_-]/gi, '');
        var ns = '.awaMuellerMenu-' + uid;
        var $trigger = $nav.find('[data-role="awa-vertical-menu-trigger"], .title-category-dropdown').first();
        var $list = $nav.children('ul.togge-menu.list-category-dropdown').first();
        var $expandLink = $nav.find('.vm-toggle-categories').first();
        var configuredLimitShow = parseInt(settings.limitShow, 10) || 0;
        var desktopPinned = false;
        var overlayGuardObserver = null;
        var listStyleGuardObserver = null;
        var listStyleGuardBusy = false;

        if (!$nav.length || !$trigger.length || !$list.length || $nav.data('awaMuellerMenuInit')) {
            return;
        }

        function isDesktop() {
            return window.innerWidth >= settings.desktopBreakpoint;
        }

        function isHomeContext() {
            var body = document.body;
            if (!body) {
                return false;
            }

            return body.classList.contains('cms-index-index')
                || body.classList.contains('cms-home')
                || body.classList.contains('cms-homepage_ayo_home5')
                || body.classList.contains('cms-homepage_ayo_home5_demo_stage');
        }

        function keepDesktopMenuExpanded() {
            return isDesktop() && !!settings.keepOpenOnHomeDesktop && isHomeContext();
        }

        function getRootList() {
            return $list;
        }

        function getTopItems() {
            return getRootList().children('li.ui-menu-item.level0');
        }

        function getParentItems() {
            return getRootList().children('li.ui-menu-item.level0.parent');
        }

        function injectCategoryIcons() {
            getTopItems().each(function () {
                var $item = $(this);
                var $link = $item.children('a.level-top');
                var titleText;

                if (!$link.length || $link.find('.awa-mueller-cat-icon, .menu-thumb-icon, img').length) {
                    return;
                }

                titleText = $.trim($link.find('> span').first().text()) || $.trim($link.text());
                $link.prepend(buildIcon(resolveIconType(titleText)));
            });
        }

        function getLevelPanels($item) {
            return $item.children('.submenu, .level0.submenu, ul.level0, .subchildmenu');
        }

        function getFirstLevelPanel($item) {
            return getLevelPanels($item).first();
        }

        function normalizeFlyoutPanel($item) {
            var $panel = getFirstLevelPanel($item);
            var $menuGrid;

            if (!$panel.length || !isDesktop()) {
                return;
            }

            $menuGrid = $panel.find('> .row > .subchildmenu.navigation__inner-list--level1').first();

            $menuGrid.children('li.navigation__inner-item.col-1').each(function () {
                this.style.setProperty('width', '50%', 'important');
                this.style.setProperty('max-width', '532px', 'important');
            });

            $menuGrid.children('li.navigation__inner-item.img-subcategory').each(function () {
                var image = this.querySelector('img');

                this.style.setProperty('display', 'block', 'important');
                this.style.setProperty('position', 'absolute', 'important');
                this.style.setProperty('top', '0', 'important');
                this.style.setProperty('left', 'auto', 'important');
                this.style.setProperty('right', '0', 'important');
                this.style.setProperty('width', '532px', 'important');
                this.style.setProperty('max-width', '532px', 'important');
                this.style.setProperty('min-width', '532px', 'important');
                this.style.setProperty('margin', '0', 'important');
                this.style.setProperty('padding', '0', 'important');
                this.style.setProperty('box-sizing', 'border-box');

                if (image) {
                    image.style.setProperty('opacity', '1', 'important');
                    image.style.setProperty('animation', 'none', 'important');
                    image.style.setProperty('width', '100%', 'important');
                    image.style.setProperty('max-width', '532px', 'important');
                    image.style.setProperty('margin', '0', 'important');
                    image.style.setProperty('padding', '0', 'important');
                }
            });
        }

        function getLimitShow() {
            return parseInt($list.attr('data-limit-show'), 10) || configuredLimitShow;
        }

        function setDisplayImportant($element, visible, displayValue) {
            var element = $element && $element.get ? $element.get(0) : null;

            if (!element) {
                return;
            }

            if (visible) {
                element.style.setProperty('display', displayValue || 'block', 'important');
                return;
            }

            element.style.setProperty('display', 'none', 'important');
        }

        function resetRootListInlineStyles() {
            var listElement = getRootList().get(0);

            if (!listElement) {
                return;
            }

            listElement.style.removeProperty('display');
            listElement.style.removeProperty('max-height');
            listElement.style.removeProperty('overflow');
            listElement.style.removeProperty('overflow-x');
            listElement.style.removeProperty('overflow-y');
        }

        function applyDesktopListInlineState(opened) {
            var listElement = getRootList().get(0);

            if (!listElement || !isDesktop()) {
                return;
            }

            if (opened) {
                listElement.style.setProperty('display', 'block', 'important');
                listElement.style.setProperty('max-height', '576px', 'important');
                listElement.style.setProperty('overflow', 'visible', 'important');
                listElement.style.setProperty('overflow-x', 'visible', 'important');
                listElement.style.setProperty('overflow-y', 'visible', 'important');
                return;
            }

            listElement.style.setProperty('display', 'none', 'important');
            listElement.style.setProperty('max-height', 'none', 'important');
            listElement.style.setProperty('overflow', 'visible', 'important');
            listElement.style.setProperty('overflow-x', 'visible', 'important');
            listElement.style.setProperty('overflow-y', 'visible', 'important');
        }

        function enforceDesktopListInlineState() {
            if (!isDesktop()) {
                return;
            }

            if ($nav.hasClass('awa-menu-expanded') || getRootList().hasClass('menu-open') || getRootList().hasClass('vmm-open')) {
                applyDesktopListInlineState(true);
                return;
            }

            applyDesktopListInlineState(false);
        }

        function setRootSubmenuState(opened) {
            getRootList().toggleClass('awa-mueller-submenu-open', !!opened);
        }

        function setMenuOpen(opened) {
            getRootList().toggleClass('menu-open', !!opened);
            getRootList().toggleClass('vmm-open', !!opened);
            $nav.toggleClass('awa-menu-expanded', !!opened);
            $trigger.toggleClass('active', !!opened).attr('aria-expanded', opened ? 'true' : 'false');
        }

        function disableOverlayArtifacts() {
            if (!settings.disableOverlay) {
                return;
            }

            $('.shadow_bkg, .shadow_bkg_show, .vmm-overlay').css({
                display: 'none',
                opacity: 0,
                visibility: 'hidden',
                pointerEvents: 'none'
            });

            $('body').removeClass('background_shadow background_shadow_show shadow_bkg_show');
        }

        function setExpandedState($item, expanded) {
            var value = expanded ? 'true' : 'false';

            $item.attr('aria-expanded', value);
            $item.attr('data-open', expanded ? 'true' : 'false');
            $item.children('a.level-top').attr('aria-expanded', value);
            $item.children('.open-children-toggle').attr('aria-expanded', value);
        }

        function closeDesktopItems() {
            getTopItems().removeClass('awa-mueller-open vmm-active');
            getTopItems().each(function () {
                setExpandedState($(this), false);
            });
            setRootSubmenuState(false);
        }

        function closeMobileItem($item) {
            var $panel = getFirstLevelPanel($item);

            $item.removeClass('_active awa-mueller-open');
            $item.children('a').removeClass('ui-state-active');
            setExpandedState($item, false);

            if ($panel.length) {
                $panel.removeClass('opened').stop(true, true).slideUp(170);
            }
        }

        function openMobileItem($item) {
            var $panel = getFirstLevelPanel($item);

            if (!$panel.length) {
                return;
            }

            $item.addClass('_active awa-mueller-open');
            $item.children('a').addClass('ui-state-active');
            setExpandedState($item, true);
            $panel.addClass('opened').stop(true, true).slideDown(170);
        }

        function closeMobileSiblingItems($item) {
            $item.siblings('.ui-menu-item.parent, .ui-menu-item.level0.parent').each(function () {
                closeMobileItem($(this));
            });
        }

        function openMenu() {
            setMenuOpen(true);
            getRootList().stop(true, true).show();

            if (isDesktop()) {
                applyDesktopListInlineState(true);
                window.requestAnimationFrame(function () {
                    applyDesktopListInlineState(true);
                });
                window.setTimeout(function () {
                    applyDesktopListInlineState(true);
                }, 90);
                disableOverlayArtifacts();
                return;
            }

            if (!settings.disableOverlay) {
                $('body').addClass('background_shadow_show');
            }
        }

        function closeMenu(forceDesktopClose) {
            if (isDesktop() && !forceDesktopClose && keepDesktopMenuExpanded()) {
                openMenu();
                return;
            }

            setMenuOpen(false);
            closeDesktopItems();

            if (isDesktop()) {
                applyDesktopListInlineState(false);
                getRootList().stop(true, true).hide();
                disableOverlayArtifacts();
                return;
            }

            resetRootListInlineStyles();
            getRootList().stop(true, true).slideUp(160);

            if (!settings.disableOverlay) {
                $('body').removeClass('background_shadow_show');
            }
        }

        function syncMenuVisibility() {
            if (isDesktop()) {
                if (keepDesktopMenuExpanded()) {
                    openMenu();
                } else if (desktopPinned || getRootList().hasClass('menu-open')) {
                    openMenu();
                } else {
                    getRootList().hide();
                    setMenuOpen(false);
                    applyDesktopListInlineState(false);
                }

                disableOverlayArtifacts();
                return;
            }

            resetRootListInlineStyles();
            if (getRootList().hasClass('menu-open')) {
                getRootList().show();
            } else {
                getRootList().hide();
            }
        }

        function openDesktopItem($item) {
            var $siblings;

            if (!$item.length) {
                return;
            }

            $siblings = $item.siblings('.ui-menu-item.level0');
            $siblings.removeClass('awa-mueller-open vmm-active');
            $siblings.each(function () {
                setExpandedState($(this), false);
            });

            $item.addClass('awa-mueller-open vmm-active');
            setExpandedState($item, true);
            setRootSubmenuState(true);
            normalizeFlyoutPanel($item);
        }

        function closeAll() {
            desktopPinned = false;
            getParentItems().each(function () {
                closeMobileItem($(this));
            });
            closeDesktopItems();
            closeMenu(true);
            disableOverlayArtifacts();
        }

        function initShowMore() {
            var limitShow = getLimitShow();
            var $expandRow = $expandLink.closest('.expand-category-link');

            if (!$expandLink.length || limitShow <= 0) {
                setDisplayImportant($expandRow, false);
                return;
            }

            if (getTopItems().length <= limitShow) {
                setDisplayImportant($expandRow, false);
                return;
            }

            getTopItems().each(function (index) {
                if (index >= limitShow) {
                    var $item = $(this);

                    $item.addClass('orther-link');
                    setDisplayImportant($item, false);
                }
            });

            setDisplayImportant($expandRow, true, 'block');

            $expandLink.off('click' + ns + ' keydown' + ns);
            $expandLink.on('click' + ns, function (event) {
                var $link = $(this);
                var expanding = !$link.hasClass('expanding');
                var $hiddenItems = getRootList().children('li.ui-menu-item.level0.orther-link');

                event.preventDefault();

                $link.toggleClass('expanding', expanding);
                $link.closest('.expand-category-link').toggleClass('expanding', expanding);
                $link.attr('aria-expanded', expanding ? 'true' : 'false');

                if ($link.data('show-text') && $link.data('hide-text')) {
                    $link.find('span').text(expanding ? $link.data('hide-text') : $link.data('show-text'));
                }

                $hiddenItems.each(function () {
                    setDisplayImportant($(this), expanding, 'block');
                });
            });

            $expandLink.on('keydown' + ns, function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    $(this).trigger('click');
                }
            });
        }

        function bindDesktopMenu() {
            var $parents = getParentItems();

            $parents.off('mouseenter' + ns + ' focusin' + ns + ' mouseleave' + ns);

            if (settings.openModeDesktop !== 'hover') {
                return;
            }

            $parents.on('mouseenter' + ns + ' focusin' + ns, function () {
                if (!isDesktop()) {
                    return;
                }

                openMenu();
                openDesktopItem($(this));
                disableOverlayArtifacts();
            });

            $parents.on('mouseleave' + ns, function () {
                if (!isDesktop()) {
                    return;
                }

                setExpandedState($(this), false);
                $(this).removeClass('awa-mueller-open');
            });

            $nav.off('mouseleave' + ns).on('mouseleave' + ns, function () {
                if (isDesktop()) {
                    closeDesktopItems();
                    if (!desktopPinned && !keepDesktopMenuExpanded()) {
                        closeMenu(true);
                    }
                }
            });
        }

        function bindTriggerEvents() {
            $trigger.off('click' + ns + ' keydown' + ns + ' mouseenter' + ns + ' focusin' + ns);

            $trigger.on('click' + ns, function (event) {
                event.preventDefault();

                if (isDesktop()) {
                    if (keepDesktopMenuExpanded()) {
                        openMenu();
                        return;
                    }

                    desktopPinned = !desktopPinned;

                    if (!desktopPinned) {
                        closeMenu(true);
                    } else {
                        openMenu();
                    }
                    return;
                }

                if (getRootList().hasClass('menu-open')) {
                    closeMenu(true);
                } else {
                    openMenu();
                }
            });

            $trigger.on('mouseenter' + ns + ' focusin' + ns, function () {
                if (!isDesktop() || settings.openModeDesktop !== 'hover') {
                    return;
                }

                if (!desktopPinned) {
                    openMenu();
                }
            });

            $trigger.on('keydown' + ns, function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    $trigger.trigger('click');
                    return;
                }

                if (event.key === 'Escape') {
                    event.preventDefault();
                    desktopPinned = false;
                    closeMenu(true);
                }
            });
        }

        function bindMobileSubmenuEvents() {
            getRootList().off('click' + ns, '.ui-menu-item.parent > .open-children-toggle, .ui-menu-item.level0.parent > .open-children-toggle');
            getRootList().off('keydown' + ns, '.ui-menu-item.parent > .open-children-toggle, .ui-menu-item.level0.parent > .open-children-toggle');

            if (settings.openModeMobile !== 'click') {
                return;
            }

            getRootList().on('click' + ns, '.ui-menu-item.parent > .open-children-toggle, .ui-menu-item.level0.parent > .open-children-toggle', function (event) {
                var $item = $(this).closest('.ui-menu-item.parent, .ui-menu-item.level0.parent');
                var $panel = getFirstLevelPanel($item);
                var opening;

                event.preventDefault();
                event.stopPropagation();

                if (isDesktop()) {
                    return;
                }

                if (!$panel.length) {
                    return;
                }

                opening = !$item.hasClass('_active') || !$panel.hasClass('opened');

                if (opening) {
                    closeMobileSiblingItems($item);
                    openMobileItem($item);
                } else {
                    closeMobileItem($item);
                }

                if ($item.hasClass('level0')) {
                    setRootSubmenuState(opening);
                }

                disableOverlayArtifacts();
            });

            getRootList().on('keydown' + ns, '.ui-menu-item.parent > .open-children-toggle, .ui-menu-item.level0.parent > .open-children-toggle', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    $(this).trigger('click');
                }
            });
        }

        function bindGlobalEvents() {
            $(document).off('keydown' + ns).on('keydown' + ns, function (event) {
                if (event.key === 'Escape') {
                    desktopPinned = false;
                    if (!isDesktop() || !keepDesktopMenuExpanded()) {
                        closeAll();
                    } else {
                        closeDesktopItems();
                    }
                }
            });

            $(document).off('click' + ns).on('click' + ns, function (event) {
                if (isDesktop()) {
                    return;
                }

                if ($(event.target).closest($nav).length) {
                    return;
                }

                closeMenu(true);
            });

            $(window).off('resize' + ns).on('resize' + ns, function () {
                disableOverlayArtifacts();
                closeDesktopItems();

                if (isDesktop()) {
                    getParentItems().each(function () {
                        getLevelPanels($(this)).removeAttr('style');
                    });
                    if (!desktopPinned && !keepDesktopMenuExpanded()) {
                        applyDesktopListInlineState(false);
                    }
                } else {
                    desktopPinned = false;
                    resetRootListInlineStyles();
                }

                syncMenuVisibility();
            });
        }

        function bindOverlayGuard() {
            if (!window.MutationObserver || !document.body || overlayGuardObserver) {
                return;
            }

            overlayGuardObserver = new MutationObserver(function () {
                if (isDesktop()) {
                    disableOverlayArtifacts();
                }
            });

            overlayGuardObserver.observe(document.body, {
                attributes: true,
                attributeFilter: ['class']
            });
        }

        function bindListStyleGuard() {
            var listElement;

            if (!window.MutationObserver || listStyleGuardObserver) {
                return;
            }

            listElement = getRootList().get(0);

            if (!listElement) {
                return;
            }

            listStyleGuardObserver = new MutationObserver(function () {
                if (!isDesktop() || listStyleGuardBusy) {
                    return;
                }

                listStyleGuardBusy = true;
                window.requestAnimationFrame(function () {
                    enforceDesktopListInlineState();
                    listStyleGuardBusy = false;
                });
            });

            listStyleGuardObserver.observe(listElement, {
                attributes: true,
                attributeFilter: ['style', 'class']
            });
        }

        function setupStructure() {
            getParentItems().each(function () {
                var $item = $(this);

                setExpandedState($item, false);
                $item.attr('data-level', $item.attr('data-level') || '0');

                $item.children('.submenu, .level0.submenu').attr('data-flyout-level', '2');
                $item.find('.subchildmenu .subchildmenu').attr('data-flyout-level', '3');
                normalizeFlyoutPanel($item);
            });

            getRootList().addClass('todasascategorias');
            injectCategoryIcons();
            setMenuOpen(false);
            setRootSubmenuState(false);
        }

        setupStructure();
        initShowMore();
        bindDesktopMenu();
        bindTriggerEvents();
        bindMobileSubmenuEvents();
        bindGlobalEvents();
        bindOverlayGuard();
        bindListStyleGuard();
        syncMenuVisibility();
        disableOverlayArtifacts();

        $nav.data('awaMuellerMenuInit', true);
    };
});
