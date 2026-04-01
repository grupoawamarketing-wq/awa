<?php

declare(strict_types=1);

namespace GrupoAwamotos\SalesIntelligence\Test\Unit\Model;

use GrupoAwamotos\SalesIntelligence\Model\AlertEngine;
use GrupoAwamotos\SalesIntelligence\Model\DemandForecast;
use GrupoAwamotos\SalesIntelligence\Model\GrowthAnalyzer;
use GrupoAwamotos\SalesIntelligence\Model\RevenuePipeline;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \GrupoAwamotos\SalesIntelligence\Model\AlertEngine
 */
class AlertEngineTest extends TestCase
{
    private AlertEngine $engine;
    private DemandForecast&MockObject $demandForecast;
    private RevenuePipeline&MockObject $revenuePipeline;
    private GrowthAnalyzer&MockObject $growthAnalyzer;
    private ScopeConfigInterface&MockObject $scopeConfig;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->demandForecast = $this->createMock(DemandForecast::class);
        $this->revenuePipeline = $this->createMock(RevenuePipeline::class);
        $this->growthAnalyzer = $this->createMock(GrowthAnalyzer::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->engine = new AlertEngine(
            $this->demandForecast,
            $this->revenuePipeline,
            $this->growthAnalyzer,
            $this->scopeConfig,
            $this->logger
        );
    }

    // ====================================================================
    // evaluate — empty when all disabled
    // ====================================================================

    public function testEvaluateReturnsEmptyWhenAllAlertsDisabled(): void
    {
        $this->scopeConfig->method('getValue')->willReturn(null);

        $this->assertSame([], $this->engine->evaluate());
    }

    // ====================================================================
    // evaluate — stockout critical
    // ====================================================================

    public function testEvaluateReturnsStockoutCriticalAlert(): void
    {
        $this->enableAlerts(['stockout']);
        $this->setDefaultThresholds();

        $this->demandForecast->method('getStockoutRisks')->willReturn([
            [
                'sku' => 'ABC123',
                'name' => 'Bagageiro CG 160',
                'days_of_stock' => 3,
                'forecast_30d_revenue' => 5000.00,
            ],
        ]);

        // Disable other alert sources
        $this->growthAnalyzer->method('getGrowthDecomposition')->willReturn($this->neutralGrowth());
        $this->revenuePipeline->method('getPipelineMetrics')->willReturn($this->neutralPipeline());

        $alerts = $this->engine->evaluate();

        $this->assertNotEmpty($alerts);
        $critical = $this->findAlertByType($alerts, 'stockout');
        $this->assertNotNull($critical);
        $this->assertSame('critical', $critical['severity']);
        $this->assertSame(3, $critical['severity_level']);
        $this->assertStringContainsString('critico', $critical['title']);
    }

    // ====================================================================
    // evaluate — stockout warning (7+ days)
    // ====================================================================

    public function testEvaluateReturnsStockoutWarningAlert(): void
    {
        $this->enableAlerts(['stockout']);
        $this->setDefaultThresholds();

        $this->demandForecast->method('getStockoutRisks')->willReturn([
            [
                'sku' => 'XYZ999',
                'name' => 'Retrovisor Fazer 250',
                'days_of_stock' => 10,
                'forecast_30d_revenue' => 2000.00,
            ],
        ]);

        $this->growthAnalyzer->method('getGrowthDecomposition')->willReturn($this->neutralGrowth());
        $this->revenuePipeline->method('getPipelineMetrics')->willReturn($this->neutralPipeline());

        $alerts = $this->engine->evaluate();

        $warning = $this->findAlertByType($alerts, 'stockout');
        $this->assertNotNull($warning);
        $this->assertSame('warning', $warning['severity']);
    }

    // ====================================================================
    // evaluate — no stockout risk
    // ====================================================================

    public function testEvaluateReturnsNoStockoutAlertWhenNoRisk(): void
    {
        $this->enableAlerts(['stockout']);
        $this->setDefaultThresholds();

        $this->demandForecast->method('getStockoutRisks')->willReturn([]);
        $this->growthAnalyzer->method('getGrowthDecomposition')->willReturn($this->neutralGrowth());
        $this->revenuePipeline->method('getPipelineMetrics')->willReturn($this->neutralPipeline());

        $alerts = $this->engine->evaluate();
        $stockout = $this->findAlertByType($alerts, 'stockout');
        $this->assertNull($stockout);
    }

