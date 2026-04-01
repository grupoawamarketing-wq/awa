<?php

declare(strict_types=1);

namespace GrupoAwamotos\SalesIntelligence\Test\Unit\Model;

use GrupoAwamotos\ERPIntegration\Api\ConnectionInterface;
use GrupoAwamotos\ERPIntegration\Model\StockSync;
use GrupoAwamotos\SalesIntelligence\Model\DemandForecast;
use Magento\Framework\App\CacheInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \GrupoAwamotos\SalesIntelligence\Model\DemandForecast
 */
class DemandForecastTest extends TestCase
{
    private DemandForecast $forecast;
    private ConnectionInterface&MockObject $connection;
    private StockSync&MockObject $stockSync;
    private CacheInterface&MockObject $cache;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(ConnectionInterface::class);
        $this->stockSync = $this->createMock(StockSync::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->forecast = new DemandForecast(
            $this->connection,
            $this->stockSync,
            $this->cache,
            $this->logger
        );
    }

    // ====================================================================
    // getProductForecast — cache hit
    // ====================================================================

    public function testGetProductForecastReturnsCachedData(): void
    {
        $cachedData = [['sku' => 'ABC', 'forecast_30d_revenue' => 5000]];
        $this->cache->method('load')->willReturn(json_encode($cachedData));

        $result = $this->forecast->getProductForecast();

        $this->assertCount(1, $result);
        $this->assertSame('ABC', $result[0]['sku']);
    }

    // ====================================================================
    // getProductForecast — empty sales data
    // ====================================================================

    public function testGetProductForecastReturnsEmptyWhenNoSalesData(): void
    {
        $this->cache->method('load')->willReturn(false);
        $this->connection->method('query')->willReturn([]);

        $result = $this->forecast->getProductForecast();
        $this->assertSame([], $result);
    }

    // ====================================================================
    // getProductForecast — builds forecast from sales data
    // ====================================================================

    public function testGetProductForecastBuildsForecasts(): void
    {
        $this->cache->method('load')->willReturn(false);

        // Simulate 3 months of sales data for 1 product
        $this->connection->method('query')->willReturn([
            ['sku' => 'BAG160', 'name' => 'Bagageiro CG 160', 'category' => 'Bagageiros',
             'total_qty' => 10, 'total_revenue' => 1000, 'sale_month' => 1, 'sale_year' => 2025],
            ['sku' => 'BAG160', 'name' => 'Bagageiro CG 160', 'category' => 'Bagageiros',
             'total_qty' => 15, 'total_revenue' => 1500, 'sale_month' => 2, 'sale_year' => 2025],
            ['sku' => 'BAG160', 'name' => 'Bagageiro CG 160', 'category' => 'Bagageiros',
             'total_qty' => 20, 'total_revenue' => 2000, 'sale_month' => 3, 'sale_year' => 2025],
        ]);

        // Stock enrichment
        $this->stockSync->method('getStockBySku')->willReturn(['qty' => 50]);

        $this->cache->expects($this->once())->method('save');

        $result = $this->forecast->getProductForecast(12, 30);

        $this->assertCount(1, $result);
        $product = $result[0];
        $this->assertSame('BAG160', $product['sku']);
        $this->assertSame('Bagageiro CG 160', $product['name']);
        $this->assertSame('Bagageiros', $product['category']);
        $this->assertArrayHasKey('forecast_30d_qty', $product);
        $this->assertArrayHasKey('forecast_30d_revenue', $product);
        $this->assertArrayHasKey('trend', $product);
        $this->assertArrayHasKey('seasonal_index', $product);
        $this->assertGreaterThan(0, $product['forecast_30d_qty']);
        $this->assertGreaterThan(0, $product['forecast_30d_revenue']);
    }

    // ====================================================================
    // getProductForecast — multiple products sorted by revenue
    // ====================================================================

    public function testGetProductForecastSortsByRevenueDescending(): void
    {
        $this->cache->method('load')->willReturn(false);

        $this->connection->method('query')->willReturn([
            ['sku' => 'LOW', 'name' => 'Low Seller', 'category' => 'Cat A',
             'total_qty' => 5, 'total_revenue' => 500, 'sale_month' => 1, 'sale_year' => 2025],
            ['sku' => 'HIGH', 'name' => 'High Seller', 'category' => 'Cat B',
             'total_qty' => 100, 'total_revenue' => 10000, 'sale_month' => 1, 'sale_year' => 2025],
        ]);

        $this->stockSync->method('getStockBySku')->willReturn(['qty' => 30]);

        $result = $this->forecast->getProductForecast();

        $this->assertSame('HIGH', $result[0]['sku']);
        $this->assertSame('LOW', $result[1]['sku']);
    }

