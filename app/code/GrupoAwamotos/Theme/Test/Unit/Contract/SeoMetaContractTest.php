<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\Test\Unit\Contract;

use PHPUnit\Framework\TestCase;

class SeoMetaContractTest extends TestCase
{
    private function getModuleRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    private function getProjectRoot(): string
    {
        return dirname($this->getModuleRoot(), 4);
    }

    public function testDefaultHeadBlocksXmlRegistersOgMetaInsideHeadAdditional(): void
    {
        $layoutPath = $this->getProjectRoot() . '/app/design/frontend/AWA_Custom/ayo_home5_child/Magento_Theme/layout/default_head_blocks.xml';

        $this->assertFileExists($layoutPath);

        $contents = (string) file_get_contents($layoutPath);

        $this->assertStringContainsString('<referenceBlock name="head.additional">', $contents);
        $this->assertMatchesRegularExpression('/name="awa\.og\.meta"[\s\S]*GrupoAwamotos\\Theme\\ViewModel\\OpenGraph/s', $contents);
        $this->assertDoesNotMatchRegularExpression('/<referenceContainer name="before\.body\.end">[\s\S]*name="awa\.og\.meta"/s', $contents);
    }

    public function testCatalogProductViewXmlUsesProductStructuredDataViewModel(): void
    {
        $layoutPath = $this->getProjectRoot() . '/app/design/frontend/AWA_Custom/ayo_home5_child/Magento_Catalog/layout/catalog_product_view.xml';

        $this->assertFileExists($layoutPath);

        $contents = (string) file_get_contents($layoutPath);

        $this->assertStringContainsString('name="awa.schema.product.jsonld"', $contents);
        $this->assertStringContainsString('GrupoAwamotos\Theme\ViewModel\ProductStructuredData', $contents);
    }

    public function testSeoTemplatesDoNotUseObjectManager(): void
    {
        $projectRoot = $this->getProjectRoot();
        $ogTemplate = $projectRoot . '/app/design/frontend/AWA_Custom/ayo_home5_child/Magento_Theme/templates/html/awa-og-meta.phtml';
        $jsonLdTemplate = $projectRoot . '/app/design/frontend/AWA_Custom/ayo_home5_child/Magento_Catalog/templates/product/view/awa-product-jsonld.phtml';

        $this->assertStringNotContainsString('ObjectManager', (string) file_get_contents($ogTemplate));
        $this->assertStringNotContainsString('ObjectManager', (string) file_get_contents($jsonLdTemplate));
    }
}
