/**
 * AWA Custom child theme (Ayo Home5) RequireJS aliases.
 *
 * rokanthemes/timecircles: Rokanthemes_Superdeals está desabilitado.
 * Mapeando para nosso stub noop.
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
        'js/vertical-menu-init': 'js/vertical-menu-init.min',
        'js/megamenu-mobile': 'js/megamenu-mobile.min',
        'js/jquery-andself-compat': 'js/jquery-andself-compat.min'
    },
    shim: {
        'swiper': {
            exports: 'Swiper'
        }
    }
};
