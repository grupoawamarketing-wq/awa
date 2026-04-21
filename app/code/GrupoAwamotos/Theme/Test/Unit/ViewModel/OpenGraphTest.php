<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\Test\Unit\ViewModel;

use GrupoAwamotos\Theme\ViewModel\OpenGraph;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Page\Config as PageConfig;
use Magento\Framework\View\Page\Title;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GrupoAwamotos\Theme\ViewModel\OpenGraph
 */
class OpenGraphTest extends TestCase
{
    private OpenGraph $viewModel;
    private StoreManagerInterface&MockObject $storeManager;
    private Registry&MockObject $registry;
    private PageConfig&MockObject $pageConfig;
    private ImageHelper&MockObject $imageHelper;
    private HttpRequest&MockObject $request;
    private UrlInterface&MockObject $urlBuilder;
    private Store&MockObject $store;

    protected function setUp(): void
    {
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->registry = $this->createMock(Registry::class);
        $this->pageConfig = $this->createMock(PageConfig::class);
        $this->imageHelper = $this->createMock(ImageHelper::class);
        $this->request = $this->createMock(HttpRequest::class);
        $this->urlBuilder = $this->createMock(UrlInterface::class);

        $this->store = $this->getMockBuilder(Store::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getBaseUrl', 'getName'])
            ->getMock();

        $this->storeManager->method('getStore')->willReturn($this->store);

        $this->viewModel = new OpenGraph(
            $this->storeManager,
            $this->registry,
            $this->pageConfig,
            $this->imageHelper,
            $this->request,
            $this->urlBuilder
        );
    }

    public function testGetMetaDataUsesProductFallbackDescriptionWhenProductDescriptionIsEmpty(): void
    {
        $title = $this->createMock(Title::class);
        $title->method('get')->willReturn('Retrovisor Titan 2000 03 D E');
        $this->pageConfig->method('getTitle')->willReturn($title);
        $this->pageConfig->method('getDescription')->willReturn('Meta description da página');
        $this->store->method('getBaseUrl')->willReturn('https://awamotos.com/');
        $this->store->method('getName')->willReturn('AWA Motos');
        $this->urlBuilder->method('getCurrentUrl')->willReturn('https://awamotos.com/retrovisor-titan-2000-03-d-e.html?utm=1');

        $product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getName', 'getFinalPrice', 'isAvailable'])
            ->addMethods(['getShortDescription', 'getDescription'])
            ->getMock();
        $product->method('getId')->willReturn(1);
        $product->method('getName')->willReturn('Retrovisor &amp; Titan');
        $product->method('getShortDescription')->willReturn('');
        $product->method('getDescription')->willReturn('');
        $product->method('getFinalPrice')->willReturn(41.32);
        $product->method('isAvailable')->willReturn(true);

        $this->registry->method('registry')
            ->willReturnCallback(static fn (string $key) => $key === 'current_product' ? $product : null);
        $this->imageHelper->method('init')->with($product, 'product_base_image')->willReturn($this->imageHelper);
        $this->imageHelper->method('getUrl')->willReturn('https://awamotos.com/media/catalog/product/10801_1.jpg');

        $meta = $this->viewModel->getMetaData();

        $this->assertSame('product', $meta['type']);
        $this->assertSame('Retrovisor & Titan', $meta['title']);
        $this->assertSame('Meta description da página', $meta['description']);
        $this->assertSame('41.32', $meta['price_amount']);
        $this->assertSame('in stock', $meta['availability']);
    }

    public function testGetMetaDataUsesCategoryDataWhenCategoryExists(): void
    {
        $title = $this->createMock(Title::class);
        $title->method('get')->willReturn('Categoria');
        $this->pageConfig->method('getTitle')->willReturn($title);
        $this->pageConfig->method('getDescription')->willReturn('Fallback da categoria');
        $this->store->method('getBaseUrl')->willReturn('https://awamotos.com/');
        $this->store->method('getName')->willReturn('AWA Motos');
        $this->urlBuilder->method('getCurrentUrl')->willReturn('https://awamotos.com/retrovisores.html');

        $category = $this->getMockBuilder(Category::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getName', 'getImageUrl'])
            ->addMethods(['getDescription'])
            ->getMock();
        $category->method('getId')->willReturn(10);
        $category->method('getName')->willReturn('Retrovisores');
        $category->method('getDescription')->willReturn('<p>Peças &amp; acessórios</p>');
        $category->method('getImageUrl')->willReturn('https://awamotos.com/media/catalog/category/retrovisores.jpg');

        $this->registry->method('registry')
            ->willReturnCallback(static fn (string $key) => $key === 'current_category' ? $category : null);

        $meta = $this->viewModel->getMetaData();

        $this->assertSame('website', $meta['type']);
        $this->assertSame('Retrovisores', $meta['title']);
        $this->assertSame('Peças & acessórios', $meta['description']);
        $this->assertSame('https://awamotos.com/media/catalog/category/retrovisores.jpg', $meta['image']);
    }

    public function testIsHomepageReturnsTrueForCmsIndexIndex(): void
    {
        $this->request->method('getFullActionName')->willReturn('cms_index_index');

        $this->assertTrue($this->viewModel->isHomepage());
    }

    public function testGetMetaDataMemoizesExpensiveLookupsWithinSameRequest(): void
    {
        $title = $this->createMock(Title::class);
        $title->method('get')->willReturn('Produto');
        $this->pageConfig->method('getTitle')->willReturn($title);
        $this->pageConfig->method('getDescription')->willReturn('Descricao');
        $this->store->method('getBaseUrl')->willReturn('https://awamotos.com/');
        $this->store->method('getName')->willReturn('AWA Motos');
        $this->urlBuilder->expects($this->once())->method('getCurrentUrl')->willReturn('https://awamotos.com/produto.html');

        $product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getName', 'getFinalPrice', 'isAvailable'])
            ->addMethods(['getShortDescription', 'getDescription'])
            ->getMock();
        $product->method('getId')->willReturn(1);
        $product->method('getName')->willReturn('Produto');
        $product->method('getShortDescription')->willReturn('');
        $product->method('getDescription')->willReturn('');
        $product->method('getFinalPrice')->willReturn(10.0);
        $product->method('isAvailable')->willReturn(true);

        $this->registry->method('registry')
            ->willReturnCallback(static fn (string $key) => $key === 'current_product' ? $product : null);
        $this->imageHelper->expects($this->once())
            ->method('init')
            ->with($product, 'product_base_image')
            ->willReturn($this->imageHelper);
        $this->imageHelper->method('getUrl')->willReturn('https://awamotos.com/media/catalog/product/produto.jpg');

        $first = $this->viewModel->getMetaData();
        $second = $this->viewModel->getMetaData();

        $this->assertSame($first, $second);
        $this->assertSame('https://awamotos.com/produto.html', $second['url']);
    }
}
