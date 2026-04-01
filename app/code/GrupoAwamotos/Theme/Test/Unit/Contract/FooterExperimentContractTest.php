<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\Test\Unit\Contract;

use PHPUnit\Framework\TestCase;

class FooterExperimentContractTest extends TestCase
{
    private function getModuleRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    private function getProjectRoot(): string
    {
        return dirname($this->getModuleRoot(), 4);
    }

    public function testSystemXmlHasFooterExperimentGroup(): void
    {
        $systemXmlPath = $this->getModuleRoot() . '/etc/adminhtml/system.xml';
        $this->assertFileExists($systemXmlPath);
        $contents = (string) file_get_contents($systemXmlPath);

        $this->assertNotSame('', $contents);
        $this->assertStringContainsString('group id="footer_experiment"', $contents);
        $this->assertStringContainsString('field id="enabled"', $contents);
        $this->assertStringContainsString('field id="rollout_percentage"', $contents);
        $this->assertStringContainsString('field id="variant_seed"', $contents);
    }

    public function testConfigXmlDefinesFooterExperimentDefaults(): void
    {
        $configXmlPath = $this->getModuleRoot() . '/etc/config.xml';
        $this->assertFileExists($configXmlPath);
        $contents = (string) file_get_contents($configXmlPath);

        $this->assertNotSame('', $contents);
        $this->assertStringContainsString('<footer_experiment>', $contents);
        $this->assertStringContainsString('<enabled>', $contents);
        $this->assertStringContainsString('<rollout_percentage>', $contents);
        $this->assertStringContainsString('<variant_seed>', $contents);
    }

    public function testFooterTemplateExposesExperimentAttributes(): void
    {
        $footerPath = $this->getProjectRoot() . '/app/design/frontend/AWA_Custom/ayo_home5_child/Rokanthemes_Themeoption/templates/html/footer.phtml';
        $this->assertFileExists($footerPath);
        $contents = (string) file_get_contents($footerPath);

        $this->assertNotSame('', $contents);
        $this->assertStringContainsString('data-awa-footer-exp-enabled', $contents);
        $this->assertStringContainsString('data-awa-footer-exp-rollout', $contents);
        $this->assertStringContainsString('data-awa-footer-exp-bucket', $contents);
        $this->assertStringContainsString('data-awa-footer-exp-seed', $contents);
        $this->assertStringContainsString('data-awa-footer-exp-variant="<?= $escaper->escapeHtmlAttr($footerExpVariant) ?>"', $contents);
        $this->assertStringContainsString('data-awa-footer-exp-active="<?= $footerExpActive ? \'1\' : \'0\' ?>"', $contents);
        $this->assertStringContainsString('$this->helper(\'GrupoAwamotos\\Theme\\Helper\\FooterExperiment\')', $contents);
        $this->assertStringContainsString('class="page_footer awa-footer--dark<?= $escaper->escapeHtmlAttr($footerExpStateClass . $footerExpVariantClass) ?>"', $contents);
        $this->assertStringContainsString('"js/awa-footer-interactions"', $contents);
    }

