<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\Test\Unit\Contract;

use PHPUnit\Framework\TestCase;

class VerticalMenuHoverIntentContractTest extends TestCase
{
    private function getModuleRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    private function getProjectRoot(): string
    {
        return dirname($this->getModuleRoot(), 4);
    }

    public function testTemplateBootstrapsHoverIntentWrapper(): void
    {
        $templatePath = $this->getProjectRoot()
            . '/app/design/frontend/AWA_Custom/ayo_home5_child/Rokanthemes_VerticalMenu/templates/sidemenu.phtml';

        $this->assertFileExists($templatePath);
        $contents = (string) file_get_contents($templatePath);
        $this->assertNotSame('', $contents);

        $this->assertStringContainsString('"js/vertical-menu-init-hover-tuned"', $contents);
        $this->assertStringContainsString('"hoverDelay": 240', $contents);
        $this->assertStringContainsString('data-role="awa-vertical-menu-status"', $contents);
    }

    public function testHoverIntentWrapperUsesConfiguredDelay(): void
    {
        $jsPath = $this->getProjectRoot()
            . '/app/design/frontend/AWA_Custom/ayo_home5_child/web/js/vertical-menu-init-hover-tuned.js';

        $this->assertFileExists($jsPath);
        $contents = (string) file_get_contents($jsPath);
        $this->assertNotSame('', $contents);

        $this->assertStringContainsString("'js/vertical-menu-init'", $contents);
        $this->assertStringContainsString('config && config.hoverDelay', $contents);
        $this->assertStringContainsString("\$nav.off('mouseenter', selector)", $contents);
        $this->assertStringContainsString("'mouseenter' + namespace", $contents);
        $this->assertStringContainsString("'mouseleave' + namespace", $contents);
        $this->assertStringContainsString("'focusin' + namespace", $contents);
    }

    public function testActiveCssFilesStillExposeDesktopContracts(): void
    {
        $cssPaths = [
            $this->getProjectRoot() . '/app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-super-global.css',
            $this->getProjectRoot() . '/app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-vertical-menu-desktop-final.css',
            $this->getProjectRoot() . '/app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-layout-bundle.css',
        ];

        $contents = '';

        foreach ($cssPaths as $cssPath) {
            $this->assertFileExists($cssPath);

            $fileContents = (string) file_get_contents($cssPath);
            $this->assertNotSame('', $fileContents);

            $contents .= $fileContents;
        }

        $this->assertStringContainsString('grid-template-columns: repeat(3, minmax(0, 1fr)) minmax(280px, 320px)', $contents);
        $this->assertStringContainsString('calc(var(--vmm-left', $contents);
        $this->assertStringContainsString('min-height: 464px', $contents);
        $this->assertStringContainsString(':focus-visible', $contents);
        $this->assertStringContainsString('body.nav-open .page-wrapper #awa-category-navigation[data-awa-nav-shell="true"]', $contents);
        $this->assertStringContainsString('content: attr(aria-label)', $contents);
        $this->assertStringContainsString('.awa-hamburger__line', $contents);
        $this->assertStringContainsString('.awa-nav-overlay.is-visible', $contents);
    }
}
