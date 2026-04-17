<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\Test\Unit\Contract;

use PHPUnit\Framework\TestCase;

class VerticalMenuContractTest extends TestCase
{
    private function getModuleRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    private function getProjectRoot(): string
    {
        return dirname($this->getModuleRoot(), 4);
    }

    public function testVerticalMenuTemplateUsesButtonTrigger(): void
    {
        $templatePath = $this->getProjectRoot()
            . '/app/design/frontend/AWA_Custom/ayo_home5_child/Rokanthemes_VerticalMenu/templates/sidemenu.phtml';

        $this->assertFileExists($templatePath);
        $contents = (string) file_get_contents($templatePath);
        $this->assertNotSame('', $contents);

        // Button semantics are important for keyboard and screen readers.
        $this->assertStringContainsString('<button type="button"', $contents);
        $this->assertStringContainsString('title-category-dropdown', $contents);
        $this->assertStringContainsString('aria-controls', $contents);
        $this->assertStringContainsString('aria-expanded', $contents);
    }

    public function testVerticalMenuInitSyncsAriaHidden(): void
    {
        $jsPath = $this->getProjectRoot()
            . '/app/design/frontend/AWA_Custom/ayo_home5_child/web/js/vertical-menu-init.js';

        $this->assertFileExists($jsPath);
        $contents = (string) file_get_contents($jsPath);
        $this->assertNotSame('', $contents);

        $this->assertStringContainsString("attr('aria-hidden'", $contents);
        $this->assertStringContainsString("resolveDesktopAnchorEl", $contents);
    }

    public function testRequireJsBootstrapsFocusTrap(): void
    {
        $configPath = $this->getProjectRoot()
            . '/app/design/frontend/AWA_Custom/ayo_home5_child/requirejs-config.js';

        $this->assertFileExists($configPath);
        $contents = (string) file_get_contents($configPath);
        $this->assertNotSame('', $contents);

        $this->assertStringContainsString('awa-vertical-menu-focus-trap', $contents);
    }

    public function testVerticalMenuCssContainsDesktopMegaMenuContracts(): void
    {
        $cssPath = $this->getProjectRoot()
            . '/app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-home-vertical-menu-shell-fix.css';

        $this->assertFileExists($cssPath);
        $contents = (string) file_get_contents($cssPath);
        $this->assertNotSame('', $contents);

        $this->assertStringContainsString('grid-template-columns: repeat(3, minmax(0, 1fr)) minmax(280px, 320px)', $contents);
        $this->assertStringContainsString('left: 290px', $contents);
        $this->assertStringContainsString('min-height: 464px', $contents);
        $this->assertStringContainsString(':focus-visible', $contents);
        $this->assertStringContainsString('body.nav-open .page-wrapper #awa-category-navigation[data-awa-nav-shell="true"]', $contents);
        $this->assertStringContainsString("content: attr(aria-label)", $contents);
    }
}
