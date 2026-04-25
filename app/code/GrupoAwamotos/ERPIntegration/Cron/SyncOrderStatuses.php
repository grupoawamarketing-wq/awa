<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Cron;

use GrupoAwamotos\ERPIntegration\Api\OrderSyncInterface;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use Psr\Log\LoggerInterface;

class SyncOrderStatuses
{
    private OrderSyncInterface $orderSync;
    private Helper $helper;
    private LoggerInterface $logger;

    public function __construct(
        OrderSyncInterface $orderSync,
        Helper $helper,
        LoggerInterface $logger
    ) {
        $this->orderSync = $orderSync;
        $this->helper = $helper;
        $this->logger = $logger;
    }

    public function execute(): void
    {
        if (!$this->helper->isOrderSyncEnabled()) {
            return;
        }

        try {
            $result = $this->orderSync->syncOrderStatuses();

            $this->logger->info('[ERP Cron] Order status sync completed', [
                'synced' => $result['synced'],
                'errors' => $result['errors'],
                'skipped' => $result['skipped'],
            ]);
        } catch (\Exception $e) {
            $this->logger->error('[ERP Cron] Order status sync failed: ' . $e->getMessage());
        }
    }
}
