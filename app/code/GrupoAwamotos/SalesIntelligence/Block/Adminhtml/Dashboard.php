<?php

/**
 * Sales Intelligence Dashboard Block
 *
 * Aggregates data from all intelligence models and provides
 * a composite Health Score for the business.
 */

declare(strict_types=1);

namespace GrupoAwamotos\SalesIntelligence\Block\Adminhtml;

use GrupoAwamotos\SalesIntelligence\Model\DemandForecast;
use GrupoAwamotos\SalesIntelligence\Model\RevenuePipeline;
use GrupoAwamotos\SalesIntelligence\Model\GrowthAnalyzer;
use GrupoAwamotos\SalesIntelligence\Model\RecommendationEngine;
use GrupoAwamotos\ERPIntegration\Model\Forecast\SalesProjection;
use GrupoAwamotos\ERPIntegration\Model\Rfm\Calculator as RfmCalculator;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

class Dashboard extends Template
{
    protected $_template = 'GrupoAwamotos_SalesIntelligence::dashboard.phtml';

    private DemandForecast $demandForecast;
    private RevenuePipeline $revenuePipeline;
    private GrowthAnalyzer $growthAnalyzer;
    private RecommendationEngine $recommendationEngine;
    private SalesProjection $salesProjection;
    private RfmCalculator $rfmCalculator;

    /** @var array<string, mixed>|null */
    private ?array $memoProjection = null;
    /** @var array<string, mixed>|null */
    private ?array $memoPipeline = null;

    public function __construct(
        Context $context,
        DemandForecast $demandForecast,
        RevenuePipeline $revenuePipeline,
        GrowthAnalyzer $growthAnalyzer,
        RecommendationEngine $recommendationEngine,
        SalesProjection $salesProjection,
        RfmCalculator $rfmCalculator,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->demandForecast = $demandForecast;
        $this->revenuePipeline = $revenuePipeline;
        $this->growthAnalyzer = $growthAnalyzer;
        $this->recommendationEngine = $recommendationEngine;
        $this->salesProjection = $salesProjection;
        $this->rfmCalculator = $rfmCalculator;
    }

    /**
     * Calculate composite Health Score (0-100)
     */
    public function getHealthScore(): array
    {
        try {
            $growth = $this->getGrowth();
            $pipeline = $this->getPipeline();
            $forecast = $this->getProductForecast();
            $projection = $this->getSalesProjection();

            // Sales score (25 pts)
            $growthPct = $growth['net_change_pct'] ?? 0;
            if ($growthPct > 5) {
                $salesScore = 25;
            } elseif ($growthPct > 0) {
                $salesScore = 18;
            } elseif ($growthPct > -5) {
                $salesScore = 10;
            } else {
                $salesScore = 3;
            }

            // Customer score (25 pts)
            $rfmStats = $this->getRfmSummary();
            $healthyPct = $rfmStats['healthy_pct'] ?? 0;
            if ($healthyPct > 40) {
                $customerScore = 25;
            } elseif ($healthyPct > 25) {
                $customerScore = 18;
            } elseif ($healthyPct > 15) {
                $customerScore = 12;
            } else {
                $customerScore = 5;
            }

            // Pipeline score (20 pts)
            $convRate = $pipeline['conversion_rate'] ?? 0;
            if ($convRate > 50) {
                $pipelineScore = 20;
            } elseif ($convRate > 30) {
                $pipelineScore = 15;
            } elseif ($convRate > 15) {
                $pipelineScore = 8;
            } else {
                $pipelineScore = 3;
            }

            // Stock score (15 pts)
            $totalProducts = count($forecast);
            $riskProducts = count(array_filter($forecast, fn($p) => $p['stockout_risk'] ?? false));
            $healthyStockPct = $totalProducts > 0 ? (($totalProducts - $riskProducts) / $totalProducts) * 100 : 100;
            if ($healthyStockPct > 90) {
                $stockScore = 15;
            } elseif ($healthyStockPct > 75) {
                $stockScore = 11;
            } elseif ($healthyStockPct > 60) {
                $stockScore = 7;
            } else {
                $stockScore = 3;
            }

            // Target score (15 pts)
            $targetPct = $projection['progress_pct'] ?? 0;
            if ($targetPct > 90) {
                $targetScore = 15;
            } elseif ($targetPct > 70) {
                $targetScore = 11;
            } elseif ($targetPct > 50) {
                $targetScore = 7;
            } else {
                $targetScore = 3;
            }

            $total = $salesScore + $customerScore + $pipelineScore + $stockScore + $targetScore;

            if ($total >= 80) {
                $label = 'Excelente';
                $color = '#059669';
            } elseif ($total >= 60) {
                $label = 'Bom';
                $color = '#2563eb';
            } elseif ($total >= 40) {
                $label = 'Atencao';
                $color = '#d97706';
            } else {
                $label = 'Critico';
                $color = '#dc2626';
            }

            return [
                'total' => $total,
                'label' => $label,
                'color' => $color,
                'breakdown' => [
                    ['name' => 'Vendas', 'score' => $salesScore, 'max' => 25, 'color' => '#059669'],
                    ['name' => 'Clientes', 'score' => $customerScore, 'max' => 25, 'color' => '#2563eb'],
                    ['name' => 'Pipeline', 'score' => $pipelineScore, 'max' => 20, 'color' => '#7c3aed'],
                    ['name' => 'Estoque', 'score' => $stockScore, 'max' => 15, 'color' => '#d97706'],
                    ['name' => 'Meta', 'score' => $targetScore, 'max' => 15, 'color' => '#dc2626'],
                ],
            ];
        } catch (\Exception $e) {
            return [
                'total' => 0,
                'label' => 'Indisponivel',
                'color' => '#9ca3af',
                'breakdown' => [],
            ];
        }
    }

