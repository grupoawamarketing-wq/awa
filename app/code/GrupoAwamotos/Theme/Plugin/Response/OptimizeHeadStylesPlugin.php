<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\Plugin\Response;

use GrupoAwamotos\Theme\Model\HeaderImpeccableCascadeLockCss;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\HttpInterface;

/**
 * Performance — dedupe CSS async + gate bundles grandes na home (Sprint 3 / PSI).
 */
class OptimizeHeadStylesPlugin
{
    private const HOME_ACTION = 'cms_index_index';
    private const HOME_DENSITY_GRID_FILE = 'awa-home-density-grid-20260611.min.css';
    private const HOME_DENSITY_GRID_QUERY = '?v=20260612-home-5up';
    private const CATALOG_DENSITY_GRID_FILE = 'awa-catalog-density-grid-20260611.min.css';
    private const CATALOG_DENSITY_GRID_QUERY = '?v=20260611a';

    /** PLP/busca/PDP: stack dedicado §89–§94 — omitir terminal Impeccable (~183KB). */
    private const CATALOG_STACK_ACTIONS = [
        'catalog_category_view',
        'catalogsearch_result_index',
        'catalog_product_view',
    ];

    /** @deprecated use CATALOG_STACK_ACTIONS — mantido para defer não-crítico PLP/busca */
    private const CATALOG_HEADER_ACTIONS = [
        'catalog_category_view',
        'catalogsearch_result_index',
    ];

    /** align-grid async — inline lock cobre container/grid no 1º paint */
    private const DEFER_ALIGN_GRID_ACTIONS = [
        self::HOME_ACTION,
        'catalog_category_view',
        'catalogsearch_result_index',
        'catalog_product_view',
        self::CART_ACTION,
    ];

    private const CATALOG_STRIP_IMPECCABLE_FRAGMENTS = [
        'awa-impeccable-audit-2026-05-28',
        'awa-commerce-impeccable-refine',
    ];

    /** PLP/busca: omitir bundles globais — stack dedicado (plp-distill + promax). */
    private const CATALOG_STRIP_LEGACY_BUNDLE_FRAGMENTS = [
        'awa-ui-simplify-terminal',
        'awa-head-tail-bundle',
        'awa-bundle-async-distill-lock',
        'awa-shelf-carousel',
    ];

    /** PLP/busca/PDP: não bloqueiam header/above-fold; defer print/onload (menos TBT no 1º paint). */
    private const CATALOG_DEFER_CSS_FRAGMENTS = [
        'awa-impeccable-audit-2026-05-28',
        'awa-audit-bundle.css',
        'awa-audit-bundle.min.css',
        'social-proof.css',
        'gallery.css',
        'awa-pdp-shell-final',
    ];

    /** Menu v2 — inline critical cobre mobile drawer + preflight dept; folha completa async. */
    private const MENU_DEFER_CSS_FRAGMENTS = [
        'awa-menu-v2-dept-open-fix',
    ];

    /** PLP/busca — grid/hero no inline lock + head-preload critical; polish async. */
    private const PLP_DEFER_CSS_FRAGMENTS = [
        'awa-plp-critical-fixes',
    ];

    private const CART_ACTION = 'checkout_cart_index';

    /** Carrinho/checkout: header em modo foco — omitir cascade-lock inline (~170KB + guard script). */
    private const CHECKOUT_FOCUS_ACTIONS = [
        'checkout_cart_index',
        'checkout_index_index',
        'onepagecheckout_index_index',
        'checkout_onepage_success',
    ];

    /** Carrinho: stack dedicado (critical + terminal + polish async); omitir refine global (~42KB). */
    private const CART_STRIP_CSS_FRAGMENTS = [
        'awa-ui-promax-bundle.min.css',
        'awa-ui-promax-bundle.css',
        'awa-commerce-impeccable-refine',
        'awa-focus-visible',
    ];

    /** Auth B2B: login.css + critical inline cobrem o shell — omitir bundles globais (~180KB). */
    private const AUTH_STRIP_CSS_FRAGMENTS = [
        'awa-plp-final-polish',
        'awa-ui-promax-bundle',
        'awa-commerce-impeccable-refine',
        'awa-ui-ux-pro-max-header',
        'awa-structural-fix-2026-05-20',
        'awa-responsive-guard',
        'custom_default.css',
        'styles-m.css',
        'styles-l.css',
    ];

    /**
     * Painel B2B logado — remover apenas folhas cosméticas/experimentais.
     * Mantemos a base global (styles-m/l, super/layout/theme) para evitar regressões visuais.
     */
    private const B2B_ACCOUNT_STRIP_CSS_FRAGMENTS = [
        'awa-plp-final-polish',
        'awa-audit-bundle',
        'awa-carousel-bundle',
    ];

    /** Fragment sem extensão — detecta tanto .css quanto .min.css no HTML. */
    /** Migrados para styles-l via _extend.less (43.01 / 43.02) — remover do HTML. */
    private const MIGRATED_HEADER_CSS_FRAGMENTS = [
        'awa-header-stack-2026-05-28',
        'awa-header-refine-terminal',
    ];

    private const REFINE_CSS_FRAGMENT = 'awa-commerce-impeccable-refine';

    /**
     * Home — CSS abaixo do fold / bundles grandes (anti-FOUC inline cobre above-fold).
     *
     * @var string[]
     */
    /** Home — só cosmético / below-fold; estrutural fica no head (print/onload stagger). */
    private const HOME_GATE_CSS_FRAGMENTS = [
        'awa-home-hover-lock.min.css',
        'awa-home-hover-lock.css',
        'awa-visual-audit-2026-05-18.min.css',
        'awa-visual-audit-2026-05-18.css',
        'awa-impeccable-audit-2026-05-28.min.css',
        'awa-impeccable-audit-2026-05-28.css',
        'awa-homepage-hierarchy.min.css',
        'awa-home-cosmetic-bundle.min.css',
        'awa-home-flex-final.min.css',
        'awa-home-flex-grid-flow.min.css',
        'awa-home-modernize-2026.min.css',
        'awa-home-b2b-density-terminal.min.css',
        'awa-head-preload-home-ext.min.css',
        'awa-head-tail-bundle.min.css',
        'awa-home-gate-postaudit-bundle.min.css',
        'awa-home-gate-postaudit-bundle.css',
        'awa-home-gate-polish-bundle.min.css',
        'awa-home-gate-polish-bundle.css',
        'awa-home-gate-polish-cards.min.css',
        'awa-home-gate-polish-cards.css',
        'awa-home-gate-polish-type.min.css',
        'awa-home-gate-polish-type.css',
        'awa-defer-global-bundle',
        'awa-grid-container-audit',
        'awa-layout-grid-system',
        'awa-bundle-refinements',
        'awa-home-gate-visual-bundle',
        'awa-ui-simplify-terminal',
        'awa-home-standardize-terminal-wins',
    ];

    /** Folhas que permanecem no head (async stagger) — nunca remover para fila idle. */
    private const HOME_NEVER_GATE_FRAGMENTS = [
        'awa-focus-visible',
        'styles-l.css',
        'awa-carousel-bundle',
        'awa-shelf-carousel',
        'awa-commerce-impeccable-refine',
        'awa-home-body-end-bundle',
        'awa-bundle-async-distill-lock',
        'awa-align-grid-terminal-2026-06-11',
        'awa-visual-bugfix.min.css',
        'awa-visual-bugfix.css',
        'awa-ui-ux-pro-max-header-2026-05-19',
        'awa-structural-fix-2026-05-20',
    ];

    public function __construct(
        private readonly HttpRequest $request,
    ) {
    }

