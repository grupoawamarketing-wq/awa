<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Cron;

use GrupoAwamotos\MarketingIntelligence\Model\Service\AudienceSyncer;
use Psr\Log\LoggerInterface;

class RefreshAudiences
{
    public function __construct(
        private readonly AudienceSyncer $audienceSyncer,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        $this->logger->info('Cron RefreshAudiences: starting.');

        try {
            $count = $this->audienceSyncer->refreshAll();
            $this->logger->info(sprintf('Cron RefreshAudiences: completed — %d audiences refreshed.', $count));
        } catch (\Exception $e) {
            $this->logger->error('Cron RefreshAudiences: failed — ' . $e->getMessage());
        }
    }
}
