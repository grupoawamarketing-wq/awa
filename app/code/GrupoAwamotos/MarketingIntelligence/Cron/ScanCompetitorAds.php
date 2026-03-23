<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Cron;

use GrupoAwamotos\MarketingIntelligence\Model\Service\CompetitorMonitor;
use Psr\Log\LoggerInterface;

class ScanCompetitorAds
{
    public function __construct(
        private readonly CompetitorMonitor $competitorMonitor,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        $this->logger->info('Cron ScanCompetitorAds: starting.');

        try {
            $count = $this->competitorMonitor->scanAll();
            $this->logger->info(sprintf('Cron ScanCompetitorAds: completed — %d new ads found.', $count));
        } catch (\Exception $e) {
            $this->logger->error('Cron ScanCompetitorAds: failed — ' . $e->getMessage());
        }
    }
}
