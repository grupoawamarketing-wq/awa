<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Cron;

use GrupoAwamotos\ERPIntegration\Api\StockSyncInterface;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use Psr\Log\LoggerInterface;

class SyncStock
{
    private StockSyncInterface $stockSync;
    private Helper $helper;
    private LoggerInterface $logger;

    public function __construct(
        StockSyncInterface $stockSync,
        Helper $helper,
        LoggerInterface $logger
    ) {
        $this->stockSync = $stockSync;
        $this->helper = $helper;
        $this->logger = $logger;
    }

    public function execute(): void
    {
        if (!$this->helper->isStockSyncEnabled()) {
            return;
        }

        $result = $this->stockSync->syncAll();
        if (!empty($result['synced']) || !empty($result['errors'])) {
            $this->logger->info('[ERP Cron] Stock sync finished.', $result);
        }
    }
}
