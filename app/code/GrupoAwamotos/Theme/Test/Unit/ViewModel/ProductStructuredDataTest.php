<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\Test\Unit\ViewModel;

use GrupoAwamotos\Theme\ViewModel\ProductStructuredData;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Framework\Registry;
use Magento\Review\Model\Review;
use Magento\Review\Model\ReviewFactory;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GrupoAwamotos\Theme\ViewModel\ProductStructuredData
 */
class ProductStructuredDataTest extends TestCase
{
    private Registry&MockObject $registry;
    private ImageHelper&MockObject $imageHelper;
    private ReviewFactory&MockObject $reviewFactory;
    private Review&MockObject $reviewModel;
    private StoreManagerInterface&MockObject $storeManager;
    private Store&MockObject $store;
    private CategoryRepositoryInterface&MockObject $categoryRepository;
    private ProductStructuredData $viewModel;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(Registry::class);
        $this->imageHelper = $this->createMock(ImageHelper::class);
        $this->reviewFactory = $this->createMock(ReviewFactory::class);
        $this->reviewModel = $this->createMock(Review::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->store = $this->getMockBuilder(Store::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getBaseUrl'])
            ->getMock();
        $this->categoryRepository = $this->createMock(CategoryRepositoryInterface::class);

        $this->reviewFactory->method('create')->willReturn($this->reviewModel);
        $this->storeManager->method('getStore')->willReturn($this->store);
        $this->store->method('getId')->willReturn(1);
        $this->store->method('getBaseUrl')->willReturn('https://awamotos.com/');

        $this->viewModel = new ProductStructuredData(
            $this->registry,
            $this->imageHelper,
            $this->reviewFactory,
            $this->storeManager,
            $this->categoryRepository
        );
    }

    public function testGetProductStructuredDataReturnsEmptyWhenNoProduct(): void
    {
        $this->registry->method('registry')->willReturn(null);

        $this->assertSame([], $this->viewModel->getProductStructuredData());
    }

    public function testGetProductStructuredDataBuildsExpectedPayload(): void
    {
        $product = $this->createProductMock();

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
        $product->method('getData')->willReturnCallback(
            static function (?string $key = null) {
                return match ($key) {
                    'rating_summary' => 80,
                    'reviews_count' => 5,
                    'mpn' => null,
                    'codigo_original' => '10801',
                    'ean' => '7890000000000',
                    'gtin' => null,
                    default => null,
                };
            }
        );
        $this->reviewModel->expects($this->once())->method('getEntitySummary')->with($product, 1);

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
        $product = $this->createProductMock();

        $product->method('getId')->willReturn(1);
        $product->method('getName')->willReturn('Produto B2B');
        $product->method('getSku')->willReturn('B2B-1');
        $product->method('getProductUrl')->willReturn('https://awamotos.com/produto-b2b.html');
        $product->method('getShortDescription')->willReturn('');
        $product->method('getDescription')->willReturn('');
        $product->method('getFinalPrice')->willReturn(0.0);
        $product->method('isAvailable')->willReturn(false);
        $product->method('getAttributeText')->willReturn(null);
        $product->method('getData')->willReturnCallback(static fn (?string $key = null) => null);

        $this->registry->method('registry')
            ->willReturnCallback(static fn (string $key) => $key === 'product' ? $product : null);
        $this->imageHelper->method('init')->with($product, 'product_base_image')->willReturn($this->imageHelper);
        $this->imageHelper->method('getUrl')->willReturn('https://awamotos.com/media/catalog/product/produto-b2b.jpg');

        $data = $this->viewModel->getProductStructuredData();

        $this->assertArrayNotHasKey('offers', $data);
    }

    public function testImageUrlIsMemoizedAcrossPdpCalls(): void
    {
        $product = $this->createProductMock();
        $product->method('getId')->willReturn(1);
        $product->method('getName')->willReturn('Produto');
        $product->method('getSku')->willReturn('SKU-1');
        $product->method('getProductUrl')->willReturn('https://awamotos.com/produto.html');
        $product->method('getShortDescription')->willReturn('');
        $product->method('getDescription')->willReturn('');
        $product->method('getFinalPrice')->willReturn(10.0);
        $product->method('isAvailable')->willReturn(true);
        $product->method('getAttributeText')->willReturn(null);
        $product->method('getData')->willReturnCallback(static fn (?string $key = null) => null);

        $this->registry->method('registry')
            ->willReturnCallback(static fn (string $key) => $key === 'product' ? $product : null);
        $this->reviewModel->method('getEntitySummary');
        $this->imageHelper->expects($this->once())
            ->method('init')
            ->with($product, 'product_base_image')
            ->willReturn($this->imageHelper);
        $this->imageHelper->method('getUrl')->willReturn('https://awamotos.com/media/catalog/product/produto.jpg');

        $this->assertSame('https://awamotos.com/media/catalog/product/produto.jpg', $this->viewModel->getProductLcpImageUrl());
        $this->assertSame('https://awamotos.com/media/catalog/product/produto.jpg', $this->viewModel->getProductStructuredData()['image']);
    }

    public function testBreadcrumbUsesCurrentCategoryWhenAvailable(): void
    {
        $product = $this->createProductMock();
        $product->method('getId')->willReturn(1);
        $product->method('getName')->willReturn('Produto');

        $category = $this->createMock(Category::class);
        $category->method('getId')->willReturn(10);
        $category->method('getName')->willReturn('Categoria');
        $category->method('getUrl')->willReturn('https://awamotos.com/categoria.html');

        $this->registry->method('registry')
            ->willReturnCallback(static function (string $key) use ($product, $category) {
                return match ($key) {
                    'product' => $product,
                    'current_category' => $category,
                    default => null,
                };
            });

        $breadcrumb = $this->viewModel->getBreadcrumbStructuredData();

        $this->assertCount(3, $breadcrumb['itemListElement']);
        $this->assertSame('Categoria', $breadcrumb['itemListElement'][1]['name']);
    }

    private function createProductMock(): Product&MockObject
    {
        return $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getId',
                'getName',
                'getSku',
                'getProductUrl',
                'getFinalPrice',
                'isAvailable',
                'getAttributeText',
                'getData',
                'getCategoryIds',
            ])
            ->addMethods([
                'getShortDescription',
                'getDescription',
            ])
            ->getMock();
    }
}