    public function beforeSendResponse(HttpInterface $subject): void
    {
        if (!$subject instanceof \Magento\Framework\HTTP\PhpEnvironment\Response) {
            return;
        }

        $contentType = $subject->getHeader('Content-Type');
        if ($contentType && stripos($contentType->getFieldValue(), 'text/html') === false) {
            return;
        }

        $html = (string) $subject->getBody();
        if ($html === '') {
            return;
        }

        $html = $this->stripRedundantAsyncNoscript($html);
        $html = $this->normalizeCssStaggerOnloadHandlers($html);
        $fullAction = $this->request->getFullActionName();

        if ($fullAction === self::CART_ACTION) {
            $html = $this->stripStylesheetFragments($html, self::CART_STRIP_CSS_FRAGMENTS);
            $html = $this->injectAlignGridStylesheetIfMissing($html);
            $subject->setHeader('X-Awa-Header-Optimize', 'v12-cart', true);
        }

        if (in_array($fullAction, self::CHECKOUT_FOCUS_ACTIONS, true)) {
            $html = $this->injectAlignGridStylesheetIfMissing($html);
        }

        if ($fullAction === 'catalog_product_view') {
            $html = $this->injectAlignGridStylesheetIfMissing($html);
        }

        if ($fullAction === self::HOME_ACTION) {
            $html = $this->stripStandaloneStylesheetNoscript($html);
            $html = $this->stripStaleHomeCriticalStylesheets($html);
            $html = $this->patchStaleHomeHeaderAssets($html);
            /* Fila gate antes de gateHomeLargeStylesheets — inject vazio após gate quebrava merge (H-opt). */
            $html = $this->injectHomeHeaderTerminalAssets($html);
            $html = $this->injectHomeAlignGridStylesheetsIfMissing($html);
            $html = $this->gateHomeLargeStylesheets($html);
            $html = $this->pruneHomeNeverGateQueueUrls($html);
            $html = $this->removePrintLinksListedInGateQueue($html);
            $html = $this->stripHomeGatedNoscriptFallbacks($html);
            $html = $this->dedupeStylesheetHrefs($html);
            $subject->setHeader('X-Awa-Header-Optimize', 'v12', true);
        } elseif (in_array($fullAction, self::CATALOG_HEADER_ACTIONS, true)) {
            $html = $this->injectHeaderTerminalStylesheetIfMissing($html);
            $html = $this->injectAlignGridStylesheetIfMissing($html);
            $html = $this->deferCatalogNonCriticalStylesheets($html);
            $html = $this->stripStylesheetFragments($html, self::CATALOG_STRIP_LEGACY_BUNDLE_FRAGMENTS);
            $html = $this->injectPlpDistillStylesheetIfMissing($html);
            $subject->setHeader('X-Awa-Header-Optimize', 'v12-catalog', true);
        }

        if (in_array($fullAction, self::CATALOG_STACK_ACTIONS, true)) {
            $html = $this->stripStylesheetFragments($html, self::CATALOG_STRIP_IMPECCABLE_FRAGMENTS);
            $subject->setHeader('X-Awa-Header-Optimize', 'v12-catalog-stack', true);
        }

        $html = $this->normalizeRefineStylesheetQuery($html);

        if ($fullAction === 'catalog_product_view') {
            $html = $this->normalizePdpTerminalStylesheets($html);
            $html = $this->deferCatalogNonCriticalStylesheets($html);
        }

        $isAuthFocusPageEarly = in_array($fullAction, HeaderImpeccableCascadeLockCss::AUTH_FOCUS_ACTIONS, true)
            || HeaderImpeccableCascadeLockCss::isAuthShellHtml($html);
        $isB2bAccountFocusEarly = $this->isB2bAccountFocusPage($fullAction, $html);
        if (!in_array($fullAction, self::CATALOG_STACK_ACTIONS, true)
            && $fullAction !== self::CART_ACTION
            && !$isAuthFocusPageEarly
            && !$isB2bAccountFocusEarly
        ) {
            $html = $this->injectGlobalRefineStylesheetIfMissing($html);
        }

        if (HeaderImpeccableCascadeLockCss::htmlHasSiteHeader($html)) {
            $html = $this->stripStylesheetFragments($html, self::MIGRATED_HEADER_CSS_FRAGMENTS);
            $html = $this->normalizeHeaderTerminalStylesheetVersion($html);
            if ($fullAction === self::HOME_ACTION) {
                // Home: v10 + critical-home no head; omitir cascade-lock (~112KB) — lock leve no body.
                $html = HeaderImpeccableCascadeLockCss::injectHomeLightBeforeBodyClose($html);
            } elseif (in_array($fullAction, HeaderImpeccableCascadeLockCss::AUTH_FOCUS_ACTIONS, true)
                || HeaderImpeccableCascadeLockCss::isAuthShellHtml($html)
            ) {
                // Auth B2B: critical inline cobre layout — só remover cascade (~111KB).
                $html = HeaderImpeccableCascadeLockCss::stripLegacyFromHtml($html);
            } elseif ($isB2bAccountFocusEarly) {
                // Painel B2B: remove só cascade-lock global; não injeta home-light para não degradar a UI da conta.
                $html = HeaderImpeccableCascadeLockCss::stripLegacyFromHtml($html);
            } elseif (in_array($fullAction, self::CATALOG_STACK_ACTIONS, true)
                || in_array($fullAction, self::CHECKOUT_FOCUS_ACTIONS, true)
            ) {
                // PLP/checkout: omitir cascade-lock do header (~170KB); footer leve separado.
                $html = HeaderImpeccableCascadeLockCss::stripLegacyFromHtml($html);
                $html = HeaderImpeccableCascadeLockCss::injectFooterOnlyBeforeBodyClose($html);
            } else {
                $html = $this->injectHeaderTerminalFixStyle($html);
            }
        }

        $html = $this->dedupeStylesheetHrefs($html);
        $isAuthFocusPage = in_array($fullAction, HeaderImpeccableCascadeLockCss::AUTH_FOCUS_ACTIONS, true)
            || HeaderImpeccableCascadeLockCss::isAuthShellHtml($html);
        $isB2bAccountFocusPage = $this->isB2bAccountFocusPage($fullAction, $html);
        if ($fullAction !== self::HOME_ACTION
            && !in_array($fullAction, self::CHECKOUT_FOCUS_ACTIONS, true)
            && !$isAuthFocusPage
            && !$isB2bAccountFocusPage
        ) {
            $html = $this->deferStylesheetsByFragments($html, self::MENU_DEFER_CSS_FRAGMENTS);
        }
        if (in_array($fullAction, self::CATALOG_HEADER_ACTIONS, true)) {
            $html = $this->deferStylesheetsByFragments($html, self::PLP_DEFER_CSS_FRAGMENTS);
        }
        if (!$isAuthFocusPage && !$isB2bAccountFocusPage) {
            $html = $this->consolidateAlignGridToBodyTerminal($html, $fullAction);
            $html = $this->injectSiteShellInlineLock($html, $fullAction);
            $html = $this->injectAlignGridHeaderContainerTerminal($html);
            $html = $this->injectMainContentSkipTargetTabindex($html);
        } else {
            // Auth / painel B2B: critical inline define layout — omitir locks globais + folhas pesadas.
            $html = preg_replace('/<style id="awa-align-grid-inline-lock[^"]*"[^>]*>.*?<\/style>/s', '', $html) ?? $html;
            $html = preg_replace('/<style id="awa-align-grid-header-container-terminal"[^>]*>.*?<\/style>/s', '', $html) ?? $html;
            $html = preg_replace('/<link\s[^>]*awa-align-grid-terminal[^>]*\/?>\s*/i', '', $html) ?? $html;
            $stripFragments = $isB2bAccountFocusPage
                ? self::B2B_ACCOUNT_STRIP_CSS_FRAGMENTS
                : self::AUTH_STRIP_CSS_FRAGMENTS;
            $html = $this->stripStylesheetFragments($html, $stripFragments);
            $html = preg_replace('/<script[^>]*awa-mirasvit-autocomplete-init[^>]*><\/script>\s*/i', '', $html) ?? $html;
            $html = preg_replace('/<link[^>]+href=["\'][^"\']*\/fonts\/(?:rubik|source-sans-3)\/[^"\']+["\'][^>]*>\s*/i', '', $html) ?? $html;
            $html = preg_replace('/<link[^>]+rel=["\']preload["\'][^>]+as=["\']style["\'][^>]+awa-cookie-consent-fix[^>]*>\s*/i', '', $html) ?? $html;
            if ($isAuthFocusPage) {
                $html = $this->stripAuthPageNoise($html);
                $subject->setHeader('X-Awa-Header-Optimize', 'v12-auth', true);
            } else {
                $html = $this->stripB2bAccountPageNoise($html);
                $subject->setHeader('X-Awa-Header-Optimize', 'v12-b2b-account', true);
            }
        }
        $subject->setBody($html);
    }

    private function isB2bAccountFocusPage(string $fullAction, string $html): bool
    {
        if (in_array($fullAction, HeaderImpeccableCascadeLockCss::B2B_ACCOUNT_FOCUS_ACTIONS, true)) {
            return true;
        }

        if (str_starts_with($fullAction, 'b2b_account_')
            && !in_array($fullAction, HeaderImpeccableCascadeLockCss::AUTH_FOCUS_ACTIONS, true)
        ) {
            return true;
        }

        return HeaderImpeccableCascadeLockCss::isB2bAccountOperationalHtml($html);
    }

    /**
     * Painel B2B logado — remove JS/CSS global desnecessário (quickview, maps, css-gate home).
     */
    private function stripB2bAccountPageNoise(string $html): string
    {
        $html = $this->stripAuthPageNoise($html);
        $html = preg_replace('/<script[^>]*awa-css-gate[^>]*><\/script>\s*/i', '', $html) ?? $html;
        $html = preg_replace('/<script id="awa-css-gate-queue"[^>]*>.*?<\/script>\s*/is', '', $html) ?? $html;

        return $html;
    }

    /**
     * Auth B2B — remove JS/CSS residual do shell global (quickview, ajaxsuite, Page Builder maps, print).
     */
    private function stripAuthPageNoise(string $html): string
    {
        $html = preg_replace('/<link\s[^>]*href=["\'][^"\']*\/css\/print\.css[^"\']*["\'][^>]*\/?>\s*/i', '', $html) ?? $html;
        $html = preg_replace('/<noscript>\s*<link[^>]+awa-cookie-consent-fix[^>]+>\s*<\/noscript>\s*/i', '', $html) ?? $html;
        $html = preg_replace(
            '/<script type="text\/x-magento-init">\s*\{[^}]*"rokanthemes\/ajaxsuite"[^<]*<\/script>\s*/is',
            '',
            $html
        ) ?? $html;
        $html = preg_replace(
            '/<script type="text\/x-magento-init">\s*\{[^}]*quickview-product[^<]*<\/script>\s*/is',
            '',
            $html
        ) ?? $html;
        $html = preg_replace(
            '/<script type="text\/x-magento-init">\s*\{[^}]*"pageCache"[^<]*<\/script>\s*/is',
            '',
            $html
        ) ?? $html;
        $html = preg_replace(
            '/<script[^>]*>\s*require\.config\(\{[^}]*googleMaps[^<]*<\/script>\s*/is',
            '',
            $html
        ) ?? $html;
        $html = preg_replace(
            '/<script[^>]*>\s*require\.config\(\{[^}]*wysiwygAdapter[^<]*<\/script>\s*/is',
            '',
            $html
        ) ?? $html;
        $html = preg_replace(
            '/<script[^>]*>\s*require\.config\(\{[^}]*Magento_PageBuilder\/js\/utils\/map[^<]*<\/script>\s*/is',
            '',
            $html
        ) ?? $html;
        $html = preg_replace(
            '/<body([^>]*)\sdata-mage-init=\'[^\']*loaderAjax[^\']*\'/i',
            '<body$1',
            $html
        ) ?? $html;

        return $html;
    }

