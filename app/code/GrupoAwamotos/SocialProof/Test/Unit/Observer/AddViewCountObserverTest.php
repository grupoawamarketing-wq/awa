<?php

declare(strict_types=1);

namespace GrupoAwamotos\SocialProof\Test\Unit\Observer;

use GrupoAwamotos\SocialProof\Observer\AddViewCountObserver;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \GrupoAwamotos\SocialProof\Observer\AddViewCountObserver
 */
class AddViewCountObserverTest extends TestCase
{
    private AddViewCountObserver $observer;
    private ResourceConnection&MockObject $resourceConnection;
    private CacheInterface&MockObject $cache;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->resourceConnection = $this->createMock(ResourceConnection::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->observer = new AddViewCountObserver(
            $this->resourceConnection,
            $this->cache,
            $this->logger
        );
    }

    // ====================================================================
    // execute — no product
    // ====================================================================

    public function testExecuteDoesNothingWhenNoProduct(): void
    {
        $event = $this->getMockBuilder(Event::class)
            ->disableOriginalConstructor()
            ->addMethods(['getProduct'])
            ->getMock();
        $event->method('getProduct')->willReturn(null);

        $observerObj = $this->createMock(Observer::class);
        $observerObj->method('getEvent')->willReturn($event);

        $this->cache->expects($this->never())->method('load');

        $this->observer->execute($observerObj);
    }

    // ====================================================================
    // execute — product without id
    // ====================================================================

    public function testExecuteDoesNothingWhenProductHasNoId(): void
    {
        $product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId'])
            ->getMock();
        $product->method('getId')->willReturn(null);

        $event = $this->getMockBuilder(Event::class)
            ->disableOriginalConstructor()
            ->addMethods(['getProduct'])
            ->getMock();
        $event->method('getProduct')->willReturn($product);

        $observerObj = $this->createMock(Observer::class);
        $observerObj->method('getEvent')->willReturn($event);

        $this->cache->expects($this->never())->method('load');

        $this->observer->execute($observerObj);
    }

    // ====================================================================
    // execute — cache hit
    // ====================================================================

