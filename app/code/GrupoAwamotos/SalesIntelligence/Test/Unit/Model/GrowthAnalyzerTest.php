<?php

declare(strict_types=1);

namespace GrupoAwamotos\SalesIntelligence\Test\Unit\Model;

use GrupoAwamotos\ERPIntegration\Api\ConnectionInterface;
use GrupoAwamotos\ERPIntegration\Model\Rfm\Calculator as RfmCalculator;
use GrupoAwamotos\SalesIntelligence\Model\GrowthAnalyzer;
use Magento\Framework\App\CacheInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \GrupoAwamotos\SalesIntelligence\Model\GrowthAnalyzer
 */
class GrowthAnalyzerTest extends TestCase
{
    private GrowthAnalyzer $analyzer;
    private ConnectionInterface&MockObject $connection;
    private RfmCalculator&MockObject $rfmCalculator;
    private CacheInterface&MockObject $cache;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(ConnectionInterface::class);
        $this->rfmCalculator = $this->createMock(RfmCalculator::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->analyzer = new GrowthAnalyzer(
            $this->connection,
            $this->rfmCalculator,
            $this->cache,
            $this->logger
        );
    }

    // ====================================================================
    // getGrowthDecomposition — cache hit
    // ====================================================================

    public function testGetGrowthDecompositionReturnsCachedData(): void
    {
        $cachedData = [
            'revenue_current' => 100000,
            'revenue_previous' => 90000,
            'net_change' => 10000,
        ];
        $this->cache->method('load')->willReturn(json_encode($cachedData));

        $result = $this->analyzer->getGrowthDecomposition(30);

        $this->assertSame(100000, $result['revenue_current']);
        $this->assertSame(10000, $result['net_change']);
    }

    // ====================================================================
    // getGrowthDecomposition — cache miss, calculates
    // ====================================================================

    public function testGetGrowthDecompositionCalculatesWhenCacheMiss(): void
    {
        $this->cache->method('load')->willReturn(false);

        // Mock fetchOne calls for getPeriodRevenue
        $callCount = 0;
        $this->connection->method('fetchOne')->willReturnCallback(
            function () use (&$callCount) {
                $callCount++;
                // First call: current revenue, second call: previous revenue
                // Third call: new customer data
                if ($callCount <= 2) {
                    return ['total' => $callCount === 1 ? 100000 : 80000];
                }
                return ['new_count' => 5, 'new_revenue' => 15000];
            }
        );

        // RFM data for churn
        $this->rfmCalculator->method('getAtRiskCustomers')->willReturn([
            ['monetary' => 5000, 'frequency' => 10, 'recency' => 180],
        ]);
        $this->rfmCalculator->method('getSegmentStats')->willReturn([
            ['segment' => 'at_risk', 'count' => 3],
            ['segment' => 'loyal', 'count' => 50],
        ]);

        $this->cache->expects($this->once())->method('save');

        $result = $this->analyzer->getGrowthDecomposition(30);

        $this->assertArrayHasKey('revenue_current', $result);
        $this->assertArrayHasKey('revenue_previous', $result);
        $this->assertArrayHasKey('net_change', $result);
        $this->assertArrayHasKey('net_change_pct', $result);
        $this->assertArrayHasKey('new_customer_revenue', $result);
        $this->assertArrayHasKey('new_customer_count', $result);
        $this->assertArrayHasKey('returning_growth', $result);
        $this->assertArrayHasKey('churn_loss', $result);
        $this->assertArrayHasKey('churned_customer_count', $result);
    }

    // ====================================================================
    // getGrowthDecomposition — exception returns empty
    // ====================================================================

