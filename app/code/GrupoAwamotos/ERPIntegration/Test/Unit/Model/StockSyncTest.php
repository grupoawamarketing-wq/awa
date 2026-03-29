<?php
declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Test\Unit\Model;

use GrupoAwamotos\ERPIntegration\Model\StockSync;
use GrupoAwamotos\ERPIntegration\Api\ConnectionInterface;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use GrupoAwamotos\ERPIntegration\Model\ResourceModel\SyncLog as SyncLogResource;
use GrupoAwamotos\ERPIntegration\Model\Validator\StockValidator;
use GrupoAwamotos\ERPIntegration\Model\Validator\ValidationResult;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class StockSyncTest extends TestCase
{
    private StockSync $stockSync;
    private ConnectionInterface|MockObject $connection;
    private Helper|MockObject $helper;
    private ProductRepositoryInterface|MockObject $productRepository;
    private StockRegistryInterface|MockObject $stockRegistry;
    private SyncLogResource|MockObject $syncLogResource;
    private StockValidator|MockObject $stockValidator;
    private CacheInterface|MockObject $cache;
    private LoggerInterface|MockObject $logger;
    private ResourceConnection|MockObject $resourceConnection;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(ConnectionInterface::class);
        $this->helper = $this->createMock(Helper::class);
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->stockRegistry = $this->createMock(StockRegistryInterface::class);
        $this->syncLogResource = $this->createMock(SyncLogResource::class);
        $this->stockValidator = $this->createMock(StockValidator::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->resourceConnection = $this->createMock(ResourceConnection::class);

        // Configure ResourceConnection mock so loadExistingSkus() works
        $dbAdapter = $this->createMock(\Magento\Framework\DB\Adapter\AdapterInterface::class);
        // Return common test SKUs as existing in catalog_product_entity
        $dbAdapter->method('fetchCol')->willReturn(['SKU-001', 'SKU-002']);
        $dbAdapter->method('fetchAll')->willReturn([]);
        $dbAdapter->method('fetchOne')->willReturn(null);
        $this->resourceConnection->method('getConnection')->willReturn($dbAdapter);
        $this->resourceConnection->method('getTableName')
            ->willReturnArgument(0);

        // Default helper behavior: single branch
        $this->helper->method('getStockFilial')->willReturn(1);
        $this->helper->method('getStockFiliais')->willReturn([1]);
        $this->helper->method('getStockCacheTtl')->willReturn(300);
        $this->helper->method('getNegativeCacheTtl')->willReturn(60);
        $this->helper->method('isMultiBranchEnabled')->willReturn(false);
        $this->helper->method('getStockAggregationMode')->willReturn('sum');
        $this->stockValidator->method('validate')
            ->willReturnCallback(function (array $row, ?string $sku = null): ValidationResult {
                $quantity = (float) ($row['QTDE'] ?? $row['QTDE_TOTAL'] ?? 0);
                if ($quantity < 0) {
                    $quantity = 0.0;
                }

                $resolvedSku = $sku ?? trim((string) ($row['MATERIAL'] ?? ''));

                return ValidationResult::success([
                    'sku' => $resolvedSku,
                    'quantity' => $quantity,
                    'anomaly_detected' => false,
                ]);
            });

        $this->stockSync = new StockSync(
            $this->connection,
            $this->helper,
            $this->productRepository,
            $this->stockRegistry,
            $this->syncLogResource,
            $this->stockValidator,
            $this->cache,
            $this->logger,
            $this->resourceConnection
        );
    }

    // ========== getStockBySku Tests ==========

    public function testGetStockBySkuReturnsCachedValue(): void
    {
        $cachedData = ['qty' => 10.0, 'cost' => 25.50, 'date' => '2024-01-15'];

        $this->cache->method('load')
            ->willReturnOnConsecutiveCalls(false, json_encode($cachedData));

        // Should not query database
        $this->connection->expects($this->never())->method('fetchOne');

        $result = $this->stockSync->getStockBySku('TEST-SKU');

        $this->assertEquals($cachedData, $result);
    }

    public function testGetStockBySkuQueriesDatabaseOnCacheMiss(): void
    {
        // Create fresh mocks for this test
        $connection = $this->createMock(ConnectionInterface::class);
        $helper = $this->createMock(Helper::class);
        $cache = $this->createMock(CacheInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $stockValidator = $this->createMock(StockValidator::class);

        // Setup helper - use method chaining to ensure all methods are configured
        $helper->method('getStockFiliais')->willReturn([1]);
        $helper->method('getStockCacheTtl')->willReturn(300);
        $helper->method('getNegativeCacheTtl')->willReturn(60);
        $helper->method('isMultiBranchEnabled')->willReturn(false);
        $stockValidator->method('validate')->willReturn(ValidationResult::success(['quantity' => 0]));

        // Set up cache to return miss (false means cache miss)
        $cache->method('load')->willReturn(false);

        // Setup database result
        $dbResult = ['QTDE' => 15.0, 'VLRMEDIA' => 30.00, 'DATA' => '2024-01-20'];
        $connection->method('fetchOne')->willReturn($dbResult);

        // Expect no errors
        $logger->expects($this->never())->method('error');

        $stockSync = new StockSync(
            $connection,
            $helper,
            $this->productRepository,
            $this->stockRegistry,
            $this->syncLogResource,
            $stockValidator,
            $cache,
            $logger,
            $this->resourceConnection
        );

        $result = $stockSync->getStockBySku('TEST-SKU');

        // Verify result
        $this->assertIsArray($result, 'Result should be an array, not null');
        $this->assertArrayHasKey('qty', $result);
        $this->assertEquals(15.0, $result['qty']);
        $this->assertEquals(30.00, $result['cost']);
        $this->assertEquals(1, $result['filial']);
    }

    public function testGetStockBySkuReturnsNullWhenNotFound(): void
    {
        $this->cache->method('load')->willReturn(false);
        $this->connection->method('fetchOne')->willReturn(null);

        $result = $this->stockSync->getStockBySku('NONEXISTENT-SKU');

        $this->assertNull($result);
    }

    // ========== Multi-Branch Stock Tests ==========

    public function testGetStockBySkuMultiBranchSumsQuantities(): void
    {
        // Setup multi-branch
        $helper = $this->createMock(Helper::class);
        $helper->method('getStockFilial')->willReturn(1);
        $helper->method('getStockFiliais')->willReturn([1, 2, 3]);
        $helper->method('getStockCacheTtl')->willReturn(300);
        $helper->method('getNegativeCacheTtl')->willReturn(60);
        $helper->method('isMultiBranchEnabled')->willReturn(true);
        $helper->method('getStockAggregationMode')->willReturn('sum');

        $stockSync = new StockSync(
            $this->connection,
            $helper,
            $this->productRepository,
            $this->stockRegistry,
            $this->syncLogResource,
            $this->stockValidator,
            $this->cache,
            $this->logger,
            $this->resourceConnection
        );

        $this->cache->method('load')->willReturn(false);

        // Mock multi-branch query results
        $this->connection->method('query')
            ->willReturn([
                ['FILIAL' => 1, 'QTDE' => 10, 'VLRMEDIA' => 100, 'DATA' => '2024-01-15'],
                ['FILIAL' => 2, 'QTDE' => 20, 'VLRMEDIA' => 100, 'DATA' => '2024-01-15'],
                ['FILIAL' => 3, 'QTDE' => 15, 'VLRMEDIA' => 100, 'DATA' => '2024-01-15'],
            ]);

        $result = $stockSync->getStockBySku('TEST-SKU');

        // Should sum quantities: 10 + 20 + 15 = 45
        $this->assertEquals(45.0, $result['qty']);
        $this->assertEquals('multi', $result['filial']);
        $this->assertArrayHasKey('branches', $result);
        $this->assertEquals([1 => 10.0, 2 => 20.0, 3 => 15.0], $result['branches']);
    }

    public function testGetStockBySkuMultiBranchUsesMinAggregation(): void
    {
        $helper = $this->createMock(Helper::class);
        $helper->method('getStockFilial')->willReturn(1);
        $helper->method('getStockFiliais')->willReturn([1, 2]);
        $helper->method('getStockCacheTtl')->willReturn(300);
        $helper->method('getNegativeCacheTtl')->willReturn(60);
        $helper->method('isMultiBranchEnabled')->willReturn(true);
        $helper->method('getStockAggregationMode')->willReturn('min');

        $stockSync = new StockSync(
            $this->connection,
            $helper,
            $this->productRepository,
            $this->stockRegistry,
            $this->syncLogResource,
            $this->stockValidator,
            $this->cache,
            $this->logger,
            $this->resourceConnection
        );

        $this->cache->method('load')->willReturn(false);

        $this->connection->method('query')
            ->willReturn([
                ['FILIAL' => 1, 'QTDE' => 10, 'VLRMEDIA' => 100, 'DATA' => '2024-01-15'],
                ['FILIAL' => 2, 'QTDE' => 5, 'VLRMEDIA' => 100, 'DATA' => '2024-01-15'],
            ]);

        $result = $stockSync->getStockBySku('TEST-SKU');

        // Should use min: min(10, 5) = 5
        $this->assertEquals(5.0, $result['qty']);
        $this->assertEquals('min', $result['aggregation_mode']);
    }

    public function testGetStockBySkuMultiBranchUsesMaxAggregation(): void
    {
        $helper = $this->createMock(Helper::class);
        $helper->method('getStockFilial')->willReturn(1);
        $helper->method('getStockFiliais')->willReturn([1, 2]);
        $helper->method('getStockCacheTtl')->willReturn(300);
        $helper->method('getNegativeCacheTtl')->willReturn(60);
        $helper->method('isMultiBranchEnabled')->willReturn(true);
        $helper->method('getStockAggregationMode')->willReturn('max');

        $stockSync = new StockSync(
            $this->connection,
            $helper,
            $this->productRepository,
            $this->stockRegistry,
            $this->syncLogResource,
            $this->stockValidator,
            $this->cache,
            $this->logger,
            $this->resourceConnection
        );

        $this->cache->method('load')->willReturn(false);

        $this->connection->method('query')
            ->willReturn([
                ['FILIAL' => 1, 'QTDE' => 10, 'VLRMEDIA' => 100, 'DATA' => '2024-01-15'],
                ['FILIAL' => 2, 'QTDE' => 25, 'VLRMEDIA' => 100, 'DATA' => '2024-01-15'],
            ]);

        $result = $stockSync->getStockBySku('TEST-SKU');

        // Should use max: max(10, 25) = 25
        $this->assertEquals(25.0, $result['qty']);
        $this->assertEquals('max', $result['aggregation_mode']);
    }

    public function testGetStockBySkuMultiBranchUsesAvgAggregation(): void
    {
        $helper = $this->createMock(Helper::class);
        $helper->method('getStockFilial')->willReturn(1);
        $helper->method('getStockFiliais')->willReturn([1, 2]);
        $helper->method('getStockCacheTtl')->willReturn(300);
        $helper->method('getNegativeCacheTtl')->willReturn(60);
        $helper->method('isMultiBranchEnabled')->willReturn(true);
        $helper->method('getStockAggregationMode')->willReturn('avg');

        $stockSync = new StockSync(
            $this->connection,
            $helper,
            $this->productRepository,
            $this->stockRegistry,
            $this->syncLogResource,
            $this->stockValidator,
            $this->cache,
            $this->logger,
            $this->resourceConnection
        );

        $this->cache->method('load')->willReturn(false);

        $this->connection->method('query')
            ->willReturn([
                ['FILIAL' => 1, 'QTDE' => 10, 'VLRMEDIA' => 100, 'DATA' => '2024-01-15'],
                ['FILIAL' => 2, 'QTDE' => 30, 'VLRMEDIA' => 100, 'DATA' => '2024-01-15'],
            ]);

        $result = $stockSync->getStockBySku('TEST-SKU');

        // Should use avg: (10 + 30) / 2 = 20
        $this->assertEquals(20.0, $result['qty']);
        $this->assertEquals('avg', $result['aggregation_mode']);
    }

    // ========== syncAll Tests ==========

    public function testSyncAllUpdatesStock(): void
    {
        $erpData = [
            ['MATERIAL' => 'SKU-001', 'QTDE' => 10.0, 'VLRMEDIA' => 25.00],
            ['MATERIAL' => 'SKU-002', 'QTDE' => 5.0, 'VLRMEDIA' => 15.00],
        ];

        $this->connection->method('query')->willReturn($erpData);

        // Both products exist in Magento
        $this->productRepository->method('get')
            ->willReturn($this->createMock(\Magento\Catalog\Api\Data\ProductInterface::class));

        // Setup stock items with different current quantities
        $stockItem1 = $this->createMock(StockItemInterface::class);
        $stockItem1->method('getQty')->willReturn(5.0); // Different from ERP
        $stockItem1->expects($this->once())->method('setQty')->with(10.0);
        $stockItem1->expects($this->once())->method('setIsInStock')->with(true);

        $stockItem2 = $this->createMock(StockItemInterface::class);
        $stockItem2->method('getQty')->willReturn(3.0); // Different from ERP
        $stockItem2->expects($this->once())->method('setQty')->with(5.0);
        $stockItem2->expects($this->once())->method('setIsInStock')->with(true);

        $this->stockRegistry->method('getStockItemBySku')
            ->willReturnMap([
                ['SKU-001', null, $stockItem1],
                ['SKU-002', null, $stockItem2],
            ]);

        $result = $this->stockSync->syncAll();

        $this->assertEquals(2, $result['updated']);
        $this->assertEquals(0, $result['errors']);
        $this->assertEquals(0, $result['not_found']);
    }

    public function testSyncAllSkipsUnchangedStock(): void
    {
        $erpData = [
            ['MATERIAL' => 'SKU-001', 'QTDE' => 10.0, 'VLRMEDIA' => 25.00],
        ];

        $this->connection->method('query')->willReturn($erpData);

        $this->productRepository->method('get')
            ->willReturn($this->createMock(\Magento\Catalog\Api\Data\ProductInterface::class));

        // Stock item already has same quantity
        $stockItem = $this->createMock(StockItemInterface::class);
        $stockItem->method('getQty')->willReturn(10.0); // Same as ERP

        // Should NOT update since quantity is same
        $stockItem->expects($this->never())->method('setQty');

        $this->stockRegistry->method('getStockItemBySku')
            ->willReturn($stockItem);

        $result = $this->stockSync->syncAll();

        $this->assertEquals(0, $result['updated']);
        $this->assertEquals(1, $result['unchanged']);
    }

    public function testSyncAllCountsNotFoundProductsCorrectly(): void
    {
        // Need a separate StockSync instance where SKU-002 is NOT in the SKU cache
        $resourceConn = $this->createMock(ResourceConnection::class);
        $dbAdapter = $this->createMock(\Magento\Framework\DB\Adapter\AdapterInterface::class);
        $dbAdapter->method('fetchCol')->willReturn(['SKU-001']); // Only SKU-001 exists
        $resourceConn->method('getConnection')->willReturn($dbAdapter);
        $resourceConn->method('getTableName')->willReturnArgument(0);

        $stockSync = new StockSync(
            $this->connection,
            $this->helper,
            $this->productRepository,
            $this->stockRegistry,
            $this->syncLogResource,
            $this->stockValidator,
            $this->cache,
            $this->logger,
            $resourceConn
        );

        $erpData = [
            ['MATERIAL' => 'SKU-001', 'QTDE' => 10.0, 'VLRMEDIA' => 25.00],
            ['MATERIAL' => 'SKU-002', 'QTDE' => 5.0, 'VLRMEDIA' => 15.00],
        ];

        $this->connection->method('query')->willReturn($erpData);

        // First product exists, second doesn't
        $this->productRepository->method('get')
            ->willReturnCallback(function ($sku) {
                if ($sku === 'SKU-002') {
                    throw new NoSuchEntityException(__('Product not found'));
                }
                return $this->createMock(\Magento\Catalog\Api\Data\ProductInterface::class);
            });

        $stockItem = $this->createMock(StockItemInterface::class);
        $stockItem->method('getQty')->willReturn(5.0);
        $this->stockRegistry->method('getStockItemBySku')->willReturn($stockItem);

        $result = $stockSync->syncAll();
        $this->assertEquals(1, $result['not_found']);
        $this->assertEquals(0, $result['errors']);
        $this->assertEquals(1, $result['updated']);
    }

    public function testSyncAllHandlesNegativeStock(): void
    {
        $erpData = [
            ['MATERIAL' => 'SKU-001', 'QTDE' => -5.0, 'VLRMEDIA' => 25.00],
        ];

        $this->connection->method('query')->willReturn($erpData);

        $this->productRepository->method('get')
            ->willReturn($this->createMock(\Magento\Catalog\Api\Data\ProductInterface::class));

        $stockItem = $this->createMock(StockItemInterface::class);
        $stockItem->method('getQty')->willReturn(10.0);

        // Negative stock should be normalized to 0
        $stockItem->expects($this->once())->method('setQty')->with(0.0);
        $stockItem->expects($this->once())->method('setIsInStock')->with(false);

        $this->stockRegistry->method('getStockItemBySku')->willReturn($stockItem);

        $result = $this->stockSync->syncAll();

        $this->assertEquals(1, $result['updated']);
    }

    public function testSyncAllResolvesErpInternalCodeToMagentoSku(): void
    {
        $resourceConn = $this->createMock(ResourceConnection::class);
        $dbAdapter = $this->createMock(\Magento\Framework\DB\Adapter\AdapterInterface::class);
        $dbAdapter->method('fetchCol')->willReturn(['SKU-001']);
        $dbAdapter->method('fetchOne')->willReturn(206);
        $dbAdapter->method('fetchAll')->willReturn([
            ['sku' => 'SKU-001', 'erp_internal_code' => 'INT-001'],
        ]);
        $resourceConn->method('getConnection')->willReturn($dbAdapter);
        $resourceConn->method('getTableName')->willReturnArgument(0);

        $stockSync = new StockSync(
            $this->connection,
            $this->helper,
            $this->productRepository,
            $this->stockRegistry,
            $this->syncLogResource,
            $this->stockValidator,
            $this->cache,
            $this->logger,
            $resourceConn
        );

        $this->connection->method('query')->willReturn([
            ['MATERIAL' => 'INT-001', 'QTDE' => 10.0, 'VLRMEDIA' => 25.00],
        ]);

        $stockItem = $this->createMock(StockItemInterface::class);
        $stockItem->method('getQty')->willReturn(5.0);
        $stockItem->expects($this->once())->method('setQty')->with(10.0);
        $stockItem->expects($this->once())->method('setIsInStock')->with(true);

        $this->stockRegistry->expects($this->once())
            ->method('getStockItemBySku')
            ->with('SKU-001')
            ->willReturn($stockItem);

        $result = $stockSync->syncAll();

        $this->assertEquals(1, $result['updated']);
        $this->assertEquals(0, $result['not_found']);
    }

    public function testSyncAllSkipsEmptySku(): void
    {
        $erpData = [
            ['MATERIAL' => '', 'QTDE' => 10.0, 'VLRMEDIA' => 25.00],
            ['MATERIAL' => '   ', 'QTDE' => 5.0, 'VLRMEDIA' => 15.00],
        ];

        $this->connection->method('query')->willReturn($erpData);

        // Should not try to get products with empty SKU
        $this->productRepository->expects($this->never())->method('get');

        $result = $this->stockSync->syncAll();

        $this->assertEquals(2, $result['skipped']);
        $this->assertEquals(0, $result['updated']);
    }

    public function testSyncAllIncludesExecutionTime(): void
    {
        $this->connection->method('query')->willReturn([]);

        $result = $this->stockSync->syncAll();

        $this->assertArrayHasKey('execution_time', $result);
        $this->assertIsFloat($result['execution_time']);
        $this->assertGreaterThanOrEqual(0, $result['execution_time']);
    }

    // ========== Cache Tests ==========

    public function testInvalidateCacheRemovesSpecificKey(): void
    {
        $this->cache->expects($this->atLeastOnce())
            ->method('remove')
            ->with($this->callback(function ($key) {
                return strpos($key, 'erp_stock_') === 0;
            }));

        $this->stockSync->invalidateCache('TEST-SKU');
    }

    public function testInvalidateAllCacheCleansTag(): void
    {
        $this->cache->expects($this->once())
            ->method('clean')
            ->with(['erp_stock']);

        $this->stockSync->invalidateAllCache();
    }

    // ========== syncBySku Tests ==========

    public function testSyncBySkuReturnsTrue(): void
    {
        $this->cache->method('load')->willReturn(false);
        $this->connection->method('fetchOne')
            ->willReturn(['QTDE' => 10.0, 'VLRMEDIA' => 25.00, 'DATA' => '2024-01-20']);

        $this->productRepository->method('get')
            ->willReturn($this->createMock(\Magento\Catalog\Api\Data\ProductInterface::class));

        $stockItem = $this->createMock(StockItemInterface::class);
        $this->stockRegistry->method('getStockItemBySku')->willReturn($stockItem);

        $result = $this->stockSync->syncBySku('SKU-001');

        $this->assertTrue($result);
    }

    public function testSyncBySkuReturnsFalseWhenNoData(): void
    {
        $this->cache->method('load')->willReturn(false);
        $this->connection->method('fetchOne')->willReturn(null);

        $result = $this->stockSync->syncBySku('NONEXISTENT-SKU');

        $this->assertFalse($result);
    }

    public function testSyncBySkuReturnsFalseWhenProductNotInMagento(): void
    {
        $this->cache->method('load')->willReturn(false);
        $this->connection->method('fetchOne')
            ->willReturn(['QTDE' => 10.0, 'VLRMEDIA' => 25.00, 'DATA' => '2024-01-20']);

        $this->productRepository->method('get')
            ->willThrowException(new NoSuchEntityException(__('Product not found')));

        $result = $this->stockSync->syncBySku('ERP-ONLY-SKU');

        $this->assertFalse($result);
    }

    public function testSyncBySkuResolvesErpInternalCodeToMagentoSku(): void
    {
        $resourceConn = $this->createMock(ResourceConnection::class);
        $dbAdapter = $this->createMock(\Magento\Framework\DB\Adapter\AdapterInterface::class);
        $dbAdapter->method('fetchCol')->willReturn(['SKU-001']);
        $dbAdapter->method('fetchOne')
            ->willReturnCallback(function (string $sql) {
                if (strpos($sql, 'SELECT ea.attribute_id') !== false) {
                    return 206;
                }

                return ['QTDE' => 10.0, 'VLRMEDIA' => 25.00, 'DATA' => '2024-01-20'];
            });
        $dbAdapter->method('fetchAll')->willReturn([
            ['sku' => 'SKU-001', 'erp_internal_code' => 'INT-001'],
        ]);
        $resourceConn->method('getConnection')->willReturn($dbAdapter);
        $resourceConn->method('getTableName')->willReturnArgument(0);

        $stockSync = new StockSync(
            $this->connection,
            $this->helper,
            $this->productRepository,
            $this->stockRegistry,
            $this->syncLogResource,
            $this->stockValidator,
            $this->cache,
            $this->logger,
            $resourceConn
        );

        $this->cache->method('load')->willReturn(false);
        $this->connection->method('fetchOne')
            ->willReturn(['QTDE' => 10.0, 'VLRMEDIA' => 25.00, 'DATA' => '2024-01-20']);

        $this->productRepository->expects($this->once())
            ->method('get')
            ->with('SKU-001')
            ->willReturn($this->createMock(\Magento\Catalog\Api\Data\ProductInterface::class));

        $stockItem = $this->createMock(StockItemInterface::class);
        $this->stockRegistry->expects($this->once())
            ->method('getStockItemBySku')
            ->with('SKU-001')
            ->willReturn($stockItem);
        $this->stockRegistry->expects($this->once())
            ->method('updateStockItemBySku')
            ->with('SKU-001', $stockItem);

        $result = $stockSync->syncBySku('INT-001');

        $this->assertTrue($result);
    }

    // ========== getStockBreakdownBySku Tests ==========

    public function testGetStockBreakdownBySkuSingleBranch(): void
    {
        $this->cache->method('load')->willReturn(false);
        $this->connection->method('fetchOne')
            ->willReturn(['QTDE' => 15.0, 'VLRMEDIA' => 30.00, 'DATA' => '2024-01-20']);

        $result = $this->stockSync->getStockBreakdownBySku('TEST-SKU');

        $this->assertArrayHasKey(1, $result);
        $this->assertEquals(15.0, $result[1]);
    }

    public function testGetStockBreakdownBySkuMultiBranch(): void
    {
        $helper = $this->createMock(Helper::class);
        $helper->method('getStockFilial')->willReturn(1);
        $helper->method('getStockFiliais')->willReturn([1, 2, 3]);
        $helper->method('getStockCacheTtl')->willReturn(300);
        $helper->method('getNegativeCacheTtl')->willReturn(60);
        $helper->method('isMultiBranchEnabled')->willReturn(true);
        $helper->method('getStockAggregationMode')->willReturn('sum');

        $stockSync = new StockSync(
            $this->connection,
            $helper,
            $this->productRepository,
            $this->stockRegistry,
            $this->syncLogResource,
            $this->stockValidator,
            $this->cache,
            $this->logger,
            $this->resourceConnection
        );

        $this->cache->method('load')->willReturn(false);

        $this->connection->method('query')
            ->willReturn([
                ['FILIAL' => 1, 'QTDE' => 10, 'VLRMEDIA' => 100, 'DATA' => '2024-01-15'],
                ['FILIAL' => 2, 'QTDE' => 20, 'VLRMEDIA' => 100, 'DATA' => '2024-01-15'],
                ['FILIAL' => 3, 'QTDE' => 5, 'VLRMEDIA' => 100, 'DATA' => '2024-01-15'],
            ]);

        $result = $stockSync->getStockBreakdownBySku('TEST-SKU');

        $this->assertEquals([1 => 10.0, 2 => 20.0, 3 => 5.0], $result);
    }

    public function testGetStockBreakdownBySkuReturnsEmptyArrayOnNotFound(): void
    {
        $this->cache->method('load')->willReturn(false);
        $this->connection->method('fetchOne')->willReturn(null);

        $result = $this->stockSync->getStockBreakdownBySku('NONEXISTENT-SKU');

        $this->assertEquals([], $result);
    }

    // ========== getAvailableBranches Tests ==========

    public function testGetAvailableBranchesReturnsData(): void
    {
        $expectedBranches = [
            ['CODIGO' => 1, 'FANTASIA' => 'Matriz', 'CIDADE' => 'São Paulo'],
            ['CODIGO' => 2, 'FANTASIA' => 'Filial Campinas', 'CIDADE' => 'Campinas'],
        ];

        $this->connection->method('query')
            ->willReturn($expectedBranches);

        $result = $this->stockSync->getAvailableBranches();

        $this->assertEquals($expectedBranches, $result);
    }

    public function testGetAvailableBranchesReturnsEmptyOnError(): void
    {
        $this->connection->method('query')
            ->willThrowException(new \Exception('Database error'));

        $result = $this->stockSync->getAvailableBranches();

        $this->assertEquals([], $result);
    }
}
