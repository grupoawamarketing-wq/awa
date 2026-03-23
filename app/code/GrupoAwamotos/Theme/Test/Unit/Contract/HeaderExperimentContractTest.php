<?php
declare(strict_types=1);

namespace GrupoAwamotos\Theme\Test\Unit\Contract;

use PHPUnit\Framework\TestCase;

class HeaderExperimentContractTest extends TestCase
{
    private function getModuleRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    private function getProjectRoot(): string
    {
        return dirname($this->getModuleRoot(), 4);
    }

    public function testSystemXmlHasHeaderExperimentGroup(): void
    {
        $systemXmlPath = $this->getModuleRoot() . '/etc/adminhtml/system.xml';
        $this->assertFileExists($systemXmlPath);
        $contents = (string) file_get_contents($systemXmlPath);

        $this->assertNotSame('', $contents);
        $this->assertStringContainsString('group id="header_experiment"', $contents);
        $this->assertStringContainsString('field id="enabled"', $contents);
        $this->assertStringContainsString('field id="rollout_percentage"', $contents);
        $this->assertStringContainsString('field id="variant_seed"', $contents);
    }

    public function testConfigXmlDefinesHeaderExperimentDefaults(): void
    {
        $configXmlPath = $this->getModuleRoot() . '/etc/config.xml';
        $this->assertFileExists($configXmlPath);
        $contents = (string) file_get_contents($configXmlPath);

        $this->assertNotSame('', $contents);
        $this->assertStringContainsString('<header_experiment>', $contents);
        $this->assertStringContainsString('<enabled>', $contents);
        $this->assertStringContainsString('<rollout_percentage>', $contents);
        $this->assertStringContainsString('<variant_seed>', $contents);
    }

    public function testHeaderTemplateExposesExperimentAttributes(): void
    {
        $headerPath = $this->getProjectRoot() . '/app/design/frontend/AWA_Custom/ayo_home5_child/Rokanthemes_Themeoption/templates/html/header.phtml';
        $this->assertFileExists($headerPath);
        $contents = (string) file_get_contents($headerPath);

        $this->assertNotSame('', $contents);
        $this->assertStringContainsString('data-awa-header-exp-enabled', $contents);
        $this->assertStringContainsString('data-awa-header-exp-rollout', $contents);
        $this->assertStringContainsString('data-awa-header-exp-seed', $contents);
    }
}
