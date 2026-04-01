<?php

declare(strict_types=1);

namespace GrupoAwamotos\SocialProof\Test\Unit\Block;

use GrupoAwamotos\SocialProof\Block\ProductInfo;
use Magento\Catalog\Model\Product;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\Catalog\Api\Data\ProductExtensionInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template\Context;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GrupoAwamotos\SocialProof\Block\ProductInfo
 */
class ProductInfoTest extends TestCase
{
    private ProductInfo $block;
    private Registry&MockObject $registry;

    protected function setUp(): void
    {
        $context = $this->createMock(Context::class);
        $this->registry = $this->createMock(Registry::class);

        $this->block = new ProductInfo($context, $this->registry);
    }

    // ====================================================================
    // getProduct
    // ====================================================================

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

    // ====================================================================
    // getViewsToday
    // ====================================================================

    public function testGetViewsTodayReturnsProductData(): void
    {
        $product = $this->createProductWithData(['views_today' => 42]);
        $this->registerProduct($product);

        $this->assertSame(42, $this->block->getViewsToday());
    }

    public function testGetViewsTodayReturnsZeroWhenNoProduct(): void
    {
        $this->registerProduct(null);

        $this->assertSame(0, $this->block->getViewsToday());
    }

    public function testGetViewsTodayReturnsZeroWhenDataMissing(): void
    {
        $product = $this->createProductWithData([]);
        $this->registerProduct($product);

        $this->assertSame(0, $this->block->getViewsToday());
    }

    // ====================================================================
    // isBestSeller
    // ====================================================================

    public function testIsBestSellerReturnsTrueWhenFlagSet(): void
    {
        $product = $this->createProductWithData(['is_best_seller' => true]);
        $this->registerProduct($product);

        $this->assertTrue($this->block->isBestSeller());
    }

    public function testIsBestSellerReturnsFalseWhenFlagNotSet(): void
    {
        $product = $this->createProductWithData(['is_best_seller' => false]);
        $this->registerProduct($product);

        $this->assertFalse($this->block->isBestSeller());
    }

    public function testIsBestSellerReturnsFalseWhenNoProduct(): void
    {
        $this->registerProduct(null);

        $this->assertFalse($this->block->isBestSeller());
    }

    // ====================================================================
    // isLowStock
    // ====================================================================

    public function testIsLowStockReturnsTrueWhenQtyBelowTen(): void
    {
        $product = $this->createProductWithStock(5);
        $this->registerProduct($product);

        $this->assertTrue($this->block->isLowStock());
    }

    public function testIsLowStockReturnsFalseWhenQtyAboveTen(): void
    {
        $product = $this->createProductWithStock(50);
        $this->registerProduct($product);

        $this->assertFalse($this->block->isLowStock());
    }

    public function testIsLowStockReturnsFalseWhenQtyIsZero(): void
    {
        $product = $this->createProductWithStock(0);
        $this->registerProduct($product);

        $this->assertFalse($this->block->isLowStock());
    }

    public function testIsLowStockReturnsFalseWhenNoProduct(): void
    {
        $this->registerProduct(null);

        $this->assertFalse($this->block->isLowStock());
    }

    public function testIsLowStockReturnsFalseWhenNoStockItem(): void
    {
        $extensionAttributes = $this->createMock(ProductExtensionInterface::class);
        $extensionAttributes->method('getStockItem')->willReturn(null);

        $product = $this->createMock(Product::class);
        $product->method('getExtensionAttributes')->willReturn($extensionAttributes);

        $this->registerProduct($product);

        $this->assertFalse($this->block->isLowStock());
    }

    public function testIsLowStockReturnsTrueAtBoundary(): void
    {
        $product = $this->createProductWithStock(9);
        $this->registerProduct($product);

        $this->assertTrue($this->block->isLowStock());
    }

    public function testIsLowStockReturnsFalseAtExactTen(): void
    {
        $product = $this->createProductWithStock(10);
        $this->registerProduct($product);

        $this->assertFalse($this->block->isLowStock());
    }

    // ====================================================================
    // getStockQty
    // ====================================================================

    public function testGetStockQtyReturnsQty(): void
    {
        $product = $this->createProductWithStock(25);
        $this->registerProduct($product);

        $this->assertSame(25, $this->block->getStockQty());
    }

    public function testGetStockQtyReturnsZeroWhenNoProduct(): void
    {
        $this->registerProduct(null);

        $this->assertSame(0, $this->block->getStockQty());
    }

    public function testGetStockQtyReturnsZeroWhenNoStockItem(): void
    {
        $extensionAttributes = $this->createMock(ProductExtensionInterface::class);
        $extensionAttributes->method('getStockItem')->willReturn(null);

        $product = $this->createMock(Product::class);
        $product->method('getExtensionAttributes')->willReturn($extensionAttributes);

        $this->registerProduct($product);

        $this->assertSame(0, $this->block->getStockQty());
    }

    // ====================================================================
    // Helpers
    // ====================================================================

    private function registerProduct(?Product $product): void
    {
        $this->registry->method('registry')
            ->with('current_product')
            ->willReturn($product);
    }

    private function createProductWithData(array $data): Product&MockObject
    {
        $product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getData'])
            ->getMock();

        $product->method('getData')->willReturnCallback(
            function (string $key) use ($data) {
                return $data[$key] ?? null;
            }
        );

        return $product;
    }

    private function createProductWithStock(int $qty): Product&MockObject
    {
        $stockItem = $this->createMock(StockItemInterface::class);
        $stockItem->method('getQty')->willReturn((float) $qty);

        $extensionAttributes = $this->createMock(ProductExtensionInterface::class);
        $extensionAttributes->method('getStockItem')->willReturn($stockItem);

        $product = $this->createMock(Product::class);
        $product->method('getExtensionAttributes')->willReturn($extensionAttributes);

        return $product;
    }
}
