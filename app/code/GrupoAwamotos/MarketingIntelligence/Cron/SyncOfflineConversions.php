<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Cron;

use GrupoAwamotos\MarketingIntelligence\Model\Service\OfflineConversionUploader;
use Psr\Log\LoggerInterface;

class SyncOfflineConversions
{
    public function __construct(
        private readonly OfflineConversionUploader $uploader,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        $this->logger->info('Cron SyncOfflineConversions: starting.');

        try {
            $count = $this->uploader->uploadRecentOrders();
            $this->logger->info(sprintf('Cron SyncOfflineConversions: completed — %d events uploaded.', $count));
        } catch (\Exception $e) {
            $this->logger->error('Cron SyncOfflineConversions: failed — ' . $e->getMessage());
        }
    }
}
