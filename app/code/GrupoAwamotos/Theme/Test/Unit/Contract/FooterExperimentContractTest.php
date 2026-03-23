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
