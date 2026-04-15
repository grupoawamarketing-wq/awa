<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\Test\Unit\ViewModel;

use GrupoAwamotos\Theme\ViewModel\ProductStructuredData;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\Framework\Registry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GrupoAwamotos\Theme\ViewModel\ProductStructuredData
 */
class ProductStructuredDataTest extends TestCase
{
    private Registry&MockObject $registry;
    private ImageHelper&MockObject $imageHelper;
    private ProductStructuredData $viewModel;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(Registry::class);
        $this->imageHelper = $this->createMock(ImageHelper::class);
        $this->viewModel = new ProductStructuredData($this->registry, $this->imageHelper);
    }

    public function testGetProductStructuredDataReturnsEmptyWhenNoProduct(): void
    {
        $this->registry->method('registry')->willReturn(null);

        $this->assertSame([], $this->viewModel->getProductStructuredData());
    }

    public function testGetProductStructuredDataBuildsExpectedPayload(): void
    {
        $product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getId', 'getName', 'getSku', 'getProductUrl', 'getShortDescription',
                'getDescription', 'getFinalPrice', 'isAvailable', 'getAttributeText', 'getData'
            ])
            ->getMock();

        $product->method('getId')->willReturn(1);
        $product->method('getName')->willReturn('Retrovisor &amp; Titan');
        $product->method('getSku')->willReturn('RET-10801');
        $product->method('getProductUrl')->willReturn('https://awamotos.com/retrovisor-titan-2000-03-d-e.html');
        $product->method('getShortDescription')->willReturn('<p>Compatível com Titan</p>');
        $product->method('getDescription')->willReturn('');
        $product->method('getFinalPrice')->willReturn(41.32);
        $product->method('isAvailable')->willReturn(true);
        $product->method('getAttributeText')->willReturnMap([
            ['manufacturer', 'Honda'],
            ['marca', null],
        ]);
        $product->method('getData')->willReturnMap([
            ['mpn', null],
            ['codigo_original', '10801'],
            ['ean', '7890000000000'],
            ['gtin', null],
        ]);

        $this->registry->method('registry')
            ->willReturnCallback(static fn (string $key) => $key === 'product' ? $product : null);
        $this->imageHelper->method('init')->with($product, 'product_base_image')->willReturn($this->imageHelper);
        $this->imageHelper->method('getUrl')->willReturn('https://awamotos.com/media/catalog/product/10801_1.jpg');

        $data = $this->viewModel->getProductStructuredData();

        $this->assertSame('Product', $data['@type']);
        $this->assertSame('Retrovisor & Titan', $data['name']);
        $this->assertSame('Honda', $data['brand']['name']);
        $this->assertSame('41.32', $data['offers']['price']);
        $this->assertSame('https://schema.org/InStock', $data['offers']['availability']);
        $this->assertSame('10801', $data['mpn']);
        $this->assertSame('7890000000000', $data['gtin13']);
    }

    public function testGetProductStructuredDataOmitsOffersWhenPriceIsZero(): void
    {
        $product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getId', 'getName', 'getSku', 'getProductUrl', 'getShortDescription',
                'getDescription', 'getFinalPrice', 'getAttributeText', 'getData'
            ])
            ->getMock();

        $product->method('getId')->willReturn(1);
        $product->method('getName')->willReturn('Produto B2B');
        $product->method('getSku')->willReturn('B2B-1');
        $product->method('getProductUrl')->willReturn('https://awamotos.com/produto-b2b.html');
        $product->method('getShortDescription')->willReturn('');
        $product->method('getDescription')->willReturn('');
        $product->method('getFinalPrice')->willReturn(0.0);
        $product->method('getAttributeText')->willReturn(null);
        $product->method('getData')->willReturn(null);

        $this->registry->method('registry')
            ->willReturnCallback(static fn (string $key) => $key === 'product' ? $product : null);
        $this->imageHelper->method('init')->with($product, 'product_base_image')->willReturn($this->imageHelper);
        $this->imageHelper->method('getUrl')->willReturn('https://awamotos.com/media/catalog/product/produto-b2b.jpg');

        $data = $this->viewModel->getProductStructuredData();

        $this->assertArrayNotHasKey('offers', $data);
    }
}
