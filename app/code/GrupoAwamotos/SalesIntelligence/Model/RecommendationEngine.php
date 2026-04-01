<?php

/**
 * Recommendation Engine
 *
 * Generates prioritized, actionable recommendations based on data from
 * DemandForecast, RevenuePipeline, GrowthAnalyzer, and RFM Calculator.
 */

declare(strict_types=1);

namespace GrupoAwamotos\SalesIntelligence\Model;

use GrupoAwamotos\ERPIntegration\Model\Rfm\Calculator as RfmCalculator;
use Magento\Framework\App\CacheInterface;
use Psr\Log\LoggerInterface;

class RecommendationEngine
{
    private const CACHE_KEY = 'si_recommendations';
    private const CACHE_TTL = 3600; // 1 hour
    private const CACHE_TAG = 'sales_intelligence_recommendations';

    private DemandForecast $demandForecast;
    private RevenuePipeline $revenuePipeline;
    private GrowthAnalyzer $growthAnalyzer;
    private RfmCalculator $rfmCalculator;
    private CacheInterface $cache;
    private LoggerInterface $logger;

    public function __construct(
        DemandForecast $demandForecast,
        RevenuePipeline $revenuePipeline,
        GrowthAnalyzer $growthAnalyzer,
        RfmCalculator $rfmCalculator,
        CacheInterface $cache,
        LoggerInterface $logger
    ) {
        $this->demandForecast = $demandForecast;
        $this->revenuePipeline = $revenuePipeline;
        $this->growthAnalyzer = $growthAnalyzer;
        $this->rfmCalculator = $rfmCalculator;
        $this->cache = $cache;
        $this->logger = $logger;
    }

    /**
     * Get prioritized recommendations
     *
     * @return array List of recommendations sorted by priority and impact
     */
    public function getRecommendations(int $limit = 10): array
    {
        $cached = $this->cache->load(self::CACHE_KEY);
        if ($cached) {
            return json_decode($cached, true) ?: [];
        }

        try {
            $recommendations = [];

            $this->addStockRecommendations($recommendations);
            $this->addGrowthRecommendations($recommendations);
            $this->addPipelineRecommendations($recommendations);
            $this->addCustomerRecommendations($recommendations);
            $this->addCategoryRecommendations($recommendations);

            // Sort by priority DESC, then impact_value DESC
            usort($recommendations, function ($a, $b) {
                if ($a['priority'] !== $b['priority']) {
                    return $b['priority'] <=> $a['priority'];
                }
                return ($b['impact_value'] ?? 0) <=> ($a['impact_value'] ?? 0);
            });

            $result = array_slice($recommendations, 0, $limit);

            $this->cache->save(
                json_encode($result),
                self::CACHE_KEY,
                [self::CACHE_TAG],
                self::CACHE_TTL
            );

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('[SalesIntelligence] Recommendations error: ' . $e->getMessage());
            return [];
        }
    }

    private function addStockRecommendations(array &$recs): void
    {
        try {
            $risks = $this->demandForecast->getStockoutRisks(15);
            $topRisks = array_slice($risks, 0, 5);

            foreach ($topRisks as $product) {
                $recs[] = [
                    'type' => 'stock',
                    'priority' => 5,
                    'title' => 'Risco de Ruptura: ' . ($product['name'] ?? $product['sku']),
                    'description' => sprintf(
                        'SKU %s tem apenas %d dias de estoque restante. Demanda projetada: %s unidades/mes.',
                        $product['sku'],
                        (int) $product['days_of_stock'],
                        number_format($product['forecast_30d_qty'], 0, ',', '.')
                    ),
                    'impact_value' => $product['forecast_30d_revenue'] ?? 0,
                    'action' => 'Reabastecer estoque imediatamente',
                    'icon' => 'warning',
                ];
            }

            if (count($risks) > 5) {
                $totalRevenue = array_sum(array_column($risks, 'forecast_30d_revenue'));
                $recs[] = [
                    'type' => 'stock',
                    'priority' => 4,
                    'title' => sprintf('%d Produtos com Risco de Ruptura', count($risks)),
                    'description' => sprintf(
                        'Alem dos listados acima, mais %d produtos estao com estoque baixo. Receita em risco: R$ %s/mes.',
                        count($risks) - 5,
                        number_format($totalRevenue, 2, ',', '.')
                    ),
                    'impact_value' => $totalRevenue,
                    'action' => 'Revisar politica de reposicao de estoque',
                    'icon' => 'inventory',
                ];
            }
        } catch (\Exception $e) {
            $this->logger->warning('[SalesIntelligence] Stock recommendations unavailable: ' . $e->getMessage());
        }
    }

