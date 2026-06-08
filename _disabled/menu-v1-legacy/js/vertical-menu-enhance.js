/**
 * Ícones SVG, divisores e realces do menu vertical AWA.
 * Invocado por js/vertical-menu-init (único ponto de boot).
 *
 * @module js/vertical-menu-enhance
 */
define(['jquery'], function ($) {
    'use strict';

    var ICONS = {
        'retrovisores': '<svg viewBox="0 0 24 24"><circle cx="8" cy="12" r="5"/><path d="M13 12h8M19 9l2 3-2 3"/></svg>',
        'bagageiros': '<svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>',
        'bauletos': '<svg viewBox="0 0 24 24"><rect x="2" y="8" width="20" height="14" rx="2"/><path d="M9 8V5a3 3 0 0 1 6 0v3M12 14v2"/></svg>',
        'manetes': '<svg viewBox="0 0 24 24"><path d="M14.7 6.3a1 1 0 0 0-1.4 0L3 16.6V21h4.4L18 10.7a1 1 0 0 0 0-1.4z"/><path d="M10 21h4"/></svg>',
        'piscas': '<svg viewBox="0 0 24 24"><path d="M9 2L3 13h7l-1 9 9-11h-7z"/></svg>',
        'pedaleiras': '<svg viewBox="0 0 24 24"><rect x="2" y="10" width="20" height="4" rx="1"/><path d="M6 10V7M10 10V7M14 10V7M18 10V7M6 14v3M10 14v3M14 14v3M18 14v3"/></svg>',
        'guidoes': '<svg viewBox="0 0 24 24"><path d="M12 2v7M8 9h8M5 9a7 7 0 0 0 14 0"/><line x1="12" y1="16" x2="12" y2="22"/></svg>',
        'cavaletes': '<svg viewBox="0 0 24 24"><path d="M12 2v7l4 4-8 .01L12 9"/><path d="M8 22l4-9 4 9"/></svg>',
        'carcacas': '<svg viewBox="0 0 24 24"><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10 10-4.5 10-10S17.5 2 12 2z"/><path d="M12 8v4l3 3"/></svg>',
        'lentes': '<svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><circle cx="11" cy="11" r="3"/><path d="M21 21l-4.35-4.35"/></svg>',
        'protetores-de-carter': '<svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
        'adaptadores': '<svg viewBox="0 0 24 24"><circle cx="7" cy="12" r="3"/><circle cx="17" cy="12" r="3"/><path d="M10 12h4M4 6v3M4 15v3M20 6v3M20 15v3"/></svg>',
        'suportes': '<svg viewBox="0 0 24 24"><path d="M2 20h20M12 4v10M5 20V9l7-5 7 5v11"/></svg>',
        'roldanas': '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/></svg>',
        'estribos': '<svg viewBox="0 0 24 24"><path d="M3 12h18M3 6l5 6-5 6M21 6l-5 6 5 6"/></svg>',
        'barras-de-guidao': '<svg viewBox="0 0 24 24"><path d="M5 5h14M5 12h14M5 19h14"/></svg>',
        'antenas': '<svg viewBox="0 0 24 24"><path d="M5 12.55a11 11 0 0 1 14.08 0M1.42 9a16 16 0 0 1 21.16 0M8.53 16.11a6 6 0 0 1 6.95 0M12 20h.01"/></svg>',
        'borrachas': '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20z"/><path d="M12 2v4M12 18v4M2 12h4M18 12h4"/></svg>',
        'piscas-1': '<svg viewBox="0 0 24 24"><path d="M9 2L3 13h7l-1 9 9-11h-7z"/></svg>',
        'super-ofertas': '<svg viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>',
        'protetor-de-carenagem': '<svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg>',
        'capas-de-corrente': '<svg viewBox="0 0 24 24"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07L12 4.93"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07L12 19.07"/></svg>',
        'blocos-oticos': '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v8M8 12h8"/></svg>',
        'lancamentos': '<svg viewBox="0 0 24 24"><path d="M12 2L9.5 9.5 2 12l7.5 2.5L12 22l2.5-7.5L22 12l-7.5-2.5z"/></svg>',
        'linha-esportiva': '<svg viewBox="0 0 24 24"><path d="M13 2L3 14h9l-1 8 10-12h-9z"/></svg>',
        'linha-honda': '<svg viewBox="0 0 24 24"><path d="M5 4h2v7h4V4h2v16h-2v-7H7v7H5z"/><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="1.5" opacity=".25"/></svg>',
        'linha-yamaha': '<svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/><circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>',
        'linha-suzuki': '<svg viewBox="0 0 24 24"><path d="M17 7H7a3 3 0 0 0 0 6h10a3 3 0 0 1 0 6H7M12 4v2M12 18v2"/></svg>',
        'ofertas': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>',
        'atacado-b2b': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/><line x1="12" y1="12" x2="12" y2="17"/><line x1="9" y1="14.5" x2="15" y2="14.5"/></svg>',
        'default': '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2"/></svg>'
    };

    var SECTION_LABELS = {
        'super-ofertas': 'Promoções',
        'lancamentos': 'Novidades',
        'linha-honda': 'Por Marca',
        'manetes': 'Peças'
    };

    var HIGHLIGHTS = ['super-ofertas', 'ofertas', 'lancamentos'];

    function normalizeIconSvg(svg) {
        if (!svg || svg.indexOf('<svg') === -1) {
            return svg;
        }
        if (svg.indexOf('stroke=') === -1) {
            svg = svg.replace('<svg ', '<svg fill="none" stroke="currentColor" stroke-width="1.6" ');
        }
        return svg;
    }

    function iconMarkup(slug) {
        return '<span class="awa-vmenu-icon" aria-hidden="true">'
            + normalizeIconSvg(ICONS[slug] || ICONS.default)
            + '</span>';
    }

    function slugify(str) {
        return (str || '').toLowerCase()
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9\s-]/g, '')
            .trim().replace(/\s+/g, '-');
    }

    function getCurrentCatSlug() {
        var path = window.location.pathname;
        var match = path.match(/\/([^/]+)\.html$/);

        if (match) {
            return match[1].toLowerCase();
        }

        var catMatch = path.match(/\/s\/([^/]+)\/id\/\d+/);
        return catMatch ? catMatch[1].toLowerCase() : null;
    }

    function styleHeader($nav) {
        var catHeader = $nav.closest('.menu_left_home1').find('.our_categories.title-category-dropdown');

        if (catHeader.length) {
            catHeader.attr('data-awa-vmenu-header-enhanced', 'true');
        }
    }

    function styleTogge($nav) {
        var $togge = $nav.find('ul.togge-menu');
        var currentSlug;
        var href;

        if (!$togge.length) {
            return;
        }

        currentSlug = getCurrentCatSlug();
        $togge.attr('data-awa-vmenu-shell-enhanced', 'true');

        $togge.children('li.level0').each(function () {
            var li = this;
            var $li = $(li);
            var link = li.querySelector('a.level-top, span.level-top');

            if (!link) {
                return;
            }

            href = link.getAttribute ? link.getAttribute('href') : '';
            if (currentSlug && href && href.indexOf(currentSlug + '.html') !== -1) {
                $li.addClass('awa-current-cat');
            }
        });
    }

    function injectEnhancements($nav) {
        var $togge = $nav.find('ul.togge-menu');
        var $items = $togge.children('li.level0');
        var isParent;

        if (!$items.length) {
            return;
        }

        $items.each(function () {
            var $item = $(this);
            var $link = $item.children('a.level-top, span.level-top').first();
            var labelSpan;
            var rawText;
            var slug;
            var $fa;
            var $prevEl;

            if (!$link.length) {
                return;
            }

            labelSpan = $link.find('.navigation__label');
            rawText = labelSpan.length
                ? labelSpan.text().trim()
                : $link.clone().children().remove().end().text().trim();
            slug = slugify(rawText);

            $fa = $link.find('em.menu-thumb-icon, img.menu-thumb-icon');
            if ($fa.length && !$link.find('.awa-vmenu-icon').length) {
                $fa.replaceWith(iconMarkup(slug));
            } else if (!$link.find('.awa-vmenu-icon').length && labelSpan.length) {
                labelSpan.before(iconMarkup(slug));
            }

            if (SECTION_LABELS[slug] && !$item.prev('.awa-vmenu__section-label').length) {
                $item.before($('<div class="awa-vmenu__divider"></div>'));
                $item.before(
                    $('<div class="awa-vmenu__section-label"></div>').text(SECTION_LABELS[slug])
                );
            }

            if (HIGHLIGHTS.indexOf(slug) !== -1) {
                $item.addClass('is-highlight');
                $prevEl = $item.prev();
                if (!$prevEl.hasClass('awa-vmenu__divider') && !$prevEl.hasClass('awa-vmenu__section-label')) {
                    $item.before('<div class="awa-vmenu__divider"></div>');
                }
            }

            $item.find('.cat-label').attr('data-awa-vmenu-badge', 'true').attr('aria-hidden', 'true');

            isParent = $item.hasClass('parent') || $item.hasClass('navigation__item--parent');
            if (isParent && !$link.find('.awa-vmenu-expand').length) {
                $link.append('<span class="awa-vmenu-expand" aria-hidden="true">›</span>');
            }
        });
    }

    /**
     * @param {HTMLElement|JQuery} element
     */
    return function enhanceVerticalMenu(element) {
        var $nav = element instanceof $ ? element : $(element);

        if (!$nav.length || $nav.data('awa-enhanced')) {
            return;
        }

        $nav.data('awa-enhanced', 1);
        styleHeader($nav);
        styleTogge($nav);
        injectEnhancements($nav);
    };
});
