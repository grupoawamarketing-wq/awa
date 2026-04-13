/**
 * AWA Custom child theme (Ayo Home5) RequireJS aliases.
 *
 * rokanthemes/timecircles: real TimeCircles lib (via tema, shim jquery).
 */
var config = {
    deps: ['awaVerticalMenu', 'awa-b2b-header', 'awa-scroll-reveal', 'awa-card-enhance', 'awa-b2b-pdp-price-reload'],
    map: {
        '*': {
            awaCustomCompatBootstrap: 'js/awa-custom-compat-bootstrap',
            'awaVerticalMenu': 'js/vertical-menu',
        }
    },
    paths: {
        'awa-b2b-header': 'js/awa-b2b-header',
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
        'awa-b2b-pdp-price-reload': 'js/awa-b2b-pdp-price-reload',
        'awa-scroll-reveal': 'js/awa-scroll-reveal',
        'awa-card-enhance': 'js/awa-card-enhance',
        // FASE E: Mobile menu focus trap (WCAG 2.4.3)
        'awa-vertical-menu-focus-trap': 'js/awa-vertical-menu-focus-trap',
        // Carousel promocional do menu vertical (VMente promo images)
        'js/vmenu-promo-carousel': 'js/vmenu-promo-carousel'
    },
    shim: {
        'swiper': {
            exports: 'Swiper'
        }
    }
};