    public function testExecuteUsesDataFromCacheWhenAvailable(): void
    {
        $product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'setData'])
            ->getMock();
        $product->method('getId')->willReturn(42);

        $cachedData = json_encode(['views_today' => 15, 'is_best_seller' => true]);
        $this->cache->method('load')
            ->with('socialproof_42')
            ->willReturn($cachedData);

        // Expect setData to be called with cached values
        $setDataCalls = [];
        $product->method('setData')->willReturnCallback(
            function (string $key, $value) use (&$setDataCalls, $product) {
                $setDataCalls[$key] = $value;
                return $product;
            }
        );

        $event = $this->getMockBuilder(Event::class)
            ->disableOriginalConstructor()
            ->addMethods(['getProduct'])
            ->getMock();
        $event->method('getProduct')->willReturn($product);

        $observerObj = $this->createMock(Observer::class);
        $observerObj->method('getEvent')->willReturn($event);

        // Should NOT query database
        $this->resourceConnection->expects($this->never())->method('getConnection');

        $this->observer->execute($observerObj);

        $this->assertSame(15, $setDataCalls['views_today']);
        $this->assertTrue($setDataCalls['is_best_seller']);
    }

    // ====================================================================
    // execute — cache miss, queries DB
    // ====================================================================

    public function testExecuteQueriesDatabaseOnCacheMiss(): void
    {
        $product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'setData'])
            ->getMock();
        $product->method('getId')->willReturn(42);

        $this->cache->method('load')->willReturn(false);

        // Mock DB adapter
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->method('select')->willReturn($select);
        $adapter->method('fetchOne')->willReturnOnConsecutiveCalls(25, 8);

        $this->resourceConnection->method('getConnection')->willReturn($adapter);
        $this->resourceConnection->method('getTableName')->willReturnArgument(0);

        $setDataCalls = [];
        $product->method('setData')->willReturnCallback(
            function (string $key, $value) use (&$setDataCalls, $product) {
                $setDataCalls[$key] = $value;
                return $product;
            }
        );

        // Expect cache save
        $this->cache->expects($this->once())->method('save');

        $event = $this->getMockBuilder(Event::class)
            ->disableOriginalConstructor()
            ->addMethods(['getProduct'])
            ->getMock();
        $event->method('getProduct')->willReturn($product);

        $observerObj = $this->createMock(Observer::class);
        $observerObj->method('getEvent')->willReturn($event);

        $this->observer->execute($observerObj);

        $this->assertSame(25, $setDataCalls['views_today']);
        // 8 qty sold in 30 days >= 5 min qty → is_best_seller = true
        $this->assertTrue($setDataCalls['is_best_seller']);
    }

    // ====================================================================
    // execute — bestseller threshold
    // ====================================================================

    public function testExecuteMarksBestSellerWhenAboveThreshold(): void
    {
        $product = $this->createProductMock(100);
        $this->setupDbMocks($product, 10, 5); // 5 qty = exactly at min threshold

        $setDataCalls = &$this->captureSetData($product);

        $this->executeObserver($product);

        $this->assertTrue($setDataCalls['is_best_seller']);
    }

    public function testExecuteDoesNotMarkBestSellerWhenBelowThreshold(): void
    {
        $product = $this->createProductMock(100);
        $this->setupDbMocks($product, 10, 4); // 4 qty < 5 min threshold

        $setDataCalls = &$this->captureSetData($product);

        $this->executeObserver($product);

        $this->assertFalse($setDataCalls['is_best_seller']);
    }

    // ====================================================================
    // execute — exception handling
    // ====================================================================

    public function testExecuteSetsDefaultsOnException(): void
    {
        $product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'setData'])
            ->getMock();
        $product->method('getId')->willReturn(42);

        $this->cache->method('load')->willReturn(false);
        $this->resourceConnection->method('getConnection')
            ->willThrowException(new \Exception('DB offline'));

        $setDataCalls = [];
        $product->method('setData')->willReturnCallback(
            function (string $key, $value) use (&$setDataCalls, $product) {
                $setDataCalls[$key] = $value;
                return $product;
            }
        );

        $this->logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('[SocialProof]'));

        $event = $this->getMockBuilder(Event::class)
            ->disableOriginalConstructor()
            ->addMethods(['getProduct'])
            ->getMock();
        $event->method('getProduct')->willReturn($product);

        $observerObj = $this->createMock(Observer::class);
        $observerObj->method('getEvent')->willReturn($event);

        $this->observer->execute($observerObj);

        $this->assertSame(0, $setDataCalls['views_today']);
        $this->assertFalse($setDataCalls['is_best_seller']);
    }

    // ====================================================================
    // Helpers
    // ====================================================================

    private function createProductMock(int $id): Product&MockObject
    {
        $product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'setData'])
            ->getMock();
        $product->method('getId')->willReturn($id);
        return $product;
    }

    private function setupDbMocks(Product&MockObject $product, int $views, int $qtySold): void
    {
        $this->cache->method('load')->willReturn(false);

        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->method('select')->willReturn($select);
        $adapter->method('fetchOne')->willReturnOnConsecutiveCalls($views, $qtySold);

        $this->resourceConnection->method('getConnection')->willReturn($adapter);
        $this->resourceConnection->method('getTableName')->willReturnArgument(0);
    }

    private function &captureSetData(Product&MockObject $product): array
    {
        $calls = [];
        $product->method('setData')->willReturnCallback(
            function (string $key, $value) use (&$calls, $product) {
                $calls[$key] = $value;
                return $product;
            }
        );
        return $calls;
    }

    private function executeObserver(Product $product): void
    {
        $event = $this->getMockBuilder(Event::class)
            ->disableOriginalConstructor()
            ->addMethods(['getProduct'])
            ->getMock();
        $event->method('getProduct')->willReturn($product);

        $observerObj = $this->createMock(Observer::class);
        $observerObj->method('getEvent')->willReturn($event);

        $this->observer->execute($observerObj);
    }
}
