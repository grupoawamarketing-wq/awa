<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Cron;

use GrupoAwamotos\ERPIntegration\Api\ImageSyncInterface;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use Psr\Log\LoggerInterface;

class SyncImages
{
    private ImageSyncInterface $imageSync;
    private Helper $helper;
    private LoggerInterface $logger;

    public function __construct(
        ImageSyncInterface $imageSync,
        Helper $helper,
        LoggerInterface $logger
    ) {
        $this->imageSync = $imageSync;
        $this->helper = $helper;
        $this->logger = $logger;
    }

    public function execute(): void
    {
        if (!$this->helper->isImageSyncEnabled()) {
            return;
        }

        try {
            $result = $this->imageSync->syncAll();

            $this->logger->info('[ERP Cron] Image sync completed', [
                'synced' => $result['synced'],
                'errors' => $result['errors'],
                'skipped' => $result['skipped'],
            ]);
        } catch (\Exception $e) {
            $this->logger->error('[ERP Cron] Image sync failed: ' . $e->getMessage());
        }
    }
}
