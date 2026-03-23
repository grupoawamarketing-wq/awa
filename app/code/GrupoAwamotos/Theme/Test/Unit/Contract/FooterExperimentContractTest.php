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
        $this->assertStringContainsString('data-awa-footer-exp-seed', $contents);
        $this->assertStringContainsString('data-awa-footer-exp-variant="control"', $contents);
        $this->assertStringContainsString('try {', $contents);
        $this->assertStringContainsString('localStorage.getItem', $contents);
        $this->assertStringContainsString('catch (error)', $contents);
    }
}
