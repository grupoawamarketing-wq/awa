<?php

declare(strict_types=1);

namespace GrupoAwamotos\RexisML\Test\Unit\Model\Product;

use GrupoAwamotos\RexisML\Model\Product\BulkSkuProductLoader;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class BulkSkuProductLoaderTest extends TestCase
{
    public function testLoadBySkusLoadsUniqueProductsInSingleCollectionFlow(): void
    {
        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collection = $this->createMock(Collection::class);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $store = $this->getMockBuilder(Store::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId'])
            ->getMock();

        $productA = $this->createConfiguredMock(Product::class, ['getSku' => 'SKU-A']);
        $productB = $this->createConfiguredMock(Product::class, ['getSku' => 'SKU-B']);

        $store->method('getId')->willReturn(1);
        $storeManager->method('getStore')->willReturn($store);
        $collectionFactory->expects($this->once())->method('create')->willReturn($collection);

        $collection->expects($this->once())->method('setStoreId')->with(1)->willReturnSelf();
        $collection->expects($this->once())->method('addStoreFilter')->with(1)->willReturnSelf();
        $collection->expects($this->once())->method('addAttributeToSelect')->willReturnSelf();
        $collection->expects($this->once())
            ->method('addAttributeToFilter')
            ->with('sku', ['in' => ['SKU-A', 'SKU-B']])
            ->willReturnSelf();
        $collection->method('getIterator')->willReturn(new \ArrayIterator([$productA, $productB]));

        $loader = new BulkSkuProductLoader($collectionFactory, $storeManager);
        $products = $loader->loadBySkus(['SKU-A', 'SKU-B', 'SKU-A', '', '  ']);

        $this->assertSame($productA, $products['SKU-A']);
        $this->assertSame($productB, $products['SKU-B']);
        $this->assertCount(2, $products);
    }

    public function testLoadBySkusSkipsCollectionWhenInputIsEmpty(): void
    {
        $collectionFactory = $this->createMock(CollectionFactory::class);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $loader = new BulkSkuProductLoader($collectionFactory, $storeManager);

        $collectionFactory->expects($this->never())->method('create');

        $this->assertSame([], $loader->loadBySkus(['', '  ']));
    }
}
