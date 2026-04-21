<?php
declare(strict_types=1);

namespace GrupoAwamotos\WhatsAppCommerce\Test\Unit\Model;

use GrupoAwamotos\WhatsAppCommerce\Model\WhatsAppAdminDashboard;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Sales\Api\OrderRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \GrupoAwamotos\WhatsAppCommerce\Model\WhatsAppAdminDashboard
 */
class WhatsAppAdminDashboardTest extends TestCase
{
    private ResourceConnection&MockObject $resource;
    private ProductRepositoryInterface&MockObject $productRepository;
    private StockRegistryInterface&MockObject $stockRegistry;
    private OrderRepositoryInterface&MockObject $orderRepository;
    private SearchCriteriaBuilder&MockObject $searchCriteriaBuilder;
    private LoggerInterface&MockObject $logger;
    private AdapterInterface&MockObject $connection;

    protected function setUp(): void
    {
        $this->resource = $this->createMock(ResourceConnection::class);
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->stockRegistry = $this->createMock(StockRegistryInterface::class);
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->connection = $this->createMock(AdapterInterface::class);

        $this->resource->method('getConnection')->willReturn($this->connection);
        $this->resource->method('getTableName')->willReturnCallback(
            static fn (string $table): string => $table
        );
    }

    public function testSalesTodayUsesSingleDbQueryAndRequestCache(): void
    {
        $select = $this->createSelectMock(['from', 'where']);
        $this->connection->expects($this->once())->method('select')->willReturn($select);
        $this->connection->expects($this->once())
            ->method('fetchRow')
            ->with($select)
            ->willReturn([
                'today_orders' => 5,
                'today_revenue' => 550.40,
                'today_avg_ticket' => 110.08,
                'today_items' => 9,
                'yesterday_orders' => 3,
                'yesterday_revenue' => 299.90,
            ]);

        $dashboard = $this->newDashboard();

        $first = $dashboard->salesToday();
        $second = $dashboard->salesToday();

        $this->assertSame($first, $second);
        $this->assertSame(5, $first['orders']);
        $this->assertSame(550.4, $first['revenue']);
        $this->assertSame(3, $first['yesterday_orders']);
    }

    public function testNewCustomersUsesConsolidatedSingleQuery(): void
    {
        $select = $this->createSelectMock(['from', 'where']);
        $this->connection->expects($this->once())->method('select')->willReturn($select);
        $this->connection->expects($this->once())
            ->method('fetchRow')
            ->with($select)
            ->willReturn([
                'last_24h' => 2,
                'last_7d' => 8,
                'last_30d' => 30,
            ]);

        $dashboard = $this->newDashboard();
        $result = $dashboard->newCustomers();

        $this->assertSame(2, $result['last_24h']);
        $this->assertSame(8, $result['last_7d']);
        $this->assertSame(30, $result['last_30d']);
        $this->assertStringContainsString('2 (24h)', $result['message']);
    }

    public function testStockCheckCachesProductAndStockLookupsWithinRequest(): void
    {
        $product = $this->createMock(ProductInterface::class);
        $product->method('getName')->willReturn('Produto Teste');
        $product->method('getPrice')->willReturn(99.9);

        $stockItem = $this->createMock(StockItemInterface::class);
        $stockItem->method('getQty')->willReturn(7.0);
        $stockItem->method('getIsInStock')->willReturn(true);
        $stockItem->method('getMinQty')->willReturn(1.0);

        $this->productRepository->expects($this->once())->method('get')->with('SKU-1')->willReturn($product);
        $this->stockRegistry->expects($this->once())->method('getStockItemBySku')->with('SKU-1')->willReturn($stockItem);

        $dashboard = $this->newDashboard();

        $first = $dashboard->stockCheck('SKU-1');
        $second = $dashboard->stockCheck('SKU-1');

        $this->assertSame($first, $second);
        $this->assertSame(7, $first['qty']);
        $this->assertTrue($first['is_in_stock']);
    }

    /**
     * @param list<string> $fluentMethods
     */
    private function createSelectMock(array $fluentMethods): Select&MockObject
    {
        $select = $this->getMockBuilder(Select::class)
            ->disableOriginalConstructor()
            ->onlyMethods($fluentMethods)
            ->getMock();

        foreach ($fluentMethods as $method) {
            $select->method($method)->willReturnSelf();
        }

        return $select;
    }

    private function newDashboard(): WhatsAppAdminDashboard
    {
        return new WhatsAppAdminDashboard(
            $this->resource,
            $this->productRepository,
            $this->stockRegistry,
            $this->orderRepository,
            $this->searchCriteriaBuilder,
            $this->logger
        );
    }
}
