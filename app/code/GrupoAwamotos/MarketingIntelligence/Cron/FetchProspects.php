<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Cron;

use GrupoAwamotos\MarketingIntelligence\Model\Service\ProspectFetcher;
use Psr\Log\LoggerInterface;

class FetchProspects
{
    public function __construct(
        private readonly ProspectFetcher $prospectFetcher,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        $this->logger->info('Cron FetchProspects: starting.');

        try {
            $count = $this->prospectFetcher->execute();
            $this->logger->info(sprintf('Cron FetchProspects: completed — %d prospects processed.', $count));
        } catch (\Exception $e) {
            $this->logger->error('Cron FetchProspects: failed — ' . $e->getMessage());
        }
    }
}