    /**
     * Neutralizador terminal — container intermediário do header sem pad (vence themes.min.css tardio).
     */
    private function injectAlignGridHeaderContainerTerminal(string $html): string
    {
        $html = preg_replace('/<style id="awa-align-grid-header-container-terminal"[^>]*>.*?<\/style>/s', '', $html) ?? $html;

        $css = '<style id="awa-align-grid-header-container-terminal">'
            . 'html body#html-body#html-body .page-wrapper .header .header-main>.container,'
            . 'html body#html-body#html-body .page-wrapper .header .header_main>.container,'
            . 'html body#html-body#html-body .page-wrapper .header .header-main .container,'
            . 'html body#html-body#html-body .page-wrapper .header .header_main .container,'
            . 'html body#html-body#html-body .page-wrapper .header-main>.container,'
            . 'html body#html-body#html-body .page-wrapper .header_main>.container,'
            . 'html body#html-body#html-body .page-wrapper .awa-site-header .header-main>.container,'
            . 'html body#html-body#html-body .page-wrapper .awa-site-header .header_main>.container'
            . '{padding:0!important;padding-inline:0!important;padding-left:0!important;padding-right:0!important}'
            . '</style>'
            . '<script id="awa-header-container-pad-zero">(function(){'
            . '"use strict";function z(){if(window.innerWidth>991)return;'
            . 'document.querySelectorAll(".page-wrapper .header-main>.container,.page-wrapper .header_main>.container").forEach(function(el){'
            . 'el.style.setProperty("padding","0","important");'
            . 'el.style.setProperty("padding-inline","0","important");'
            . 'el.style.setProperty("padding-left","0","important");'
            . 'el.style.setProperty("padding-right","0","important");});}'
            . 'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",z,{once:true});}'
            . 'else{z();}window.addEventListener("load",z,{once:true,passive:true});})();</script>';

        $injected = preg_replace('/<\/body>/i', $css . "\n</body>", $html, 1);

        return is_string($injected) ? $injected : $html;
    }

    /**
     * WCAG 2.4.1 — skip-link precisa de alvo focável (#maincontent nas páginas não-home).
     */
    private function injectMainContentSkipTargetTabindex(string $html): string
    {
        if (!str_contains($html, 'id="maincontent"')) {
            return $html;
        }

        if (preg_match('/<main\s[^>]*id="maincontent"[^>]*\btabindex\s*=/i', $html)) {
            return $html;
        }

        $patched = preg_replace(
            '/<main(\s[^>]*id="maincontent"[^>]*)>/i',
            '<main$1 tabindex="-1">',
            $html,
            1
        );

        return is_string($patched) ? $patched : $html;
    }

