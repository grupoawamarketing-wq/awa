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
        'swiper': 'js/swiper-bundle.min'
    },
    shim: {
        'swiper': {
            exports: 'Swiper'
        }
    }
};
