<?php

declare(strict_types=1);

namespace GrupoAwamotos\SocialProof\Test\Unit\Block;

use GrupoAwamotos\SocialProof\Block\ProductInfo;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template\Context;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GrupoAwamotos\SocialProof\Block\ProductInfo
 */
class ProductInfoTest extends TestCase
{
    private ProductInfo $block;
    private Registry&MockObject $registry;
    private CacheInterface&MockObject $cache;
    private ResourceConnection&MockObject $resourceConnection;

    protected function setUp(): void
    {
        $context = $this->createMock(Context::class);
        $this->registry = $this->createMock(Registry::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->resourceConnection = $this->createMock(ResourceConnection::class);
        $logger = $this->createMock(LoggerInterface::class);

        $this->block = new ProductInfo(
            $context,
            $this->registry,
            $this->resourceConnection,
            $this->cache,
            $logger
        );
    }

    public function testGetProductReturnsFromRegistry(): void
    {
        $product = $this->createMock(Product::class);
        $this->registry->method('registry')
            ->with('current_product')
            ->willReturn($product);

        $this->assertSame($product, $this->block->getProduct());
    }

    public function testGetProductReturnsNullWhenNoProduct(): void
    {
        $this->registry->method('registry')
            ->with('current_product')
            ->willReturn(null);

        $this->assertNull($this->block->getProduct());
    }

    public function testGettersReuseLoadedCacheDataAcrossSameRequest(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn(42);
        $this->registerProduct($product);

        $this->cache->expects($this->once())
            ->method('load')
            ->with('socialproof_pdp_42')
            ->willReturn(json_encode([
                'views_today' => 11,
                'is_best_seller' => true,
                'low_stock' => true,
                'qty' => 3,
            ]));

        $this->assertSame(11, $this->block->getViewsToday());
        $this->assertTrue($this->block->isBestSeller());
        $this->assertTrue($this->block->isLowStock());
        $this->assertSame(3, $this->block->getStockQty());
    }

    public function testReturnsDefaultDataWhenProductIsMissing(): void
    {
        $this->registerProduct(null);
        $this->cache->expects($this->never())->method('load');

        $this->assertSame(0, $this->block->getViewsToday());
        $this->assertFalse($this->block->isBestSeller());
        $this->assertFalse($this->block->isLowStock());
        $this->assertSame(0, $this->block->getStockQty());
    }

    private function registerProduct(?Product $product): void
    {
        $this->registry->method('registry')
            ->with('current_product')
            ->willReturn($product);
    }
}
