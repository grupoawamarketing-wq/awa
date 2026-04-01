<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Cron;

use GrupoAwamotos\ERPIntegration\Api\PriceSyncInterface;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use Psr\Log\LoggerInterface;

class SyncPrices
{
    private PriceSyncInterface $priceSync;
    private Helper $helper;
    private LoggerInterface $logger;

    public function __construct(
        PriceSyncInterface $priceSync,
        Helper $helper,
        LoggerInterface $logger
    ) {
        $this->priceSync = $priceSync;
        $this->helper = $helper;
        $this->logger = $logger;
    }

    public function execute(): void
    {
        if (!$this->helper->isPriceSyncEnabled()) {
            return;
        }

        $this->logger->info('[ERP Cron] Starting price sync...');
        $result = $this->priceSync->syncAll();
        $this->logger->info('[ERP Cron] Price sync finished.', $result);
    }
}
