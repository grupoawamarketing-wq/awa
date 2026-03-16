/**
 * AWA B2B — Vertical Mega Menu Controller (Mueller-style)
 *
 * position:fixed panel escapes overflow:hidden ancestors.
 * Intercepts vertical-menu-init.js .show()/.hide() calls which set
 * display:block inline style — we need display:flex instead.
 *
 * @module js/awa-vertical-mega-menu
 */
define(['jquery'], function ($) {
    'use strict';

    var DESKTOP_BP = 992;
    var ACTIVE_CLASS = 'vmm-active';
    var OPEN_CLASS = 'vmm-open';
    var OVERLAY_ID = 'vmm-backdrop';
    var NS = '.aVMM3';

    /* ================================================================
       CATEGORY ICONS — inline SVG (Lucide-style, 24×24 stroke icons)
       Rendered at 20×20 via CSS class .vmm-cat-icon
       ================================================================ */
    var SVG_OPEN = '<svg class="vmm-cat-icon" xmlns="http://www.w3.org/2000/svg" ' +
        'viewBox="0 0 24 24" fill="none" stroke="currentColor" ' +
        'stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">';
    var SVG_CLOSE = '</svg>';

    /** SVG inner content per icon type */
    var ICON_PATHS = {
        /* Barras De Guidão, Guidões — T-handlebar with grips */
        handlebar:
            '<path d="M4 14V9a8 8 0 0 1 16 0v5"/>' +
            '<line x1="2" y1="14" x2="6" y2="14"/>' +
            '<line x1="18" y1="14" x2="22" y2="14"/>',

        /* Bauletos, Bagageiros — box with handle */
        luggage:
            '<rect x="3" y="7" width="18" height="13" rx="2"/>' +
            '<path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>' +
            '<line x1="10" y1="12" x2="14" y2="12"/>',

        /* Retrovisores — mirror on stem */
        mirror:
            '<ellipse cx="14" cy="8" rx="6" ry="5"/>' +
            '<path d="M9 12 5 21"/>',

        /* Piscas, Blocos Oticos, Farol — headlight beam */
        light:
            '<path d="M9 18h6"/><path d="M10 22h4"/>' +
            '<path d="M12 2a7 7 0 0 0-4 12.7V17h8v-2.3A7 7 0 0 0 12 2z"/>',

        /* Farol, Lente de Farol — headlight circle with rays */
        headlight:
            '<circle cx="12" cy="12" r="5"/>' +
            '<line x1="12" y1="2" x2="12" y2="4"/>' +
            '<line x1="12" y1="20" x2="12" y2="22"/>' +
            '<line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/>' +
            '<line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>' +
            '<line x1="2" y1="12" x2="4" y2="12"/>' +
            '<line x1="20" y1="12" x2="22" y2="12"/>',

        /* Protetores De Carter, Protetor De Carenagem — shield */
        shield:
            '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',

        /* Linha Honda/Suzuki/Yamaha, Linha Esportiva, Capacete — motorcycle */
        motorcycle:
            '<circle cx="5" cy="17" r="3"/><circle cx="19" cy="17" r="3"/>' +
            '<path d="M5 14l4-7h4l3 3.5h4"/><path d="M9 7 8 4h3"/>',

        /* Capacetes — helmet shape */
        helmet:
            '<path d="M12 2C7.03 2 3 6.5 3 12c0 2.5.9 4.8 2.4 6.5H18.6C20.1 16.8 21 14.5 21 12c0-5.5-4.03-10-9-10z"/>' +
            '<path d="M3 12h18"/>' +
            '<path d="M5 16h6"/>',

        /* Adaptadores, Suportes — wrench */
        wrench:
            '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0' +
            'l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3' +
            'l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',

        /* Roldanas, Embreagem — gear cog */
        cog:
            '<circle cx="12" cy="12" r="3"/>' +
            '<path d="M12 1v4m0 14v4M4.93 4.93l2.83 2.83m8.48 8.48l2.83 2.83' +
            'M1 12h4m14 0h4M4.93 19.07l2.83-2.83m8.48-8.48l2.83-2.83"/>',

        /* Corrente, Capas De Corrente — chain sprocket */
        chain:
            '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>' +
            '<path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',

        /* Pneus — tire wheel */
        tire:
            '<circle cx="12" cy="12" r="10"/>' +
            '<circle cx="12" cy="12" r="6"/>' +
            '<line x1="12" y1="2" x2="12" y2="6"/>' +
            '<line x1="12" y1="18" x2="12" y2="22"/>' +
            '<line x1="2" y1="12" x2="6" y2="12"/>' +
            '<line x1="18" y1="12" x2="22" y2="12"/>',

        /* Amortecedor, Suspensão — spring/shock */
        shock:
            '<line x1="12" y1="2" x2="12" y2="5"/>' +
            '<path d="M9 5h6v2H9zm1 2h4v2h-4zm-1 2h6v2H9zm1 2h4v2h-4zm-1 2h6v2H9z"/>' +
            '<line x1="12" y1="19" x2="12" y2="22"/>',

        /* Freios, Disco de Freio — brake disc */
        brake:
            '<circle cx="12" cy="12" r="9"/>' +
            '<circle cx="12" cy="12" r="4"/>' +
            '<circle cx="12" cy="5" r="1"/>' +
            '<circle cx="18.5" cy="8.5" r="1"/>' +
            '<circle cx="18.5" cy="15.5" r="1"/>' +
            '<circle cx="12" cy="19" r="1"/>' +
            '<circle cx="5.5" cy="15.5" r="1"/>' +
            '<circle cx="5.5" cy="8.5" r="1"/>',

        /* Tanque, Combustível — fuel/tank */
        fuel:
            '<path d="M3 22V8l5-6h8l5 6v14H3z"/>' +
            '<path d="M3 13h18"/>' +
            '<path d="M17 3v5h4"/>',

        /* Banco, Selim — seat */
        seat:
            '<rect x="3" y="13" width="18" height="5" rx="2.5"/>' +
            '<path d="M5 13V9a7 7 0 0 1 14 0v4"/>',

        /* Escapamento, Silencioso — exhaust pipe */
        exhaust:
            '<path d="M3 17h14a4 4 0 0 0 4-4V9"/>' +
            '<line x1="3" y1="17" x2="3" y2="20"/>' +
            '<path d="M17 9h4V6h-4v3z"/>',

        /* Filtro de Ar, Filtro de Óleo — filter funnel */
        filter:
            '<polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>',

        /* Borrachas — O-ring */
        ring:
            '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="5"/>',

        /* Carcaças — 3D cube/housing */
        cube:
            '<path d="M21 16V8l-9-5-9 5v8l9 5z"/>' +
            '<path d="M3.3 7 12 12l8.7-5"/><line x1="12" y1="22" x2="12" y2="12"/>',

        /* Manetes — brake lever */
        lever:
            '<path d="M6 3v6a2 2 0 0 0 2 2h9"/>' +
            '<circle cx="19" cy="11" r="2"/><line x1="4" y1="3" x2="8" y2="3"/>',

        /* Pedaleiras, Estribos — foot peg */
        footpeg:
            '<rect x="5" y="9" width="14" height="6" rx="1.5"/>' +
            '<path d="M9 9V5m6 4V5M9 15v4m6-4v4"/>',

        /* Cavaletes — kickstand */
        kickstand:
            '<path d="M12 3v10"/><path d="M7 21l5-8 5 8"/>',

        /* Velocímetro, Painel — speedometer */
        speedometer:
            '<path d="M12 2a10 10 0 0 1 10 10"/>' +
            '<path d="M2 12a10 10 0 0 0 10 10"/>' +
            '<path d="M12 12L8 8"/>' +
            '<circle cx="12" cy="12" r="2"/>',

        /* Antenas — signal/antenna */
        signal:
            '<line x1="12" y1="20" x2="12" y2="10"/>' +
            '<path d="M8.5 6.5a5 5 0 0 1 7 0"/>' +
            '<path d="M5.5 3.5a9 9 0 0 1 13 0"/>',

        /* Kit, Kits — package/box */
        kit:
            '<path d="M16.5 9.4l-9-5.19M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>' +
            '<polyline points="3.27 6.96 12 12.01 20.73 6.96"/>' +
            '<line x1="12" y1="22.08" x2="12" y2="12"/>',

        /* Super Ofertas, Oferta — sale tag */
        tag:
            '<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10' +
            'l8.59 8.59a2 2 0 0 1 0 2.82z"/><circle cx="7" cy="7" r="1"/>'  ,

        /* Acessórios — star badge */
        star:
            '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>'
    };

    /**
     * Map category name (lowercase, trimmed) to icon type.
     * Matched against indexOf for flexible substring matching.
     */
    var CATEGORY_ICON_MAP = [
        /* exact or keyword → icon type */
        { k: 'barra',        icon: 'handlebar' },
        { k: 'guid',         icon: 'handlebar' },    /* guidão, guidões */
        { k: 'bauleto',      icon: 'luggage' },
        { k: 'bagageiro',    icon: 'luggage' },
        { k: 'retrovisor',   icon: 'mirror' },
        { k: 'capacete',     icon: 'helmet' },
        { k: 'pisca',        icon: 'light' },
        { k: 'bloco',        icon: 'headlight' },    /* blocos oticos */
        { k: 'farol',        icon: 'headlight' },
        { k: 'lente',        icon: 'headlight' },
        { k: 'protetor',     icon: 'shield' },        /* carter + carenagem */
        { k: 'carter',       icon: 'shield' },
        { k: 'carenagem',    icon: 'shield' },
        { k: 'pneu',         icon: 'tire' },
        { k: 'roda',         icon: 'tire' },
        { k: 'amortecedor',  icon: 'shock' },
        { k: 'suspensao',    icon: 'shock' },
        { k: 'suspensão',    icon: 'shock' },
        { k: 'freio',        icon: 'brake' },
        { k: 'disco',        icon: 'brake' },
        { k: 'pastilha',     icon: 'brake' },
        { k: 'corrente',     icon: 'chain' },
        { k: 'capa',         icon: 'chain' },         /* capas de corrente */
        { k: 'tanque',       icon: 'fuel' },
        { k: 'combustiv',    icon: 'fuel' },
        { k: 'banco',        icon: 'seat' },
        { k: 'selim',        icon: 'seat' },
        { k: 'escapamento',  icon: 'exhaust' },
        { k: 'silencioso',   icon: 'exhaust' },
        { k: 'filtro',       icon: 'filter' },
        { k: 'velocimetro',  icon: 'speedometer' },
        { k: 'velocímetro',  icon: 'speedometer' },
        { k: 'painel',       icon: 'speedometer' },
        { k: 'linha',        icon: 'motorcycle' },    /* linha honda/suzuki/yamaha/esportiva */
        { k: 'esportiva',    icon: 'motorcycle' },
        { k: 'embreagem',    icon: 'cog' },
        { k: 'roldana',      icon: 'cog' },
        { k: 'adaptador',    icon: 'wrench' },
        { k: 'suporte',      icon: 'wrench' },
        { k: 'borracha',     icon: 'ring' },
        { k: 'carca',        icon: 'cube' },          /* carcaças */
        { k: 'manete',       icon: 'lever' },
        { k: 'pedaleira',    icon: 'footpeg' },
        { k: 'pedal',        icon: 'footpeg' },
        { k: 'estribo',      icon: 'footpeg' },
        { k: 'cavalete',     icon: 'kickstand' },
        { k: 'antena',       icon: 'signal' },
        { k: 'kit',          icon: 'kit' },
        { k: 'acessorio',    icon: 'star' },
        { k: 'acessório',    icon: 'star' },
        { k: 'super oferta', icon: 'tag' },
        { k: 'oferta',       icon: 'tag' }
    ];

    /** Build full SVG string for an icon type */
    function buildIconSvg(type) {
        var inner = ICON_PATHS[type];
        return inner ? SVG_OPEN + inner + SVG_CLOSE : '';
    }

    /** Find icon type for a category name — fallback to 'cube' if no match */
    function resolveIconType(name) {
        var lower = (name || '').toLowerCase().trim();
        for (var i = 0; i < CATEGORY_ICON_MAP.length; i++) {
            if (lower.indexOf(CATEGORY_ICON_MAP[i].k) !== -1) {
                return CATEGORY_ICON_MAP[i].icon;
            }
        }
        return 'cube'; /* generic fallback — 3D box icon */
    }

    var iconsInjected = false;

    /**
     * Inject SVG icons into L0 menu items that don't already have an
     * admin-uploaded icon (.menu-thumb-icon).
     */
    function injectCategoryIcons($panel) {
        if (iconsInjected) { return; }
        iconsInjected = true;

        $panel.children('.ui-menu-item.level0').each(function () {
            var $a = $(this).children('a.level-top');

            /* ---- FORCE SVG INJECTION: Remove any pre-existing Magento icons/images ---- */
            $a.find('.menu-thumb-icon').remove();
            $a.find('img').remove();

            /* Skip if we already injected an SVG in a previous pass */
            if ($a.find('.vmm-cat-icon').length) { return; }

            var catName = $a.find('> span').first().text();
            var iconType = resolveIconType(catName);
            if (iconType) {
                var svgHtml = buildIconSvg(iconType);
                if (svgHtml) {
                    $a.prepend(svgHtml);
                }
            }
        });
    }

    function isDesktop() {
        return window.innerWidth >= DESKTOP_BP;
    }

    function getOverlay() {
        var $o = $('#' + OVERLAY_ID);
        if (!$o.length) {
            $o = $('<div>').attr('id', OVERLAY_ID).addClass('vmm-overlay').appendTo('body');
        }
        return $o;
    }

    function positionPanel($trigger, $panel) {
        var rect = $trigger.get(0).getBoundingClientRect();
        var vpW  = window.innerWidth;
        var panelW = Math.min(vpW - rect.left - 8, 980);
        panelW = Math.max(panelW, 560);
        var topPx   = rect.bottom.toFixed(1) + 'px';
        var leftPx  = rect.left.toFixed(1)   + 'px';
        var widthPx = panelW.toFixed(1)       + 'px';

        $panel[0].style.setProperty('--vmm-top',   topPx);
        $panel[0].style.setProperty('--vmm-left',  leftPx);
        $panel[0].style.setProperty('--vmm-width', widthPx);

        /* Also inject onto each .level0.submenu and .vmm-empty-submenu so their
           position:fixed left = panel-left + 240px, width = panel-width - 240px, top = panel-top */
        $panel.find('> li.ui-menu-item.level0 > .level0.submenu, > li.ui-menu-item.level0 > .vmm-empty-submenu').each(function () {
            this.style.setProperty('--vmm-top',   topPx);
            this.style.setProperty('--vmm-left',  leftPx);
            this.style.setProperty('--vmm-width', widthPx);
        });
    }

    /**
     * Inject product count badge + category thumbnail into subcategory items.
     * Reads data-product-count and data-cat-image from <li> elements
     * injected by SafeVerticalmenu.php (Phase C).
     */
    function injectSubcatMeta($subchildmenu) {
        if (!$subchildmenu || !$subchildmenu.length) { return; }
        /* Only inject once per subchildmenu */
        if ($subchildmenu.data('vmmMetaInjected')) { return; }
        $subchildmenu.data('vmmMetaInjected', true);

        $subchildmenu.children('li.ui-menu-item').each(function () {
            var $li = $(this);
            var count = $li.attr('data-product-count');
            var imgUrl = $li.attr('data-cat-image');
            var $a = $li.children('a').first();
            if (!$a.length) { return; }

            /* Product count badge */
            if (count && parseInt(count, 10) > 0 && !$a.find('.vmm-product-count').length) {
                $a.find('> span').first().append(
                    '<span class="vmm-product-count">' + parseInt(count, 10) + '</span>'
                );
            }

            /* Category thumbnail */
            if (imgUrl && !$a.find('.vmm-subcat-thumb').length) {
                $a.prepend(
                    '<img class="vmm-subcat-thumb" src="' + imgUrl +
                    '" alt="" loading="lazy" width="36" height="36" />'
                );
            }
        });
    }

    function activateItem($item) {
        $item.siblings('.ui-menu-item.level0').removeClass(ACTIVE_CLASS);
        /* Reset aria-expanded em todos os irmãos */
        $item.siblings('.ui-menu-item.level0').children('a.level-top').attr('aria-expanded', 'false').attr('tabindex', '-1');
        $item.addClass(ACTIVE_CLASS);
        /* Roving tabindex — active item becomes tabbable */
        $item.children('a.level-top').attr('tabindex', '0');
        /* Marcar item ativo como expandido se tiver submenu */
        if ($item.children('.level0.submenu').length) {
            $item.children('a.level-top').attr('aria-expanded', 'true');
        }

        /* ---- Inject category title into right pane header ---- */
        var $submenu = $item.children('.level0.submenu');
        var catName = ($item.children('a.level-top').clone().children().remove().end().text() || '').trim();
        var catUrl  = $item.children('a.level-top').attr('href') || '#';

        if ($submenu.length) {
            var $header = $submenu.find('.vmm-category-header');
            if (!$header.length) {
                $header = $(
                    '<div class="vmm-category-header">' +
                        '<span class="vmm-category-header__title"></span>' +
                    '</div>'
                ).prependTo($submenu);
            }
            $header.find('.vmm-category-header__title').text(catName);

            /* "Ver tudo" link at the bottom of the subcategory grid */
            var $subchildmenu = $submenu.find('.subchildmenu');
            if ($subchildmenu.length) {
                var $viewAllItem = $subchildmenu.find('.vmm-view-all-item');
                if (!$viewAllItem.length) {
                    $viewAllItem = $('<li class="vmm-view-all-item"><a class="vmm-view-all-link" href=""></a></li>').appendTo($subchildmenu);
                }
                $viewAllItem.find('.vmm-view-all-link').attr('href', catUrl).text('Ver tudo em ' + catName);
            }

            /* ---- Phase C: inject product count + category thumbnail ---- */
            injectSubcatMeta($subchildmenu);

            /* Remove empty-state if subcats exist */
            $submenu.find('.vmm-empty-state').remove();
        } else {
            /* ---- Empty right pane — category without subcategories ---- */
            /* Build a temporary submenu with elegant empty state */
            var $emptySubmenu = $item.find('.vmm-empty-submenu');
            if (!$emptySubmenu.length) {
                $emptySubmenu = $(
                    '<div class="level0 submenu vmm-empty-submenu">' +
                        '<div class="vmm-category-header">' +
                            '<span class="vmm-category-header__title"></span>' +
                        '</div>' +
                        '<div class="vmm-empty-state">' +
                            '<svg class="vmm-empty-state__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">' +
                                '<path d="M21 16V8l-9-5-9 5v8l9 5z"/>' +
                                '<path d="M3.3 7 12 12l8.7-5"/><line x1="12" y1="22" x2="12" y2="12"/>' +
                            '</svg>' +
                            '<p class="vmm-empty-state__text">Explore todos os produtos desta categoria</p>' +
                            '<a class="vmm-empty-state__link" href="">Ver produtos</a>' +
                        '</div>' +
                    '</div>'
                ).appendTo($item);
            }
            $emptySubmenu.find('.vmm-category-header__title').text(catName);
            $emptySubmenu.find('.vmm-empty-state__link').attr('href', catUrl);
        }
    }

    /**
     * Show ALL L0 items regardless of limitShow JS logic.
     * vertical-menu-init.js hides items beyond limitShow on desktop,
     * but for the mega panel we want all categories visible.
     */
    function showAllCategories($panel) {
        $panel.find('> li.ui-menu-item.level0').each(function () {
            /* Remove any inline display:none set by limitShow JS */
            var $li = $(this);
            if ($li.css('display') === 'none' || $li[0].style.display === 'none') {
                $li.css('display', '');
            }
        });
    }

    return function (config, element) {
        var $nav = $(element);
        if (!$nav.length || $nav.data('awVMM3')) { return; }
        $nav.data('awVMM3', 1);

        var $trigger = $nav.find('h2.title-category-dropdown, .our_categories.title-category-dropdown').first();
        var $panel   = $nav.find('.togge-menu.list-category-dropdown');
        if (!$panel.length) { return; }

        var $overlay = getOverlay();

        /* ---- ARIA setup ------------------------------------------- */
        $trigger.attr('aria-haspopup', 'true').attr('aria-expanded', 'false');
        $panel.attr('role', 'menubar').attr('aria-label', 'Categorias de produtos');
        $panel.find('> li.ui-menu-item.level0').each(function () {
            var $li = $(this);
            $li.attr('role', 'none'); /* li is presentational in menubar pattern */
            var $a  = $li.children('a.level-top');
            $a.attr('role', 'menuitem').attr('tabindex', '-1');
            if ($li.children('.level0.submenu').length) {
                $a.attr('aria-haspopup', 'true').attr('aria-expanded', 'false');
            }
        });
        /* Make the first menuitem tabbable by default */
        $panel.find('> li.ui-menu-item.level0:first-child > a.level-top').attr('tabindex', '0');

        /* ARIA on submenus */
        $panel.find('.level0.submenu').attr('role', 'menu');
        $panel.find('.level0.submenu .subchildmenu').attr('role', 'group');
        $panel.find('.level0.submenu .subchildmenu li.ui-menu-item').attr('role', 'none');
        $panel.find('.level0.submenu .subchildmenu li.ui-menu-item > a').attr('role', 'menuitem');

        /* ---- EARLY SVG INJECTION (Phase 2.6) ---------------------- */
        /* Inject icons immediately at init — don't wait for panel open */
        injectCategoryIcons($panel);

        /* ---- Open / Close ----------------------------------------- */

        var openScrollY = 0;

        function openPanel() {
            if (!isDesktop()) { return; }

            positionPanel($trigger.length ? $trigger : $nav, $panel);
            $('body').removeClass('background_shadow background_shadow_show shadow_bkg_show');

            /* Force grid regardless of any inline display:block from parent JS */
            $panel.css('display', 'grid');
            $panel.addClass(OPEN_CLASS);
            openScrollY = window.scrollY || window.pageYOffset || 0;
            $overlay.addClass('vmm-overlay-show');

            showAllCategories($panel);

            /* Inject category icons on first open */
            injectCategoryIcons($panel);

            /* Pre-activate first category with a submenu */
            if (!$panel.find('.' + ACTIVE_CLASS).length) {
                var $first = $panel.children('.ui-menu-item.level0.parent').first();
                if ($first.length) { activateItem($first); }
            }

            $trigger.attr('aria-expanded', 'true');
        }

        function closePanel() {
            $panel.css('display', '');
            $panel.removeClass(OPEN_CLASS);
            $panel.find('.' + ACTIVE_CLASS).removeClass(ACTIVE_CLASS);
            /* Reset aria-expanded em todos os itens */
            $panel.find('> li.ui-menu-item.level0 > a.level-top').attr('aria-expanded', 'false');
            $overlay.removeClass('vmm-overlay-show');
            $trigger.attr('aria-expanded', 'false');
        }

        /* ---- MutationObserver: watch class AND style changes -------- */
        /* vertical-menu-init.js calls $list.show() → display:block.     */
        /* MutationObserver catches the style attribute change and fixes  */
        /* it back to flex immediately.                                   */

        var panelEl = $panel.get(0);

        var observer = new (window.MutationObserver || window.WebKitMutationObserver)(function (mutations) {
            mutations.forEach(function (m) {
                /* Class changed: menu-open added → ensure flex */
                if (m.attributeName === 'class') {
                    if ($panel.hasClass('menu-open') && isDesktop()) {
                        /* Parent JS just opened the menu */
                        window.setTimeout(openPanel, 0);
                    } else if (!$panel.hasClass('menu-open') && $panel.hasClass(OPEN_CLASS)) {
                        closePanel();
                    }
                }
                /* Style changed: parent JS called .show() → display:block */
                if (m.attributeName === 'style' && isDesktop() && $panel.hasClass('menu-open')) {
                    var curDisplay = panelEl.style.display;
                    if (curDisplay === 'block' || curDisplay === '') {
                        /* Fix to grid without triggering another mutation */
                        window.requestAnimationFrame(function () {
                            panelEl.style.display = 'grid';
                            showAllCategories($panel);
                        });
                    }
                }
            });
        });

        observer.observe(panelEl, {
            attributes: true,
            attributeFilter: ['class', 'style']
        });

        /* ---- Trigger hover → open (desktop, Amazon-style delay) --- */
        var openTimer = null;
        var leaveTimer = null;
        var HOVER_ENTRY_DELAY = 300; /* ms — NNGroup recommendation */

        $nav.on('mouseenter' + NS, function () {
            if (!isDesktop()) { return; }
            clearTimeout(leaveTimer);
            if ($panel.hasClass(OPEN_CLASS)) { return; } /* already open */
            openTimer = window.setTimeout(function () {
                openPanel();
            }, HOVER_ENTRY_DELAY);
        });

        /* ---- Close on leave (debounced) ---------------------------- */
        function scheduleClose() {
            clearTimeout(openTimer);
            leaveTimer = window.setTimeout(function () {
                if (isDesktop()) {
                    var navEl = $nav.get(0);
                    var panelNode = $panel.get(0);

                    if ((navEl && navEl.matches(':hover')) || (panelNode && panelNode.matches(':hover'))) {
                        return;
                    }

                    closePanel();
                    $panel.removeClass('menu-open');
                    $('body').removeClass('background_shadow background_shadow_show shadow_bkg_show');
                }
            }, 180);
        }

        function cancelClose() {
            clearTimeout(leaveTimer);
            clearTimeout(openTimer); /* ensure no stale open timer */
        }

        $nav.on('mouseleave' + NS, scheduleClose);
        $panel.on('mouseenter' + NS, cancelClose);
        $panel.on('mouseleave' + NS, scheduleClose);

        /* ---- Close on scroll > 50px (user approved) --------------- */
        $(window).on('scroll' + NS, function () {
            if (!$panel.hasClass(OPEN_CLASS)) { return; }
            var currentY = window.scrollY || window.pageYOffset || 0;
            if (Math.abs(currentY - openScrollY) > 50) {
                closePanel();
                $panel.removeClass('menu-open');
                $('body').removeClass('background_shadow background_shadow_show shadow_bkg_show');
            }
        });

        /* ---- Overlay click → close --------------------------------- */
        $overlay.on('click' + NS, function () {
            closePanel();
            $panel.removeClass('menu-open');
            $('body').removeClass('background_shadow background_shadow_show shadow_bkg_show');
        });

        /* ---- Escape key -------------------------------------------- */
        $(document).on('keydown' + NS, function (e) {
            if (e.key === 'Escape' && $panel.hasClass(OPEN_CLASS)) {
                closePanel();
                $panel.removeClass('menu-open');
                $trigger.focus();
            }
        });

        /* ---- Fechar quando foco sai do nav e do painel (keyboard) -- */
        var focusTimer = null;
        function onFocusOut() {
            focusTimer = window.setTimeout(function () {
                var $focused = $(document.activeElement);
                if (!$focused.closest($nav).length && !$focused.closest($panel).length) {
                    if ($panel.hasClass(OPEN_CLASS)) {
                        closePanel();
                        $panel.removeClass('menu-open');
                    }
                }
            }, 0);
        }
        $nav.on('focusout'  + NS, onFocusOut);
        $panel.on('focusout' + NS, onFocusOut);
        $nav.on('focusin'   + NS, function () { clearTimeout(focusTimer); });
        $panel.on('focusin'  + NS, function () { clearTimeout(focusTimer); });

        /* ---- Left column hover → show right pane ------------------- */
        $panel.on('mouseenter' + NS, '.ui-menu-item.level0', function () {
            if (!isDesktop()) { return; }
            var $item = $(this);
            if (!$item.hasClass(ACTIVE_CLASS)) { activateItem($item); }
        });

        /* ---- Keyboard: ArrowUp/Down/Right/Left/Home/End ----------- */
        $panel.on('keydown' + NS, '.ui-menu-item.level0 > a.level-top', function (e) {
            if (!isDesktop()) { return; }
            var $cur = $(this).closest('.ui-menu-item.level0');
            var $allItems = $panel.children('.ui-menu-item.level0');
            var $target;
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                $target = $cur.nextAll('.ui-menu-item.level0').first();
                if ($target.length) { activateItem($target); $target.children('a').first().focus(); }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                $target = $cur.prevAll('.ui-menu-item.level0').first();
                if ($target.length) { activateItem($target); $target.children('a').first().focus(); }
            } else if (e.key === 'Home') {
                e.preventDefault();
                $target = $allItems.first();
                if ($target.length) { activateItem($target); $target.children('a').first().focus(); }
            } else if (e.key === 'End') {
                e.preventDefault();
                $target = $allItems.last();
                if ($target.length) { activateItem($target); $target.children('a').first().focus(); }
            } else if (e.key === 'ArrowRight') {
                /* Enter submenu — focus first link in right pane */
                e.preventDefault();
                var $sub = $cur.children('.level0.submenu');
                if (!$sub.length) { $sub = $cur.find('.vmm-empty-submenu'); }
                if ($sub.length) {
                    var $firstLink = $sub.find('a').first();
                    if ($firstLink.length) { $firstLink.focus(); }
                }
            }
        });

        /* ArrowLeft from right pane → back to left column */
        $panel.on('keydown' + NS, '.level0.submenu a, .vmm-empty-submenu a', function (e) {
            if (!isDesktop() || e.key !== 'ArrowLeft') { return; }
            e.preventDefault();
            var $item = $(this).closest('.ui-menu-item.level0');
            if ($item.length) { $item.children('a.level-top').focus(); }
        });

        /* ---- Resize ------------------------------------------------ */
        $(window).on('resize' + NS, function () {
            if (!isDesktop() && $panel.hasClass(OPEN_CLASS)) {
                closePanel();
            } else if (isDesktop() && $panel.hasClass(OPEN_CLASS)) {
                positionPanel($trigger.length ? $trigger : $nav, $panel);
            }
        });

        /* ---- Highlight current page category in menu --------------- */
        (function highlightCurrent() {
            var loc = window.location.href.replace(/[?#].*$/, '').replace(/\/$/, '');
            $panel.find('a[href]').each(function () {
                var href = (this.getAttribute('href') || '').replace(/[?#].*$/, '').replace(/\/$/, '');
                if (href && href === loc) {
                    $(this).addClass('vmm-current');
                    /* If it's in the left column, mark that L0 too */
                    $(this).closest('.ui-menu-item.level0').addClass('vmm-current-cat');
                }
            });
        })();

        /* ---- Init: panel already open at page load ----------------- */
        if ($panel.hasClass('menu-open') && isDesktop()) {
            window.setTimeout(openPanel, 50);
        }

        /* ---- Cleanup ----------------------------------------------- */
        $nav.one('remove' + NS, function () {
            observer.disconnect();
            $panel.off(NS); $nav.off(NS);
            $overlay.off(NS); $(document).off(NS); $(window).off(NS);
        });
    };
});
