<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\Test\Unit\Contract;

use PHPUnit\Framework\TestCase;

class HeadPreloadContractTest extends TestCase
{
    private function getModuleRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    private function getProjectRoot(): string
    {
        return dirname($this->getModuleRoot(), 4);
    }

    public function testDefaultHeadBlocksXmlDoesNotUseUnsupportedOnloadAttributes(): void
    {
        $layoutPath = $this->getProjectRoot() . '/app/design/frontend/AWA_Custom/ayo_home5_child/Magento_Theme/layout/default_head_blocks.xml';

        $this->assertFileExists($layoutPath);

        $contents = (string) file_get_contents($layoutPath);

        $this->assertNotSame('', $contents);
        $this->assertDoesNotMatchRegularExpression('/<css[^>]+onload=/i', $contents);
        $this->assertDoesNotMatchRegularExpression('/<link[^>]+onload=/i', $contents);
    }

    public function testHeadPreloadPhtmlOwnsAsyncStylesheetStrategy(): void
    {
        $templatePath = $this->getProjectRoot() . '/app/design/frontend/AWA_Custom/ayo_home5_child/Magento_Theme/templates/html/awa-head-preload.phtml';

        $this->assertFileExists($templatePath);

        $contents = (string) file_get_contents($templatePath);

        $this->assertNotSame('', $contents);
        $this->assertStringContainsString('onload="this.media=\'all\'"', $contents);
        $this->assertStringContainsString('<noscript><link rel="stylesheet"', $contents);
        $this->assertStringContainsString('$asyncBundles = [', $contents);
    }
}
