/**
 * AWA Custom child theme (Ayo Home5) RequireJS aliases.
 *
 * Hotfix operacional:
 * neste ambiente o asset publicado `requirejs-config.js` está sendo servido a
 * partir desta fonte do tema filho, sem o merge completo esperado do Magento.
 * Para restaurar o bootstrap AMD da storefront, este arquivo precisa carregar
 * também os aliases-base do Magento_Theme e os aliases críticos do RokanBase.
 */
var config = {
    waitSeconds: 0,
    deps: [
        'rokanthemes/theme',
        'awa-nav-cls-fix-reset',
        'awa-vertical-menu-focus-trap',
        'awa-b2b-header',
        'awa-scroll-reveal',
        'awa-card-enhance',
        'awa-b2b-pdp-price-reload',
        'awa-b2b-price-hydrator',
        'awa-footer-returns-hotfix',
        'awa-link-a11y-hotfix',
        'awa-b2b-plp-qty'
    ],
    map: {
        '*': {
            'ko': 'knockoutjs/knockout',
            'knockout': 'knockoutjs/knockout',
            'mageUtils': 'mage/utils/main',
            'rjsResolver': 'mage/requirejs/resolver',
            'jquery-ui-modules/core': 'jquery/ui-modules/core',
            'jquery-ui-modules/accordion': 'jquery/ui-modules/widgets/accordion',
            'jquery-ui-modules/autocomplete': 'jquery/ui-modules/widgets/autocomplete',
            'jquery-ui-modules/button': 'jquery/ui-modules/widgets/button',
            'jquery-ui-modules/datepicker': 'jquery/ui-modules/widgets/datepicker',
            'jquery-ui-modules/dialog': 'jquery/ui-modules/widgets/dialog',
            'jquery-ui-modules/draggable': 'jquery/ui-modules/widgets/draggable',
            'jquery-ui-modules/droppable': 'jquery/ui-modules/widgets/droppable',
            'jquery-ui-modules/effect-blind': 'jquery/ui-modules/effects/effect-blind',
            'jquery-ui-modules/effect-bounce': 'jquery/ui-modules/effects/effect-bounce',
            'jquery-ui-modules/effect-clip': 'jquery/ui-modules/effects/effect-clip',
            'jquery-ui-modules/effect-drop': 'jquery/ui-modules/effects/effect-drop',
            'jquery-ui-modules/effect-explode': 'jquery/ui-modules/effects/effect-explode',
            'jquery-ui-modules/effect-fade': 'jquery/ui-modules/effects/effect-fade',
            'jquery-ui-modules/effect-fold': 'jquery/ui-modules/effects/effect-fold',
            'jquery-ui-modules/effect-highlight': 'jquery/ui-modules/effects/effect-highlight',
            'jquery-ui-modules/effect-scale': 'jquery/ui-modules/effects/effect-scale',
            'jquery-ui-modules/effect-pulsate': 'jquery/ui-modules/effects/effect-pulsate',
            'jquery-ui-modules/effect-shake': 'jquery/ui-modules/effects/effect-shake',
            'jquery-ui-modules/effect-slide': 'jquery/ui-modules/effects/effect-slide',
            'jquery-ui-modules/effect-transfer': 'jquery/ui-modules/effects/effect-transfer',
            'jquery-ui-modules/effect': 'jquery/ui-modules/effect',
            'jquery-ui-modules/menu': 'jquery/ui-modules/widgets/menu',
            'jquery-ui-modules/mouse': 'jquery/ui-modules/widgets/mouse',
            'jquery-ui-modules/position': 'jquery/ui-modules/position',
            'jquery-ui-modules/progressbar': 'jquery/ui-modules/widgets/progressbar',
            'jquery-ui-modules/resizable': 'jquery/ui-modules/widgets/resizable',
            'jquery-ui-modules/selectable': 'jquery/ui-modules/widgets/selectable',
            'jquery-ui-modules/selectmenu': 'jquery/ui-modules/widgets/selectmenu',
            'jquery-ui-modules/slider': 'jquery/ui-modules/widgets/slider',
            'jquery-ui-modules/sortable': 'jquery/ui-modules/widgets/sortable',
            'jquery-ui-modules/spinner': 'jquery/ui-modules/widgets/spinner',
            'jquery-ui-modules/tabs': 'jquery/ui-modules/widgets/tabs',
            'jquery-ui-modules/tooltip': 'jquery/ui-modules/widgets/tooltip',
            'jquery-ui-modules/widget': 'jquery/ui-modules/widget',
            'jquery-ui-modules/timepicker': 'jquery/timepicker',
            'vimeo': 'vimeo/player',
            'vimeoWrapper': 'vimeo/vimeo-wrapper',
            awaCustomCompatBootstrap: 'js/awa-custom-compat-bootstrap',
            'awaVerticalMenu': 'js/vertical-menu',
            'Magento_Catalog/js/product/breadcrumbs': 'js/awa-pdp-breadcrumbs',
            'rokanthemes/ajaxsuite': 'js/awa-ajaxsuite-patch',
            'AWA_Custom/js/awa-back-to-top': 'js/awa-back-to-top'
        }
    },
    paths: {
        'jquery/validate': 'jquery/jquery.validate',
        'jquery/uppy-core': 'jquery/uppy/dist/uppy.min',
        'prototype': 'legacy-build.min',
        'jquery/jquery-storageapi': 'js-storage/storage-wrapper',
        'text': 'mage/requirejs/text',
        'domReady': 'requirejs/domReady',
        'spectrum': 'jquery/spectrum/spectrum',
        'tinycolor': 'jquery/spectrum/tinycolor',
        'jquery-ui-modules': 'jquery/ui-modules',
        'awa-b2b-header': 'js/awa-b2b-header',
        'rokanthemes/timecircles': 'js/rokanthemes/timecircles',
        'swiper': 'js/swiper-bundle.min',
        'js/awa-carousel': 'js/awa-carousel.min',
        'js/awa-home-category-carousel': 'js/awa-home-category-carousel',
        'js/vertical-menu-init': 'js/vertical-menu-init',
        'js/awa-vertical-mega-menu': 'js/awa-vertical-mega-menu',
        'js/jquery-andself-compat': 'js/jquery-andself-compat.min',
        'awa-header-sticky': 'js/awa-header-sticky',
        'awa-header-nav-runtime': 'js/awa-header-nav-runtime',
        'awa-header-customer-runtime': 'js/awa-header-customer-runtime',
        'awa-header-runtime-bootstrap': 'js/awa-header-runtime-bootstrap',
        'awa-b2b-pdp-price-reload': 'js/awa-b2b-pdp-price-reload',
        'awa-b2b-price-hydrator': 'js/awa-b2b-price-hydrator',
        'awa-scroll-reveal': 'js/awa-scroll-reveal',
        'awa-card-enhance': 'js/awa-card-enhance',
        'awa-footer-returns-hotfix': 'js/awa-footer-returns-hotfix',
        'awa-link-a11y-hotfix': 'js/awa-link-a11y-hotfix',
        'awa-b2b-plp-qty': 'js/awa-b2b-plp-qty',
        'awa-nav-cls-fix-reset': 'js/awa-nav-cls-fix-reset',
        'awa-vertical-menu-bootstrap': 'js/awa-vertical-menu-bootstrap',
        'awa-vertical-menu-toggle-hotfix': 'js/awa-vertical-menu-toggle-hotfix',
        'awa-vertical-menu-focus-trap': 'js/awa-vertical-menu-focus-trap',
        'js/vmenu-promo-carousel': 'js/vmenu-promo-carousel',
        'rokanthemes/verticalmenu': 'js/vendor-verticalmenu-fixed',
        'rokanthemes/fancybox': 'Rokanthemes_RokanBase/js/jquery_fancybox',
        'rokanthemes/owl': 'Rokanthemes_RokanBase/js/owl_carousel',
        'rokanthemes/elevatezoom': 'Rokanthemes_RokanBase/js/jquery.elevatezoom',
        'rokanthemes/choose': 'Rokanthemes_RokanBase/js/jquery_choose',
        'rokanthemes/equalheight': 'Rokanthemes_RokanBase/js/equalheight',
        'rokanthemes/lazyloadimg': 'Rokanthemes_RokanBase/js/jquery.lazyload.min'
    },
    shim: {
        'mage/adminhtml/backup': ['prototype'],
        'mage/captcha': ['prototype'],
        'mage/new-gallery': ['jquery'],
        'jquery/ui': ['jquery'],
        'matchMedia': {
            'exports': 'mediaCheck'
        },
        'magnifier/magnifier': ['jquery'],
        'vimeo/player': {
            'exports': 'Player'
        },
        'swiper': {
            exports: 'Swiper'
        },
        'rokanthemes/owl': ['jquery'],
        'rokanthemes/elevatezoom': ['jquery'],
        'rokanthemes/choose': ['jquery'],
        'rokanthemes/fancybox': ['jquery'],
        'rokanthemes/lazyloadimg': ['jquery']
    },
    config: {
        text: {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        }
    }
};

if (typeof require !== 'undefined' && require && typeof require.config === 'function') {
    require.config(config);
}

require(['jquery'], function ($) {
    'use strict';

    $.noConflict();
});

