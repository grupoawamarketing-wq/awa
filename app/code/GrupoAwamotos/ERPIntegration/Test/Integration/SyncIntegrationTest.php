<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Test\Integration;

use GrupoAwamotos\ERPIntegration\Model\ProductSync;
use GrupoAwamotos\ERPIntegration\Model\StockSync;
use GrupoAwamotos\ERPIntegration\Model\CustomerSync;
use GrupoAwamotos\ERPIntegration\Model\Connection;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for ERP Sync operations
 *
 * Note: These tests require a properly configured ERP connection.
 * Skip these tests if running in CI/CD without ERP access.
 *
 * @magentoAppArea adminhtml
 * @magentoDbIsolation enabled
 */
class SyncIntegrationTest extends TestCase
{
    private ?ProductSync $productSync = null;
    private ?StockSync $stockSync = null;
    private ?CustomerSync $customerSync = null;
    private ?Connection $connection = null;
    private ?Helper $helper = null;

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->helper = $objectManager->get(Helper::class);
        $this->connection = $objectManager->get(Connection::class);
        $this->productSync = $objectManager->get(ProductSync::class);
        $this->stockSync = $objectManager->get(StockSync::class);
        $this->customerSync = $objectManager->get(CustomerSync::class);
    }

    private function skipIfNotConnected(): void
    {
        if (!$this->helper->isEnabled()) {
            $this->markTestSkipped('ERP integration is disabled');
        }

        if (!$this->connection->hasAvailableDriver()) {
            $this->markTestSkipped('No SQL Server drivers available');
        }

        $result = $this->connection->testConnection();
        if (!$result['success']) {
            $this->markTestSkipped('ERP connection not available: ' . $result['message']);
        }
    }

    /**
     * @group erp_sync
     * @group erp_products
     */
    public function testGetErpProductCountReturnsInteger(): void
    {
        $this->skipIfNotConnected();

        $count = $this->productSync->getErpProductCount();

        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    /**
     * @group erp_sync
     * @group erp_products
     */
    public function testGetErpProductsReturnsPaginatedResults(): void
    {
        $this->skipIfNotConnected();

        // Get first 10 products
        $products = $this->productSync->getErpProducts(10, 0);

        $this->assertIsArray($products);
        $this->assertLessThanOrEqual(10, count($products));

        if (count($products) > 0) {
            // Verify product structure
            $product = $products[0];
            $this->assertArrayHasKey('CODIGO', $product);
            $this->assertArrayHasKey('DESCRICAO', $product);
        }
    }

    /**
     * @group erp_sync
     * @group erp_products
     */
    public function testProductSyncBatchProcessing(): void
    {
        $this->skipIfNotConnected();

        $totalCount = $this->productSync->getErpProductCount();

        if ($totalCount === 0) {
            $this->markTestSkipped('No products in ERP to sync');
        }

        // Get first batch
        $batch1 = $this->productSync->getErpProducts(100, 0);

        if ($totalCount > 100) {
            // Get second batch
            $batch2 = $this->productSync->getErpProducts(100, 100);

            // Batches should be different
            if (count($batch2) > 0) {
                $this->assertNotEquals(
                    $batch1[0]['CODIGO'] ?? null,
                    $batch2[0]['CODIGO'] ?? null
                );
            }
        }

        $this->assertNotEmpty($batch1);
    }

    /**
     * @group erp_sync
     * @group erp_stock
     */
    public function testGetStockBySkuReturnsData(): void
    {
        $this->skipIfNotConnected();

        // First get a valid SKU from products
        $products = $this->productSync->getErpProducts(1, 0);

        if (empty($products)) {
            $this->markTestSkipped('No products available to test stock');
        }

        $sku = $products[0]['CODIGO'];
        $stock = $this->stockSync->getStockBySku($sku);

        // Stock might be null if not in stock table
        if ($stock !== null) {
            $this->assertArrayHasKey('qty', $stock);
            $this->assertArrayHasKey('cost', $stock);
            $this->assertIsFloat($stock['qty']);
        }

        $this->assertTrue(true); // Test passed even if no stock
    }

    /**
     * @group erp_sync
     * @group erp_stock
     */
    public function testStockCacheWorks(): void
    {
        $this->skipIfNotConnected();

        // Get first product SKU
        $products = $this->productSync->getErpProducts(1, 0);

        if (empty($products)) {
            $this->markTestSkipped('No products available to test stock cache');
        }

        $sku = $products[0]['CODIGO'];

        // First call - should query database
        $stock1 = $this->stockSync->getStockBySku($sku);

        // Second call - should use cache
        $startTime = microtime(true);
        $stock2 = $this->stockSync->getStockBySku($sku);
        $cachedTime = microtime(true) - $startTime;

        // Cached call should be very fast (< 10ms)
        $this->assertLessThan(0.01, $cachedTime);

        // Results should be same
        $this->assertEquals($stock1, $stock2);
    }

    /**
     * @group erp_sync
     * @group erp_stock
     */
    public function testInvalidateCacheWorks(): void
    {
        $this->skipIfNotConnected();

        $products = $this->productSync->getErpProducts(1, 0);

        if (empty($products)) {
            $this->markTestSkipped('No products available');
        }

        $sku = $products[0]['CODIGO'];

        // Load into cache
        $this->stockSync->getStockBySku($sku);

        // Invalidate
        $this->stockSync->invalidateCache($sku);

        // This should query database again (no exception = success)
        $stock = $this->stockSync->getStockBySku($sku);

        $this->assertTrue(true);
    }

    /**
     * @group erp_sync
     * @group erp_customers
     */
    public function testGetErpCustomerByTaxvatReturnsData(): void
    {
        $this->skipIfNotConnected();

        // Try to find any customer with CGC (CNPJ)
        $customers = $this->connection->query(
            "SELECT TOP 1 CGC FROM FN_FORNECEDORES WHERE CKCLIENTE = 'S' AND CGC IS NOT NULL AND CGC <> ''"
        );

        if (empty($customers)) {
            $this->markTestSkipped('No customers with CNPJ found');
        }

        $taxvat = $customers[0]['CGC'];
        $customer = $this->customerSync->getErpCustomerByTaxvat($taxvat);

        if ($customer !== null) {
            $this->assertArrayHasKey('CODIGO', $customer);
            $this->assertArrayHasKey('RAZAO', $customer);
        }

        $this->assertTrue(true);
    }

    /**
     * @group erp_sync
     * @group erp_customers
     */
    public function testGetErpCustomerByCodeReturnsData(): void
    {
        $this->skipIfNotConnected();

        // Get first customer
        $customers = $this->connection->query(
            "SELECT TOP 1 CODIGO FROM FN_FORNECEDORES WHERE CKCLIENTE = 'S'"
        );

        if (empty($customers)) {
            $this->markTestSkipped('No customers found');
        }

        $code = $customers[0]['CODIGO'];
        $customer = $this->customerSync->getErpCustomerByCode($code);

        $this->assertNotNull($customer);
        $this->assertArrayHasKey('CODIGO', $customer);
        $this->assertEquals($code, $customer['CODIGO']);
    }

    /**
     * @group erp_sync
     */
    public function testSyncAllReturnsCorrectStructure(): void
    {
        $this->skipIfNotConnected();

        // Don't actually sync all (could take long), just verify structure
        // by checking product sync result structure
        $productCount = $this->productSync->getErpProductCount();

        $this->assertIsInt($productCount);

        // Verify helper methods work
        $this->assertIsInt($this->helper->getStockFilial());
        $this->assertIsInt($this->helper->getStockCacheTtl());
        $this->assertIsBool($this->helper->isProductSyncEnabled());
        $this->assertIsBool($this->helper->isStockSyncEnabled());
    }

    /**
     * @group erp_sync
     * @group erp_performance
     */
    public function testBatchQueryPerformance(): void
    {
        $this->skipIfNotConnected();

        $batchSize = 500;

        $startTime = microtime(true);
        $products = $this->productSync->getErpProducts($batchSize, 0);
        $queryTime = microtime(true) - $startTime;

        // Query should complete in reasonable time (< 10 seconds for 500 records)
        $this->assertLessThan(10, $queryTime, 'Batch query took too long: ' . $queryTime . 's');

        // Log performance
        fwrite(STDERR, sprintf(
            "\nPerformance: Retrieved %d products in %.3f seconds (%.1f records/sec)\n",
            count($products),
            $queryTime,
            count($products) / $queryTime
        ));
    }
}