    // ====================================================================
    // getProductForecast — trend detection (up/down/stable)
    // ====================================================================

    public function testGetProductForecastDetectsUpTrend(): void
    {
        $this->cache->method('load')->willReturn(false);

        // 6 months with clear upward trend
        $months = [];
        for ($m = 1; $m <= 6; $m++) {
            $months[] = [
                'sku' => 'TREND', 'name' => 'Trending Product', 'category' => 'Cat',
                'total_qty' => $m * 10, 'total_revenue' => $m * 1000,
                'sale_month' => $m, 'sale_year' => 2025,
            ];
        }
        $this->connection->method('query')->willReturn($months);
        $this->stockSync->method('getStockBySku')->willReturn(['qty' => 100]);

        $result = $this->forecast->getProductForecast();

        $this->assertSame('up', $result[0]['trend']);
        $this->assertGreaterThan(10, $result[0]['trend_pct']);
    }

    public function testGetProductForecastDetectsDownTrend(): void
    {
        $this->cache->method('load')->willReturn(false);

        // 6 months with clear downward trend
        $months = [];
        for ($m = 1; $m <= 6; $m++) {
            $months[] = [
                'sku' => 'DECL', 'name' => 'Declining Product', 'category' => 'Cat',
                'total_qty' => (7 - $m) * 10, 'total_revenue' => (7 - $m) * 1000,
                'sale_month' => $m, 'sale_year' => 2025,
            ];
        }
        $this->connection->method('query')->willReturn($months);
        $this->stockSync->method('getStockBySku')->willReturn(['qty' => 100]);

        $result = $this->forecast->getProductForecast();

        $this->assertSame('down', $result[0]['trend']);
        $this->assertLessThan(-10, $result[0]['trend_pct']);
    }

    // ====================================================================
    // getProductForecast — stock enrichment
    // ====================================================================

    public function testGetProductForecastEnrichesWithStockData(): void
    {
        $this->cache->method('load')->willReturn(false);

        $this->connection->method('query')->willReturn([
            ['sku' => 'STK', 'name' => 'Stock Test', 'category' => 'Cat',
             'total_qty' => 30, 'total_revenue' => 3000, 'sale_month' => 1, 'sale_year' => 2025],
        ]);

        $this->stockSync->method('getStockBySku')->willReturn(['qty' => 5]);

        $result = $this->forecast->getProductForecast();

        $this->assertSame(5.0, $result[0]['current_stock']);
        $this->assertTrue($result[0]['stockout_risk']);
    }

    // ====================================================================
    // getProductForecast — stock lookup failure
    // ====================================================================

    public function testGetProductForecastHandlesStockLookupFailure(): void
    {
        $this->cache->method('load')->willReturn(false);

        $this->connection->method('query')->willReturn([
            ['sku' => 'ERR', 'name' => 'Error Product', 'category' => 'Cat',
             'total_qty' => 10, 'total_revenue' => 1000, 'sale_month' => 1, 'sale_year' => 2025],
        ]);

        $this->stockSync->method('getStockBySku')
            ->willThrowException(new \Exception('ERP offline'));

        $result = $this->forecast->getProductForecast();

        $this->assertSame(0, $result[0]['current_stock']);
        $this->assertFalse($result[0]['stockout_risk']);
    }

    // ====================================================================
    // getProductForecast — exception returns empty
    // ====================================================================

