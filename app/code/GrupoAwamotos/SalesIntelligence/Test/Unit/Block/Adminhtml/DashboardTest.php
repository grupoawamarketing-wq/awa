<?php

declare(strict_types=1);

namespace GrupoAwamotos\SalesIntelligence\Test\Unit\Block\Adminhtml;

use GrupoAwamotos\ERPIntegration\Model\Forecast\SalesProjection;
use GrupoAwamotos\ERPIntegration\Model\Rfm\Calculator as RfmCalculator;
use GrupoAwamotos\SalesIntelligence\Block\Adminhtml\Dashboard;
use GrupoAwamotos\SalesIntelligence\Model\DemandForecast;
use GrupoAwamotos\SalesIntelligence\Model\GrowthAnalyzer;
use GrupoAwamotos\SalesIntelligence\Model\RecommendationEngine;
use GrupoAwamotos\SalesIntelligence\Model\RevenuePipeline;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Dashboard Block memoization
 *
 * @covers \GrupoAwamotos\SalesIntelligence\Block\Adminhtml\Dashboard
 */
class DashboardTest extends TestCase
{
    private Dashboard $dashboard;
    private RevenuePipeline&MockObject $revenuePipeline;
    private SalesProjection&MockObject $salesProjection;
    private GrowthAnalyzer&MockObject $growthAnalyzer;
    private DemandForecast&MockObject $demandForecast;
    private RecommendationEngine&MockObject $recommendationEngine;
    private RfmCalculator&MockObject $rfmCalculator;

    protected function setUp(): void
    {
        $this->revenuePipeline = $this->createMock(RevenuePipeline::class);
        $this->salesProjection = $this->createMock(SalesProjection::class);
        $this->growthAnalyzer = $this->createMock(GrowthAnalyzer::class);
        $this->demandForecast = $this->createMock(DemandForecast::class);
        $this->recommendationEngine = $this->createMock(RecommendationEngine::class);
        $this->rfmCalculator = $this->createMock(RfmCalculator::class);

        // Avoid invoking Magento backend block constructor (requires full DI stack)
        $this->dashboard = $this->getMockBuilder(Dashboard::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $this->injectProperty('revenuePipeline', $this->revenuePipeline);
        $this->injectProperty('salesProjection', $this->salesProjection);
        $this->injectProperty('growthAnalyzer', $this->growthAnalyzer);
        $this->injectProperty('demandForecast', $this->demandForecast);
        $this->injectProperty('recommendationEngine', $this->recommendationEngine);
        $this->injectProperty('rfmCalculator', $this->rfmCalculator);
        $this->injectProperty('memoProjection', null);
        $this->injectProperty('memoPipeline', null);
    }

    // ====================================================================
    // getPipeline() memoization
    // ====================================================================

    public function testGetPipelineCallsModelOnlyOnce(): void
    {
        $expected = ['pipeline_value' => 5000.0, 'conversion_rate' => 35.0, 'total_quotes' => 10];

        $this->revenuePipeline
            ->expects($this->once())
            ->method('getPipelineMetrics')
            ->with(30)
            ->willReturn($expected);

        $first = $this->dashboard->getPipeline();
        $second = $this->dashboard->getPipeline();
        $third = $this->dashboard->getPipeline();

        $this->assertSame($expected, $first);
        $this->assertSame($first, $second);
        $this->assertSame($first, $third);
    }

    public function testGetPipelineReturnsMemoizedEmptyArray(): void
    {
        $this->revenuePipeline
            ->expects($this->once())
            ->method('getPipelineMetrics')
            ->willReturn([]);

        $this->dashboard->getPipeline();
        $this->assertSame([], $this->dashboard->getPipeline());
    }

    // ====================================================================
    // getSalesProjection() memoization
    // ====================================================================

    public function testGetSalesProjectionCallsModelOnlyOnce(): void
    {
        $raw = [
            'actual_sales' => 12000.0,
            'target' => 15000.0,
            'projected_total' => 13500.0,
            'progress_percentage' => 80.0,
            'pessimistic' => 12000.0,
            'optimistic' => 15000.0,
            'will_hit_target' => false,
            'daily_average' => 400.0,
            'days_remaining' => 8,
            'target_daily_needed' => 375.0,
            'vs_last_month' => 5.0,
            'trend_factor' => 1.05,
            'alert_level' => 'normal',
        ];

        $this->salesProjection
            ->expects($this->once())
            ->method('getCurrentMonthProjection')
            ->willReturn($raw);

        $first = $this->dashboard->getSalesProjection();
        $second = $this->dashboard->getSalesProjection();
        $third = $this->dashboard->getSalesProjection();

        $this->assertSame(12000.0, $first['actual']);
        $this->assertSame(15000.0, $first['target']);
        $this->assertSame($first, $second);
        $this->assertSame($first, $third);
    }

    public function testGetSalesProjectionReturnsFallbackOnException(): void
    {
        $this->salesProjection
            ->method('getCurrentMonthProjection')
            ->willThrowException(new \RuntimeException('ERP connection failed'));

        $result = $this->dashboard->getSalesProjection();

        $this->assertSame(0, $result['actual']);
        $this->assertSame(0, $result['target']);
        $this->assertFalse($result['will_hit_target']);
    }

    public function testGetSalesProjectionMemoizesFallbackOnException(): void
    {
        $this->salesProjection
            ->expects($this->once())
            ->method('getCurrentMonthProjection')
            ->willThrowException(new \RuntimeException('ERP down'));

        $first = $this->dashboard->getSalesProjection();
        $second = $this->dashboard->getSalesProjection();

        $this->assertSame($first, $second);
    }

    // ====================================================================
    // formatPrice / formatNumber — pure function checks
    // ====================================================================

    public function testFormatPriceFormatsCorrectly(): void
    {
        $this->assertSame('R$ 1.234,56', $this->dashboard->formatPrice(1234.56));
        $this->assertSame('R$ 0,00', $this->dashboard->formatPrice(0.0));
        $this->assertSame('R$ 1.000.000,00', $this->dashboard->formatPrice(1_000_000.0));
    }

    public function testFormatNumberFormatsCorrectly(): void
    {
        $this->assertSame('1.234', $this->dashboard->formatNumber(1234.0));
        $this->assertSame('0', $this->dashboard->formatNumber(0.0));
    }

    // ====================================================================
    // Helper
    // ====================================================================

    private function injectProperty(string $name, mixed $value): void
    {
        $ref = new \ReflectionProperty(Dashboard::class, $name);
        $ref->setAccessible(true);
        $ref->setValue($this->dashboard, $value);
    }
}
