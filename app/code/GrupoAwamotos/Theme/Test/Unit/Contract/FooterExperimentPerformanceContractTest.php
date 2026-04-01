<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\Test\Unit\Contract;

use PHPUnit\Framework\TestCase;

class FooterExperimentPerformanceContractTest extends TestCase
{
    private function getModuleRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    private function getProjectRoot(): string
    {
        return dirname($this->getModuleRoot(), 4);
    }

    public function testFooterTreatmentPerformanceRulesAreScopedByVariant(): void
    {
        $cssPath = $this->getProjectRoot() . '/app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-bundle-refinements.unmin.css';
        $this->assertFileExists($cssPath);
        $contents = (string) file_get_contents($cssPath);

        $this->assertNotSame('', $contents);
        $this->assertStringContainsString('.page_footer.awa-footer-exp--treatment .awa-footer-trust-grid', $contents);
        $this->assertStringContainsString('.page_footer.awa-footer-exp--treatment .awa-footer-brands', $contents);
        $this->assertStringContainsString('content-visibility: auto;', $contents);
        $this->assertStringContainsString('contain-intrinsic-size: auto 220px;', $contents);
    }
}