    /**
     * Get 4 KPI cards data
     */
    public function getKpis(): array
    {
        $projection = $this->getSalesProjection();
        $pipeline = $this->getPipeline();

        return [
            [
                'title' => 'Receita MTD',
                'value' => $this->formatPrice((float) ($projection['actual'] ?? 0)),
                'subtitle' => sprintf('Meta: %s', $this->formatPrice((float) ($projection['target'] ?? 0))),
                'progress' => min(100, (float) ($projection['progress_pct'] ?? 0)),
                'trend' => ((float) ($projection['progress_pct'] ?? 0)) >= 70 ? 'up' : 'down',
            ],
            [
                'title' => 'Projecao do Mes',
                'value' => $this->formatPrice((float) ($projection['projected'] ?? 0)),
                'subtitle' => sprintf(
                    'Range: %s - %s',
                    $this->formatPrice((float) ($projection['p25'] ?? 0)),
                    $this->formatPrice((float) ($projection['p75'] ?? 0))
                ),
                'progress' => null,
                'trend' => ((float) ($projection['projected'] ?? 0)) >= ((float) ($projection['target'] ?? 1)) ? 'up' : 'down',
            ],
            [
                'title' => 'Pipeline Ativo',
                'value' => $this->formatPrice((float) ($pipeline['pipeline_value'] ?? 0)),
                'subtitle' => sprintf(
                    'Receita esperada: %s',
                    $this->formatPrice((float) ($pipeline['expected_revenue'] ?? 0))
                ),
                'progress' => null,
                'trend' => ($pipeline['pipeline_value'] ?? 0) > 0 ? 'up' : 'stable',
            ],
            [
                'title' => 'Taxa de Conversao',
                'value' => sprintf('%.1f%%', $pipeline['conversion_rate'] ?? 0),
                'subtitle' => sprintf('%d cotacoes no periodo', $pipeline['total_quotes'] ?? 0),
                'progress' => null,
                'trend' => ($pipeline['conversion_rate'] ?? 0) >= 30 ? 'up' : 'down',
            ],
        ];
    }

    public function getProductForecast(): array
    {
        return $this->demandForecast->getProductForecast(12, 30);
    }

    public function getCategoryGrowth(): array
    {
        return $this->demandForecast->getCategoryForecast();
    }

    public function getPipeline(): array
    {
        return $this->memoPipeline ??= $this->revenuePipeline->getPipelineMetrics(30);
    }

    public function getGrowth(): array
    {
        return $this->growthAnalyzer->getGrowthDecomposition(30);
    }