    // ====================================================================
    // evaluate — revenue critical drop
    // ====================================================================

    public function testEvaluateReturnsRevenueCriticalWhenDropExceedsThreshold(): void
    {
        $this->enableAlerts(['revenue']);
        $this->setDefaultThresholds();

        $this->growthAnalyzer->method('getGrowthDecomposition')->willReturn([
            'net_change_pct' => -20.0,
            'net_change' => -50000.0,
            'revenue_current' => 200000,
            'revenue_previous' => 250000,
            'churned_customer_count' => 0,
            'churned_customer_value' => 0,
        ]);

        $this->demandForecast->method('getStockoutRisks')->willReturn([]);
        $this->revenuePipeline->method('getPipelineMetrics')->willReturn($this->neutralPipeline());

        $alerts = $this->engine->evaluate();

        $revenue = $this->findAlertByType($alerts, 'revenue');
        $this->assertNotNull($revenue);
        $this->assertSame('critical', $revenue['severity']);
        $this->assertStringContainsString('critica', $revenue['title']);
    }

    // ====================================================================
    // evaluate — revenue warning drop
    // ====================================================================

    public function testEvaluateReturnsRevenueWarningWhenModestDrop(): void
    {
        $this->enableAlerts(['revenue']);
        $this->setDefaultThresholds();

        $this->growthAnalyzer->method('getGrowthDecomposition')->willReturn([
            'net_change_pct' => -8.0,
            'net_change' => -20000.0,
            'revenue_current' => 230000,
            'revenue_previous' => 250000,
            'churned_customer_count' => 0,
            'churned_customer_value' => 0,
        ]);

        $this->demandForecast->method('getStockoutRisks')->willReturn([]);
        $this->revenuePipeline->method('getPipelineMetrics')->willReturn($this->neutralPipeline());

        $alerts = $this->engine->evaluate();

        $revenue = $this->findAlertByType($alerts, 'revenue');
        $this->assertNotNull($revenue);
        $this->assertSame('warning', $revenue['severity']);
    }

    // ====================================================================
    // evaluate — no revenue alert when growth positive
    // ====================================================================

    public function testEvaluateReturnsNoRevenueAlertWhenGrowthPositive(): void
    {
        $this->enableAlerts(['revenue']);
        $this->setDefaultThresholds();

        $this->growthAnalyzer->method('getGrowthDecomposition')->willReturn([
            'net_change_pct' => 12.0,
            'net_change' => 30000.0,
            'revenue_current' => 280000,
            'revenue_previous' => 250000,
            'churned_customer_count' => 0,
            'churned_customer_value' => 0,
        ]);

        $this->demandForecast->method('getStockoutRisks')->willReturn([]);
        $this->revenuePipeline->method('getPipelineMetrics')->willReturn($this->neutralPipeline());

        $alerts = $this->engine->evaluate();
        $revenue = $this->findAlertByType($alerts, 'revenue');
        $this->assertNull($revenue);
    }

    // ====================================================================
    // evaluate — pipeline low conversion
    // ====================================================================

    public function testEvaluateReturnsPipelineAlertWhenConversionLow(): void
    {
        $this->enableAlerts(['pipeline']);
        $this->setDefaultThresholds();

        $this->revenuePipeline->method('getPipelineMetrics')->willReturn([
            'total_quotes' => 10,
            'conversion_rate' => 10.0,
            'avg_response_time_days' => 2.0,
            'pipeline_value' => 50000.0,
        ]);

        $this->demandForecast->method('getStockoutRisks')->willReturn([]);
        $this->growthAnalyzer->method('getGrowthDecomposition')->willReturn($this->neutralGrowth());

        $alerts = $this->engine->evaluate();
        $pipeline = $this->findAlertByType($alerts, 'pipeline');
        $this->assertNotNull($pipeline);
        $this->assertStringContainsString('conversao', $pipeline['title']);
    }

    // ====================================================================
    // evaluate — pipeline slow response time
    // ====================================================================

