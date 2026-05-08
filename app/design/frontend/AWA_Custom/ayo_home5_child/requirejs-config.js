/**
 * AWA Custom child theme (Ayo Home5) RequireJS aliases.
 *
 * rokanthemes/timecircles: real TimeCircles lib (via tema, shim jquery).
 */
var config = {
    deps: ['awa-nav-cls-fix-reset', 'awa-vertical-menu-focus-trap', 'awa-b2b-header', 'awa-scroll-reveal', 'awa-card-enhance', 'awa-b2b-pdp-price-reload', 'awa-b2b-price-hydrator', 'awa-footer-returns-hotfix', 'awa-link-a11y-hotfix', 'awa-b2b-plp-qty'],
    map: {
        '*': {
            awaCustomCompatBootstrap: 'js/awa-custom-compat-bootstrap',
            'awaVerticalMenu': 'js/vertical-menu',
            'Magento_Catalog/js/product/breadcrumbs': 'js/awa-pdp-breadcrumbs',
            // Backward compatibility for cached HTML still referencing old module ID.
            'AWA_Custom/js/awa-back-to-top': 'js/awa-back-to-top'
        }
    },
    paths: {
        'awa-b2b-header': 'js/awa-b2b-header',
        'rokanthemes/timecircles': 'js/rokanthemes/timecircles',
        // Swiper 11 — UMD bundle for carousel migration
        'swiper': 'js/swiper-bundle.min',
        // Unified carousel module (replaces products-swiper-init, tab-swiper-init, superdeals-swiper-init)
        'js/awa-carousel': 'js/awa-carousel.min',
        // Home category carousel usa fonte não-minificada para manter paridade com a versão auditada.
        'js/awa-home-category-carousel': 'js/awa-home-category-carousel',
        // Hotfix Ayo VM: usar fonte para evitar descompasso com artefato minificado legado.
        'js/vertical-menu-init': 'js/vertical-menu-init',
        'js/awa-vertical-mega-menu': 'js/awa-vertical-mega-menu',
        'js/jquery-andself-compat': 'js/jquery-andself-compat.min',
        // Sticky header — non-minified until terser build step is added
        'awa-header-sticky': 'js/awa-header-sticky',
        // Header runtime syncs (mobile nav aria + cart badge) extracted from inline script
        'awa-header-nav-runtime': 'js/awa-header-nav-runtime',
        // Header customer syncs (account menu + MCP dashboard link)
        'awa-header-customer-runtime': 'js/awa-header-customer-runtime',
        // Declarative bootstrap for all header runtime modules
        'awa-header-runtime-bootstrap': 'js/awa-header-runtime-bootstrap',
        'awa-b2b-pdp-price-reload': 'js/awa-b2b-pdp-price-reload',
        'awa-b2b-price-hydrator': 'js/awa-b2b-price-hydrator',
        'awa-scroll-reveal': 'js/awa-scroll-reveal',
        'awa-card-enhance': 'js/awa-card-enhance',
        // Runtime fix: normalize legacy footer returns URL to canonical route.
        'awa-footer-returns-hotfix': 'js/awa-footer-returns-hotfix',
        // Runtime fix: propagate image alt text to icon/image-only links.
        'awa-link-a11y-hotfix': 'js/awa-link-a11y-hotfix',
        // B2B PLP qty stepper: converts hidden qty to visible +/- stepper for B2B customers
        'awa-b2b-plp-qty': 'js/awa-b2b-plp-qty',
        'awa-nav-cls-fix-reset': 'js/awa-nav-cls-fix-reset',
        'awa-vertical-menu-bootstrap': 'js/awa-vertical-menu-bootstrap',
        'awa-vertical-menu-toggle-hotfix': 'js/awa-vertical-menu-toggle-hotfix',
        // FASE E: Mobile menu focus trap (WCAG 2.4.3)
        'awa-vertical-menu-focus-trap': 'js/awa-vertical-menu-focus-trap',
        // Carousel promocional do menu vertical (VMente promo images)
        'js/vmenu-promo-carousel': 'js/vmenu-promo-carousel',
        // Fix [A1][A2]: substituir IIFE+shim vendor por versão AMD com .on()/.off()
        'rokanthemes/verticalmenu': 'js/vendor-verticalmenu-fixed'
    },
    shim: {
        'swiper': {
            exports: 'Swiper'
        }
    }
};
