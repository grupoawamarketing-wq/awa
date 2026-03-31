<?php
declare(strict_types=1);

namespace GrupoAwamotos\SalesIntelligence\Cron;

use GrupoAwamotos\SalesIntelligence\Model\DemandForecast;
use GrupoAwamotos\SalesIntelligence\Model\RevenuePipeline;
use GrupoAwamotos\SalesIntelligence\Model\GrowthAnalyzer;
use GrupoAwamotos\SalesIntelligence\Model\RecommendationEngine;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Psr\Log\LoggerInterface;

class WarmCache
{
    private DemandForecast $demandForecast;
    private RevenuePipeline $revenuePipeline;
    private GrowthAnalyzer $growthAnalyzer;
    private RecommendationEngine $recommendationEngine;
    private ScopeConfigInterface $scopeConfig;
    private LoggerInterface $logger;

    public function __construct(
        DemandForecast $demandForecast,
        RevenuePipeline $revenuePipeline,
        GrowthAnalyzer $growthAnalyzer,
        RecommendationEngine $recommendationEngine,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger
    ) {
        $this->demandForecast = $demandForecast;
        $this->revenuePipeline = $revenuePipeline;
        $this->growthAnalyzer = $growthAnalyzer;
        $this->recommendationEngine = $recommendationEngine;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
    }

    public function execute(): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        // Prevent runaway execution — ERP queries can hang under load
        set_time_limit(180);

        $start = microtime(true);
        $this->logger->info('[SalesIntelligence] Cache warming started.');

        try {
            // Clear existing caches first
            $this->demandForecast->clearCache();
            $this->revenuePipeline->clearCache();
            $this->growthAnalyzer->clearCache();
            $this->recommendationEngine->clearCache();

            // Rebuild caches
            $this->demandForecast->getProductForecast(12, 30);
            $this->demandForecast->getCategoryForecast();
            $this->revenuePipeline->getPipelineMetrics(30);
            $this->growthAnalyzer->getGrowthDecomposition(30);
            $this->growthAnalyzer->getGrowthTrend(6);
            $this->recommendationEngine->getRecommendations(10);

            $elapsed = round(microtime(true) - $start, 2);
            $this->logger->info("[SalesIntelligence] Cache warming completed in {$elapsed}s.");
        } catch (\Exception $e) {
            $this->logger->error('[SalesIntelligence] Cache warming error: ' . $e->getMessage());
        }
    }

    private function isEnabled(): bool
    {
        return (bool) $this->scopeConfig->getValue('salesintelligence/general/enabled');
    }
}
