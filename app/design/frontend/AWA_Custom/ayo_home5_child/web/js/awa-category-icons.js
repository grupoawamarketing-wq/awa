/**
 * AWA — Shared Category Icons Module
 *
 * Provides SVG icon paths, keyword→icon resolution, and SVG builder
 * for vertical mega menus (VMM and Mueller variants).
 *
 * @module js/awa-category-icons
 */
define([], function () {
    'use strict';

    /* ================================================================
       SVG ICON PATHS — Lucide-style, 24×24 stroke icons
       ================================================================ */
    const ICON_PATHS = {
        handlebar:
            '<path d="M4 14V9a8 8 0 0 1 16 0v5"/>' +
            '<line x1="2" y1="14" x2="6" y2="14"/>' +
            '<line x1="18" y1="14" x2="22" y2="14"/>',

        luggage:
            '<rect x="3" y="7" width="18" height="13" rx="2"/>' +
            '<path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>' +
            '<line x1="10" y1="12" x2="14" y2="12"/>',

        mirror:
            '<ellipse cx="14" cy="8" rx="6" ry="5"/>' +
            '<path d="M9 12 5 21"/>',

        light:
            '<path d="M9 18h6"/><path d="M10 22h4"/>' +
            '<path d="M12 2a7 7 0 0 0-4 12.7V17h8v-2.3A7 7 0 0 0 12 2z"/>',

        headlight:
            '<circle cx="12" cy="12" r="5"/>' +
            '<line x1="12" y1="2" x2="12" y2="4"/>' +
            '<line x1="12" y1="20" x2="12" y2="22"/>' +
            '<line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/>' +
            '<line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>' +
            '<line x1="2" y1="12" x2="4" y2="12"/>' +
            '<line x1="20" y1="12" x2="22" y2="12"/>',

        shield:
            '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',

        motorcycle:
            '<circle cx="5" cy="17" r="3"/><circle cx="19" cy="17" r="3"/>' +
            '<path d="M5 14l4-7h4l3 3.5h4"/><path d="M9 7 8 4h3"/>',

        helmet:
            '<path d="M12 2C7.03 2 3 6.5 3 12c0 2.5.9 4.8 2.4 6.5H18.6C20.1 16.8 21 14.5 21 12c0-5.5-4.03-10-9-10z"/>' +
            '<path d="M3 12h18"/>' +
            '<path d="M5 16h6"/>',

        wrench:
            '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0' +
            'l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3' +
            'l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',

        cog:
            '<circle cx="12" cy="12" r="3"/>' +
            '<path d="M12 1v4m0 14v4M4.93 4.93l2.83 2.83m8.48 8.48l2.83 2.83' +
            'M1 12h4m14 0h4M4.93 19.07l2.83-2.83m8.48-8.48l2.83-2.83"/>',

        chain:
            '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>' +
            '<path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',

        tire:
            '<circle cx="12" cy="12" r="10"/>' +
            '<circle cx="12" cy="12" r="6"/>' +
            '<line x1="12" y1="2" x2="12" y2="6"/>' +
            '<line x1="12" y1="18" x2="12" y2="22"/>' +
            '<line x1="2" y1="12" x2="6" y2="12"/>' +
            '<line x1="18" y1="12" x2="22" y2="12"/>',

        shock:
            '<line x1="12" y1="2" x2="12" y2="5"/>' +
            '<path d="M9 5h6v2H9zm1 2h4v2h-4zm-1 2h6v2H9zm1 2h4v2h-4zm-1 2h6v2H9z"/>' +
            '<line x1="12" y1="19" x2="12" y2="22"/>',

        brake:
            '<circle cx="12" cy="12" r="9"/>' +
            '<circle cx="12" cy="12" r="4"/>' +
            '<circle cx="12" cy="5" r="1"/>' +
            '<circle cx="18.5" cy="8.5" r="1"/>' +
            '<circle cx="18.5" cy="15.5" r="1"/>' +
            '<circle cx="12" cy="19" r="1"/>' +
            '<circle cx="5.5" cy="15.5" r="1"/>' +
            '<circle cx="5.5" cy="8.5" r="1"/>',

        fuel:
            '<path d="M3 22V8l5-6h8l5 6v14H3z"/>' +
            '<path d="M3 13h18"/>' +
            '<path d="M17 3v5h4"/>',

        seat:
            '<rect x="3" y="13" width="18" height="5" rx="2.5"/>' +
            '<path d="M5 13V9a7 7 0 0 1 14 0v4"/>',

        exhaust:
            '<path d="M3 17h14a4 4 0 0 0 4-4V9"/>' +
            '<line x1="3" y1="17" x2="3" y2="20"/>' +
            '<path d="M17 9h4V6h-4v3z"/>',

        filter:
            '<polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>',

        ring:
            '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="5"/>',

        cube:
            '<path d="M21 16V8l-9-5-9 5v8l9 5z"/>' +
            '<path d="M3.3 7 12 12l8.7-5"/><line x1="12" y1="22" x2="12" y2="12"/>',

        lever:
            '<path d="M6 3v6a2 2 0 0 0 2 2h9"/>' +
            '<circle cx="19" cy="11" r="2"/><line x1="4" y1="3" x2="8" y2="3"/>',

        footpeg:
            '<rect x="5" y="9" width="14" height="6" rx="1.5"/>' +
            '<path d="M9 9V5m6 4V5M9 15v4m6-4v4"/>',

        kickstand:
            '<path d="M12 3v10"/><path d="M7 21l5-8 5 8"/>',

        speedometer:
            '<path d="M12 2a10 10 0 0 1 10 10"/>' +
            '<path d="M2 12a10 10 0 0 0 10 10"/>' +
            '<path d="M12 12L8 8"/>' +
            '<circle cx="12" cy="12" r="2"/>',

        signal:
            '<line x1="12" y1="20" x2="12" y2="10"/>' +
            '<path d="M8.5 6.5a5 5 0 0 1 7 0"/>' +
            '<path d="M5.5 3.5a9 9 0 0 1 13 0"/>',

        kit:
            '<path d="M16.5 9.4l-9-5.19M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>' +
            '<polyline points="3.27 6.96 12 12.01 20.73 6.96"/>' +
            '<line x1="12" y1="22.08" x2="12" y2="12"/>',

        tag:
            '<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10' +
            'l8.59 8.59a2 2 0 0 1 0 2.82z"/><circle cx="7" cy="7" r="1"/>',

        star:
            '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',

        /* Alias: Mueller uses 'package' as fallback (same SVG as 'cube') */
        package:
            '<path d="M21 16V8l-9-5-9 5v8l9 5z"/>' +
            '<path d="M3.3 7 12 12l8.7-5"/><line x1="12" y1="22" x2="12" y2="12"/>'
    };

    /* ================================================================
       KEYWORD → ICON MAPPING
       All keywords are pre-NFD-normalized (no accents).
       Order matters: first match wins.
       ================================================================ */
    const CATEGORY_KEYWORDS = [
        { icon: 'handlebar', keywords: ['barra', 'guid'] },
        { icon: 'luggage',   keywords: ['bauleto', 'bagageiro'] },
        { icon: 'mirror',    keywords: ['retrovisor'] },
        { icon: 'helmet',    keywords: ['capacete'] },
        { icon: 'light',     keywords: ['pisca'] },
        { icon: 'headlight', keywords: ['bloco', 'farol', 'lente'] },
        { icon: 'shield',    keywords: ['protetor', 'carter', 'carenagem'] },
        { icon: 'tire',      keywords: ['pneu', 'roda'] },
        { icon: 'shock',     keywords: ['amortecedor', 'suspensao'] },
        { icon: 'brake',     keywords: ['freio', 'disco', 'pastilha'] },
        { icon: 'chain',     keywords: ['corrente', 'capa'] },
        { icon: 'fuel',      keywords: ['tanque', 'combustiv'] },
        { icon: 'seat',      keywords: ['banco', 'selim'] },
        { icon: 'exhaust',   keywords: ['escapamento', 'silencioso'] },
        { icon: 'filter',    keywords: ['filtro'] },
        { icon: 'speedometer', keywords: ['velocimetro', 'painel'] },
        { icon: 'motorcycle',  keywords: ['linha', 'esportiva'] },
        { icon: 'cog',       keywords: ['embreagem', 'roldana'] },
        { icon: 'wrench',    keywords: ['adaptador', 'suporte'] },
        { icon: 'ring',      keywords: ['borracha'] },
        { icon: 'cube',      keywords: ['carca'] },
        { icon: 'lever',     keywords: ['manete'] },
        { icon: 'footpeg',   keywords: ['pedaleira', 'pedal', 'estribo'] },
        { icon: 'kickstand', keywords: ['cavalete'] },
        { icon: 'signal',    keywords: ['antena'] },
        { icon: 'kit',       keywords: ['kit'] },
        { icon: 'star',      keywords: ['acessorio'] },
        { icon: 'tag',       keywords: ['super oferta', 'oferta'] }
    ];

    /**
     * Normalize category name: lowercase, strip diacritics, trim.
     * @param {string} name
     * @returns {string}
     */
    function normalizeName(name) {
        return (name || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim();
    }

    /**
     * Resolve icon type from category name via keyword matching.
     * @param {string} name  - Category display name
     * @param {string} [fallback='cube'] - Fallback icon when no keyword matches
     * @returns {string} Icon type key
     */
    function resolveIconType(name, fallback) {
        const lower = normalizeName(name);
        for (let gi = 0; gi < CATEGORY_KEYWORDS.length; gi++) {
            const group = CATEGORY_KEYWORDS[gi];
            for (let ki = 0; ki < group.keywords.length; ki++) {
                if (lower.indexOf(group.keywords[ki]) !== -1) {
                    return group.icon;
                }
            }
        }
        return fallback || 'cube';
    }

    /**
     * Build full SVG markup for an icon type.
     * @param {string} type - Icon type key from ICON_PATHS
     * @param {Object} [options]
     * @param {string} [options.cssClass='vmm-cat-icon'] - CSS class on <svg>
     * @param {string} [options.strokeWidth='1.6']
     * @param {string} [options.fallback='cube'] - Fallback icon if type not found
     * @returns {string} Full SVG HTML string
     */
    function buildIconSvg(type, options) {
        const opts = options || {};
        const cssClass = opts.cssClass || 'vmm-cat-icon';
        const strokeWidth = opts.strokeWidth || '1.6';
        const fb = opts.fallback || 'cube';
        const inner = ICON_PATHS[type] || ICON_PATHS[fb];
        if (!inner) {
            return '';
        }
        return '<svg class="' + cssClass + '" xmlns="http://www.w3.org/2000/svg" ' +
            'viewBox="0 0 24 24" fill="none" stroke="currentColor" ' +
            'stroke-width="' + strokeWidth + '" stroke-linecap="round" stroke-linejoin="round" ' +
            'aria-hidden="true" focusable="false">' +
            inner + '</svg>';
    }

    return {
        ICON_PATHS: ICON_PATHS,
        CATEGORY_KEYWORDS: CATEGORY_KEYWORDS,
        normalizeName: normalizeName,
        resolveIconType: resolveIconType,
        buildIconSvg: buildIconSvg
    };
});
