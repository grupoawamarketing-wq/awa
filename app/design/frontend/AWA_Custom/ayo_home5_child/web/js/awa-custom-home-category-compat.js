/* global define, window, document */
define(['jquery'], function ($) {
    'use strict';

    /* ----------------------------------------------------------------
     * AWA Home/Category Compat JS
     * Applies ARIA/accessibility attributes to Rokanthemes components.
     * Runs on: homepage, catalog-category-view, catalogsearch-result-index
     * ---------------------------------------------------------------- */

    var OBSERVER_KEY   = '__awaHomeCategoryCompatObserver';
    var SCHEDULED_KEY  = '__awaHomeCategoryCompatScheduled';
    var MAX_OBS_FIRES  = 12;     /* disconnect after N relevant mutations */
    var obsFireCount   = 0;

    /* Selectors that constitute "relevant" DOM additions worth re-running for */
    var RELEVANT_SELECTORS = [
        '.navigation.verticalmenu',
        '.togge-menu',
        '.navigation.custommenu',
        '.open-children-toggle',
        '.filter-options-item',
        '.toolbar-products',
        '.products-grid',
        '.owl-prev', '.owl-next',
        '.owl-controls'
    ];

    function isRelevantPage() {
        var b = document.body;
        return !!(b && (
            b.classList.contains('cms-index-index') ||
            b.classList.contains('cms-home') ||
            b.classList.contains('catalog-category-view') ||
            b.classList.contains('catalogsearch-result-index')
        ));
    }

    function isVisible(el) {
        return !!(el && (el.offsetWidth || el.offsetHeight || el.getClientRects().length));
    }

    function setLabel($el, label) {
        if ($el.length && label) {
            if (!$el.attr('title'))     { $el.attr('title', label); }
            if (!$el.attr('aria-label')) { $el.attr('aria-label', label); }
        }
    }

    function trimText(val) {
        return $.trim(String(val || '').replace(/\s+/g, ' '));
    }

    /* ---- Vertical Menu ---- */
    function applyVerticalMenuA11y() {
        var $nav = $('.navigation.verticalmenu.side-verticalmenu');
        if (!$nav.length) { return; }

        $nav.attr('data-awa-component', $nav.attr('data-awa-component') || 'vertical-menu');

        var $panel = $nav.find('.togge-menu').first();
        var isOpen = $panel.hasClass('menu-open') || isVisible($panel.get(0));
        $nav.toggleClass('is-open', !!isOpen);

        $nav.find('.ui-menu-item.parent, .ui-menu-item.level0.parent').each(function () {
            var $li      = $(this);
            var $link    = $li.children('a').first();
            var $toggle  = $li.children('.open-children-toggle').first();
            var $submenu = $li.children('.submenu, .subchildmenu, ul.level0').first();
            var label    = trimText($link.text()) || 'Submenu';
            var open     = $li.hasClass('_active') || $li.hasClass('active') ||
                           ($submenu.length && ($submenu.hasClass('opened') || isVisible($submenu.get(0))));

            $li.toggleClass('is-open', !!open).attr('data-awa-parent-item', 'true');

            if ($toggle.length) {
                var panelId = $submenu.attr('id');
                if (!panelId && $submenu.length) {
                    panelId = 'awa-vm-panel-' + Math.random().toString(36).slice(2, 9); // nosemgrep: rules/lgpl/javascript/crypto/rule-node-insecure-random-generator
                    $submenu.attr('id', panelId);
                }
                $toggle.attr({
                    role:           'button',
                    tabindex:       $toggle.attr('tabindex') || '0',
                    'aria-expanded': open ? 'true' : 'false'
                });
                if (panelId) { $toggle.attr('aria-controls', panelId); }
                setLabel($toggle, (open ? 'Recolher ' : 'Expandir ') + label);
            }

            if ($submenu.length) { $submenu.attr('aria-hidden', open ? 'false' : 'true'); }
            if ($link.length)    { setLabel($link, label); }
        });

        $('.shadow_bkg_show').attr('aria-hidden',
            $('body').hasClass('background_shadow_show') ? 'false' : 'true');

        if (!isVisible($panel.get(0))) {
            $('body').removeClass('awa-vertical-menu-open background_shadow_show');
        }
    }

    /* ---- Custom (Horizontal) Menu ---- */
    function applyCustomMenuA11y() {
        $('.navigation.custommenu.main-nav').each(function () {
            var $nav = $(this);
            $nav.attr('data-awa-component', $nav.attr('data-awa-component') || 'main-nav');

            $nav.find('a[href]').each(function () {
                var $a    = $(this);
                var label = trimText($a.text());
                if (!label && $a.find('i.fa').length) { label = 'Link de navegação'; }
                setLabel($a, label);
            });

            $nav.find('.open-children-toggle').each(function () {
                var $t       = $(this);
                var $li      = $t.closest('li');
                var label    = trimText($li.children('a').first().text()) || 'Submenu';
                var $sub     = $li.children('.submenu, .groupmenu, .subchildmenu').first();
                var expanded = $li.hasClass('active') || $li.hasClass('_active') ||
                               ($sub.length && isVisible($sub.get(0)));
                $t.attr('aria-expanded', expanded ? 'true' : 'false');
                setLabel($t, (expanded ? 'Recolher ' : 'Expandir ') + label);
            });
        });
    }

    /* ---- Search Form ---- */
    function applySearchA11y() {
        var $form = $('#search_mini_form, form.form.minisearch').first();
        if (!$form.length) { return; }

        var $input = $form.find('#search, #search-input-autocomplate, input[name="q"]').first();
        var $autocomplete = $('#search_autocomplete, .searchsuite-autocomplete').first();
        var acVisible = $autocomplete.length && isVisible($autocomplete.get(0));

        $form.attr('data-awa-component', $form.attr('data-awa-component') || 'search-form')
             .toggleClass('is-open', !!acVisible);

        if ($input.length) {
            setLabel($input, $input.attr('placeholder') || 'Buscar produtos');
            $input.attr('aria-expanded', acVisible ? 'true' : 'false');
        }

        $form.find('.action.search').each(function () { setLabel($(this), 'Pesquisar'); });

        if ($autocomplete.length) {
            $autocomplete.attr('aria-hidden', acVisible ? 'false' : 'true');
            $autocomplete.find('a[href]').each(function () {
                var $a = $(this); setLabel($a, trimText($a.text()));
            });
        }
    }

    /* ---- PLP / Filters ---- */
    function applyPlpA11y() {
        var isMobile = window.matchMedia && window.matchMedia('(max-width: 767px)').matches;
        var isCategoryOrSearch = document.body &&
            (document.body.classList.contains('catalog-category-view') ||
             document.body.classList.contains('catalogsearch-result-index'));

        $('.toolbar.toolbar-products').each(function () {
            $(this).attr('data-awa-component', $(this).attr('data-awa-component') || 'plp-toolbar');
        });

        /* Layered filter groups */
        $('.filter-options-item').each(function () {
            var $item    = $(this);
            var $title   = $item.children('.filter-options-title').first();
            var $content = $item.children('.filter-options-content').first();
            var expanded = $item.hasClass('active') || ($content.length && isVisible($content.get(0)));
            var label    = trimText($title.text());

            if (!$title.length) { return; }
            $item.attr('data-awa-component', 'filter-group');
            $title.attr({
                role:           'button',
                tabindex:       $title.attr('tabindex') || '0',
                'aria-expanded': expanded ? 'true' : 'false'
            });
            setLabel($title, (expanded ? 'Recolher filtro ' : 'Expandir filtro ') + (label || 'Filtro'));

            if ($content.length) {
                var cid = $content.attr('id');
                if (!cid) {
                    cid = 'awa-filter-content-' + Math.random().toString(36).slice(2, 9); // nosemgrep: rules/lgpl/javascript/crypto/rule-node-insecure-random-generator
                    $content.attr('id', cid);
                }
                $title.attr('aria-controls', cid);
            }
        });

        $title_keydown_bind:
        $('.filter-options-title').off('keydown.awaFilterA11y').on('keydown.awaFilterA11y', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                $(this).trigger('click');
            }
        });

        /* Mobile filter toggle */
        if (isCategoryOrSearch) {
            var $body    = $('body');
            var $filter  = $('#layered-ajax-filter-block, .block.filter').first();
            var $toolbar = $('.shop-tab-select .toolbar.toolbar-products').first();
            var $label   = $toolbar.find('.modes .modes-label').first();

            if ($filter.length && $toolbar.length && $label.length) {
                $label.attr({ role: 'button', tabindex: '0', 'data-awa-filter-toggle': 'true' });

                if (isMobile && !$body.attr('data-awa-filter-init')) {
                    $body.attr('data-awa-filter-init', 'true').addClass('awa-plp-filters-collapsed');
                } else if (!isMobile) {
                    $body.removeClass('awa-plp-filters-collapsed');
                }

                $label.off('keydown.awaFilterToggle').on('keydown.awaFilterToggle', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        $(this).trigger('click');
                    }
                });

                $(document)
                    .off('click.awaFilterToggle', '.toolbar .modes .modes-label[data-awa-filter-toggle="true"]')
                    .on('click.awaFilterToggle', '.toolbar .modes .modes-label[data-awa-filter-toggle="true"]', function (e) {
                        if (isMobile) {
                            e.preventDefault();
                            $body.toggleClass('awa-plp-filters-collapsed');
                            updateFilterLabel();
                        }
                    });

                updateFilterLabel();
            }
        }

        function updateFilterLabel() {
            var $body  = $('body');
            var $label = $('.shop-tab-select .toolbar.toolbar-products').find('.modes .modes-label').first();
            var $filter = $('#layered-ajax-filter-block, .block.filter').first();
            if (!$label.length) { return; }
            var isMob    = window.matchMedia && window.matchMedia('(max-width: 767px)').matches;
            var collapsed = isMob && $body.hasClass('awa-plp-filters-collapsed');
            $label.text(collapsed ? 'Mostrar Filtros' : 'Ocultar Filtros')
                  .attr('aria-expanded', collapsed ? 'false' : 'true');
            if ($filter.length) {
                $filter.attr('aria-hidden', collapsed ? 'true' : 'false');
            }
        }
    }

    /* ---- Product Cards ---- */
    function applyProductCardA11y() {
        $('.products-grid .product-item-info, .products-grid .item-product').each(function () {
            var $card  = $(this);
            var $name  = $card.find('.product-item-name a').first();
            var label  = trimText($name.text());

            $card.attr('data-awa-component', $card.attr('data-awa-component') || 'product-card');
            if (label) { setLabel($name, label); }

            $card.find('.actions-primary .action, .product-item-actions .action').each(function () {
                var $btn  = $(this);
                var bText = trimText($btn.text()) || label;
                if ($btn.hasClass('tocart'))     { setLabel($btn, label ? 'Comprar ' + label : 'Comprar produto'); }
                else if ($btn.hasClass('towishlist')) { setLabel($btn, label ? 'Adicionar ' + label + ' aos favoritos' : 'Adicionar aos favoritos'); }
                else if ($btn.hasClass('tocompare'))  { setLabel($btn, label ? 'Comparar ' + label : 'Comparar produto'); }
                else                                  { setLabel($btn, bText); }
            });
        });
    }

    /* ---- OWL Nav Controls ---- */
    function applyOwlA11y() {
        $('.owl-prev, .owl-next').each(function () {
            var $b = $(this);
            var isPrev = $b.hasClass('owl-prev');
            setLabel($b, isPrev ? 'Ver itens anteriores' : 'Ver próximos itens');
            $b.attr('aria-disabled', $b.hasClass('disabled') ? 'true' : 'false');
        });
        $('.owl-controls .owl-buttons div').each(function () {
            var $b = $(this);
            var isPrev = $b.hasClass('owl-prev');
            if (isPrev || $b.hasClass('owl-next')) {
                setLabel($b, isPrev ? 'Ver itens anteriores' : 'Ver próximos itens');
            }
        });
    }

    /* ---- Master run ---- */
    function runAll() {
        if (!isRelevantPage()) { return; }
        applyVerticalMenuA11y();
        applyCustomMenuA11y();
        applySearchA11y();
        applyPlpA11y();
        applyProductCardA11y();
        applyOwlA11y();
    }

    /* ---- Debounced scheduler (LCP-safe: 3s delay para nao criar LCP re-candidate) ---- */
    function schedule() {
        if (window[SCHEDULED_KEY]) { return; }
        window[SCHEDULED_KEY] = true;
        window.setTimeout(function () {
            window[SCHEDULED_KEY] = false;
            runAll();
        }, 3000);
    }

    /* ---- MutationObserver — only fires for RELEVANT node additions ---- */
    function hasRelevantNode(mutations) {
        var i, j, node, $node;
        for (i = 0; i < mutations.length; i++) {
            var added = mutations[i].addedNodes;
            for (j = 0; j < added.length; j++) {
                node = added[j];
                if (node.nodeType !== 1) { continue; }       /* element nodes only */
                /* Skip OWL internal clones — they are never relevant */
                if (node.classList &&
                    (node.classList.contains('owl-item') ||
                     node.classList.contains('owl-wrapper') ||
                     node.classList.contains('owl-wrapper-outer'))) {
                    continue;
                }
                $node = $(node);
                for (var k = 0; k < RELEVANT_SELECTORS.length; k++) {
                    if ($node.is(RELEVANT_SELECTORS[k]) || $node.find(RELEVANT_SELECTORS[k]).length) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    function setupObserver() {
        if (!window.MutationObserver || window[OBSERVER_KEY]) { return; }
        if (!document.body) { return; }

        window[OBSERVER_KEY] = new window.MutationObserver(function (mutations) {
            if (!hasRelevantNode(mutations)) { return; }
            obsFireCount++;
            if (obsFireCount > MAX_OBS_FIRES) {
                /* Site is stable — no more significant DOM additions expected */
                window[OBSERVER_KEY].disconnect();
                return;
            }
            schedule();
        });

        window[OBSERVER_KEY].observe(document.body, { childList: true, subtree: true });
    }

    /* ---- Entry point (called by awa-custom-compat-bootstrap.js) ---- */
    return function () {
        if (!isRelevantPage()) { return; }

        /* Adiado para apos o LCP:
         * runAll() imediato ou em 2000ms causava long tasks que criavam
         * novas LCP candidates via forced layout (isVisible -> offsetWidth).
         * Com schedule() usando 3s debounce + primeiro runAll em 5s,
         * o LCP da hero img e capturado em ~2.6s sem interferencia.
         * ARIA/A11y attributes do menu sao imperceptiveis ao usuario em 5s.
         */
        window.setTimeout(runAll, 5000);

        /* Retry: apenas uma vez, bem apos o LCP estar estabilizado */
        window.setTimeout(runAll, 8000);

        /* Observer for dynamic content (AJAX navigation, lazy blocks) */
        setupObserver();
    };
});
