/**
 * AWA Custom child theme (Ayo Home5) RequireJS aliases.
 *
 * rokanthemes/timecircles: real TimeCircles lib (via tema, shim jquery).
 */
var config = {
    map: {
        '*': {
            awaCustomCompatBootstrap: 'js/awa-custom-compat-bootstrap'
        }
    },
    paths: {
        'rokanthemes/timecircles': 'js/rokanthemes/timecircles',
        // Restore Owl Carousel explicitly for Rokanthemes modules that hardcode the path
        'rokanthemes/owl': 'Rokanthemes_Themeoption/js/owl.carousel',
        // Swiper 11 — UMD bundle for carousel migration
        'swiper': 'js/swiper-bundle.min',
        // Custom AWA init scripts — minified versions (geradas com terser, 2026–FASE3)
        'js/tab-swiper-init': 'js/tab-swiper-init.min',
        'js/tab-carousel-init': 'js/tab-carousel-init.min',
        'js/products-swiper-init': 'js/products-swiper-init.min',
        'js/rokanthemes-owl-element-init': 'js/rokanthemes-owl-element-init.min',
        'js/superdeals-swiper-init': 'js/superdeals-swiper-init.min',
        'js/owl-carousel-init': 'js/owl-carousel-init.min',
        // Home category carousel usa fonte não-minificada para manter paridade com a versão auditada.
        'js/awa-home-category-carousel': 'js/awa-home-category-carousel',
        // Hotfix Ayo VM: usar fonte para evitar descompasso com artefato minificado legado.
        'js/vertical-menu-init': 'js/vertical-menu-init',
        'js/jquery-andself-compat': 'js/jquery-andself-compat.min',
        // Sticky header — non-minified until terser build step is added
        'awa-header-sticky': 'js/awa-header-sticky',
        // FASE E: Mobile menu focus trap (WCAG 2.4.3)
        'awa-vertical-menu-focus-trap': 'js/awa-vertical-menu-focus-trap'
    },
    shim: {
        'swiper': {
            exports: 'Swiper'
        }
    }
};