    public function testEvaluateReturnsPipelineAlertWhenResponseSlow(): void
    {
        $this->enableAlerts(['pipeline']);
        $this->setDefaultThresholds();

        $this->revenuePipeline->method('getPipelineMetrics')->willReturn([
            'total_quotes' => 10,
            'conversion_rate' => 50.0,
            'avg_response_time_days' => 8.0,
            'pipeline_value' => 50000.0,
        ]);

        $this->demandForecast->method('getStockoutRisks')->willReturn([]);
        $this->growthAnalyzer->method('getGrowthDecomposition')->willReturn($this->neutralGrowth());

        $alerts = $this->engine->evaluate();
        $pipeline = $this->findAlertByType($alerts, 'pipeline');
        $this->assertNotNull($pipeline);
        $this->assertStringContainsString('resposta', $pipeline['title']);
    }

    // ====================================================================
    // evaluate — pipeline not enough data
    // ====================================================================

    public function testEvaluateNoPipelineAlertWhenTooFewQuotes(): void
    {
        $this->enableAlerts(['pipeline']);
        $this->setDefaultThresholds();

        $this->revenuePipeline->method('getPipelineMetrics')->willReturn([
            'total_quotes' => 2,
            'conversion_rate' => 0.0,
            'avg_response_time_days' => 0.0,
            'pipeline_value' => 0.0,
        ]);

        $this->demandForecast->method('getStockoutRisks')->willReturn([]);
        $this->growthAnalyzer->method('getGrowthDecomposition')->willReturn($this->neutralGrowth());

        $alerts = $this->engine->evaluate();
        $pipeline = $this->findAlertByType($alerts, 'pipeline');
        $this->assertNull($pipeline);
    }

    // ====================================================================
    // evaluate — churn warning
    // ====================================================================

    public function testEvaluateReturnsChurnWarningAlert(): void
    {
        $this->enableAlerts(['churn']);
        $this->setDefaultThresholds();

        $this->growthAnalyzer->method('getGrowthDecomposition')->willReturn([
            'net_change_pct' => 5.0,
            'net_change' => 10000.0,
            'revenue_current' => 260000,
            'revenue_previous' => 250000,
            'churned_customer_count' => 7,
            'churned_customer_value' => 15000.0,
        ]);

        $this->demandForecast->method('getStockoutRisks')->willReturn([]);
        $this->revenuePipeline->method('getPipelineMetrics')->willReturn($this->neutralPipeline());

        $alerts = $this->engine->evaluate();
        $churn = $this->findAlertByType($alerts, 'churn');
        $this->assertNotNull($churn);
        $this->assertSame('warning', $churn['severity']);
        $this->assertStringContainsString('churn', $churn['title']);
    }

    // ====================================================================
    // evaluate — churn critical (double threshold)
    // ====================================================================

    public function testEvaluateReturnsChurnCriticalWhenDoubleThreshold(): void
    {
        $this->enableAlerts(['churn']);
        $this->setDefaultThresholds();

        $this->growthAnalyzer->method('getGrowthDecomposition')->willReturn([
            'net_change_pct' => -2.0,
            'net_change' => -5000.0,
            'revenue_current' => 245000,
            'revenue_previous' => 250000,
            'churned_customer_count' => 12,
            'churned_customer_value' => 30000.0,
        ]);

        $this->demandForecast->method('getStockoutRisks')->willReturn([]);
        $this->revenuePipeline->method('getPipelineMetrics')->willReturn($this->neutralPipeline());

        $alerts = $this->engine->evaluate();
        $churn = $this->findAlertByType($alerts, 'churn');
        $this->assertNotNull($churn);
        $this->assertSame('critical', $churn['severity']);
    }

    // ====================================================================
    // evaluate — no churn alert when below threshold
    // ====================================================================

    public function testEvaluateNoChurnAlertWhenBelowThreshold(): void
    {
        $this->enableAlerts(['churn']);
        $this->setDefaultThresholds();

        $this->growthAnalyzer->method('getGrowthDecomposition')->willReturn([
            'net_change_pct' => 5.0,
            'net_change' => 10000.0,
            'revenue_current' => 260000,
            'revenue_previous' => 250000,
            'churned_customer_count' => 2,
            'churned_customer_value' => 3000.0,
        ]);

        $this->demandForecast->method('getStockoutRisks')->willReturn([]);
        $this->revenuePipeline->method('getPipelineMetrics')->willReturn($this->neutralPipeline());

        $alerts = $this->engine->evaluate();
        $churn = $this->findAlertByType($alerts, 'churn');
        $this->assertNull($churn);
    }