    public function testGetProductForecastReturnsEmptyOnException(): void
    {
        $this->cache->method('load')->willReturn(false);
        $this->connection->method('query')
            ->willThrowException(new \Exception('Connection refused'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('DemandForecast error'));

        $result = $this->forecast->getProductForecast();
        $this->assertSame([], $result);
    }

    // ====================================================================
    // getCategoryForecast — cache hit
    // ====================================================================

    public function testGetCategoryForecastReturnsCachedData(): void
    {
        $cached = [['category' => 'Bagageiros', 'revenue_current' => 10000]];
        $this->cache->method('load')->willReturn(json_encode($cached));

        $result = $this->forecast->getCategoryForecast();
        $this->assertCount(1, $result);
    }

    // ====================================================================
    // getCategoryForecast — calculates from ERP
    // ====================================================================

    public function testGetCategoryForecastCalculatesFromErp(): void
    {
        $this->cache->method('load')->willReturn(false);

        $this->connection->method('query')->willReturn([
            ['category' => 'Bagageiros', 'revenue_current' => 10000, 'revenue_previous' => 8000, 'product_count' => 5],
            ['category' => 'Retrovisores', 'revenue_current' => 5000, 'revenue_previous' => 7000, 'product_count' => 3],
        ]);

        $this->cache->expects($this->once())->method('save');

        $result = $this->forecast->getCategoryForecast();

        $this->assertCount(2, $result);
        // Should be sorted by growth_pct descending
        $this->assertGreaterThan($result[1]['growth_pct'], $result[0]['growth_pct']);
        $this->assertSame('Bagageiros', $result[0]['category']); // 25% growth > -28.6% decline
    }

    // ====================================================================
    // getCategoryForecast — trend labels
    // ====================================================================

    public function testGetCategoryForecastAssignsTrendLabels(): void
    {
        $this->cache->method('load')->willReturn(false);

        $this->connection->method('query')->willReturn([
            ['category' => 'Growing', 'revenue_current' => 15000, 'revenue_previous' => 10000, 'product_count' => 5],
            ['category' => 'Stable', 'revenue_current' => 10000, 'revenue_previous' => 10000, 'product_count' => 3],
            ['category' => 'Declining', 'revenue_current' => 5000, 'revenue_previous' => 10000, 'product_count' => 2],
        ]);

        $result = $this->forecast->getCategoryForecast();

        $trends = array_column($result, 'trend', 'category');
        $this->assertSame('up', $trends['Growing']);
        $this->assertSame('stable', $trends['Stable']);
        $this->assertSame('down', $trends['Declining']);
    }

    // ====================================================================
    // getStockoutRisks — filters correctly
    // ====================================================================

    public function testGetStockoutRisksFiltersCorrectly(): void
    {
        // Provide cached data for getProductForecast
        $products = [
            ['sku' => 'RISK', 'stockout_risk' => true, 'days_of_stock' => 5, 'forecast_30d_qty' => 20],
            ['sku' => 'SAFE', 'stockout_risk' => false, 'days_of_stock' => 100, 'forecast_30d_qty' => 10],
            ['sku' => 'BORDER', 'stockout_risk' => true, 'days_of_stock' => 14, 'forecast_30d_qty' => 15],
        ];
        $this->cache->method('load')->willReturn(json_encode($products));

        $risks = $this->forecast->getStockoutRisks(15);

        $this->assertCount(2, $risks);
        $skus = array_column($risks, 'sku');
        $this->assertContains('RISK', $skus);
        $this->assertContains('BORDER', $skus);
        $this->assertNotContains('SAFE', $skus);
    }

    // ====================================================================
    // getStockoutRisks — excludes zero forecast
    // ====================================================================

    public function testGetStockoutRisksExcludesZeroForecast(): void
    {
        $products = [
            ['sku' => 'ZERO', 'stockout_risk' => true, 'days_of_stock' => 5, 'forecast_30d_qty' => 0],
        ];
        $this->cache->method('load')->willReturn(json_encode($products));

        $risks = $this->forecast->getStockoutRisks(15);
        $this->assertSame([], $risks);
    }

    // ====================================================================
    // clearCache
    // ====================================================================

    public function testClearCacheCallsCacheClean(): void
    {
        $this->cache->expects($this->once())
            ->method('clean')
            ->with(['sales_intelligence_demand']);

        $this->forecast->clearCache();
    }

    // ====================================================================
    // getProductForecast — seasonal index defaults to 1.0 for sparse data
    // ====================================================================

    public function testForecastSeasonalIndexDefaultsForSparseData(): void
    {
        $this->cache->method('load')->willReturn(false);

        // Only 3 months of data — not enough for seasonality
        $this->connection->method('query')->willReturn([
            ['sku' => 'S1', 'name' => 'Sparse', 'category' => 'Cat',
             'total_qty' => 10, 'total_revenue' => 1000, 'sale_month' => 1, 'sale_year' => 2025],
            ['sku' => 'S1', 'name' => 'Sparse', 'category' => 'Cat',
             'total_qty' => 10, 'total_revenue' => 1000, 'sale_month' => 2, 'sale_year' => 2025],
            ['sku' => 'S1', 'name' => 'Sparse', 'category' => 'Cat',
             'total_qty' => 10, 'total_revenue' => 1000, 'sale_month' => 3, 'sale_year' => 2025],
        ]);

        $this->stockSync->method('getStockBySku')->willReturn(['qty' => 100]);

        $result = $this->forecast->getProductForecast();

        // With < 6 months of data, seasonal_index should be 1.0
        $this->assertSame(1.0, $result[0]['seasonal_index']);
    }
}