    public function testFooterTemplateKeepsExperimentLogicOutOfInlineScripts(): void
    {
        $footerPath = $this->getProjectRoot() . '/app/design/frontend/AWA_Custom/ayo_home5_child/Rokanthemes_Themeoption/templates/html/footer.phtml';
        $this->assertFileExists($footerPath);
        $contents = (string) file_get_contents($footerPath);

        $this->assertNotSame('', $contents);
        $this->assertStringContainsString('<script type="text/x-magento-init">', $contents);
        $this->assertStringNotContainsString('localStorage', $contents);
        $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*type="text\/x-magento-init")/i', $contents);
    }

    public function testFooterTemplateDefinesTreatmentQuickActions(): void
    {
        $footerPath = $this->getProjectRoot() . '/app/design/frontend/AWA_Custom/ayo_home5_child/Rokanthemes_Themeoption/templates/html/footer.phtml';
        $this->assertFileExists($footerPath);
        $contents = (string) file_get_contents($footerPath);

        $this->assertNotSame('', $contents);
        $this->assertStringContainsString('$showFooterQuickActions = $footerExpActive && $footerExpVariant === \'treatment\';', $contents);
        $this->assertStringContainsString("'customer/account/create/'", $contents);
        $this->assertStringContainsString("'contact/'", $contents);
        $this->assertStringContainsString('class="awa-footer-quick-actions"', $contents);
        $this->assertStringContainsString('class="awa-footer-quick-actions__link"', $contents);
    }

    public function testFooterInteractionsScriptUsesLazySliderInitialization(): void
    {
        $scriptPath = $this->getProjectRoot() . '/app/design/frontend/AWA_Custom/ayo_home5_child/web/js/awa-footer-interactions.js';
        $this->assertFileExists($scriptPath);
        $contents = (string) file_get_contents($scriptPath);

        $this->assertNotSame('', $contents);
        $this->assertStringContainsString('function scheduleBrandSliderInit()', $contents);
        $this->assertStringContainsString('function scheduleResizeSync()', $contents);
        $this->assertStringContainsString('window.IntersectionObserver', $contents);
        $this->assertStringContainsString('window.requestIdleCallback', $contents);
        $this->assertStringContainsString("autoplay: !prefersReducedMotion()", $contents);
        $this->assertStringContainsString("data-awa-footer-slider-ready", $contents);
    }

    public function testFooterTreatmentCssUsesProgressiveContentVisibility(): void
    {
        $cssPath = $this->getProjectRoot() . '/app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-bundle-custom.unmin.css';
        $this->assertFileExists($cssPath);
        $contents = (string) file_get_contents($cssPath);

        $this->assertNotSame('', $contents);
        $this->assertStringContainsString('@supports (content-visibility: auto)', $contents);
        $this->assertStringContainsString('.page_footer.awa-footer-exp--treatment .awa-footer-brands', $contents);
        $this->assertStringContainsString('.page_footer.awa-footer-exp--treatment .awa-footer-tags', $contents);
        $this->assertStringContainsString('.page_footer.awa-footer-exp--treatment .footer-bottom', $contents);
        $this->assertStringContainsString('contain-intrinsic-size: 1px 280px;', $contents);
    }

    public function testFooterTreatmentCssStylesQuickActionsRail(): void
    {
        $cssPath = $this->getProjectRoot() . '/app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-bundle-custom.unmin.css';
        $this->assertFileExists($cssPath);
        $contents = (string) file_get_contents($cssPath);

        $this->assertNotSame('', $contents);
        $this->assertStringContainsString('.page_footer.awa-footer-exp--treatment .awa-footer-quick-actions', $contents);
        $this->assertStringContainsString('.page_footer.awa-footer-exp--treatment .awa-footer-quick-actions__inner', $contents);
        $this->assertStringContainsString('.page_footer.awa-footer-exp--treatment .awa-footer-quick-actions__link', $contents);
        $this->assertStringContainsString('grid-template-columns: repeat(4, minmax(0, 1fr));', $contents);
    }

    public function testFooterExperimentHelperExists(): void
    {
        $helperPath = $this->getModuleRoot() . '/Helper/FooterExperiment.php';

        $this->assertFileExists($helperPath);
        $contents = (string) file_get_contents($helperPath);

        $this->assertStringContainsString('class FooterExperiment extends AbstractHelper', $contents);
        $this->assertStringContainsString('public function getPayload(?int $storeId = null): array', $contents);
    }

    public function testFooterExperimentDeciderExists(): void
    {
        $deciderPath = $this->getModuleRoot() . '/Model/FooterExperimentDecider.php';

        $this->assertFileExists($deciderPath);
        $contents = (string) file_get_contents($deciderPath);

        $this->assertStringContainsString('class FooterExperimentDecider', $contents);
        $this->assertStringContainsString("private const EXPERIMENT_CODE = 'footer_progressive_rollout';", $contents);
        $this->assertStringContainsString('public function decide(', $contents);
    }
}