    public function getGrowthTrend(): array
    {
        return $this->growthAnalyzer->getGrowthTrend(6);
    }

    public function getRecommendations(): array
    {
        return $this->recommendationEngine->getRecommendations(10);
    }

    public function getSalesProjection(): array
    {
        if ($this->memoProjection !== null) {
            return $this->memoProjection;
        }
        try {
            $raw = $this->salesProjection->getCurrentMonthProjection();
            $this->memoProjection = [
                'actual' => $raw['actual_sales'] ?? 0,
                'target' => $raw['target'] ?? 0,
                'projected' => $raw['projected_total'] ?? $raw['realistic'] ?? 0,
                'progress_pct' => $raw['progress_percentage'] ?? 0,
                'p25' => $raw['pessimistic'] ?? 0,
                'p75' => $raw['optimistic'] ?? 0,
                'will_hit_target' => $raw['will_hit_target'] ?? false,
                'daily_average' => $raw['daily_average'] ?? 0,
                'days_remaining' => $raw['days_remaining'] ?? 0,
                'target_daily_needed' => $raw['target_daily_needed'] ?? 0,
                'vs_last_month' => $raw['vs_last_month'] ?? 0,
                'trend_factor' => $raw['trend_factor'] ?? 1,
                'alert_level' => $raw['alert_level'] ?? 'normal',
            ];
        } catch (\Exception $e) {
            $this->memoProjection = [
                'actual' => 0,
                'target' => 0,
                'projected' => 0,
                'progress_pct' => 0,
                'p25' => 0,
                'p75' => 0,
                'will_hit_target' => false,
            ];
        }
        return $this->memoProjection;
    }

    /**
     * Get RFM summary with healthy customer percentage
     */
    public function getRfmSummary(): array
    {
        try {
            $stats = $this->rfmCalculator->getSegmentStats();
            $total = 0;
            $healthy = 0;
            $segments = [];

            foreach ($stats as $segmentKey => $segment) {
                $count = (int) ($segment['count'] ?? 0);
                $total += $count;
                if (in_array($segmentKey, ['champions', 'loyal', 'potential'])) {
                    $healthy += $count;
                }
                $segments[] = array_merge($segment, ['segment' => $segmentKey]);
            }

            return [
                'total_customers' => $total,
                'healthy_count' => $healthy,
                'healthy_pct' => $total > 0 ? round(($healthy / $total) * 100, 1) : 0,
                'segments' => $segments,
            ];
        } catch (\Exception $e) {
            return ['total_customers' => 0, 'healthy_count' => 0, 'healthy_pct' => 0, 'segments' => []];
        }
    }

    /**
     * Get sales chart data as JSON for Chart.js
     */
    public function getSalesChartJson(): string
    {
        try {
            $trend = $this->getGrowthTrend();
            $projection = $this->getSalesProjection();

            $labels = array_column($trend, 'month_label');
            $revenues = array_column($trend, 'revenue');

            // Add projection for next month
            $nextMonth = date('M/y', strtotime('+1 month'));
            $labels[] = $nextMonth;
            $projected = $projection['projected'] ?? 0;
            $revenues[] = null; // null for actual (bar)

            $projectionLine = array_fill(0, count($trend), null);
            if (!empty($trend)) {
                $projectionLine[count($trend) - 1] = end($revenues) ?: $trend[count($trend) - 1]['revenue'];
            }
            $projectionLine[] = $projected;

            $target = $projection['target'] ?? 0;
            $targetLine = array_fill(0, count($labels), $target > 0 ? $target : null);

            return json_encode([
                'labels' => $labels,
                'revenues' => array_slice($revenues, 0, -1), // actual data (bars)
                'projection' => $projectionLine,
                'target' => $targetLine,
            ], JSON_THROW_ON_ERROR);
        } catch (\Exception $e) {
            return '{"labels":[],"revenues":[],"projection":[],"target":[]}';
        }
    }

    public function formatPrice(float $price): string
    {
        return 'R$ ' . number_format($price, 2, ',', '.');
    }

    public function formatNumber(float $number): string
    {
        return number_format($number, 0, ',', '.');
    }
}
