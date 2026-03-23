<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Cron;

use GrupoAwamotos\MarketingIntelligence\Model\Service\InsightsApiService;
use GrupoAwamotos\MarketingIntelligence\Model\Service\CampaignClassifier;
use Psr\Log\LoggerInterface;

class SyncInsights
{
    public function __construct(
        private readonly InsightsApiService $insightsApiService,
        private readonly CampaignClassifier $campaignClassifier,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        $this->logger->info('Cron SyncInsights: starting.');

        try {
            $synced = $this->insightsApiService->fetchAndStore();
            $this->logger->info(sprintf('Cron SyncInsights: fetched %d insight rows.', $synced));

            $classified = $this->campaignClassifier->classifyAll();
            $this->logger->info(sprintf('Cron SyncInsights: classified %d insights.', $classified));
        } catch (\Exception $e) {
            $this->logger->error('Cron SyncInsights: failed — ' . $e->getMessage());
        }
    }
}