    private function addGrowthRecommendations(array &$recs): void
    {
        try {
            $growth = $this->growthAnalyzer->getGrowthDecomposition(30);

            $netChangePct = $growth['net_change_pct'] ?? 0;
            $netChange = $growth['net_change'] ?? 0;
            $churnCount = $growth['churned_customer_count'] ?? 0;
            $churnValue = $growth['churned_customer_value'] ?? 0;
            $newCount = $growth['new_customer_count'] ?? 0;
            $newRevenue = $growth['new_customer_revenue'] ?? 0;

            // Revenue declining
            if ($netChangePct < -5) {
                $recs[] = [
                    'type' => 'sales',
                    'priority' => 5,
                    'title' => sprintf('Receita em Queda (%.1f%%)', $netChangePct),
                    'description' => sprintf(
                        'Receita caiu R$ %s vs periodo anterior. Foque em reativacao de %d clientes inativos.',
                        number_format(abs($netChange), 2, ',', '.'),
                        $churnCount
                    ),
                    'impact_value' => abs($netChange),
                    'action' => 'Campanha de reativacao de clientes',
                    'icon' => 'trending_down',
                ];
            } elseif ($netChangePct > 10) {
                $recs[] = [
                    'type' => 'sales',
                    'priority' => 1,
                    'title' => sprintf('Crescimento Forte (+%.1f%%)', $netChangePct),
                    'description' => sprintf(
                        'Receita cresceu R$ %s. %d novos clientes trouxeram R$ %s.',
                        number_format($netChange, 2, ',', '.'),
                        $newCount,
                        number_format($newRevenue, 2, ',', '.')
                    ),
                    'impact_value' => $netChange,
                    'action' => 'Manter estrategia atual e investir em aquisicao',
                    'icon' => 'trending_up',
                ];
            }

            // Churn alert
            if ($churnCount > 0 && $churnValue > 0) {
                $recs[] = [
                    'type' => 'customer',
                    'priority' => 4,
                    'title' => sprintf('%d Clientes em Risco de Churn', $churnCount),
                    'description' => sprintf(
                        'Valor mensal estimado em risco: R$ %s. Contate os principais clientes inativos.',
                        number_format($churnValue, 2, ',', '.')
                    ),
                    'impact_value' => $churnValue * 3, // 3 month impact
                    'action' => 'Contato proativo com clientes at-risk',
                    'icon' => 'person_off',
                ];
            }

            // New customer acquisition
            if ($newCount > 0) {
                $recs[] = [
                    'type' => 'customer',
                    'priority' => 2,
                    'title' => sprintf('%d Novos Clientes no Periodo', $newCount),
                    'description' => sprintf(
                        'Novos clientes geraram R$ %s. Ticket medio: R$ %s.',
                        number_format($newRevenue, 2, ',', '.'),
                        $newCount > 0 ? number_format($newRevenue / $newCount, 2, ',', '.') : '0,00'
                    ),
                    'impact_value' => $newRevenue,
                    'action' => 'Nutrir novos clientes para fidelizacao',
                    'icon' => 'person_add',
                ];
            }
        } catch (\Exception $e) {
            $this->logger->warning('[SalesIntelligence] Growth recommendations unavailable: ' . $e->getMessage());
        }
    }

    private function addPipelineRecommendations(array &$recs): void
    {
        try {
            $pipeline = $this->revenuePipeline->getPipelineMetrics(30);

            $conversionRate = $pipeline['conversion_rate'] ?? 0;
            $pipelineValue = $pipeline['pipeline_value'] ?? 0;
            $avgResponseTime = $pipeline['avg_response_time_days'] ?? 0;
            $totalQuotes = $pipeline['total_quotes'] ?? 0;

            if ($totalQuotes === 0) {
                return; // No pipeline data
            }

            // Low conversion rate
            if ($conversionRate < 30 && $totalQuotes >= 3) {
                $recs[] = [
                    'type' => 'operational',
                    'priority' => 3,
                    'title' => sprintf('Taxa de Conversao Baixa (%.1f%%)', $conversionRate),
                    'description' => sprintf(
                        'Apenas %.1f%% das cotacoes sao aceitas. Pipeline aberto: R$ %s. Revise tempo de resposta e precos.',
                        $conversionRate,
                        number_format($pipelineValue, 2, ',', '.')
                    ),
                    'impact_value' => $pipelineValue * 0.1, // 10% improvement potential
                    'action' => 'Reduzir tempo de resposta e revisar politica de precos',
                    'icon' => 'speed',
                ];
            }

            // Slow response time
            if ($avgResponseTime > 3) {
                $recs[] = [
                    'type' => 'operational',
                    'priority' => 3,
                    'title' => sprintf('Tempo de Resposta Alto (%.1f dias)', $avgResponseTime),
                    'description' => 'Cotacoes demoram em media mais de 3 dias para serem respondidas. Clientes podem desistir.',
                    'impact_value' => $pipelineValue * 0.05,
                    'action' => 'Definir SLA de 24h para resposta de cotacoes',
                    'icon' => 'schedule',
                ];
            }

            // Active pipeline value
            if ($pipelineValue > 0) {
                $expectedRevenue = $pipeline['expected_revenue'] ?? 0;
                $recs[] = [
                    'type' => 'sales',
                    'priority' => 2,
                    'title' => sprintf('Pipeline Ativo: R$ %s', number_format($pipelineValue, 0, ',', '.')),
                    'description' => sprintf(
                        '%d cotacoes abertas. Receita esperada: R$ %s (baseado na taxa de conversao de %.1f%%).',
                        ($pipeline['pending_quotes'] ?? 0) + ($pipeline['quoted_quotes'] ?? 0),
                        number_format($expectedRevenue, 2, ',', '.'),
                        $conversionRate
                    ),
                    'impact_value' => $expectedRevenue,
                    'action' => 'Acompanhar cotacoes pendentes',
                    'icon' => 'request_quote',
                ];
            }
        } catch (\Exception $e) {
            $this->logger->warning('[SalesIntelligence] Pipeline recommendations unavailable: ' . $e->getMessage());
        }
    }