    public function testGetGrowthDecompositionReturnsEmptyOnException(): void
    {
        $this->cache->method('load')->willReturn(false);
        $this->connection->method('fetchOne')
            ->willThrowException(new \Exception('DB error'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('GrowthAnalyzer error'));

        $result = $this->analyzer->getGrowthDecomposition();

        $this->assertSame(0, $result['revenue_current']);
        $this->assertSame(0, $result['net_change']);
        $this->assertSame(0, $result['churn_loss']);
    }

    // ====================================================================
    // getGrowthTrend — cache hit
    // ====================================================================

    public function testGetGrowthTrendReturnsCachedData(): void
    {
        $cachedTrend = [
            ['month' => '2025-01', 'revenue' => 50000],
            ['month' => '2025-02', 'revenue' => 55000],
        ];
        $this->cache->method('load')->willReturn(json_encode($cachedTrend));

        $result = $this->analyzer->getGrowthTrend(6);
        $this->assertCount(2, $result);
        $this->assertSame('2025-01', $result[0]['month']);
    }

    // ====================================================================
    // getGrowthTrend — calculates monthly trend
    // ====================================================================

    public function testGetGrowthTrendCalculatesMonthlyTrend(): void
    {
        $this->cache->method('load')->willReturn(false);

        $this->connection->method('query')->willReturn([
            ['yr' => 2025, 'mo' => 1, 'revenue' => 50000, 'order_count' => 100, 'customer_count' => 80],
            ['yr' => 2025, 'mo' => 2, 'revenue' => 55000, 'order_count' => 110, 'customer_count' => 85],
            ['yr' => 2025, 'mo' => 3, 'revenue' => 60000, 'order_count' => 120, 'customer_count' => 90],
        ]);

        $this->cache->expects($this->once())->method('save');

        $result = $this->analyzer->getGrowthTrend(6);

        $this->assertCount(3, $result);
        $this->assertSame('2025-01', $result[0]['month']);
        $this->assertSame(50000.0, $result[0]['revenue']);
        $this->assertSame(0.0, $result[0]['mom_growth_pct']); // first month: no previous
        $this->assertSame(10.0, $result[1]['mom_growth_pct']); // (55000-50000)/50000 = 10%
    }

    // ====================================================================
    // getGrowthTrend — month labels in Portuguese
    // ====================================================================

    public function testGetGrowthTrendUsesPortugueseMonthLabels(): void
    {
        $this->cache->method('load')->willReturn(false);

        $this->connection->method('query')->willReturn([
            ['yr' => 2025, 'mo' => 6, 'revenue' => 40000, 'order_count' => 80, 'customer_count' => 60],
            ['yr' => 2025, 'mo' => 12, 'revenue' => 45000, 'order_count' => 90, 'customer_count' => 70],
        ]);

        $result = $this->analyzer->getGrowthTrend(6);

        $this->assertSame('Jun/25', $result[0]['month_label']);
        $this->assertSame('Dez/25', $result[1]['month_label']);
    }

    // ====================================================================
    // getGrowthTrend — exception returns empty
    // ====================================================================

    public function testGetGrowthTrendReturnsEmptyOnException(): void
    {
        $this->cache->method('load')->willReturn(false);
        $this->connection->method('query')
            ->willThrowException(new \Exception('timeout'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('GrowthTrend error'));

        $result = $this->analyzer->getGrowthTrend();
        $this->assertSame([], $result);
    }

    // ====================================================================
    // clearCache
    // ====================================================================

    public function testClearCacheCallsCacheClean(): void
    {
        $this->cache->expects($this->once())
            ->method('clean')
            ->with(['sales_intelligence_growth']);

        $this->analyzer->clearCache();
    }

    // ====================================================================
    // getGrowthDecomposition — growth percentage formula
    // ====================================================================

    public function testDecompositionCalculatesGrowthPercentage(): void
    {
        $this->cache->method('load')->willReturn(false);

        $callCount = 0;
        $this->connection->method('fetchOne')->willReturnCallback(
            function () use (&$callCount) {
                $callCount++;
                if ($callCount === 1) {
                    return ['total' => 120000];
                }
                if ($callCount === 2) {
                    return ['total' => 100000];
                }
                return ['new_count' => 0, 'new_revenue' => 0];
            }
        );

        $this->rfmCalculator->method('getAtRiskCustomers')->willReturn([]);
        $this->rfmCalculator->method('getSegmentStats')->willReturn([]);

        $result = $this->analyzer->getGrowthDecomposition(30);

        $this->assertSame(120000.0, $result['revenue_current']);
        $this->assertSame(100000.0, $result['revenue_previous']);
        $this->assertSame(20000.0, $result['net_change']);
        $this->assertSame(20.0, $result['net_change_pct']); // (20000/100000)*100
    }

    // ====================================================================
    // getGrowthDecomposition — zero previous revenue
    // ====================================================================

    public function testDecompositionHandlesZeroPreviousRevenue(): void
    {
        $this->cache->method('load')->willReturn(false);

        $callCount = 0;
        $this->connection->method('fetchOne')->willReturnCallback(
            function () use (&$callCount) {
                $callCount++;
                if ($callCount === 1) {
                    return ['total' => 50000];
                }
                if ($callCount === 2) {
                    return ['total' => 0];
                }
                return ['new_count' => 0, 'new_revenue' => 0];
            }
        );

        $this->rfmCalculator->method('getAtRiskCustomers')->willReturn([]);
        $this->rfmCalculator->method('getSegmentStats')->willReturn([]);

        $result = $this->analyzer->getGrowthDecomposition(30);

        // When previous is 0 and current > 0, should be 100%
        $this->assertSame(100.0, $result['net_change_pct']);
    }
}