    /**
     * Lock inline — última camada da cascata; eixo 1280px site-wide (vence page-containers async).
     */
    private function injectSiteShellInlineLock(string $html, string $fullAction): string
    {
        $html = preg_replace('/<style id="awa-align-grid-inline-lock[^"]*"[^>]*>.*?<\/style>/s', '', $html) ?? $html;

        $css = '<style id="awa-align-grid-inline-lock-20260612">'
            . 'html body#html-body#html-body{--awa-grid-shell-max:min(100%,1280px);--awa-grid-container-pad:16px;'
            . '--awa-grid-card-gap:12px;--awa-grid-col-gap:12px;--awa-grid-section-gap:16px}'
            . 'html body#html-body#html-body .page-wrapper .awa-site-header'
            . ' :is(.header-main,.header_main)>.container,html body#html-body#html-body .page-wrapper #header'
            . ' :is(.header-main,.header_main)>.container{padding-inline:0!important;max-width:100%!important;'
            . 'width:100%!important;margin-inline:0!important;box-sizing:border-box!important}'
            . 'html body#html-body#html-body .page-wrapper .awa-site-header'
            . ' :is(.awa-main-header__inner,.awa-header-inner,.header-content){box-sizing:border-box!important;'
            . 'margin-inline:auto!important;max-width:min(100%,1280px)!important;'
            . 'padding-inline:16px!important;width:100%!important}'
            . 'html body#html-body#html-body:not(.checkout-index-index):not(.onepagecheckout-index-index):not(.rokanthemes-onepagecheckout)'
            . ' .page-wrapper :is(.page-main.container,#maincontent.page-main.container,#maincontent#maincontent.page-main.container){'
            . 'box-sizing:border-box!important;margin-inline:auto!important;'
            . 'max-width:min(100%,1280px)!important;padding-inline:16px!important;width:100%!important}'
            . 'html body#html-body#html-body .page-wrapper :is(.page_footer,.page-footer) #footer .footer-container{'
            . 'box-sizing:border-box!important;margin-inline:auto!important;max-width:min(100%,1280px)!important;'
            . 'padding-inline:16px!important;width:100%!important}'
            . 'html body#html-body#html-body.checkout-cart-index .page-wrapper .cart-container{'
            . 'box-sizing:border-box!important;margin-inline:auto!important;max-width:min(100%,1280px)!important;'
            . 'padding-inline:16px!important;width:100%!important}'
            . 'html body#html-body#html-body.checkout-cart-index .page-wrapper :is(.page-main.container,#maincontent.page-main.container){'
            . 'box-sizing:border-box!important;margin-inline:auto!important;max-width:min(100%,1280px)!important;'
            . 'padding-inline:16px!important;width:100%!important}'
            . 'html body#html-body#html-body:is(.catalog-category-view,.catalogsearch-result-index,.catalog-product-view)'
            . ' .page-wrapper .page-main.container .breadcrumbs{box-sizing:border-box!important;margin-inline:0!important;'
            . 'max-width:100%!important;padding-inline:0!important;width:100%!important}'
            . 'html body#html-body#html-body:is(.catalog-category-view,.catalogsearch-result-index,.catalog-product-view)'
            . ' .page-wrapper .nav-breadcrumbs{min-height:0!important;max-height:none!important}'
            . 'html body#html-body#html-body .page-wrapper .awa-site-header'
            . ' :is(.awa-utility-bar>.container,.top-header .container){box-sizing:border-box!important;'
            . 'margin-inline:auto!important;max-width:min(100%,1280px)!important;'
            . 'padding-inline:16px!important;width:100%!important}'
            . '@media (max-width:991px){html body#html-body#html-body .page-wrapper .awa-site-header,'
            . 'html body#html-body#html-body .page-wrapper .awa-site-header :is(.header.awa-main-header,.header-main,.header_main,.top-header){'
            . 'width:100%!important;max-width:100%!important;margin-inline:0!important;padding-inline:0!important}'
            . 'html body#html-body#html-body .page-wrapper .awa-site-header :is(.header-main,.header_main)>.container,'
            . 'html body#html-body#html-body .page-wrapper #header :is(.header-main,.header_main)>.container{'
            . 'padding-inline:0!important;max-width:100%!important;width:100%!important;margin-inline:0!important}'
            . 'html body#html-body#html-body .page-wrapper .awa-site-header'
            . ' :is(.awa-main-header__inner,.awa-header-inner,.header-content),html body#html-body#html-body:not(.checkout-index-index):not(.onepagecheckout-index-index)'
            . ':not(.rokanthemes-onepagecheckout) .page-wrapper :is(.page-main.container,#maincontent.page-main.container),'
            . 'html body#html-body#html-body .page-wrapper :is(.page_footer,.page-footer) #footer .footer-container{'
            . 'max-width:100%!important;padding-inline:16px!important}}';

        if ($fullAction === self::HOME_ACTION) {
            $css .= 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .content-top-home{padding-inline:0!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .content-top-home'
                . ' .ayo-home5-wrapper--template-driven :is('
                . '.top-home-content:not(.top-home-content--above-fold)>.container,'
                . '.top-home-content--category-carousel.awa-home-section>.container),'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .content-top-home'
                . ' :is(.top-home-content.awa-home-section,.top-home-content.awa-carousel-section)>.container,'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper'
                . ' .content-top-home .awa-hero-b2b-cta__inner.container,'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper .awa-home-pricing-notice>.container'
                . '{box-sizing:border-box!important;margin-inline:auto!important;max-width:min(100%,1280px)!important;'
                . 'padding-inline:16px!important;width:100%!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper'
                . ' .content-top-home .ayo-home5-wrapper--template-driven'
                . ' .top-home-content--category-carousel.awa-home-section{width:100%!important;max-width:100%!important;margin-inline:0!important}'
                . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper'
                . ' .content-top-home :is(.top-home-content.awa-home-section,.top-home-content.awa-carousel-section):not(.top-home-content--above-fold)'
                . '{padding-inline:0!important}'
                . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper'
                . ' .content-top-home :is(.awa-section-header,.awa-shelf__header){padding-inline:0!important}'
                . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper'
                . ' .awa-carousel-section :is(.owl-stage,.owl-wrapper,.owl-wrapper-outer){align-items:stretch!important;display:flex!important}'
                . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper'
                . ' .awa-carousel-section .product-thumb .product-thumb-link{aspect-ratio:1/1!important}'
                . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper'
                . ' .awa-carousel-section :is(.content-item-product,.item-product){max-height:none!important}'
                . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper'
                . ' :is(.top-home-content.awa-home-section,.top-home-content.awa-carousel-section):not(.top-home-content--above-fold)'
                . '{padding-block:clamp(12px,1.5vw,20px)!important;margin-block:0!important}'
                . 'html body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper'
                . ' :is(.awa-section-header,.awa-shelf__header){display:flex!important;flex-wrap:wrap!important;'
                . 'justify-content:space-between!important;align-items:flex-end!important;'
                . 'gap:clamp(8px,1vw,16px)!important;margin-block-end:12px!important}'
                . '@media(max-width:767px){html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5)'
                . ' .page-wrapper :is(.page-main.container,#maincontent.page-main.container){padding-inline:16px!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5){overflow-x:clip!important}}';
        }

        if (in_array($fullAction, self::CATALOG_HEADER_ACTIONS, true)) {
            $css .= 'html body#html-body:is(.catalog-category-view,.catalogsearch-result-index) .page-wrapper'
                . ' .page-main>.columns.layout.layout-2-col.row{margin-inline:0!important;padding-inline:0!important}'
                . 'html body#html-body:is(.catalog-category-view,.catalogsearch-result-index) .page-wrapper'
                . ' .page-main>.columns.layout.layout-2-col>[class*=col-]{float:none!important;width:auto!important;'
                . 'max-width:none!important;padding-inline:0!important;min-width:0!important}'
                . 'html body#html-body:is(.catalog-category-view,.catalogsearch-result-index) .page-wrapper .columns{'
                . 'display:grid!important;column-gap:12px!important;row-gap:0!important;'
                . 'grid-template-columns:minmax(220px,260px) minmax(0,1fr)!important;align-items:start!important;'
                . 'margin-inline:0!important;padding-inline:0!important}'
                . 'html body#html-body:is(.catalog-category-view,.catalogsearch-result-index) .page-wrapper .products-grid .product-items{'
                . 'display:grid!important;gap:12px!important;'
                . 'grid-template-columns:repeat(4,minmax(0,1fr))!important;align-items:stretch!important}'
                . 'html body#html-body:is(.catalog-category-view,.catalogsearch-result-index) .page-wrapper .products-grid .item-product{'
                . 'display:flex!important;flex-direction:column!important;height:100%!important;min-width:0!important}'
                . 'html body#html-body:is(.catalog-category-view,.catalogsearch-result-index) .page-wrapper'
                . ' :is(.page-title-wrapper .page-title,.awa-category-hero__title){'
                . 'font-family:var(--awa-font-heading,"Rubik",system-ui,sans-serif)!important;'
                . 'font-weight:600!important;font-size:clamp(1.25rem,1rem + 1vw,1.5rem)!important;line-height:1.3!important}'
                . 'html body#html-body:is(.catalog-category-view,.catalogsearch-result-index) .page-wrapper .toolbar.toolbar-products{'
                . 'display:flex!important;flex-wrap:wrap!important;align-items:center!important;'
                . 'justify-content:space-between!important;gap:8px 12px!important}'
                . '@media(max-width:1199px){html body#html-body:is(.catalog-category-view,.catalogsearch-result-index) .page-wrapper'
                . ' .products-grid .product-items{grid-template-columns:repeat(3,minmax(0,1fr))!important}}'
                . '@media(max-width:991px){html body#html-body:is(.catalog-category-view,.catalogsearch-result-index) .page-wrapper .columns{'
                . 'grid-template-columns:minmax(0,1fr)!important}'
                . 'html body#html-body:is(.catalog-category-view,.catalogsearch-result-index) .page-wrapper'
                . ' .products-grid .product-items{grid-template-columns:repeat(2,minmax(0,1fr))!important}}'
                . '@media(max-width:479px){html body#html-body:is(.catalog-category-view,.catalogsearch-result-index) .page-wrapper'
                . ' .products-grid .product-items{grid-template-columns:minmax(0,1fr)!important}}';
        }

        if ($fullAction === 'catalog_product_view') {
            $css .= 'html body#html-body.catalog-product-view .page-wrapper'
                . ' :is(.nav-breadcrumbs,#maincontent.page-main.container,.page-main.container){'
                . 'box-sizing:border-box!important;margin-inline:auto!important;'
                . 'max-width:min(100%,1280px)!important;padding-inline:16px!important;width:100%!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .page-main.container{'
                . 'box-sizing:border-box!important;margin-inline:auto!important;'
                . 'max-width:min(100%,1280px)!important;padding-inline:16px!important;width:100%!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .page-title-wrapper .page-title{'
                . 'font-family:var(--awa-font-heading,"Rubik",system-ui,sans-serif)!important;'
                . 'font-weight:600!important;font-size:clamp(1.375rem,1.1rem + 1.2vw,1.75rem)!important;'
                . 'line-height:1.25!important;margin-block-end:12px!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product-info-main{'
                . 'display:flex!important;flex-direction:column!important;gap:12px!important;min-width:0!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product-info-main'
                . ' :is(.product-info-stock-sku,.product-info-price){margin:0!important;padding:0!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product-info-main .product-info-price{'
                . 'display:grid!important;gap:8px!important;margin-block-end:8px!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product-info-main .box-tocart{'
                . 'border:1px solid var(--awa-border,#e5e5e5)!important;border-radius:8px!important;'
                . 'background:var(--awa-bg,#ffffff)!important;padding:8px!important;margin:0!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product-info-main .box-tocart .fieldset{'
                . 'display:grid!important;grid-template-columns:minmax(84px,108px) minmax(0,1fr)!important;'
                . 'gap:8px!important;align-items:end!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product-info-main .box-tocart'
                . ' :is(.field.qty,.actions){margin:0!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product-info-main .product-add-form{'
                . 'margin:0!important;padding:8px!important;border:1px solid var(--awa-border,#e5e5e5)!important;'
                . 'border-radius:8px!important;background:var(--awa-bg,#ffffff)!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product-info-main .product-add-form'
                . ' form#product_addtocart_form{display:grid!important;grid-template-columns:minmax(84px,108px) minmax(0,1fr)!important;'
                . 'gap:8px!important;align-items:end!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product-info-main .product-add-form'
                . ' form#product_addtocart_form>:is(.attr-product,.actions){margin:0!important;min-width:0!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product-info-main .product-add-form'
                . ' form#product_addtocart_form>.actions .action.tocart{width:100%!important;max-width:none!important;'
                . 'display:inline-flex!important;justify-content:center!important}'
                . '@media(max-width:991px){html body#html-body.catalog-product-view .page-wrapper .main-detail>.row{'
                . 'display:flex!important;flex-direction:column!important;gap:12px!important;align-items:stretch!important;'
                . 'grid-template-columns:none!important;margin-inline:0!important;padding-inline:0!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .main-detail>.row>:is(.col-md-6,.col-sm-6){'
                . 'flex:0 0 100%!important;width:100%!important;max-width:100%!important;min-width:0!important}}'
                . '@media(min-width:992px){html body#html-body.catalog-product-view .page-wrapper .main-detail>.row{'
                . 'display:flex!important;flex-wrap:nowrap!important;gap:12px!important;align-items:flex-start!important;'
                . 'grid-template-columns:none!important;margin-inline:0!important;padding-inline:0!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .main-detail>.row>.col-md-6.col-sm-6.col-xs-12:first-child{'
                . 'flex:1 1 54%!important;max-width:54%!important;min-width:0!important;width:auto!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .main-detail>.row>.col-md-6.col-sm-6.col-xs-12:last-child{'
                . 'flex:1 1 46%!important;max-width:46%!important;min-width:0!important;width:auto!important}}'
                . 'html body#html-body.catalog-product-view .page-wrapper .main-detail>.row>.col-md-6:last-child .product-info-main,'
                . 'html body#html-body.catalog-product-view .page-wrapper .product-info-main.detail-info{'
                . 'width:100%!important;max-width:100%!important;min-width:0!important;box-sizing:border-box!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product-info-main .product-add-form form#product_addtocart_form{'
                . 'align-items:end!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product-info-main .product-add-form form#product_addtocart_form>.attr-product{'
                . 'align-self:end!important;margin-bottom:0!important;padding-bottom:0!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product-info-main .product-add-form form#product_addtocart_form>.actions{'
                . 'align-self:end!important;margin-bottom:0!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product-info-main .product-add-form form#product_addtocart_form'
                . ' :is(.info-qty input.qty,.actions .action.tocart){height:44px!important;box-sizing:border-box!important}'
                . '@media(max-width:767px){html body#html-body.catalog-product-view .page-wrapper .product-info-main .box-tocart .fieldset{'
                . 'grid-template-columns:minmax(0,1fr)!important;gap:8px!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product-info-main .product-add-form'
                . ' form#product_addtocart_form{grid-template-columns:minmax(0,1fr)!important;gap:8px!important}}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product.info.detailed{'
                . 'box-sizing:border-box!important;margin-top:clamp(12px,2vw,20px)!important;margin-bottom:clamp(8px,1.5vw,12px)!important;padding:12px!important;'
                . 'border:1px solid var(--awa-border)!important;border-radius:8px!important;background:var(--awa-bg)!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product.data.items{'
                . 'box-sizing:border-box!important;margin-top:0!important;margin-bottom:0!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product.data.items>.item.title{'
                . 'margin:0!important;min-height:40px!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product.data.items>.item.title>.switch{'
                . 'min-height:40px!important;padding:8px 12px!important;border-radius:6px 6px 0 0!important;'
                . 'font-size:13px!important;font-weight:700!important;line-height:1.35!important;letter-spacing:.01em!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product.data.items>.item.content{'
                . 'box-sizing:border-box!important;padding:12px!important;border:1px solid var(--awa-border)!important;'
                . 'border-radius:0 8px 8px 8px!important;background:var(--awa-bg)!important;font-size:13px!important;line-height:1.45!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product.data.items'
                . ' :is(table,table.additional-attributes,.additional-attributes){width:100%!important;max-width:100%!important;'
                . 'font-size:13px!important;line-height:1.4!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product.data.items'
                . ' :is(th,td){padding:8px 12px!important;border-color:var(--awa-border)!important;vertical-align:top!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product.data.items th{'
                . 'font-weight:700!important;color:var(--awa-text)!important;background:color-mix(in srgb,var(--awa-primary) 5%,var(--awa-bg))!important}'
                . '@media(min-width:768px){html body#html-body#html-body#html-body.catalog-product-view .page-wrapper'
                . ' .product.data.items table.additional-attributes :is(th,td),'
                . 'html body#html-body#html-body#html-body.catalog-product-view .page-wrapper'
                . ' .product.data.items .additional-attributes :is(th,td){display:table-cell!important;'
                . 'padding:8px 12px!important;margin:0!important;line-height:1.4!important}}'
                . '@media(max-width:767px){html body#html-body.catalog-product-view .page-wrapper .product.info.detailed{'
                . 'margin-top:12px!important;padding:8px!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product.data.items>.item.content{padding:8px!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product.data.items :is(th,td){padding:8px!important}}'
                . '@media(max-width:991px){html body#html-body.catalog-product-view .page-wrapper'
                . ' :is(.gallery-placeholder,.fotorama-item,.fotorama){'
                . 'min-height:min(374px,calc(100vw - 16px))!important;box-sizing:border-box!important}}'
                . '@media(min-width:992px){html body#html-body.catalog-product-view .page-wrapper .product.media .fotorama__stage{'
                . 'min-height:0!important;height:clamp(340px,32vw,400px)!important;max-height:clamp(340px,32vw,400px)!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product.media'
                . ' :is(.fotorama__wrap,.fotorama-item,.gallery-placeholder){max-height:clamp(440px,38vw,500px)!important;min-height:0!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product.media .fotorama__nav-wrap{'
                . 'margin-top:6px!important;padding-block:4px!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .product.media .fotorama__nav__frame{height:56px!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .main-detail:has(.b2b-login-to-see-price) .product.media{min-height:0!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .main-detail:has(.b2b-login-to-see-price) .product.media .fotorama__stage{'
                . 'height:clamp(280px,24vw,340px)!important;max-height:clamp(280px,24vw,340px)!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .main-detail:has(.b2b-login-to-see-price) .product.media'
                . ' :is(.fotorama__wrap,.fotorama-item,.gallery-placeholder){max-height:clamp(380px,30vw,440px)!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .main-detail:has(.b2b-login-to-see-price) .product.media'
                . ' .fotorama__nav__frame{height:52px!important}}'
                . 'html body#html-body.catalog-product-view .page-wrapper .col-main>.product-view{margin-bottom:0!important}'
                . '@media(min-width:992px){html body#html-body#html-body#html-body#html-body.catalog-product-view .page-wrapper'
                . ' .col-main:has(.b2b-login-to-see-price)>.product.info.detailed{'
                . 'margin-top:clamp(-140px,-10vw,-72px)!important;position:relative;z-index:2}}'
                . 'html body#html-body.catalog-product-view .page-wrapper .awa-pdp-related{'
                . 'margin-top:clamp(16px,2.5vw,24px)!important;margin-bottom:clamp(16px,2.5vw,24px)!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .awa-pdp-related.awa-shelf--carousel .awa-carousel__viewport{'
                . 'display:flex!important;flex-direction:row!important;flex-wrap:nowrap!important;overflow-x:auto!important;'
                . 'width:100%!important;gap:0!important;padding-block:8px!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .awa-pdp-related.awa-shelf--carousel .awa-carousel__track{'
                . 'display:flex!important;flex-direction:row!important;gap:12px!important;width:max-content!important;min-width:100%!important}'
                . 'html body#html-body.catalog-product-view .page-wrapper .awa-pdp-related>.awa-owl-nav,'
                . 'html body#html-body.catalog-product-view .page-wrapper .awa-pdp-related>.awa-owl-progress{'
                . 'display:none!important}'
                . '@media(pointer:coarse){html body#html-body.catalog-product-view .page-wrapper .product-info-main'
                . ' :is(.action.primary,#product-addtocart-button){min-height:44px!important;min-width:44px!important;'
                . 'display:inline-flex!important;align-items:center!important;justify-content:center!important}}';
        }

        if ($fullAction === self::CART_ACTION) {
            $css .= 'html body#html-body.checkout-cart-index .page-wrapper .page-title-wrapper .page-title{'
                . 'font-family:var(--awa-font-heading,"Rubik",system-ui,sans-serif)!important;'
                . 'font-weight:600!important;font-size:clamp(1.25rem,1rem + 1vw,1.5rem)!important;line-height:1.3!important}'
                . '@media(min-width:768px){html body#html-body.checkout-cart-index .page-wrapper .cart-container{'
                . 'display:grid!important;grid-template-columns:minmax(0,1fr) minmax(300px,360px)!important;'
                . 'gap:clamp(20px,2.5vw,28px)!important;align-items:start!important}}'
                . '@media(max-width:767px){html body#html-body.checkout-cart-index .page-wrapper .cart-container{'
                . 'display:flex!important;flex-direction:column!important;gap:clamp(16px,4vw,24px)!important}'
                . 'html body#html-body.checkout-cart-index .page-wrapper .cart-container .cart-summary{order:-1!important}}'
                . 'html body#html-body#html-body.checkout-cart-index .page-wrapper .awa-site-header .awa-header-search-col '
                . 'form#search_mini_form{display:flex!important;align-items:stretch!important;flex-wrap:nowrap!important}'
                . 'html body#html-body#html-body.checkout-cart-index .page-wrapper .awa-site-header .awa-header-search-col '
                . 'form#search_mini_form .field.search{flex:1 1 auto!important;min-width:0!important;width:auto!important}';
        }

        // §APF-T — busca header mobile compacta (polish 2026-06-12, última camada inline)
        $css .= 'html body#html-body#html-body#html-body#html-body .page-wrapper .awa-site-header:not(.awa-header-condensed) '
            . '.awa-header-search-col :is(form#search_mini_form,form.minisearch){display:flex!important;align-items:stretch!important;'
            . 'height:36px!important;min-height:36px!important;max-height:36px!important;overflow:hidden!important}'
            . 'html body#html-body#html-body#html-body#html-body .page-wrapper .awa-site-header:not(.awa-header-condensed) '
            . '.awa-header-search-col form#search_mini_form .field.search{display:flex!important;flex:1 1 auto!important;'
            . 'min-width:0!important;height:36px!important;min-height:36px!important;max-height:36px!important;margin:0!important;padding:0!important}'
            . 'html body#html-body#html-body#html-body#html-body .page-wrapper .awa-site-header:not(.awa-header-condensed) '
            . '.awa-header-search-col form#search_mini_form .field.search>label.label{border:0!important;'
            . 'clip:rect(0,0,0,0)!important;height:1px!important;margin:-1px!important;overflow:hidden!important;'
            . 'padding:0!important;position:absolute!important;width:1px!important;white-space:nowrap!important}'
            . 'html body#html-body#html-body#html-body#html-body .page-wrapper .awa-site-header:not(.awa-header-condensed) '
            . '.awa-header-search-col form#search_mini_form .field.search .control{display:flex!important;flex:1 1 auto!important;'
            . 'min-width:0!important;width:auto!important;height:36px!important;min-height:36px!important;max-height:36px!important}'
            . 'html body#html-body#html-body#html-body#html-body .page-wrapper .awa-site-header:not(.awa-header-condensed) '
            . '.awa-header-search-col form#search_mini_form input#search{position:static!important;display:block!important;'
            . 'width:100%!important;min-width:0!important;height:36px!important;min-height:36px!important;'
            . 'max-height:36px!important;margin:0!important;box-sizing:border-box!important}'
            . 'html body#html-body#html-body#html-body#html-body .page-wrapper .awa-site-header:not(.awa-header-condensed) '
            . '.awa-header-search-col form#search_mini_form .actions{display:flex!important;align-items:stretch!important;'
            . 'align-self:stretch!important;flex:0 0 48px!important;width:48px!important;min-width:48px!important;'
            . 'max-width:48px!important;height:36px!important;min-height:36px!important;max-height:36px!important;'
            . 'margin:0!important;padding:0!important;position:static!important;inset:auto!important}'
            . 'html body#html-body#html-body#html-body#html-body .page-wrapper .awa-site-header:not(.awa-header-condensed) '
            . '.awa-header-search-col form#search_mini_form :is(.action.search,button.action.search){display:inline-flex!important;'
            . 'align-items:center!important;justify-content:center!important;flex:0 0 48px!important;'
            . 'width:48px!important;min-width:48px!important;max-width:48px!important;'
            . 'height:36px!important;min-height:36px!important;max-height:36px!important;align-self:stretch!important;'
            . 'position:static!important;inset:auto!important;margin:0!important;padding:0!important;box-sizing:border-box!important}'
            . '@media(max-width:767px){'
            . 'html body#html-body#html-body#html-body#html-body .page-wrapper .awa-b2b-promo-bar{height:32px!important;'
            . 'min-height:32px!important;max-height:32px!important;overflow:visible!important;background:var(--awa-primary)!important;'
            . 'color:var(--awa-on-primary,white)!important;width:100vw!important;max-width:100vw!important;'
            . 'margin-inline:calc(50% - 50vw)!important}'
            . 'html body#html-body#html-body#html-body#html-body .page-wrapper .awa-b2b-promo-bar__inner{height:32px!important;'
            . 'min-height:32px!important;max-height:32px!important;display:flex!important;align-items:center!important;'
            . 'justify-content:center!important;position:relative!important;padding-inline:48px!important;overflow:visible!important;'
            . 'background:var(--awa-primary)!important;color:var(--awa-on-primary,white)!important}'
            . 'html body#html-body#html-body#html-body#html-body .page-wrapper .awa-b2b-promo-bar '
            . ':is(.awa-b2b-promo-bar__lead,.awa-b2b-promo-bar__lead-long,.awa-b2b-promo-bar__lead-short,'
            . '.awa-b2b-promo-bar__separator,.awa-b2b-promo-bar__tail,.awa-b2b-promo-bar__cta-long){display:none!important}'
            . 'html body#html-body#html-body#html-body#html-body .page-wrapper .awa-b2b-promo-bar__cta-short{display:block!important}'
            . 'html body#html-body#html-body#html-body#html-body .page-wrapper .awa-b2b-promo-bar__cta{display:inline-flex!important;'
            . 'align-items:center!important;justify-content:center!important;min-height:44px!important;height:44px!important;'
            . 'padding:8px 10px!important;margin-block:-8px!important;line-height:1.2!important;white-space:nowrap!important}'
            . 'html body#html-body#html-body#html-body#html-body .page-wrapper .awa-b2b-promo-close{position:absolute!important;'
            . 'right:6px!important;top:-6px!important;width:44px!important;height:44px!important;min-width:44px!important;'
            . 'min-height:44px!important;display:flex!important;align-items:center!important;justify-content:center!important;'
            . 'border:0!important;background:transparent!important;color:var(--awa-on-primary,white)!important;box-shadow:none!important}'
            . '}';

        $css .= '</style>';

        $injected = preg_replace('/<\/body>/i', $css . "\n</body>", $html, 1);

        return is_string($injected) ? $injected : $html;
    }

    /**
     * Única instância do align-grid — remove head/body-end duplicados e reinjeta só antes de </body>
     * (final-wins sobre bundles async). Economiza ~37KB de download duplicado na home/PLP.
     */
    private function consolidateAlignGridToBodyTerminal(string $html, string $fullAction): string
    {
        $html = $this->normalizeAlignGridStylesheetVersion($html);
        $deferAlignGrid = in_array($fullAction, self::DEFER_ALIGN_GRID_ACTIONS, true);

        if (!str_contains($html, 'awa-align-grid-terminal-2026-06-11')) {
            return $this->injectAlignGridBodyTerminalIfMissing($html, $deferAlignGrid, $fullAction);
        }

        $pattern = '/<link\s[^>]*awa-align-grid-terminal-2026-06-11[^>]*\/?>\s*/i';
        $html = preg_replace($pattern, '', $html) ?? $html;

        return $this->injectAlignGridBodyTerminalIfMissing($html, $deferAlignGrid, $fullAction);
    }

    /**
     * Normaliza query string stale do align-grid (evita 2 versões no mesmo HTML).
     */
    private function normalizeAlignGridStylesheetVersion(string $html): string
    {
        $file = HeaderImpeccableCascadeLockCss::ALIGN_GRID_CSS_FILE;
        $query = HeaderImpeccableCascadeLockCss::ALIGN_GRID_QUERY;
        $canonical = $file . $query;

        $html = preg_replace(
            '/awa-align-grid-terminal-2026-06-11(?:\.min)?\.css\?v=[^"\'&\s>]+/',
            $canonical,
            $html
        ) ?? $html;

        // Mantém só o link ativo (body-terminal > head sync); remove duplicatas pós-normalização.
        $pattern = '/<link\s[^>]*href=(["\'])' . preg_quote($canonical, '/') . '\1[^>]*\/?>\s*/i';
        if (!preg_match_all($pattern, $html, $matches) || count($matches[0]) < 2) {
            return $html;
        }

        $keepBody = null;
        foreach ($matches[0] as $tag) {
            if (str_contains($tag, 'data-awa-align-grid-body-terminal')) {
                $keepBody = $tag;
                break;
            }
        }

        $keep = $keepBody ?? $matches[0][array_key_last($matches[0])];
        $first = true;

        return preg_replace_callback(
            $pattern,
            static function (array $m) use (&$first, $keep): string {
                if ($first) {
                    $first = false;

                    return $keep;
                }

                return '';
            },
            $html
        ) ?? $html;
    }

    /**
     * Última camada CSS — reinjeta align-grid antes de </body> para vencer body-end/polish-type.
     */
    private function injectAlignGridBodyTerminalIfMissing(string $html, bool $defer = false, string $fullAction = ''): string
    {
        if (str_contains($html, 'data-awa-align-grid-body-terminal="1"')) {
            return $html;
        }

        if (!preg_match(
            '#/static/(version\d+)/frontend/AWA_Custom/ayo_home5_child/pt_BR/#',
            $html,
            $versionMatch
        )) {
            return $html;
        }

        $href = '/static/' . $versionMatch[1]
            . '/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/'
            . HeaderImpeccableCascadeLockCss::ALIGN_GRID_CSS_FILE
            . HeaderImpeccableCascadeLockCss::ALIGN_GRID_QUERY;
        $isHome = $fullAction === self::HOME_ACTION;
        $isCatalogDensity = in_array($fullAction, self::CATALOG_STACK_ACTIONS, true)
            || in_array($fullAction, self::CATALOG_HEADER_ACTIONS, true);
        $homeDensityHref = '/static/' . $versionMatch[1]
            . '/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/'
            . self::HOME_DENSITY_GRID_FILE
            . self::HOME_DENSITY_GRID_QUERY;
        $catalogDensityHref = '/static/' . $versionMatch[1]
            . '/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/'
            . self::CATALOG_DENSITY_GRID_FILE
            . self::CATALOG_DENSITY_GRID_QUERY;

        if ($defer) {
            /* Home/PLP/busca: inline lock cobre container/grid no 1º paint — folha completa async */
            $tag = '<link rel="preload" href="' . $href . '" as="style"/>'
                . '<link rel="stylesheet" href="' . $href . '" media="print" onload="this.media=\'all\'"'
                . ' data-awa-align-grid-body-terminal="1" data-awa-bundle="align-grid-terminal" data-awa-defer="1"/>'
                . '<noscript><link rel="stylesheet" href="' . $href . '" media="all"'
                . ' data-awa-align-grid-body-terminal="1" data-awa-bundle="align-grid-terminal"/></noscript>';
            if ($isHome && !str_contains($html, 'data-awa-home-density-grid-body-terminal="1"')) {
                $tag .= '<link rel="preload" href="' . $homeDensityHref . '" as="style"/>'
                    . '<link rel="stylesheet" href="' . $homeDensityHref . '" media="print" onload="this.media=\'all\'"'
                    . ' data-awa-home-density-grid-body-terminal="1" data-awa-bundle="home-density-grid" data-awa-defer="1"/>'
                    . '<noscript><link rel="stylesheet" href="' . $homeDensityHref . '" media="all"'
                    . ' data-awa-home-density-grid-body-terminal="1" data-awa-bundle="home-density-grid"/></noscript>';
            }
            if ($isCatalogDensity && !str_contains($html, 'data-awa-catalog-density-grid-body-terminal="1"')) {
                $tag .= '<link rel="stylesheet" href="' . $catalogDensityHref . '" media="all"'
                    . ' data-awa-catalog-density-grid-body-terminal="1" data-awa-bundle="catalog-density-grid"/>';
            }
        } else {
            $tag = '<link rel="stylesheet" href="' . $href . '" media="all"'
                . ' data-awa-align-grid-body-terminal="1" data-awa-bundle="align-grid-terminal"/>';
            if ($isHome && !str_contains($html, 'data-awa-home-density-grid-body-terminal="1"')) {
                $tag .= '<link rel="stylesheet" href="' . $homeDensityHref . '" media="all"'
                    . ' data-awa-home-density-grid-body-terminal="1" data-awa-bundle="home-density-grid"/>';
            }
            if ($isCatalogDensity && !str_contains($html, 'data-awa-catalog-density-grid-body-terminal="1"')) {
                $tag .= '<link rel="stylesheet" href="' . $catalogDensityHref . '" media="all"'
                    . ' data-awa-catalog-density-grid-body-terminal="1" data-awa-bundle="catalog-density-grid"/>';
            }
        }

        $injected = preg_replace('/<\/body>/i', $tag . "\n</body>", $html, 1);

        return is_string($injected) ? $injected : $html;
    }

    /**
     * PLP/busca/PDP: folhas não críticas para header/above-fold no 1º paint.
     */
    private function deferCatalogNonCriticalStylesheets(string $html): string
    {
        return $this->deferStylesheetsByFragments($html, self::CATALOG_DEFER_CSS_FRAGMENTS);
    }

    /**
     * Converte stylesheets síncronos em async (media=print + onload).
     *
     * @param string[] $fragments
     */
    private function deferStylesheetsByFragments(string $html, array $fragments): string
    {
        foreach ($fragments as $fragment) {
            $escaped = preg_quote($fragment, '/');
            $html = preg_replace_callback(
                '/<link\s+([^>]*?' . $escaped . '[^>]*?)\s*\/?>/i',
                static function (array $matches): string {
                    $attrs = $matches[1];
                    if (!preg_match('/rel=["\']stylesheet["\']/i', $attrs)) {
                        return $matches[0];
                    }
                    if (preg_match('/media=["\']print["\']/i', $attrs) || preg_match('/onload=/i', $attrs)) {
                        return $matches[0];
                    }

                    $attrs = preg_replace('/\s*media=["\']all["\']\s*/i', ' ', $attrs) ?? $attrs;
                    $attrs = trim(preg_replace('/\s+/', ' ', $attrs) ?? $attrs);
                    $attrs = rtrim($attrs, '/');

                    return '<link ' . $attrs . ' media="print" onload="this.media=\'all\'" data-awa-defer="catalog-noncritical"/>';
                },
                $html
            ) ?? $html;
        }

        return $html;
    }

    /**
     * PLP/busca/carrinho: templates ainda referenciam v=11; normaliza em toda resposta HTML com header.
     */
    private function normalizeHeaderTerminalStylesheetVersion(string $html): string
    {
        $terminalV = HeaderImpeccableCascadeLockCss::HEADER_TERMINAL_VERSION;

        if (!str_contains($html, 'awa-header-refine-terminal.min.css')) {
            return $html;
        }

        return preg_replace(
            '/awa-header-refine-terminal\.min\.css(?:\?v=[^"\'&\s>]+)?/',
            'awa-header-refine-terminal.min.css?v=' . $terminalV,
            $html
        ) ?? $html;
    }

    private function isHomeNeverGateFragment(string $fragment): bool
    {
        foreach (self::HOME_NEVER_GATE_FRAGMENTS as $never) {
            if (str_contains($fragment, $never)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Home: com stack consolidado ativo, remove folhas críticas legadas (FPC stale).
     */
    private function stripStaleHomeCriticalStylesheets(string $html): string
    {
        if (!str_contains($html, 'awa-home-critical-stack')) {
            return $html;
        }

        return $this->stripStylesheetFragments($html, [
            'awa-header-mobile-grid-critical',
            'awa-impeccable-critical-home',
            'awa-header-home-light-lock-v1',
            'awa-home-first-paint-critical',
            'awa-bundle-async-distill-lock',
            'awa-menu-v2-dept-open-fix',
        ]);
    }

    private function patchStaleHomeHeaderAssets(string $html): string
    {
        $gateJs = 'awa-css-gate.min.js?v=' . HeaderImpeccableCascadeLockCss::GATE_SCRIPT_QUERY;
        $html = str_replace(
            [
                'awa-css-gate.min.js?v=20260528-loader',
                'awa-css-gate.min.js?v=20260531-impeccable-v10',
                'awa-css-gate.min.js?v=20260531-impeccable-v11',
                'awa-css-gate.min.js?v=20260531-impeccable-v12',
                'awa-css-gate.min.js?v=20260601-home-opt3',
                'awa-css-gate.min.js?v=20260601-home-opt4',
                'awa-css-gate.min.js?v=20260601-home-opt5',
                'awa-css-gate.min.js?v=20260601-home-opt6',
                'awa-css-gate.min.js?v=20260601-home-opt7',
                'awa-css-gate.min.js?v=20260601-home-opt8',
                'awa-css-gate.min.js?v=20260601-carousel13',
                'awa-css-gate.min.js?v=20260601-home-opt17',
                'awa-css-gate.min.js?v=20260531-optimize',
            ],
            $gateJs,
            $html
        );

        $terminalV = HeaderImpeccableCascadeLockCss::HEADER_TERMINAL_VERSION;
        $html = preg_replace(
            '/awa-home-critical-stack-2026-06-11\.min\.css\?v=[^"\'&\s>]+/',
            'awa-home-critical-stack-2026-06-11.min.css?v=20260612-mobile-112',
            $html
        ) ?? $html;
        if (!str_contains($html, 'awa-header-refine-terminal.min.css?v=')) {
            $html = str_replace(
                'awa-header-refine-terminal.min.css">',
                'awa-header-refine-terminal.min.css?v=' . $terminalV . '">',
                $html
            );
        }

        return preg_replace(
            '/awa-header-refine-terminal\.min\.css\?v=[^"\'&\s>]+/',
            'awa-header-refine-terminal.min.css?v=' . $terminalV,
            $html
        ) ?? $html;
    }

    private function injectHeaderTerminalFixStyle(string $html): string
    {
        return HeaderImpeccableCascadeLockCss::injectBeforeBodyClose($html);
    }

    private function injectHeaderTerminalStylesheetIfMissing(string $html): string
    {
        /* header-refine-terminal migrado para styles-l — não reinjetar folha standalone. */
        return $html;
    }

    /**
     * Home: garante terminal CSS + fila gate no HTML (preload pode omitir quando $__gatedCssUrls vazio).
     */
    private function injectHomeHeaderTerminalAssets(string $html): string
    {
        $html = $this->injectHeaderTerminalStylesheetIfMissing($html);
        if (!preg_match(
            '#/static/(version\d+)/frontend/AWA_Custom/ayo_home5_child/pt_BR/#',
            $html,
            $versionMatch
        )) {
            return $html;
        }

        $staticBase = '/static/' . $versionMatch[1] . '/frontend/AWA_Custom/ayo_home5_child/pt_BR/';
        $terminalV = HeaderImpeccableCascadeLockCss::HEADER_TERMINAL_VERSION;
        $terminalHref = $staticBase . 'css/awa-header-refine-terminal.min.css?v=' . $terminalV;

        if (!str_contains($html, 'id="awa-css-gate-queue"')) {
            $queueTag = '<script id="awa-css-gate-queue" type="application/json" data-awa-terminal-href="'
                . htmlspecialchars($terminalHref, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '">[]</script>';
            $gateJs = $staticBase . 'js/awa-css-gate.min.js?v=' . HeaderImpeccableCascadeLockCss::GATE_SCRIPT_QUERY;
            $gateTag = '<script src="' . $gateJs . '" defer></script>';
            $replaced = preg_replace('/<\/head>/i', $queueTag . "\n" . $gateTag . "\n</head>", $html, 1);

            if (is_string($replaced)) {
                $html = $replaced;
            }
        }

        return $html;
    }

    /**
     * Home: garante distill-lock + align-grid terminal no head (sync media=all).
     * Templates/FPC podem omitir — plugin reinjeta antes de </head>.
     */
    private function injectHomeAlignGridStylesheetsIfMissing(string $html): string
    {
        if (!preg_match(
            '#/static/(version\d+)/frontend/AWA_Custom/ayo_home5_child/pt_BR/#',
            $html,
            $versionMatch
        )) {
            return $html;
        }

        $staticBase = '/static/' . $versionMatch[1] . '/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/';
        $tags = '';

        if (!str_contains($html, 'awa-home-critical-stack')
            && !str_contains($html, 'awa-bundle-async-distill-lock')
        ) {
            $tags .= '<link rel="stylesheet" href="' . $staticBase
                . 'awa-bundle-async-distill-lock.min.css' . HeaderImpeccableCascadeLockCss::HOME_DISTILL_LOCK_QUERY
                . '" media="all" data-awa-bundle="async-distill-lock"/>' . "\n";
        }

        /* align-grid: só via consolidateAlignGridToBodyTerminal (evita duplicata head + body) */

        if ($tags === '') {
            return $html;
        }

        $injected = preg_replace('/<\/head>/i', $tags . '</head>', $html, 1);

        return is_string($injected) ? $injected : $html;
    }

    /**
     * PLP/PDP/carrinho: garante align-grid terminal quando o loader body-end não renderiza (FPC).
     */
    private function injectAlignGridStylesheetIfMissing(string $html): string
    {
        if (str_contains($html, 'awa-align-grid-terminal-2026-06-11')) {
            return $html;
        }

        if (!preg_match(
            '#/static/(version\d+)/frontend/AWA_Custom/ayo_home5_child/pt_BR/#',
            $html,
            $versionMatch
        )) {
            return $html;
        }

        $href = '/static/' . $versionMatch[1]
            . '/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/'
            . HeaderImpeccableCascadeLockCss::ALIGN_GRID_CSS_FILE
            . HeaderImpeccableCascadeLockCss::ALIGN_GRID_QUERY;

        $tag = '<link rel="stylesheet" href="' . $href . '" media="all"'
            . ' data-awa-align-grid-terminal="1" data-awa-bundle="align-grid-terminal"/>';

        $injected = preg_replace('/<\/head>/i', $tag . "\n</head>", $html, 1);

        return is_string($injected) ? $injected : $html;
    }

    private function normalizeRefineStylesheetQuery(string $html): string
    {
        // Normaliza TODAS as versões antigas (.css?v=X e .min.css?v=X) para .min.css?v=18.
        return preg_replace(
            '/awa-commerce-impeccable-refine\.(?:min\.)?css(?:\?v=[^"\'&\s>]+)?/',
            HeaderImpeccableCascadeLockCss::REFINE_CSS_FILE . HeaderImpeccableCascadeLockCss::REFINE_QUERY,
            $html
        ) ?? $html;
    }

    /**
     * PDP: normaliza versões stale (OPcache / block_html) para terminal round 6.
     */
    private function normalizePdpTerminalStylesheets(string $html): string
    {
        $html = preg_replace(
            '/awa-bundle-async-distill-lock\.min\.css(?:\?v=[^"\'&\s>]+)?/',
            'awa-bundle-async-distill-lock.min.css' . HeaderImpeccableCascadeLockCss::PDP_DISTILL_LOCK_QUERY,
            $html
        ) ?? $html;

        return preg_replace(
            '/awa-ui-simplify-terminal\.min\.css(?:\?v=[^"\'&\s>]+)?/',
            'awa-ui-simplify-terminal.min.css' . HeaderImpeccableCascadeLockCss::PDP_UI_SIMPLIFY_QUERY,
            $html
        ) ?? $html;
    }

    /**
     * @param string[] $fragments
     */
    private function stripStylesheetFragments(string $html, array $fragments): string
    {
        foreach ($fragments as $fragment) {
            $html = preg_replace(
                '/<link\s[^>]*' . preg_quote($fragment, '/') . '[^>]*\/?>\s*/i',
                '',
                $html
            ) ?? $html;
            $html = preg_replace(
                '/<noscript>\s*<link\s[^>]*' . preg_quote($fragment, '/') . '[^>]*\/?>\s*<\/noscript>\s*/i',
                '',
                $html
            ) ?? $html;
        }

        return $html;
    }

    private function injectGlobalRefineStylesheetIfMissing(string $html): string
    {
        // REFINE_CSS_FRAGMENT não tem extensão: detecta tanto .css quanto .min.css.
        if (str_contains($html, self::REFINE_CSS_FRAGMENT)) {
            return $html;
        }

        if (!preg_match('#/static/(version\d+)/frontend/AWA_Custom/ayo_home5_child/pt_BR/#', $html, $versionMatch)) {
            return $html;
        }

        $href = '/static/' . $versionMatch[1]
            . '/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/'
            . HeaderImpeccableCascadeLockCss::REFINE_CSS_FILE
            . HeaderImpeccableCascadeLockCss::REFINE_QUERY;

        $tag = '<link rel="stylesheet" href="' . $href . '" media="print" onload="this.media=\'all\'"'
            . ' data-awa-impeccable-terminal="refine"/>';

        $injected = preg_replace('/<\/head>/i', $tag . "\n</head>", $html, 1);

        return is_string($injected) ? $injected : $html;
    }

    /**
     * PLP/busca: garante awa-plp-distill mesmo com block_html stale (e2e + terminal wins).
     */
    private function injectPlpDistillStylesheetIfMissing(string $html): string
    {
        if (str_contains($html, 'awa-plp-distill')) {
            return $html;
        }

        if (!preg_match('#/static/(version\d+)/frontend/AWA_Custom/ayo_home5_child/pt_BR/#', $html, $versionMatch)) {
            return $html;
        }

        $href = '/static/' . $versionMatch[1]
            . '/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/awa-plp-distill.min.css?v=20260610-round5';

        $tag = '<link rel="stylesheet" href="' . $href . '" media="print" onload="this.media=\'all\'"'
            . ' data-awa-plp-distill="terminal"/>';

        $injected = preg_replace('/<\/head>/i', $tag . "\n</head>", $html, 1);

        return is_string($injected) ? $injected : $html;
    }

    /**
     * Normaliza handlers onload legados (inline ~250B × N) para __awaCssQ(this) + bootstrap único.
     */
    private function normalizeCssStaggerOnloadHandlers(string $html): string
    {
        $legacy = 'var F=window.__awaCssQ;if(!F){var q=[],M=96;F=window.__awaCssQ=function(e){if(q.length<M){q.push(e);if(!F._r){F._r=1;requestAnimationFrame(function(){!function n(){q.length?(q.shift().media=\'all\',requestAnimationFrame(function(){setTimeout(n,32)})):F._r=0}()})}}}};F(this)';
        if (!str_contains($html, $legacy)) {
            return $html;
        }

        $bootstrap = '<script>!function(w){var q=[],r=0;w.__awaCssQ=function(e){if(q.length<96){q.push(e);if(!r){r=1;w.requestAnimationFrame(function t(){q.length?(q.shift().media="all",w.requestAnimationFrame(function(){setTimeout(t,40)})):r=0})}}}}(window);</script>';
        if (!str_contains($html, 'w.__awaCssQ=function')) {
            $replaced = preg_replace(
                '/(<!-- AWA CSS Stagger[^>]*-->\s*)?(<link\s[^>]*rel=["\']stylesheet["\'])/i',
                '$1' . $bootstrap . "\n" . '$2',
                $html,
                1
            );
            if (is_string($replaced)) {
                $html = $replaced;
            }
        }

        return str_replace($legacy, '__awaCssQ(this)', $html);
    }

    private function stripRedundantAsyncNoscript(string $html): string
    {
        $pattern = '/(<link\s(?=[^>]*rel=["\']stylesheet["\'])'
            . '(?=[^>]*media=["\']print["\'])[^>]*href=(["\'])([^"\']+)\1[^>]*\/?>)'
            . '\s*<noscript>\s*<link\s[^>]*href=\1\2\1[^>]*\/?>\s*<\/noscript>/i';

        $previous = null;
        while ($previous !== $html) {
            $previous = $html;
            $html = preg_replace($pattern, '$1', $html) ?? $html;
        }

        return $html;
    }

    private function stripStandaloneStylesheetNoscript(string $html): string
    {
        $pattern = '/<noscript>\s*<link\s[^>]*rel=["\']stylesheet["\'][^>]*\/?>\s*<\/noscript>/i';

        return preg_replace($pattern, '', $html) ?? $html;
    }

    private function gateHomeLargeStylesheets(string $html): string
    {
        if (!str_contains($html, 'id="awa-css-gate-queue"')) {
            return $html;
        }

        $urlsToGate = [];
        $fragmentPattern = static function (string $fragment): string {
            return preg_quote($fragment, '/');
        };

        foreach (self::HOME_GATE_CSS_FRAGMENTS as $fragment) {
            if ($this->isHomeNeverGateFragment($fragment)) {
                continue;
            }

            if (preg_match(
                '/href=(["\'])([^"\']*' . $fragmentPattern($fragment) . '[^"\']*)\1/is',
                $html,
                $match
            )) {
                $urlsToGate[] = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }

            /* Uma tag <link> por match — sem [\s\S] livre (evita comer o documento inteiro). */
            $html = preg_replace(
                '/<noscript>\s*<link\s[^>]*' . $fragmentPattern($fragment) . '[^>]*\/?>\s*<\/noscript>\s*/i',
                '',
                $html
            ) ?? $html;
        }

        $urlsToGate = array_values(array_filter(array_unique($urlsToGate)));

        if ($urlsToGate === []) {
            return $html;
        }

        return $this->mergeIntoCssGateQueue($html, $urlsToGate);
    }

    /**
     * Home: remove da fila gate folhas omitidas no preload (consolidadas em critical-home / gate-polish).
     */
    private function pruneHomeNeverGateQueueUrls(string $html): string
    {
        $urls = $this->extractGateQueueUrls($html);
        if ($urls === []) {
            return $html;
        }

        $filtered = array_values(array_filter(
            $urls,
            function ($url): bool {
                if (!is_string($url)) {
                    return false;
                }
                foreach (self::HOME_NEVER_GATE_FRAGMENTS as $never) {
                    if (str_contains($url, $never)) {
                        return false;
                    }
                }

                return true;
            }
        ));

        if (count($filtered) === count($urls)) {
            return $html;
        }

        return $this->replaceCssGateQueue($html, $filtered);
    }

    /**
     * @param string   $html
     * @param string[] $urls
     */
    private function replaceCssGateQueue(string $html, array $urls): string
    {
        $stylesM = [];
        $rest = [];
        foreach ($urls as $url) {
            if (is_string($url) && str_contains($url, 'styles-m.css')) {
                $stylesM[] = $url;
            } else {
                $rest[] = $url;
            }
        }
        $ordered = array_merge($rest, $stylesM);

        return preg_replace_callback(
            '/<script\s+id="awa-css-gate-queue"\s+type="application\/json"'
            . '(?:\s+data-awa-terminal-href="([^"]*)")?>(\[[^\]]*\])<\/script>/i',
            static function (array $matches) use ($ordered): string {
                $terminalAttr = isset($matches[1]) && $matches[1] !== ''
                    ? ' data-awa-terminal-href="' . htmlspecialchars($matches[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
                    : '';

                return '<script id="awa-css-gate-queue" type="application/json"' . $terminalAttr . '>'
                    . json_encode($ordered, JSON_UNESCAPED_SLASHES)
                    . '</script>';
            },
            $html,
            1
        ) ?? $html;
    }

    /**
     * Remove blocos <noscript> só com folhas já na fila gate (evita fetch duplicado no HTML).
     */
    private function stripHomeGatedNoscriptFallbacks(string $html): string
    {
        $pattern = '/<noscript>\s*((?:<link\s[^>]*\/?>\s*)+)<\/noscript>/i';

        return preg_replace_callback(
            $pattern,
            static function (array $matches): string {
                $block = $matches[1];
                if (!preg_match_all('/href=(["\'])([^"\']+)\1/i', $block, $hrefMatches)) {
                    return $matches[0];
                }

                foreach ($hrefMatches[2] as $href) {
                    $isGated = false;
                    foreach (self::HOME_GATE_CSS_FRAGMENTS as $fragment) {
                        if (str_contains($href, $fragment)) {
                            $isGated = true;
                            break;
                        }
                    }
                    if (!$isGated) {
                        return $matches[0];
                    }
                }

                return '';
            },
            $html
        ) ?? $html;
    }

    private function removePrintLinksListedInGateQueue(string $html): string
    {
        foreach ($this->extractGateQueueUrls($html) as $url) {
            $escaped = preg_quote($url, '/');
            $html = preg_replace(
                '/<link\s[^>]*rel=["\']stylesheet["\'][^>]*href=["\']' . $escaped . '["\'][^>]*\/?>\s*/i',
                '',
                $html
            ) ?? $html;
        }

        return $html;
    }

    /**
     * @return string[]
     */
    private function extractGateQueueUrls(string $html): array
    {
        if (!preg_match(
            '/<script\s+id="awa-css-gate-queue"\s+type="application\/json"'
            . '(?:\s+data-awa-terminal-href="[^"]*")?>(\[[^\]]*\])<\/script>/i',
            $html,
            $match
        )) {
            return [];
        }

        $decoded = json_decode($match[1], true);

        return is_array($decoded) ? $decoded : [];
    }

    private function dedupeStylesheetHrefs(string $html): string
    {
        $pattern = '/<link\s[^>]*rel=["\']stylesheet["\'][^>]*\/?>/i';
        $hrefCounts = [];
        $hasActiveTag = [];
        $seen = [];

        $isActiveStylesheet = static function (string $tag): bool {
            return !preg_match('/media=["\']print["\']/i', $tag)
                && !preg_match('/onload\s*=/i', $tag);
        };

        if (!preg_match_all($pattern, $html, $stylesheetMatches)) {
            return $html;
        }

        foreach ($stylesheetMatches[0] as $tag) {
            if (!preg_match('/href=(["\'])([^"\']+)\1/i', $tag, $hrefMatch)) {
                continue;
            }

            $href = $hrefMatch[2];
            $hrefCounts[$href] = ($hrefCounts[$href] ?? 0) + 1;
            if ($isActiveStylesheet($tag)) {
                $hasActiveTag[$href] = true;
            }
        }

        return preg_replace_callback(
            $pattern,
            static function (array $matches) use (&$seen, $hrefCounts, $hasActiveTag, $isActiveStylesheet): string {
                $tag = $matches[0];
                if (!preg_match('/href=(["\'])([^"\']+)\1/i', $tag, $hrefMatch)) {
                    return $tag;
                }

                $href = $hrefMatch[2];
                if (($hrefCounts[$href] ?? 0) < 2) {
                    return $tag;
                }

                $active = $isActiveStylesheet($tag);
                if (($hasActiveTag[$href] ?? false) && !$active) {
                    return '';
                }

                if (isset($seen[$href])) {
                    return '';
                }

                $seen[$href] = true;

                return $tag;
            },
            $html
        ) ?? $html;
    }

    /**
     * @param string   $html
     * @param string[] $urls
     */
    private function mergeIntoCssGateQueue(string $html, array $urls): string
    {
        return preg_replace_callback(
            '/<script\s+id="awa-css-gate-queue"\s+type="application\/json"'
            . '(?:\s+data-awa-terminal-href="([^"]*)")?>(\[[^\]]*\])<\/script>/i',
            static function (array $matches) use ($urls): string {
                $existing = json_decode($matches[2], true);
                if (!is_array($existing)) {
                    $existing = [];
                }
                $merged = array_values(array_unique(array_merge($urls, $existing)));
                $stylesM = [];
                $rest = [];
                foreach ($merged as $url) {
                    if (is_string($url) && str_contains($url, 'styles-m.css')) {
                        $stylesM[] = $url;
                    } else {
                        $rest[] = $url;
                    }
                }
                $merged = array_merge($rest, $stylesM);
                $terminalAttr = isset($matches[1]) && $matches[1] !== ''
                    ? ' data-awa-terminal-href="' . htmlspecialchars($matches[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
                    : '';

                return '<script id="awa-css-gate-queue" type="application/json"' . $terminalAttr . '>'
                    . json_encode($merged, JSON_UNESCAPED_SLASHES)
                    . '</script>';
            },
            $html,
            1
        ) ?? $html;
    }
}