    private function addCustomerRecommendations(array &$recs): void
    {
        try {
            $stats = $this->rfmCalculator->getSegmentStats();
            $totalCustomers = 0;
            $championsCount = 0;
            $loyalCount = 0;

            foreach ($stats as $segment) {
                $count = (int) ($segment['count'] ?? 0);
                $totalCustomers += $count;
                $seg = $segment['segment'] ?? '';
                if ($seg === 'champions') {
                    $championsCount = $count;
                }
                if ($seg === 'loyal') {
                    $loyalCount = $count;
                }
            }

            if ($totalCustomers > 0) {
                $healthyPct = ($championsCount + $loyalCount) / $totalCustomers * 100;
                if ($healthyPct < 20) {
                    $recs[] = [
                        'type' => 'customer',
                        'priority' => 3,
                        'title' => sprintf('Base de Clientes Fragil (%.0f%% fieis)', $healthyPct),
                        'description' => sprintf(
                            'Apenas %d de %d clientes sao Champions ou Loyal. Invista em programas de fidelizacao.',
                            $championsCount + $loyalCount,
                            $totalCustomers
                        ),
                        'impact_value' => 0,
                        'action' => 'Criar programa de fidelidade ou incentivos',
                        'icon' => 'loyalty',
                    ];
                }
            }
        } catch (\Exception $e) {
            $this->logger->warning('[SalesIntelligence] Customer recommendations unavailable: ' . $e->getMessage());
        }
    }

    private function addCategoryRecommendations(array &$recs): void
    {
        try {
            $categories = $this->demandForecast->getCategoryForecast();
            $growingCategories = array_filter($categories, fn($c) => ($c['growth_pct'] ?? 0) > 15);
            $decliningCategories = array_filter($categories, fn($c) => ($c['growth_pct'] ?? 0) < -15);

            foreach (array_slice($growingCategories, 0, 3) as $cat) {
                $recs[] = [
                    'type' => 'sales',
                    'priority' => 2,
                    'title' => sprintf('Categoria em Alta: %s (+%.0f%%)', $cat['category'], $cat['growth_pct']),
                    'description' => sprintf(
                        'Receita subiu de R$ %s para R$ %s. %d produtos ativos.',
                        number_format($cat['revenue_previous'], 0, ',', '.'),
                        number_format($cat['revenue_current'], 0, ',', '.'),
                        $cat['product_count']
                    ),
                    'impact_value' => $cat['revenue_current'] * ($cat['growth_pct'] / 100),
                    'action' => 'Expandir mix de produtos nesta categoria',
                    'icon' => 'category',
                ];
            }

            foreach (array_slice(array_values($decliningCategories), 0, 2) as $cat) {
                $recs[] = [
                    'type' => 'sales',
                    'priority' => 3,
                    'title' => sprintf('Categoria em Queda: %s (%.0f%%)', $cat['category'], $cat['growth_pct']),
                    'description' => sprintf(
                        'Receita caiu de R$ %s para R$ %s. Investigue causas.',
                        number_format($cat['revenue_previous'], 0, ',', '.'),
                        number_format($cat['revenue_current'], 0, ',', '.')
                    ),
                    'impact_value' => abs($cat['revenue_current'] - $cat['revenue_previous']),
                    'action' => 'Analisar competitividade de precos e demanda',
                    'icon' => 'analytics',
                ];
            }
        } catch (\Exception $e) {
            $this->logger->warning('[SalesIntelligence] Category recommendations unavailable: ' . $e->getMessage());
        }
    }

    public function clearCache(): void
    {
        $this->cache->clean([self::CACHE_TAG]);
    }
}
