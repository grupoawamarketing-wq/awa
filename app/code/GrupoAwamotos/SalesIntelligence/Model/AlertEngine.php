<?php

declare(strict_types=1);

namespace GrupoAwamotos\SalesIntelligence\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Psr\Log\LoggerInterface;

class AlertEngine
{
    private DemandForecast $demandForecast;
    private RevenuePipeline $revenuePipeline;
    private GrowthAnalyzer $growthAnalyzer;
    private ScopeConfigInterface $scopeConfig;
    private LoggerInterface $logger;

    public function __construct(
        DemandForecast $demandForecast,
        RevenuePipeline $revenuePipeline,
        GrowthAnalyzer $growthAnalyzer,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger
    ) {
        $this->demandForecast = $demandForecast;
        $this->revenuePipeline = $revenuePipeline;
        $this->growthAnalyzer = $growthAnalyzer;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
    }

    /**
     * Evaluate all alert conditions and return active alerts
     *
     * @return array List of alerts with type, severity, title, description, impact
     */
    public function evaluate(): array
    {
        $alerts = [];

        try {
            $this->checkStockoutAlerts($alerts);
            $this->checkRevenueAlerts($alerts);
            $this->checkPipelineAlerts($alerts);
            $this->checkChurnAlerts($alerts);
        } catch (\Exception $e) {
            $this->logger->error('[SalesIntelligence] AlertEngine error: ' . $e->getMessage());
        }

        // Sort by severity descending (critical first)
        usort($alerts, fn($a, $b) => $b['severity_level'] <=> $a['severity_level']);

        return $alerts;
    }

    /**
     * Check if any alerts should be sent (at least one critical or warning)
     */
    public function hasActionableAlerts(): bool
    {
        $alerts = $this->evaluate();
        foreach ($alerts as $alert) {
            if (in_array($alert['severity'], ['critical', 'warning'])) {
                return true;
            }
        }
        return false;
    }

    private function checkStockoutAlerts(array &$alerts): void
    {
        if (!$this->isAlertTypeEnabled('stockout')) {
            return;
        }

        $threshold = (int) ($this->scopeConfig->getValue('salesintelligence/alerts/stockout_threshold') ?: 15);
        $risks = $this->demandForecast->getStockoutRisks($threshold);

        if (empty($risks)) {
            return;
        }

        $criticalProducts = array_filter($risks, fn($p) => ($p['days_of_stock'] ?? 999) < 7);
        $warningProducts = array_filter($risks, fn($p) => ($p['days_of_stock'] ?? 999) >= 7);

        if (!empty($criticalProducts)) {
            $totalRevenue = array_sum(array_column($criticalProducts, 'forecast_30d_revenue'));
            $names = array_slice(array_column($criticalProducts, 'name'), 0, 3);
            $alerts[] = [
                'type' => 'stockout',
                'severity' => 'critical',
                'severity_level' => 3,
                'title' => sprintf('%d produtos com estoque critico (< 7 dias)', count($criticalProducts)),
                'description' => sprintf(
                    'Produtos em ruptura iminente: %s%s. Receita mensal em risco: R$ %s.',
                    implode(', ', $names),
                    count($criticalProducts) > 3 ? sprintf(' e mais %d', count($criticalProducts) - 3) : '',
                    number_format($totalRevenue, 2, ',', '.')
                ),
                'impact' => $totalRevenue,
                'products' => array_slice($criticalProducts, 0, 10),
            ];
        }

        if (!empty($warningProducts)) {
            $totalRevenue = array_sum(array_column($warningProducts, 'forecast_30d_revenue'));
            $alerts[] = [
                'type' => 'stockout',
                'severity' => 'warning',
                'severity_level' => 2,
                'title' => sprintf('%d produtos com estoque baixo (< %d dias)', count($warningProducts), $threshold),
                'description' => sprintf(
                    'Receita mensal em risco: R$ %s. Recomendado reabastecer.',
                    number_format($totalRevenue, 2, ',', '.')
                ),
                'impact' => $totalRevenue,
                'products' => array_slice(array_values($warningProducts), 0, 10),
            ];
        }
    }

