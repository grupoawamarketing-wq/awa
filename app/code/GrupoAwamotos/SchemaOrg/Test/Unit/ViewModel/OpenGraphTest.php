<?php

declare(strict_types=1);

namespace GrupoAwamotos\SchemaOrg\Test\Unit\ViewModel;

use GrupoAwamotos\SchemaOrg\ViewModel\OpenGraph;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Registry;
use Magento\Framework\View\Page\Config as PageConfig;
use Magento\Framework\View\Page\Title;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GrupoAwamotos\SchemaOrg\ViewModel\OpenGraph
 */
class OpenGraphTest extends TestCase
{
    private OpenGraph $viewModel;
    private StoreManagerInterface&MockObject $storeManager;
    private Registry&MockObject $registry;
    private PageConfig&MockObject $pageConfig;
    private ImageHelper&MockObject $imageHelper;
    private HttpRequest&MockObject $request;
    private Store&MockObject $store;

    protected function setUp(): void
    {
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->registry = $this->createMock(Registry::class);
        $this->pageConfig = $this->createMock(PageConfig::class);
        $this->imageHelper = $this->createMock(ImageHelper::class);
        $this->request = $this->createMock(HttpRequest::class);

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
            $this->request
        );
    }

    // ====================================================================
    // getBaseUrl
    // ====================================================================

    public function testGetBaseUrlTrimsTrailingSlash(): void
    {
        $this->store->method('getBaseUrl')->willReturn('https://awamotos.com.br/');

        $this->assertSame('https://awamotos.com.br', $this->viewModel->getBaseUrl());
    }

    public function testGetBaseUrlPreservesUrlWithoutSlash(): void
    {
        $this->store->method('getBaseUrl')->willReturn('https://awamotos.com.br');

        $this->assertSame('https://awamotos.com.br', $this->viewModel->getBaseUrl());
    }

    // ====================================================================
    // getStoreName
    // ====================================================================

    public function testGetStoreNameReturnsStoreName(): void
    {
        $this->store->method('getName')->willReturn('AWA Motos');
        $this->store->method('getBaseUrl')->willReturn('https://awamotos.com.br/');

        $this->assertSame('AWA Motos', $this->viewModel->getStoreName());
    }

    // ====================================================================
    // getCurrentUrl
    // ====================================================================

    public function testGetCurrentUrlBuildsFromPathInfo(): void
    {
        $this->store->method('getBaseUrl')->willReturn('https://awamotos.com.br/');
        $this->request->method('getPathInfo')->willReturn('/bagageiro-cg-160.html');

        $this->assertSame(
            'https://awamotos.com.br/bagageiro-cg-160.html',
            $this->viewModel->getCurrentUrl()
        );
    }

    public function testGetCurrentUrlHandlesHomepage(): void
    {
        $this->store->method('getBaseUrl')->willReturn('https://awamotos.com.br/');
        $this->request->method('getPathInfo')->willReturn('/');

        $this->assertSame('https://awamotos.com.br/', $this->viewModel->getCurrentUrl());
    }

    public function testGetCurrentUrlHandlesEmptyPath(): void
    {
        $this->store->method('getBaseUrl')->willReturn('https://awamotos.com.br/');
        $this->request->method('getPathInfo')->willReturn('');

        $this->assertSame('https://awamotos.com.br/', $this->viewModel->getCurrentUrl());
    }

    // ====================================================================
    // getCurrentProduct
    // ====================================================================

    public function testGetCurrentProductReturnsProductFromRegistry(): void
    {
        $product = $this->createMock(Product::class);
        $this->registry->method('registry')
            ->with('current_product')
            ->willReturn($product);

        $this->assertSame($product, $this->viewModel->getCurrentProduct());
    }

    public function testGetCurrentProductReturnsNullWhenNoProduct(): void
    {
        $this->registry->method('registry')
            ->with('current_product')
            ->willReturn(null);

        $this->assertNull($this->viewModel->getCurrentProduct());
    }

    // ====================================================================
    // getCurrentCategory
    // ====================================================================

    public function testGetCurrentCategoryReturnsCategoryFromRegistry(): void
    {
        $category = $this->createMock(Category::class);
        $this->registry->method('registry')
            ->willReturnCallback(function (string $key) use ($category) {
                return $key === 'current_category' ? $category : null;
            });

        $this->assertSame($category, $this->viewModel->getCurrentCategory());
    }

    // ====================================================================
    // getPageTitle
    // ====================================================================

    public function testGetPageTitleReturnsConfigTitle(): void
    {
        $title = $this->createMock(Title::class);
        $title->method('get')->willReturn('Bagageiro CG 160 - AWA Motos');
        $this->pageConfig->method('getTitle')->willReturn($title);

        $this->assertSame('Bagageiro CG 160 - AWA Motos', $this->viewModel->getPageTitle());
    }

    // ====================================================================
    // getPageDescription
    // ====================================================================

    public function testGetPageDescriptionReturnsConfigDescription(): void
    {
        $this->pageConfig->method('getDescription')
            ->willReturn('O melhor bagageiro para CG 160');

        $this->assertSame('O melhor bagageiro para CG 160', $this->viewModel->getPageDescription());
    }

    public function testGetPageDescriptionReturnsFallbackWhenEmpty(): void
    {
        $this->pageConfig->method('getDescription')->willReturn('');

        $this->assertSame(
            'Peças e acessórios para motos — AWA Motos',
            $this->viewModel->getPageDescription()
        );
    }

    // ====================================================================
    // getDefaultImage
    // ====================================================================

    public function testGetDefaultImageReturnsLogoPath(): void
    {
        $this->store->method('getBaseUrl')->willReturn('https://awamotos.com.br/');

        $defaultImage = $this->viewModel->getDefaultImage();
        $this->assertStringContainsString('logo-awa.png', $defaultImage);
        $this->assertStringStartsWith('https://awamotos.com.br', $defaultImage);
    }

    // ====================================================================
    // getProductImageUrl
    // ====================================================================

    public function testGetProductImageUrlUsesImageHelper(): void
    {
        $product = $this->createMock(Product::class);
        $this->imageHelper->method('init')
            ->with($product, 'product_base_image')
            ->willReturn($this->imageHelper);
        $this->imageHelper->method('getUrl')
            ->willReturn('https://awamotos.com.br/media/catalog/product/bag.jpg');

        $url = $this->viewModel->getProductImageUrl($product);
        $this->assertSame('https://awamotos.com.br/media/catalog/product/bag.jpg', $url);
    }

    // ====================================================================
    // isHomepage
    // ====================================================================

    public function testIsHomepageReturnsTrueForCmsIndex(): void
    {
        $this->request->method('getFullActionName')->willReturn('cms_index_index');
        $this->assertTrue($this->viewModel->isHomepage());
    }

    public function testIsHomepageReturnsFalseForProductPage(): void
    {
        $this->request->method('getFullActionName')->willReturn('catalog_product_view');
        $this->assertFalse($this->viewModel->isHomepage());
    }

    public function testIsHomepageReturnsFalseForCategoryPage(): void
    {
        $this->request->method('getFullActionName')->willReturn('catalog_category_view');
        $this->assertFalse($this->viewModel->isHomepage());
    }
}
