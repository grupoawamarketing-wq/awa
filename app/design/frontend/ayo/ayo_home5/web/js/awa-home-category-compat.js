define([
    'jquery'
], function ($) {
    'use strict';

    var OBS_KEY = '__awaHomeCategoryCompatObserver';
    var scheduled = false;

    function inScope() {
        var body = document.body;

        if (!body) {
            return false;
        }

        return body.classList.contains('cms-index-index') ||
            body.classList.contains('cms-home') ||
            body.classList.contains('catalog-category-view') ||
            body.classList.contains('catalogsearch-result-index');
    }

    function visible(el) {
        return !!(el && (el.offsetWidth || el.offsetHeight || el.getClientRects().length));
    }

    function setLabel($el, text) {
        if (!$el.length || !text) {
            return;
        }

        if (!$el.attr('title')) {
            $el.attr('title', text);
        }

        if (!$el.attr('aria-label')) {
            $el.attr('aria-label', text);
        }
    }

    function normalizeText(text) {
        return $.trim(String(text || '').replace(/\s+/g, ' '));
    }

    function syncVerticalMenu() {
        var $nav = $('.navigation.verticalmenu.side-verticalmenu');
        var $list;
        var open;

        if (!$nav.length) {
            return;
        }

        $nav.attr('data-awa-component', $nav.attr('data-awa-component') || 'vertical-menu');
        $list = $nav.find('.togge-menu').first();
        open = $list.hasClass('menu-open') || visible($list.get(0));

        $nav.toggleClass('is-open', !!open);

        $nav.find('.ui-menu-item.parent, .ui-menu-item.level0.parent').each(function () {
            var $item = $(this);
            var $link = $item.children('a').first();
            var $toggle = $item.children('.open-children-toggle').first();
            var $panel = $item.children('.submenu, .subchildmenu, ul.level0').first();
            var text = normalizeText($link.text()) || 'Submenu';
            var isOpen = $item.hasClass('_active') || $item.hasClass('active') || ($panel.length && ($panel.hasClass('opened') || visible($panel.get(0))));
            var panelId;

            $item.toggleClass('is-open', !!isOpen)
                .attr('data-awa-parent-item', 'true');

            if ($toggle.length) {
                panelId = $panel.attr('id');
                if (!panelId && $panel.length) {
                    panelId = 'awa-vm-panel-' + Math.random().toString(36).slice(2, 9);
                    $panel.attr('id', panelId);
                }

                $toggle.attr({
                    'role': 'button',
                    'tabindex': $toggle.attr('tabindex') || '0',
                    'aria-expanded': isOpen ? 'true' : 'false'
                });

                if (panelId) {
                    $toggle.attr('aria-controls', panelId);
                }

                setLabel($toggle, (isOpen ? 'Recolher ' : 'Expandir ') + text);
            }

            if ($panel.length) {
                $panel.attr('aria-hidden', isOpen ? 'false' : 'true');
            }

            if ($link.length) {
                setLabel($link, text);
            }
        });

        $('.shadow_bkg_show').attr('aria-hidden', $('body').hasClass('background_shadow_show') ? 'false' : 'true');

        // Failsafe: clear stale overlay/body state when menu list is hidden.
        if (!visible($list.get(0))) {
            $('body').removeClass('awa-vertical-menu-open background_shadow_show');
        }
    }

    function syncMainNav() {
        $('.navigation.custommenu.main-nav').each(function () {
            var $nav = $(this);

            $nav.attr('data-awa-component', $nav.attr('data-awa-component') || 'main-nav');
            $nav.find('a[href]').each(function () {
                var $a = $(this);
                var text = normalizeText($a.text());

                if (!text && $a.find('i.fa').length) {
                    text = 'Link de navegação';
                }
                setLabel($a, text);
            });

            $nav.find('.open-children-toggle').each(function () {
                var $toggle = $(this);
                var $item = $toggle.closest('li');
                var $link = $item.children('a').first();
                var text = normalizeText($link.text()) || 'Submenu';
                var $submenu = $item.children('.submenu, .groupmenu, .subchildmenu').first();
                var isOpen = $item.hasClass('active') || $item.hasClass('_active') || ($submenu.length && visible($submenu.get(0)));

                $toggle.attr('aria-expanded', isOpen ? 'true' : 'false');
                setLabel($toggle, (isOpen ? 'Recolher ' : 'Expandir ') + text);
            });
        });
    }

    function syncSearch() {
        var $form = $('#search_mini_form, form.form.minisearch').first();
        var $input = $form.find('#search, #search-input-autocomplate, input[name="q"]').first();
        var $panel = $('#search_autocomplete, .searchsuite-autocomplete').first();
        var isOpen = $panel.length && visible($panel.get(0));

        if (!$form.length) {
            return;
        }

        $form.attr('data-awa-component', $form.attr('data-awa-component') || 'search-form')
            .toggleClass('is-open', !!isOpen);

        if ($input.length) {
            setLabel($input, $input.attr('placeholder') || 'Buscar produtos');
            $input.attr('aria-expanded', isOpen ? 'true' : 'false');
        }

        $form.find('.action.search').each(function () {
            setLabel($(this), 'Pesquisar');
        });

        if ($panel.length) {
            $panel.attr('aria-hidden', isOpen ? 'false' : 'true');
            $panel.find('a[href]').each(function () {
                var $a = $(this);
                setLabel($a, normalizeText($a.text()));
            });
        }
    }

    function syncToolbarAndFilters() {
        var isMobile = window.matchMedia && window.matchMedia('(max-width: 767px)').matches;
        var isPlpScope = document.body && (
            document.body.classList.contains('catalog-category-view') ||
            document.body.classList.contains('catalogsearch-result-index')
        );

        $('.toolbar.toolbar-products').each(function () {
            var $toolbar = $(this);
            $toolbar.attr('data-awa-component', $toolbar.attr('data-awa-component') || 'plp-toolbar');
        });

        $('.toolbar-products .modes-mode, .toolbar-products .sorter-action, .toolbar-products .pages-item a, .toolbar-products .pages-item strong').each(function () {
            var $el = $(this);
            var text = normalizeText($el.text());
            if (!text) {
                if ($el.hasClass('mode-grid')) {
                    text = 'Visualização em grade';
                } else if ($el.hasClass('mode-list')) {
                    text = 'Visualização em lista';
                } else if ($el.closest('.pages-item').length) {
                    text = 'Página';
                }
            }
            setLabel($el, text);
        });

        $('.filter-options-item').each(function () {
            var $item = $(this);
            var $title = $item.children('.filter-options-title').first();
            var $content = $item.children('.filter-options-content').first();
            var isOpen = $item.hasClass('active') || ($content.length && visible($content.get(0)));
            var text = normalizeText($title.text());
            var contentId;

            if (!$title.length) {
                return;
            }

            $item.attr('data-awa-component', 'filter-group');
            $title.attr({
                'role': 'button',
                'tabindex': $title.attr('tabindex') || '0',
                'aria-expanded': isOpen ? 'true' : 'false'
            });
            setLabel($title, (isOpen ? 'Recolher filtro ' : 'Expandir filtro ') + (text || 'Filtro'));

            if ($content.length) {
                contentId = $content.attr('id');
                if (!contentId) {
                    contentId = 'awa-filter-content-' + Math.random().toString(36).slice(2, 9);
                    $content.attr('id', contentId);
                }
                $title.attr('aria-controls', contentId);
            }
        });

        $('.filter-options-title').off('keydown.awaFilterA11y').on('keydown.awaFilterA11y', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                $(this).trigger('click');
            }
        });

        if (isPlpScope) {
            var $body = $('body');
            var $filter = $('#layered-ajax-filter-block, .block.filter').first();
            var $toolbarTop = $('.shop-tab-select .toolbar.toolbar-products').first();
            var $filterToggle = $toolbarTop.find('.modes .modes-label').first();

            if ($filter.length && $toolbarTop.length && $filterToggle.length) {
                $filterToggle.attr({
                    'role': 'button',
                    'tabindex': '0',
                    'data-awa-filter-toggle': 'true'
                });

                if (isMobile && !$body.attr('data-awa-filter-init')) {
                    $body.attr('data-awa-filter-init', 'true')
                        .addClass('awa-plp-filters-collapsed');
                } else if (!isMobile) {
                    $body.removeClass('awa-plp-filters-collapsed');
                }

                $filterToggle.off('keydown.awaFilterToggle').on('keydown.awaFilterToggle', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        $(this).trigger('click');
                    }
                });

                $(document).off('click.awaFilterToggle', '.toolbar .modes .modes-label[data-awa-filter-toggle="true"]')
                    .on('click.awaFilterToggle', '.toolbar .modes .modes-label[data-awa-filter-toggle="true"]', function (e) {
                        if (!isMobile) {
                            return;
                        }

                        e.preventDefault();
                        $body.toggleClass('awa-plp-filters-collapsed');
                        scheduleDecorate();
                    });

                var collapsed = isMobile && $body.hasClass('awa-plp-filters-collapsed');
                var label = collapsed ? 'Mostrar Filtros' : 'Ocultar Filtros';

                $filterToggle.text(label)
                    .attr('aria-expanded', collapsed ? 'false' : 'true');
                $filter.attr('aria-hidden', collapsed ? 'true' : 'false');
            }
        }
    }

    function syncProductCards() {
        $('.products-grid .product-item-info, .products-grid .item-product').each(function () {
            var $card = $(this);
            var $nameLink = $card.find('.product-item-name a').first();
            var name = normalizeText($nameLink.text());

            $card.attr('data-awa-component', $card.attr('data-awa-component') || 'product-card');
            if (name) {
                setLabel($nameLink, name);
            }

            $card.find('.actions-primary .action, .product-item-actions .action').each(function () {
                var $btn = $(this);
                var txt = normalizeText($btn.text()) || name;
                if ($btn.hasClass('tocart')) {
                    setLabel($btn, name ? ('Comprar ' + name) : 'Comprar produto');
                } else if ($btn.hasClass('towishlist')) {
                    setLabel($btn, name ? ('Adicionar ' + name + ' aos favoritos') : 'Adicionar aos favoritos');
                } else if ($btn.hasClass('tocompare')) {
                    setLabel($btn, name ? ('Comparar ' + name) : 'Comparar produto');
                } else {
                    setLabel($btn, txt);
                }
            });
        });
    }

    function syncOwl() {
        $('.owl-prev, .owl-next').each(function () {
            var $btn = $(this);
            var isPrev = $btn.hasClass('owl-prev');
            var label = isPrev ? 'Ver itens anteriores' : 'Ver próximos itens';
            setLabel($btn, label);
            $btn.attr('aria-disabled', $btn.hasClass('disabled') ? 'true' : 'false');
        });

        $('.owl-controls .owl-buttons div').each(function () {
            var $el = $(this);
            var isPrev = $el.hasClass('owl-prev');
            if (isPrev || $el.hasClass('owl-next')) {
                setLabel($el, isPrev ? 'Ver itens anteriores' : 'Ver próximos itens');
            }
        });
    }

    function decorate() {
        if (!inScope()) {
            return;
        }

        syncVerticalMenu();
        syncMainNav();
        syncSearch();
        syncToolbarAndFilters();
        syncProductCards();
        syncOwl();
    }

    function scheduleDecorate() {
        if (scheduled) {
            return;
        }

        scheduled = true;

        function flush() {
            scheduled = false;
            decorate();
        }

        if (typeof window.requestAnimationFrame === 'function') {
            window.requestAnimationFrame(flush);
            return;
        }

        window.setTimeout(flush, 0);
    }

    return function initAwaHomeCategoryCompat() {
        if (!inScope()) {
            return;
        }

        decorate();

        if (window.MutationObserver && !window[OBS_KEY]) {
            window[OBS_KEY] = new window.MutationObserver(function () {
                scheduleDecorate();
            });

            if (!document.body) {
                return;
            }

            window[OBS_KEY].observe(document.body, {
                childList: true,
                subtree: true
            });
        }
    };
});