    private function checkRevenueAlerts(array &$alerts): void
    {
        if (!$this->isAlertTypeEnabled('revenue')) {
            return;
        }

        $growth = $this->growthAnalyzer->getGrowthDecomposition(30);
        $netChangePct = $growth['net_change_pct'] ?? 0;
        $netChange = $growth['net_change'] ?? 0;

        $criticalThreshold = (float) ($this->scopeConfig->getValue('salesintelligence/alerts/revenue_critical_pct') ?: -15);
        $warningThreshold = (float) ($this->scopeConfig->getValue('salesintelligence/alerts/revenue_warning_pct') ?: -5);

        if ($netChangePct <= $criticalThreshold) {
            $alerts[] = [
                'type' => 'revenue',
                'severity' => 'critical',
                'severity_level' => 3,
                'title' => sprintf('Queda critica de receita: %.1f%%', $netChangePct),
                'description' => sprintf(
                    'Receita caiu R$ %s vs periodo anterior. Atual: R$ %s. Anterior: R$ %s.',
                    number_format(abs($netChange), 2, ',', '.'),
                    number_format($growth['revenue_current'] ?? 0, 2, ',', '.'),
                    number_format($growth['revenue_previous'] ?? 0, 2, ',', '.')
                ),
                'impact' => abs($netChange),
                'growth_data' => $growth,
            ];
        } elseif ($netChangePct <= $warningThreshold) {
            $alerts[] = [
                'type' => 'revenue',
                'severity' => 'warning',
                'severity_level' => 2,
                'title' => sprintf('Receita em queda: %.1f%%', $netChangePct),
                'description' => sprintf(
                    'Receita diminuiu R$ %s comparado ao periodo anterior.',
                    number_format(abs($netChange), 2, ',', '.')
                ),
                'impact' => abs($netChange),
                'growth_data' => $growth,
            ];
        }
    }

    private function checkPipelineAlerts(array &$alerts): void
    {
        if (!$this->isAlertTypeEnabled('pipeline')) {
            return;
        }

        $pipeline = $this->revenuePipeline->getPipelineMetrics(30);
        $totalQuotes = $pipeline['total_quotes'] ?? 0;

        if ($totalQuotes < 3) {
            return; // Not enough data
        }

        $conversionRate = $pipeline['conversion_rate'] ?? 0;
        $avgResponseTime = $pipeline['avg_response_time_days'] ?? 0;
        $pipelineValue = $pipeline['pipeline_value'] ?? 0;

        if ($conversionRate < 15 && $totalQuotes >= 5) {
            $alerts[] = [
                'type' => 'pipeline',
                'severity' => 'warning',
                'severity_level' => 2,
                'title' => sprintf('Taxa de conversao muito baixa: %.1f%%', $conversionRate),
                'description' => sprintf(
                    'Apenas %.1f%% das %d cotacoes foram aceitas. Pipeline aberto: R$ %s.',
                    $conversionRate,
                    $totalQuotes,
                    number_format($pipelineValue, 2, ',', '.')
                ),
                'impact' => $pipelineValue * 0.15,
                'pipeline_data' => $pipeline,
            ];
        }

        if ($avgResponseTime > 5) {
            $alerts[] = [
                'type' => 'pipeline',
                'severity' => 'warning',
                'severity_level' => 2,
                'title' => sprintf('Tempo de resposta excessivo: %.1f dias', $avgResponseTime),
                'description' => 'Cotacoes estao levando mais de 5 dias para resposta. Clientes podem estar desistindo.',
                'impact' => $pipelineValue * 0.05,
                'pipeline_data' => $pipeline,
            ];
        }
    }

    private function checkChurnAlerts(array &$alerts): void
    {
        if (!$this->isAlertTypeEnabled('churn')) {
            return;
        }

        $growth = $this->growthAnalyzer->getGrowthDecomposition(30);
        $churnCount = $growth['churned_customer_count'] ?? 0;
        $churnValue = $growth['churned_customer_value'] ?? 0;

        $churnThreshold = (int) ($this->scopeConfig->getValue('salesintelligence/alerts/churn_threshold') ?: 5);

        if ($churnCount >= $churnThreshold && $churnValue > 0) {
            $severity = $churnCount >= $churnThreshold * 2 ? 'critical' : 'warning';
            $alerts[] = [
                'type' => 'churn',
                'severity' => $severity,
                'severity_level' => $severity === 'critical' ? 3 : 2,
                'title' => sprintf('%d clientes em risco de churn', $churnCount),
                'description' => sprintf(
                    'Valor mensal estimado em risco: R$ %s. Contato proativo recomendado.',
                    number_format($churnValue, 2, ',', '.')
                ),
                'impact' => $churnValue * 3,
            ];
        }
    }

    private function isAlertTypeEnabled(string $type): bool
    {
        return (bool) $this->scopeConfig->getValue("salesintelligence/alerts/{$type}_enabled");
    }
}
