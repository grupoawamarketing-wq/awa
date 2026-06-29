<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\Plugin\Response;

use GrupoAwamotos\Theme\Model\HeaderImpeccableCascadeLockCss;
use Magento\Framework\App\Request\Http\Proxy as HttpRequestProxy;
use Magento\Framework\App\Response\HttpInterface;

/**
 * Performance — dedupe CSS async + gate bundles grandes na home (Sprint 3 / PSI).
 */
class OptimizeHeadStylesPlugin
{
    private const HOME_ACTION = 'cms_index_index';
    private const HOME_DENSITY_GRID_FILE = 'awa-home-density-grid-20260611.min.css';
    private const HOME_DENSITY_GRID_QUERY = '?v=20260623-shell1280-r20';
    private const HEADER_CONTRACT_GRID_FILE = 'awa-header-contract-grid-20260626.min.css';
    private const HEADER_CONTRACT_GRID_QUERY = '?v=20260626-phase3d24-r2';
    // MORTO: awa-catalog-density-grid-20260611.min.css → _deprecated/
    private const CATALOG_DENSITY_GRID_FILE = '';
    private const CATALOG_DENSITY_GRID_QUERY = '';

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
        // MORTO: 'awa-menu-v2-dept-open-fix' → _deprecated/
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
        // MORTO: 'awa-ui-promax-bundle.css' → _deprecated/
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
        // MORTO: 'awa-responsive-guard' → _deprecated/
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
        // MORTO: 'awa-homepage-hierarchy.min.css' → _deprecated/
        // MORTO: 'awa-home-cosmetic-bundle.min.css' → _deprecated/
        // MORTO: 'awa-home-flex-final.min.css' → _deprecated/
        'awa-home-flex-grid-flow.min.css',
        // MORTO: 'awa-home-modernize-2026.min.css' → _deprecated/
        // MORTO: 'awa-home-b2b-density-terminal.min.css' → _deprecated/
        // MORTO: 'awa-head-preload-home-ext.min.css' → _deprecated/
        'awa-head-tail-bundle.min.css',
        // MORTO: 'awa-home-gate-postaudit-bundle' → _deprecated/ (Fase 4 Jun/24 — skip flag permanente)
        // MORTO: 'awa-home-gate-polish-bundle' → _deprecated/ (Fase 4 Jun/24 — skip flag permanente)
        // MORTO: 'awa-home-gate-polish-cards' → _deprecated/ (Fase 4 Jun/24 — skip flag permanente)
        // MORTO: 'awa-home-gate-polish-type' → _deprecated/ (Fase 4 Jun/24 — skip flag permanente)
        'awa-defer-global-bundle',
        // MORTO: 'awa-grid-container-audit' → _deprecated/
        // MORTO: 'awa-layout-grid-system' → _deprecated/
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
        private readonly HttpRequestProxy $request,
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
            } elseif (in_array($fullAction, self::CATALOG_STACK_ACTIONS, true)) {
                // PLP/PDP/busca: injeta cascade lock v18 do header (BUG-01 fix).
                // injectBeforeBodyClose faz strip de legado + injeta v18 + guard script.
                $html = HeaderImpeccableCascadeLockCss::injectBeforeBodyClose($html);
            } elseif (in_array($fullAction, self::CHECKOUT_FOCUS_ACTIONS, true)) {
                // Checkout/carrinho: mantém sem guard para preservar o shell foco.
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
            $html = $this->injectHeaderSimplifyUiTerminalLock($html);
            $html = $this->injectMainContentSkipTargetTabindex($html);
        } else {
            // Auth / painel B2B: critical inline define layout — omitir locks globais + folhas pesadas.
            $html = preg_replace('/<style id="awa-align-grid-inline-lock[^"]*"[^>]*>.*?<\/style>/s', '', $html) ?? $html;
            $html = preg_replace('/<style id="awa-align-grid-header-container-terminal"[^>]*>.*?<\/style>/s', '', $html) ?? $html;
            $html = preg_replace('/<link\s[^>]*awa-align-grid-terminal[^>]*\/?>\s*/i', '', $html) ?? $html;
            $html = preg_replace('/<link\s[^>]*awa-header-contract-grid-20260626[^>]*\/?>\s*/i', '', $html) ?? $html;
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

        $html = $this->normalizeHeaderGeometryAuthorityInlineStyles(
            $html,
            $fullAction,
            $isAuthFocusPage,
            $isB2bAccountFocusPage
        );

        $html = $this->stripHeavyHeadPreloadInlineStyleOutsideHome(
            $html,
            $fullAction,
            $isAuthFocusPage,
            $isB2bAccountFocusPage
        );

        $html = $this->stripRedundantAsyncNoscript($html);
        $html = $this->stripStylePreloadDuplicates($html);
        $html = $this->injectGlobalFocusVisibleFallback($html);
        $html = $this->injectGlobalWebVitalsRum($html);

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
            . '</style>';

        $injected = preg_replace('/<\/body>/i', $css . "\n</body>", $html, 1);

        return is_string($injected) ? $injected : $html;
    }

    /**
     * Header UI simplify — style terminal após align-grid/header-container (10× #html-body vence home critical).
     */
    private function injectHeaderSimplifyUiTerminalLock(string $html): string
    {
        $styleId = HeaderImpeccableCascadeLockCss::HEADER_SIMPLIFY_UI_STYLE_ID;
        $html = preg_replace('/<style id="' . preg_quote($styleId, '/') . '"[^>]*>.*?<\/style>/s', '', $html) ?? $html;

        if (!HeaderImpeccableCascadeLockCss::htmlHasSiteHeader($html)) {
            return $html;
        }

        $tag = HeaderImpeccableCascadeLockCss::headerSimplifyUiTerminalStyleTag();
        $injected = preg_replace('/<\/body>/i', $tag . "\n</body>", $html, 1);

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

        $css = '<style id="awa-align-grid-inline-lock-20260626-phase3d22b">'
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
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body:not(.checkout-index-index):not(.onepagecheckout-index-index):not(.rokanthemes-onepagecheckout)'
            . ' .page-wrapper :is(.page-main.container,#maincontent.page-main.container,#maincontent#maincontent.page-main.container){'
            . 'box-sizing:border-box!important;margin-inline:auto!important;'
            . 'max-width:100%!important;padding-inline:16px!important;width:min(100%,1280px)!important}'
            . 'html body#html-body#html-body .page-wrapper :is(.page_footer,.page-footer) #footer.footer-container,'
            . 'html body#html-body#html-body .page-wrapper :is(.page_footer,.page-footer) #footer .footer-container{'
            . 'box-sizing:border-box!important;margin-inline:auto!important;max-width:min(100%,1280px)!important;'
            . 'padding-inline:16px!important;width:100%!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper '
            . 'footer.page-footer .page_footer>#footer.footer-container{'
            . 'box-sizing:border-box!important;margin-left:auto!important;margin-right:auto!important;'
            . 'margin-inline:auto!important;max-width:min(100%,1280px)!important;'
            . 'padding-left:16px!important;padding-right:16px!important;width:100%!important}'
            . 'html body#html-body#html-body .page-wrapper :is(.page_footer,.page-footer) #footer.footer-container>.container{'
            . 'box-sizing:border-box!important;margin-inline:auto!important;max-width:100%!important;'
            . 'padding-inline:0!important;width:100%!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper :is(.page_footer,.page-footer) .footer-bottom{'
            . 'box-sizing:border-box!important;margin:0!important;margin-left:0!important;margin-right:0!important;'
            . 'max-width:100%!important;padding:0!important;padding-left:0!important;padding-right:0!important;'
            . 'width:100%!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper :is(.page_footer,.page-footer) .footer-bottom>.container{'
            . 'box-sizing:border-box!important;margin-inline:auto!important;max-width:100%!important;'
            . 'padding:0!important;width:min(100%,1280px)!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper :is(.page_footer,.page-footer) .footer-bottom .footer-bottom-inner{'
            . 'box-sizing:border-box!important;display:grid!important;gap:10px!important;'
            . 'grid-template-columns:minmax(0,1fr)!important;justify-items:stretch!important;'
            . 'margin:0!important;max-width:none!important;padding:12px 16px!important;width:100%!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper :is(.page_footer,.page-footer) '
            . '.footer-bottom .footer-bottom-inner>.row.awa-footer-bottom__row{'
            . 'box-sizing:border-box!important;display:grid!important;'
            . 'grid-template-columns:minmax(88px,118px) minmax(220px,1fr) minmax(180px,240px)!important;'
            . 'align-items:center!important;gap:10px 18px!important;justify-self:center!important;'
            . 'margin:0 auto!important;max-width:760px!important;min-width:0!important;width:100%!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper :is(.page_footer,.page-footer) '
            . '.footer-bottom .footer-bottom-inner>.row.awa-footer-bottom__row>[class*="col-"]{'
            . 'box-sizing:border-box!important;float:none!important;margin:0!important;max-width:none!important;'
            . 'min-width:0!important;padding-inline:0!important;width:auto!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper :is(.page_footer,.page-footer) '
            . '.footer-bottom .awa-footer-bottom__copyright{'
            . 'box-sizing:border-box!important;justify-self:center!important;margin:0!important;'
            . 'max-width:1040px!important;width:100%!important}'
            . '@media(max-width:991px){'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper :is(.page_footer,.page-footer) '
            . '.footer-bottom .footer-bottom-inner>.row.awa-footer-bottom__row{'
            . 'grid-template-columns:minmax(0,1fr)!important;justify-items:center!important;'
            . 'max-width:100%!important;text-align:center!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper :is(.page_footer,.page-footer) '
            . '.footer-bottom :is(.awa-footer-pay-logos,.awa-footer-sec-seals){justify-content:center!important}'
            . '}'
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
            . 'height:36px!important;min-height:36px!important;max-height:36px!important;overflow:visible!important;overflow-x:visible!important;overflow-y:visible!important}'
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
            $css .= '@media(max-width:991px){html body#html-body#html-body#html-body#html-body .page-wrapper '
                . '.awa-site-header .awa-b2b-promo-close{'
                . 'box-sizing:border-box!important;width:44px!important;height:44px!important;min-width:44px!important;'
                . 'min-height:44px!important;max-width:44px!important;max-height:44px!important;display:flex!important;'
                . 'align-items:center!important;justify-content:center!important;padding:0!important;line-height:1!important}}';

            $css .= 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
                . '.awa-b2b-promo-bar :is(strong.awa-b2b-promo-bar__cta-long,.awa-b2b-promo-bar__cta-long){'
                . 'color:var(--awa-on-primary,var(--awa-text-inverse,CanvasText))!important;text-shadow:none!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
                . ':is(form#search_mini_form,#search_mini_form,.awa-site-header form#search_mini_form) {'
                . 'overflow:visible!important;overflow-x:visible!important;overflow-y:visible!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
                . '#search_mini_form :is(.field.search,.control,.search-autocomplete,.mst-searchautocomplete__autocomplete){'
                . 'overflow:visible!important;overflow-x:visible!important;overflow-y:visible!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
                . ':is(.header-control.header-nav,.header-control.header-nav.awa-nav-bar){'
                . 'box-sizing:border-box!important;padding-block:4px!important;padding-top:4px!important;padding-bottom:4px!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
                . ':is(.header-control.header-nav,.header-control.header-nav.awa-nav-bar)>.container{'
                . 'box-sizing:border-box!important;padding-block:4px!important;padding-top:4px!important;padding-bottom:4px!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
                . ':is(.content-top-home,.content-top-home .ayo-home5-wrapper,.content-top-home .ayo-home5-wrapper--template-driven){'
                . 'overflow:visible!important;overflow-x:visible!important;overflow-y:visible!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
                . ':is(.awa-product-promo-banners__item,a.awa-product-promo-banners__item){'
                . 'overflow:visible!important;overflow-x:visible!important;overflow-y:visible!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
                . '.awa-product-promo-banners__item :is(picture,img){border-radius:inherit!important}'
                . 'html body#html-body#html-body:is(.cms-index-index,.cms-home,.cms-homepage_ayo_home5) .page-wrapper '
                . ':is(.awa-carousel-card-slot,.item-product,.content-item-product.awa-product-card) '
                . ':is(h3.product-name,.product-name,a.product-item-link){line-height:1.35!important}';
        $css .= 'html body#html-body#html-body .page-wrapper :is([id^="awa-vertical-menu-"],.navigation.verticalmenu,.navigation.verticalmenu.side-verticalmenu,.side-verticalmenu>ul.togge-menu){'
            . 'box-shadow:0 2px 8px color-mix(in srgb,CanvasText 8%,transparent)!important}'
            . 'html body#html-body#html-body .page-wrapper :is(.page_footer,.page-footer) p.awa-footer-atendimento__store-address{'
            . 'line-height:1.45!important}'
            . 'html body#html-body#html-body .page-wrapper :is(.page_footer,.page-footer) p.awa-footer-copyright__disclaimer{'
            . 'line-height:1.45!important;margin-inline:auto!important;max-width:min(110ch,100%)!important;text-wrap:pretty!important}'
            . 'html body#html-body#html-body#html-body#html-body .page-wrapper :is(.page_footer,.page-footer) '
            . '.footer-bottom .awa-footer-bottom__copyright p.awa-footer-copyright__disclaimer{'
            . 'line-height:1.45!important;margin-inline:auto!important;max-width:min(110ch,100%)!important;width:auto!important;text-wrap:pretty!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body.cms-index-index.page-layout-1column .page-wrapper '
            . '.awa-site-header #awa-b2b-promo-bar .awa-b2b-promo-bar__cta strong.awa-b2b-promo-bar__cta-long{'
            . 'color:Canvas!important;text-shadow:none!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body.cms-index-index.page-layout-1column .page-wrapper '
            . '.awa-site-header #search_mini_form{overflow:visible!important;overflow-x:visible!important;overflow-y:visible!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body.cms-index-index.page-layout-1column .page-wrapper '
            . '.awa-site-header .header-control.header-nav.awa-nav-bar{'
            . 'padding-block:4px!important;padding-top:4px!important;padding-bottom:4px!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body.cms-index-index.page-layout-1column .page-wrapper '
            . '.awa-site-header .header-control.header-nav.awa-nav-bar>.container{'
            . 'padding-block:4px!important;padding-top:4px!important;padding-bottom:4px!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body.cms-index-index.page-layout-1column '
            . '.page-wrapper .awa-site-header #header.header-container{'
            . 'box-sizing:border-box!important;height:44px!important;min-height:44px!important;max-height:44px!important;padding-bottom:0!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body.cms-index-index.page-layout-1column '
            . '.page-wrapper .awa-site-header #awa-b2b-promo-bar.awa-b2b-promo-bar{'
            . 'background:var(--awa-primary)!important;background-color:var(--awa-primary)!important;box-sizing:border-box!important;color:var(--awa-on-primary,Canvas)!important;height:44px!important;min-height:44px!important;max-height:44px!important;padding-bottom:0!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body.cms-index-index.page-layout-1column '
            . '.page-wrapper .awa-site-header #awa-b2b-promo-bar :is(.awa-b2b-promo-bar__inner,.awa-b2b-promo-bar__layout){'
            . 'height:44px!important;min-height:44px!important;max-height:44px!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body.cms-index-index.page-layout-1column '
            . '.page-wrapper .awa-site-header #awa-b2b-promo-bar :is(.awa-b2b-promo-close,#awa-b2b-promo-close){'
            . 'width:44px!important;min-width:44px!important;max-width:44px!important;height:44px!important;'
            . 'min-height:44px!important;max-height:44px!important;display:inline-flex!important;align-items:center!important;'
            . 'justify-content:center!important;color:inherit!important;background:transparent!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body.cms-index-index.page-layout-1column '
            . '.page-wrapper .awa-site-header #awa-b2b-promo-bar .awa-b2b-promo-bar__cta{'
            . 'color:inherit!important;min-height:44px!important;height:44px!important;max-height:44px!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body.cms-index-index.page-layout-1column '
            . '.page-wrapper .awa-site-header #awa-b2b-promo-bar :is(.awa-b2b-promo-bar__text,.awa-b2b-promo-bar__lead,'
            . '.awa-b2b-promo-bar__lead-long,.awa-b2b-promo-bar__tail,.awa-b2b-promo-bar__separator){'
            . 'color:inherit!important}'
            . '@media(min-width:768px) and (max-width:991px){'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body.cms-index-index.page-layout-1column '
            . '.page-wrapper .awa-site-header .header-wrapper-sticky{display:flex!important;flex-direction:column!important;'
            . 'height:auto!important;min-height:calc(56px + 48px)!important;max-height:none!important;overflow:visible!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body.cms-index-index.page-layout-1column '
            . '.page-wrapper .awa-site-header :is(.header-control.header-nav,.header-control.awa-nav-bar){'
            . 'display:flex!important;visibility:visible!important;height:48px!important;min-height:48px!important;'
            . 'max-height:48px!important;overflow:visible!important;pointer-events:auto!important}}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body.cms-index-index.page-layout-1column '
            . '.page-wrapper .awa-site-header .awa-header-account-prompt :is(.awa-header-account-prompt__link--login,'
            . '.awa-header-account-prompt__link--register,.awa-header-account-prompt__link){'
            . 'display:inline-flex!important;align-items:center!important;min-height:44px!important;height:44px!important;'
            . 'box-sizing:border-box!important;line-height:1.2!important;padding:0 4px!important}'
            . '@media(min-width:768px){html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body.cms-index-index.page-layout-1column '
            . '.page-wrapper .awa-site-header #awa-b2b-promo-bar .awa-b2b-promo-bar__cta strong.awa-b2b-promo-bar__cta-long{'
            . 'color:var(--awa-on-primary,Canvas)!important;text-shadow:none!important}}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body.cms-index-index.page-layout-1column '
            . '.page-wrapper .awa-site-header div.awa-main-header__inner.wp-header{'
            . 'box-sizing:border-box!important;height:64px!important;min-height:64px!important;max-height:64px!important;'
            . 'padding-top:0!important;padding-bottom:0!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body.cms-index-index.page-layout-1column '
            . '.page-wrapper .awa-site-header #awa-search-label>span{'
            . 'border:0!important;clip:rect(0,0,0,0)!important;clip-path:inset(50%)!important;height:1px!important;margin:-1px!important;overflow:hidden!important;'
            . 'font-size:0!important;line-height:0!important;padding:0!important;position:absolute!important;width:1px!important;white-space:nowrap!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body.cms-index-index.page-layout-1column .page-wrapper '
            . '.awa-site-header .header-control.header-nav.awa-nav-bar>.container{'
            . 'box-sizing:border-box!important;padding-left:16px!important;padding-right:16px!important}'
            . '@media(max-width:767px){html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body.cms-index-index.page-layout-1column '
            . '.page-wrapper .awa-site-header .header-wrapper-sticky{'
            . 'box-sizing:border-box!important;height:96px!important;min-height:96px!important;max-height:96px!important;'
            . 'padding:0 16px!important;padding-block:0!important;overflow:visible!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body.cms-index-index.page-layout-1column '
            . '.page-wrapper .awa-site-header :is(.header.awa-main-header,.header_main.awa-main-header-inner-wrap,.header-main,.header-main>.container){'
            . 'box-sizing:border-box!important;height:96px!important;min-height:96px!important;max-height:96px!important;'
            . 'padding:0!important;margin:0!important;overflow:visible!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body.cms-index-index.page-layout-1column '
            . '.page-wrapper .awa-site-header div.awa-main-header__inner.wp-header{'
            . 'box-sizing:border-box!important;display:grid!important;gap:4px 8px!important;grid-template-areas:"toggle brand cart" "search search search"!important;'
            . 'grid-template-columns:44px minmax(0,1fr) 44px!important;grid-template-rows:44px 44px!important;'
            . 'height:96px!important;min-height:96px!important;max-height:96px!important;overflow:visible!important;'
            . 'padding:4px 16px 0!important;padding-block:4px 0!important;padding-inline:16px!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body.cms-index-index.page-layout-1column '
            . '.page-wrapper .awa-site-header :is(.header-control.header-nav,.header-control.header-nav.awa-nav-bar,'
            . '.header-control.header-nav.awa-nav-bar>.container,.awa-nav-bar__inner){'
            . 'box-sizing:border-box!important;height:0!important;min-height:0!important;max-height:0!important;'
            . 'padding:0!important;margin:0!important;overflow:hidden!important;visibility:hidden!important}}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper '
            . ':is([id^="awa-vertical-menu-"],.navigation.verticalmenu,.navigation.verticalmenu.side-verticalmenu,.side-verticalmenu>ul.togge-menu){'
            . 'border:0!important;box-shadow:0 2px 8px color-mix(in srgb,CanvasText 8%,transparent)!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper '
            . '.awa-site-header .awa-header-minicart:has(.minicart-wrapper .showcart)>.awa-header-cart-fallback{'
            . 'display:none!important;visibility:hidden!important;width:0!important;height:0!important;'
            . 'min-width:0!important;min-height:0!important;overflow:hidden!important;pointer-events:none!important;'
            . 'position:absolute!important;clip:rect(0,0,0,0)!important;clip-path:inset(50%)!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper '
            . '.awa-site-header #search_mini_form #awa-search-clear.awa-search-clear-btn[hidden]{display:none!important;visibility:hidden!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body .page-wrapper '
            . '.awa-site-header #search_mini_form #awa-search-clear.awa-search-clear-btn:not([hidden]){display:inline-flex!important;visibility:visible!important}'
            . '@layer awa-fixes{/* Required: legacy awa-fixes !important header rules beat unlayered terminal CSS. */'
            . '@media(max-width:767px){html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body '
            . '.page-wrapper header.awa-site-header .header-wrapper-sticky>div.header.awa-main-header[data-awa-header-main="true"]{'
            . 'box-sizing:border-box!important;display:block!important;height:96px!important;min-height:96px!important;'
            . 'max-height:96px!important;block-size:96px!important;min-block-size:96px!important;max-block-size:96px!important;'
            . 'padding:0!important;margin:0!important;overflow:visible!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body:not(.cms-index-index) '
            . '.page-wrapper header.awa-site-header .header-wrapper-sticky{'
            . 'box-sizing:border-box!important;height:96px!important;min-height:96px!important;max-height:96px!important;'
            . 'padding:0!important;margin:0!important;overflow:visible!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body '
            . '.page-wrapper header.awa-site-header .header-wrapper-sticky div.awa-main-header__inner.wp-header{'
            . 'box-sizing:border-box!important;display:grid!important;grid-template:"toggle brand cart" 44px "search search search" 44px/44px minmax(0,1fr) 44px!important;'
            . 'gap:4px 8px!important;height:96px!important;min-height:96px!important;max-height:96px!important;'
            . 'padding:4px 16px 0!important;padding-block:4px 0!important;padding-inline:16px!important;'
            . 'align-content:start!important;align-items:center!important;overflow:visible!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body '
            . '.page-wrapper header.awa-site-header .header-wrapper-sticky :is(.awa-header-search-col,.block-search,.block-search .block-content){'
            . 'box-sizing:border-box!important;height:44px!important;min-height:44px!important;max-height:44px!important;'
            . 'padding:0!important;margin:0!important;overflow:visible!important}'
            . 'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body '
            . '.page-wrapper header.awa-site-header .header-wrapper-sticky>.header-control.header-nav.awa-nav-bar[data-awa-header-nav="true"]{'
            . 'display:none!important;visibility:hidden!important;height:0!important;min-height:0!important;max-height:0!important;'
            . 'padding:0!important;margin:0!important;border:0!important;overflow:hidden!important}}}';

        $css .= $this->buildUnifiedHeaderGeometryAuthorityCss();

        $css .= '</style>';
        $script = '<script id="awa-header-hidden-focus-sync">(function(){'
            . 'var owned="data-awa-hidden-focus-sync",storedTab="data-awa-prev-tabindex",storedAria="data-awa-prev-aria-hidden";'
            . 'function isHidden(el){if(!el||!el.isConnected){return true;}'
            . 'if(el.hidden||el.closest("[hidden]")){return true;}'
            . 'var cs=getComputedStyle(el),r=el.getBoundingClientRect();'
            . 'return cs.display==="none"||cs.visibility==="hidden"||r.width===0||r.height===0;}'
            . 'function remember(el){if(!el.hasAttribute(storedTab)){el.setAttribute(storedTab,el.hasAttribute("tabindex")?el.getAttribute("tabindex"):"");}'
            . 'if(!el.hasAttribute(storedAria)){el.setAttribute(storedAria,el.hasAttribute("aria-hidden")?el.getAttribute("aria-hidden"):"");}}'
            . 'function restore(el){if(el.getAttribute(owned)!=="1"){return;}'
            . 'var tab=el.getAttribute(storedTab),aria=el.getAttribute(storedAria);'
            . 'if(tab===""){el.removeAttribute("tabindex");}else if(tab!==null){el.setAttribute("tabindex",tab);}'
            . 'if(aria===""){el.removeAttribute("aria-hidden");}else if(aria!==null){el.setAttribute("aria-hidden",aria);}'
            . 'if("inert" in el){el.inert=false;}el.removeAttribute(owned);el.removeAttribute(storedTab);el.removeAttribute(storedAria);}'
            . 'function hide(el){if(el.getAttribute(owned)==="1"&&el.getAttribute("tabindex")==="-1"&&el.getAttribute("aria-hidden")==="true"){return;}remember(el);el.setAttribute("tabindex","-1");el.setAttribute("aria-hidden","true");'
            . 'if("inert" in el){el.inert=true;}el.setAttribute(owned,"1");}'
            . 'function sync(){var root=document.querySelector(".awa-site-header");if(!root){return;}'
            . 'var nodes=root.querySelectorAll(".action.nav-toggle,.awa-header-mobile-toggle,.awa-header-cart-link,.awa-header-cart-fallback,.awa-minicart-continue,.header-control.header-nav,.header-control.header-nav a,.header-control.header-nav button,.awa-nav-quick-links__link,.title-category-dropdown,.awa-header-account-prompt__icon,.awa-header-account-prompt__link,.awa-top-link-anchor,.header.links a,.top-header a[href]");'
            . 'nodes.forEach(function(el){if(isHidden(el)){hide(el);}else{restore(el);}});}'
            . 'function soon(){requestAnimationFrame(sync);setTimeout(sync,250);setTimeout(sync,1200);}'
            . 'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",soon,{once:true});}else{soon();}'
            . 'window.addEventListener("load",soon,{once:true,passive:true});window.addEventListener("resize",soon,{passive:true});'
            . 'if(window.MutationObserver){new MutationObserver(function(){soon();}).observe(document.documentElement,{childList:true,subtree:true,attributes:true,attributeFilter:["class","style","hidden","aria-hidden"]});}'
            . '})();</script>';

        $injected = preg_replace('/<\/body>/i', $css . $script . "\n</body>", $html, 1);

        return is_string($injected) ? $injected : $html;
    }

    /**
     * Geometria única do header (3D.2.2B): fonte de verdade para home/PLP/PDP/cart.
     */
    private function buildUnifiedHeaderGeometryAuthorityCss(): string
    {
        $css = <<<'CSS'
@media(min-width:1200px){html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"]{--awa-header-main-row-h:68px!important;--awa-header-nav-h:48px!important;--awa-header-shell-pad:24px!important;--awa-header-col-gap:24px!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .header.awa-main-header{height:var(--awa-header-main-row-h)!important;min-height:var(--awa-header-main-row-h)!important;max-height:var(--awa-header-main-row-h)!important;padding:0 var(--awa-header-shell-pad)!important;box-sizing:border-box!important;overflow:visible!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky :is(.header_main.awa-main-header-inner-wrap,.header-main,.header-main>.container,.awa-main-header__inner.wp-header,.awa-main-header__inner[data-awa-header-row="brand-search"]){height:var(--awa-header-main-row-h)!important;min-height:var(--awa-header-main-row-h)!important;max-height:var(--awa-header-main-row-h)!important;box-sizing:border-box!important;overflow:visible!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky :is(.awa-main-header__inner.wp-header,.awa-main-header__inner[data-awa-header-row="brand-search"]){display:grid!important;grid-template-columns:minmax(140px,172px) minmax(360px,1fr) minmax(240px,300px)!important;grid-template-areas:"brand search actions"!important;align-items:center!important;column-gap:var(--awa-header-col-gap)!important;width:min(100%,1280px)!important;max-width:1280px!important;margin-inline:auto!important;padding-inline:var(--awa-header-shell-pad)!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .awa-header-primary-row{display:contents!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .awa-header-brand-cell{grid-area:brand!important;display:flex!important;align-items:center!important;justify-content:flex-start!important;height:var(--awa-header-main-row-h)!important;min-height:0!important;max-height:var(--awa-header-main-row-h)!important;overflow:hidden!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .awa-header-search-col{grid-area:search!important;display:flex!important;align-items:center!important;width:100%!important;min-width:0!important;max-width:none!important;height:var(--awa-header-main-row-h)!important;min-height:0!important;max-height:var(--awa-header-main-row-h)!important;overflow:visible!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .awa-header-right-col{grid-area:actions!important;display:flex!important;align-items:center!important;justify-content:flex-end!important;gap:8px!important;width:100%!important;min-width:0!important;max-width:300px!important;height:var(--awa-header-main-row-h)!important;min-height:0!important;max-height:var(--awa-header-main-row-h)!important;overflow:visible!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] :is(.header-control.header-nav,.header-control.awa-nav-bar,.awa-nav-bar){height:var(--awa-header-nav-h)!important;min-height:var(--awa-header-nav-h)!important;max-height:var(--awa-header-nav-h)!important;overflow:visible!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .awa-nav-bar__inner{height:var(--awa-header-nav-h)!important;min-height:var(--awa-header-nav-h)!important;max-height:var(--awa-header-nav-h)!important;box-sizing:border-box!important;width:min(100%,1280px)!important;max-width:1280px!important;margin-inline:auto!important;padding-inline:var(--awa-header-shell-pad)!important}}
@media(min-width:992px) and (max-width:1199px){html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"]{--awa-header-main-row-h:64px!important;--awa-header-nav-h:46px!important;--awa-header-shell-pad:20px!important;--awa-header-col-gap:16px!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky :is(.awa-main-header__inner.wp-header,.awa-main-header__inner[data-awa-header-row="brand-search"]){grid-template-columns:minmax(132px,160px) minmax(320px,1fr) minmax(220px,280px)!important;grid-template-areas:"brand search actions"!important;column-gap:var(--awa-header-col-gap)!important;padding-inline:var(--awa-header-shell-pad)!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .header.awa-main-header{height:var(--awa-header-main-row-h)!important;min-height:var(--awa-header-main-row-h)!important;max-height:var(--awa-header-main-row-h)!important;padding:0 var(--awa-header-shell-pad)!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky :is(.header_main.awa-main-header-inner-wrap,.header-main,.header-main>.container,.awa-main-header__inner.wp-header,.awa-main-header__inner[data-awa-header-row="brand-search"]){height:var(--awa-header-main-row-h)!important;min-height:var(--awa-header-main-row-h)!important;max-height:var(--awa-header-main-row-h)!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] :is(.header-control.header-nav,.header-control.awa-nav-bar,.awa-nav-bar){height:var(--awa-header-nav-h)!important;min-height:var(--awa-header-nav-h)!important;max-height:var(--awa-header-nav-h)!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .awa-nav-bar__inner{height:var(--awa-header-nav-h)!important;min-height:var(--awa-header-nav-h)!important;max-height:var(--awa-header-nav-h)!important;padding-inline:var(--awa-header-shell-pad)!important}}
@media(min-width:768px) and (max-width:991px){html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"]{--awa-header-main-row-h:56px!important;--awa-header-nav-h:44px!important;--awa-header-shell-pad:16px!important;--awa-header-col-gap:16px!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky :is(.awa-main-header__inner.wp-header,.awa-main-header__inner[data-awa-header-row="brand-search"]){display:grid!important;grid-template-columns:minmax(112px,148px) minmax(260px,1fr) minmax(180px,240px)!important;grid-template-areas:"brand search actions"!important;column-gap:var(--awa-header-col-gap)!important;padding-inline:var(--awa-header-shell-pad)!important;height:var(--awa-header-main-row-h)!important;min-height:var(--awa-header-main-row-h)!important;max-height:var(--awa-header-main-row-h)!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .header.awa-main-header{height:var(--awa-header-main-row-h)!important;min-height:var(--awa-header-main-row-h)!important;max-height:var(--awa-header-main-row-h)!important;padding:0 var(--awa-header-shell-pad)!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .awa-header-primary-row{display:contents!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .awa-header-brand-cell{grid-area:brand!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .awa-header-search-col{grid-area:search!important;max-width:none!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .awa-header-right-col{grid-area:actions!important;max-width:240px!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] :is(.header-control.header-nav,.header-control.awa-nav-bar,.awa-nav-bar){height:var(--awa-header-nav-h)!important;min-height:var(--awa-header-nav-h)!important;max-height:var(--awa-header-nav-h)!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .awa-nav-bar__inner{height:var(--awa-header-nav-h)!important;min-height:var(--awa-header-nav-h)!important;max-height:var(--awa-header-nav-h)!important;padding-inline:var(--awa-header-shell-pad)!important}}
@media(max-width:767px){html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky{height:auto!important;min-height:0!important;max-height:none!important;padding:0!important;overflow:visible!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky :is(.header.awa-main-header,.header_main.awa-main-header-inner-wrap,.header-main,.header-main>.container){height:auto!important;min-height:0!important;max-height:none!important;padding:0!important;margin:0!important;overflow:visible!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky :is(.awa-main-header__inner.wp-header,.awa-main-header__inner[data-awa-header-row="brand-search"]){display:grid!important;grid-template-areas:"toggle brand cart" "search search search"!important;grid-template-columns:44px minmax(0,1fr) 44px!important;grid-template-rows:auto auto!important;row-gap:12px!important;column-gap:12px!important;height:auto!important;min-height:0!important;max-height:none!important;padding:12px 16px!important;overflow:visible!important;box-sizing:border-box!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .awa-header-primary-row{display:contents!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .awa-header-mobile-toggle{grid-area:toggle!important;justify-self:start!important;align-self:center!important;width:44px!important;height:44px!important;min-width:44px!important;min-height:44px!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .awa-header-brand-cell{grid-area:brand!important;justify-self:center!important;align-self:center!important;min-width:0!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .awa-header-cart-link{grid-area:cart!important;justify-self:end!important;align-self:center!important;width:44px!important;height:44px!important;min-width:44px!important;min-height:44px!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .awa-header-search-col{grid-area:search!important;width:100%!important;min-width:0!important;max-width:none!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .awa-header-search-col form#search_mini_form{display:grid!important;grid-template-columns:minmax(0,1fr) 44px!important;grid-template-areas:"field submit"!important;width:100%!important;min-width:0!important;max-width:100%!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .awa-header-search-col form#search_mini_form .field.search{grid-area:field!important;min-width:0!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .awa-header-search-col input#search{height:44px!important;min-height:44px!important;max-height:44px!important;line-height:44px!important;font-size:16px!important;padding-block:0!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .awa-header-search-col form#search_mini_form .actions{grid-area:submit!important;width:44px!important;min-width:44px!important;max-width:44px!important;height:44px!important;min-height:44px!important;max-height:44px!important;padding:0!important;margin:0!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .awa-header-search-col form#search_mini_form :is(.action.search,button.action.search){width:44px!important;min-width:44px!important;max-width:44px!important;height:44px!important;min-height:44px!important;max-height:44px!important;padding:0!important}html body#html-body#html-body:not(.b2b-auth-shell):not(.checkout-index-index):not(.onepagecheckout-index-index) .page-wrapper .awa-site-header[data-awa-header-mode="default"] .header-wrapper-sticky .awa-header-right-col{grid-area:search!important;width:100%!important;min-width:0!important;max-width:none!important}}
CSS;

        return str_replace(
            'html body#html-body#html-body',
            'html body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body#html-body',
            $css
        );
    }

    private function normalizeHeaderGeometryAuthorityInlineStyles(
        string $html,
        string $fullAction,
        bool $isAuthFocusPage,
        bool $isB2bAccountFocusPage
    ): string {
        if ($isAuthFocusPage || $isB2bAccountFocusPage) {
            return $html;
        }

        // Legacy lock de paridade do catálogo introduz uma segunda fonte de verdade para header.
        $html = preg_replace('/<style id="awa-header-catalog-parity-v1"[^>]*>.*?<\/style>\s*/is', '', $html) ?? $html;

        if ($fullAction === self::HOME_ACTION) {
            foreach ([
                'awa-home-cls-critical-opt21',
                'awa-home-critical-cls-shell',
                'awa-home-critical-cls-final-lock',
                'awa-header-vtex-clean-critical-20260622',
            ] as $styleId) {
                $html = preg_replace(
                    '/<style id="' . preg_quote($styleId, '/') . '"[^>]*>.*?<\/style>\s*/is',
                    '',
                    $html
                ) ?? $html;
            }

            $html = str_replace(
                [
                    'minmax(420px,1fr)',
                    'min-height:84px!important',
                    'height:84px!important',
                    'max-height:84px!important',
                ],
                [
                    'minmax(360px,1fr)',
                    'min-height:68px!important',
                    'height:68px!important',
                    'max-height:68px!important',
                ],
                $html
            );
        }

        $html = str_replace(
            [
                'grid-template-columns:clamp(128px,13vw,184px) minmax(0,1fr) minmax(260px,max-content)!important;',
                'grid-template-columns:clamp(112px,14vw,148px) minmax(0,1fr) minmax(220px,max-content)!important;',
                'minmax(260px,max-content)',
                'minmax(220px,max-content)',
            ],
            [
                'grid-template-columns:minmax(140px,172px) minmax(360px,1fr) minmax(240px,300px)!important;',
                'grid-template-columns:minmax(112px,148px) minmax(260px,1fr) minmax(180px,240px)!important;',
                'minmax(240px,300px)',
                'minmax(180px,240px)',
            ],
            $html
        );

        return $html;
    }

    /**
     * PLP/PDP/carrinho: remove bloco legacy de head-preload (sem id, ~70KB) para reduzir parsing/heap no browser.
     * Home/auth/painel B2B preservam o comportamento atual.
     */
    private function stripHeavyHeadPreloadInlineStyleOutsideHome(
        string $html,
        string $fullAction,
        bool $isAuthFocusPage,
        bool $isB2bAccountFocusPage
    ): string {
        if ($fullAction === self::HOME_ACTION || $isAuthFocusPage || $isB2bAccountFocusPage) {
            return $html;
        }

        if (!str_contains($html, '--head-preload-c1:')) {
            return $html;
        }

        $stripped = preg_replace_callback(
            '/<style\b([^>]*)>(.*?)<\/style>\s*/is',
            static function (array $matches): string {
                $attrs = $matches[1] ?? '';
                $body = $matches[2] ?? '';

                if (preg_match('/\bid\s*=/i', $attrs)) {
                    return $matches[0];
                }

                if (!str_contains($body, '--head-preload-c1:')
                    || !str_contains($body, '--head-preload-footer-surface:')
                ) {
                    return $matches[0];
                }

                return '';
            },
            $html
        );

        return is_string($stripped) ? $stripped : $html;
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
        $hasAlignGridBodyTerminal = str_contains($html, 'data-awa-align-grid-body-terminal="1"');
        $hasHeaderContractBodyTerminal = str_contains($html, 'data-awa-header-contract-grid-body-terminal="1"');
        if ($hasAlignGridBodyTerminal && $hasHeaderContractBodyTerminal) {
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
        $contractHref = '/static/' . $versionMatch[1]
            . '/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/'
            . self::HEADER_CONTRACT_GRID_FILE
            . self::HEADER_CONTRACT_GRID_QUERY;
        $isHome = $fullAction === self::HOME_ACTION;
        $isCatalogDensity = in_array($fullAction, self::CATALOG_STACK_ACTIONS, true)
            || in_array($fullAction, self::CATALOG_HEADER_ACTIONS, true);
        $hasHomeDensityHref = self::HOME_DENSITY_GRID_FILE !== '';
        $hasCatalogDensityHref = self::CATALOG_DENSITY_GRID_FILE !== '';
        $homeDensityHref = $hasHomeDensityHref
            ? '/static/' . $versionMatch[1]
                . '/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/'
                . self::HOME_DENSITY_GRID_FILE
                . self::HOME_DENSITY_GRID_QUERY
            : '';
        $catalogDensityHref = $hasCatalogDensityHref
            ? '/static/' . $versionMatch[1]
                . '/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/'
                . self::CATALOG_DENSITY_GRID_FILE
                . self::CATALOG_DENSITY_GRID_QUERY
            : '';

        if ($defer) {
            /* Home/PLP/busca: inline lock cobre container/grid no 1º paint — folha completa async */
            $tag = '';
            if (!$hasAlignGridBodyTerminal) {
                $tag .= '<link rel="stylesheet" href="' . $href . '" media="print" onload="this.media=\'all\'"'
                    . ' data-awa-align-grid-body-terminal="1" data-awa-bundle="align-grid-terminal" data-awa-defer="1"/>';
            }
            if ($hasHomeDensityHref
                && $isHome
                && !str_contains($html, 'data-awa-home-density-grid-body-terminal="1"')
            ) {
                $tag .= '<link rel="stylesheet" href="' . $homeDensityHref . '" media="print" onload="this.media=\'all\'"'
                    . ' data-awa-home-density-grid-body-terminal="1" data-awa-bundle="home-density-grid" data-awa-defer="1"/>';
            }
            if ($hasCatalogDensityHref
                && $isCatalogDensity
                && !str_contains($html, 'data-awa-catalog-density-grid-body-terminal="1"')
            ) {
                $tag .= '<link rel="stylesheet" href="' . $catalogDensityHref . '" media="all"'
                    . ' data-awa-catalog-density-grid-body-terminal="1" data-awa-bundle="catalog-density-grid"/>';
            }
            if (!$hasHeaderContractBodyTerminal) {
                $tag .= '<link rel="stylesheet" href="' . $contractHref . '" media="print" onload="this.media=\'all\'"'
                    . ' data-awa-header-contract-grid-body-terminal="1" data-awa-bundle="header-contract-grid" data-awa-defer="1"/>';
            }
        } else {
            $tag = '';
            if (!$hasAlignGridBodyTerminal) {
                $tag .= '<link rel="stylesheet" href="' . $href . '" media="all"'
                    . ' data-awa-align-grid-body-terminal="1" data-awa-bundle="align-grid-terminal"/>';
            }
            if ($hasHomeDensityHref
                && $isHome
                && !str_contains($html, 'data-awa-home-density-grid-body-terminal="1"')
            ) {
                $tag .= '<link rel="stylesheet" href="' . $homeDensityHref . '" media="all"'
                    . ' data-awa-home-density-grid-body-terminal="1" data-awa-bundle="home-density-grid"/>';
            }
            if ($hasCatalogDensityHref
                && $isCatalogDensity
                && !str_contains($html, 'data-awa-catalog-density-grid-body-terminal="1"')
            ) {
                $tag .= '<link rel="stylesheet" href="' . $catalogDensityHref . '" media="all"'
                    . ' data-awa-catalog-density-grid-body-terminal="1" data-awa-bundle="catalog-density-grid"/>';
            }
            if (!$hasHeaderContractBodyTerminal) {
                $tag .= '<link rel="stylesheet" href="' . $contractHref . '" media="all"'
                    . ' data-awa-header-contract-grid-body-terminal="1" data-awa-bundle="header-contract-grid"/>';
            }
        }

        if ($tag === '') {
            return $html;
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
            // MORTO: 'awa-menu-v2-dept-open-fix' → _deprecated/
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
            . '(?=[^>]*media=["\']print["\'])[^>]*href=(["\'])([^"\']+)\2[^>]*\/?>)'
            . '\s*<noscript>\s*<link\s[^>]*href=\2\3\2[^>]*\/?>\s*<\/noscript>/i';

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


    private function stripStylePreloadDuplicates(string $html): string
    {
        if (!preg_match_all('/<link\s[^>]*rel=["\']stylesheet["\'][^>]*href=(["\'])([^"\']+)\1[^>]*\/?>/i', $html, $stylesheetMatches)) {
            return $html;
        }

        $stylesheetHrefs = [];
        foreach ($stylesheetMatches[2] as $href) {
            if (!is_string($href) || $href === '') {
                continue;
            }

            $stylesheetHrefs[html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8')] = true;
        }

        if ($stylesheetHrefs === []) {
            return $html;
        }

        return preg_replace_callback(
            '/<link\s(?=[^>]*rel=["\']preload["\'])(?=[^>]*as=["\']style["\'])[^>]*href=(["\'])([^"\']+)\1[^>]*\/?>\s*/i',
            static function (array $matches) use ($stylesheetHrefs): string {
                $href = html_entity_decode($matches[2] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if ($href !== '' && isset($stylesheetHrefs[$href])) {
                    return '';
                }

                return $matches[0];
            },
            $html
        ) ?? $html;
    }

    private function injectGlobalFocusVisibleFallback(string $html): string
    {
        if (str_contains($html, 'id="awa-focus-visible-global-guard"')) {
            return $html;
        }

        $style = '<style id="awa-focus-visible-global-guard">'
            . ':where(a,button,[role="button"],input,select,textarea,[tabindex]:not([tabindex="-1"])):focus-visible{outline:2px solid var(--awa-primary,#b73337)!important;outline-offset:2px!important}'
            . '</style>';

        $injected = preg_replace('/<\/head>/i', $style . "\n</head>", $html, 1);

        return is_string($injected) ? $injected : $html;
    }

    private function injectGlobalWebVitalsRum(string $html): string
    {
        if (str_contains($html, 'id="awa-web-vitals-rum"')) {
            return $html;
        }

        $script = '<script id="awa-web-vitals-rum">'
            . '(function(w,d,p){"use strict";if(w.__awaWebVitalsRumInit){return;}w.__awaWebVitalsRumInit=1;'
            . 'var s=(w.PerformanceObserver&&w.PerformanceObserver.supportedEntryTypes)?w.PerformanceObserver.supportedEntryTypes:[],sent={},cls=0,lcp=null,inp=0,inpSrc="event",path=(w.location&&w.location.pathname)?w.location.pathname:"/",low=(path||"").toLowerCase(),kind=(low==="/"||low==="/index.php")?"home":"internal",nav=(p&&typeof p.getEntriesByType==="function")?p.getEntriesByType("navigation")[0]:null,done=false;'
            . 'function r(v,dg){var f;if(typeof v!=="number"||!isFinite(v)){return null;}f=Math.pow(10,dg||0);return Math.round(v*f)/f;}'
            . 'function rate(n,v){if(n==="LCP"){return v<=2500?"good":(v<=4000?"needs-improvement":"poor");}if(n==="CLS"){return v<=0.1?"good":(v<=0.25?"needs-improvement":"poor");}if(n==="INP"){return v<=200?"good":(v<=500?"needs-improvement":"poor");}if(n==="FCP"){return v<=1800?"good":(v<=3000?"needs-improvement":"poor");}if(n==="TTFB"){return v<=800?"good":(v<=1800?"needs-improvement":"poor");}return "unknown";}'
            . 'function push(n,v,e){var pld,val=r(v,n==="CLS"?4:0);if(val===null||sent[n]){return;}sent[n]=1;w.dataLayer=w.dataLayer||[];pld={event:"awa_web_vital",web_vital_name:n,web_vital_value:val,web_vital_rating:rate(n,val),web_vital_page_path:path,web_vital_page_kind:kind};if(e&&typeof e==="object"){if(e.id){pld.web_vital_id=e.id;}if(e.delta!==undefined&&e.delta!==null){pld.web_vital_delta=r(e.delta,n==="CLS"?4:0);}if(e.navigationType){pld.web_vital_navigation_type=e.navigationType;}if(e.source){pld.web_vital_source=e.source;}}try{w.dataLayer.push(pld);}catch(_){}}'
            . 'function fin(){if(done){return;}done=true;if(lcp&&typeof lcp.startTime==="number"){push("LCP",lcp.startTime,{id:lcp.id});}push("CLS",cls);if(inp>0){push("INP",inp,{source:inpSrc});}}'
            . 'if(nav&&typeof nav.responseStart==="number"){push("TTFB",nav.responseStart,{navigationType:nav.type||"navigate"});}'
            . 'if(s.indexOf("paint")!==-1){try{(new w.PerformanceObserver(function(list){list.getEntries().forEach(function(en){if(en&&en.name==="first-contentful-paint"){push("FCP",en.startTime,{id:en.name});}});})).observe({type:"paint",buffered:true});}catch(_){}}'
            . 'if(s.indexOf("largest-contentful-paint")!==-1){try{(new w.PerformanceObserver(function(list){var es=list.getEntries();if(es.length){lcp=es[es.length-1];}})).observe({type:"largest-contentful-paint",buffered:true});}catch(_){}}'
            . 'if(s.indexOf("layout-shift")!==-1){try{(new w.PerformanceObserver(function(list){list.getEntries().forEach(function(en){if(en&&!en.hadRecentInput){cls+=en.value;}});})).observe({type:"layout-shift",buffered:true});}catch(_){}}'
            . 'if(s.indexOf("event")!==-1){try{(new w.PerformanceObserver(function(list){list.getEntries().forEach(function(en){if(en&&en.interactionId&&en.duration>inp){inp=en.duration;}});})).observe({type:"event",buffered:true,durationThreshold:40});}catch(_){}}else if(s.indexOf("first-input")!==-1){inpSrc="first-input";try{(new w.PerformanceObserver(function(list){var fi=list.getEntries()[0];if(fi&&fi.duration>inp){inp=fi.duration;}})).observe({type:"first-input",buffered:true});}catch(_){}}'
            . 'w.addEventListener("pagehide",fin,{capture:true,once:true});d.addEventListener("visibilitychange",function(){if(d.visibilityState==="hidden"){fin();}},{capture:true,once:true});w.addEventListener("load",function(){w.setTimeout(fin,15000);},{once:true});'
            . '})(window,document,window.performance||null);'
            . '</script>';

        $injected = preg_replace('/<\/head>/i', $script . "\n</head>", $html, 1);

        return is_string($injected) ? $injected : $html;
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

        // Exclude <noscript> content from analysis — links inside noscript are fallbacks,
        // not active stylesheets. Counting them as active causes async print/onload links
        // to be incorrectly removed (the noscript link triggers hasActiveTag, removing the async one).
        $htmlForAnalysis = preg_replace('/<noscript>.*?<\/noscript>/is', '', $html) ?? $html;

        if (!preg_match_all($pattern, $htmlForAnalysis, $stylesheetMatches)) {
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