    // ====================================================================
    // evaluate — sorted by severity descending
    // ====================================================================

    public function testEvaluateSortsBySeverityDescending(): void
    {
        $this->enableAlerts(['stockout', 'revenue', 'pipeline', 'churn']);
        $this->setDefaultThresholds();

        // Stockout critical
        $this->demandForecast->method('getStockoutRisks')->willReturn([
            ['sku' => 'A', 'name' => 'Product A', 'days_of_stock' => 3, 'forecast_30d_revenue' => 5000.0],
        ]);

        // Revenue warning
        $this->growthAnalyzer->method('getGrowthDecomposition')->willReturn([
            'net_change_pct' => -8.0,
            'net_change' => -20000.0,
            'revenue_current' => 230000,
            'revenue_previous' => 250000,
            'churned_customer_count' => 2,
            'churned_customer_value' => 1000.0,
        ]);

        $this->revenuePipeline->method('getPipelineMetrics')->willReturn($this->neutralPipeline());

        $alerts = $this->engine->evaluate();
        $this->assertGreaterThanOrEqual(2, count($alerts));

        // First should be critical (severity_level 3)
        $this->assertSame(3, $alerts[0]['severity_level']);
    }

    // ====================================================================
    // evaluate — exception handling
    // ====================================================================

    public function testEvaluateLogsErrorOnException(): void
    {
        $this->enableAlerts(['stockout']);

        $this->demandForecast->method('getStockoutRisks')
            ->willThrowException(new \Exception('Connection failed'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('AlertEngine error'));

        $result = $this->engine->evaluate();
        $this->assertIsArray($result);
    }

    // ====================================================================
    // hasActionableAlerts — true when critical exists
    // ====================================================================

    public function testHasActionableAlertsReturnsTrueWhenCritical(): void
    {
        $this->enableAlerts(['stockout']);
        $this->setDefaultThresholds();

        $this->demandForecast->method('getStockoutRisks')->willReturn([
            ['sku' => 'A', 'name' => 'P', 'days_of_stock' => 2, 'forecast_30d_revenue' => 1000.0],
        ]);

        $this->growthAnalyzer->method('getGrowthDecomposition')->willReturn($this->neutralGrowth());
        $this->revenuePipeline->method('getPipelineMetrics')->willReturn($this->neutralPipeline());

        $this->assertTrue($this->engine->hasActionableAlerts());
    }

    // ====================================================================
    // hasActionableAlerts — false when no alerts
    // ====================================================================

    public function testHasActionableAlertsReturnsFalseWhenNoAlerts(): void
    {
        $this->scopeConfig->method('getValue')->willReturn(null);

        $this->assertFalse($this->engine->hasActionableAlerts());
    }

    // ====================================================================
    // Helpers
    // ====================================================================

    /**
     * Enable specific alert types via scopeConfig mock
     */
    private function enableAlerts(array $types): void
    {
        $this->scopeConfig->method('getValue')->willReturnCallback(
            function (string $path) use ($types) {
                // Enable alert types
                foreach ($types as $type) {
                    if ($path === "salesintelligence/alerts/{$type}_enabled") {
                        return '1';
                    }
                }

                // Thresholds
                $thresholds = [
                    'salesintelligence/alerts/stockout_threshold' => '15',
                    'salesintelligence/alerts/revenue_critical_pct' => '-15',
                    'salesintelligence/alerts/revenue_warning_pct' => '-5',
                    'salesintelligence/alerts/churn_threshold' => '5',
                ];

                return $thresholds[$path] ?? null;
            }
        );
    }

    private function setDefaultThresholds(): void
    {
        // Already handled in enableAlerts callback
    }

    private function neutralGrowth(): array
    {
        return [
            'net_change_pct' => 5.0,
            'net_change' => 10000.0,
            'revenue_current' => 260000,
            'revenue_previous' => 250000,
            'churned_customer_count' => 0,
            'churned_customer_value' => 0,
        ];
    }

    private function neutralPipeline(): array
    {
        return [
            'total_quotes' => 0,
            'conversion_rate' => 0,
            'avg_response_time_days' => 0,
            'pipeline_value' => 0,
        ];
    }

    private function findAlertByType(array $alerts, string $type): ?array
    {
        foreach ($alerts as $alert) {
            if ($alert['type'] === $type) {
                return $alert;
            }
        }
        return null;
    }
}
