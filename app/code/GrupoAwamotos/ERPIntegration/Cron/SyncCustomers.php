<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Cron;

use GrupoAwamotos\ERPIntegration\Api\CustomerSyncInterface;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use Psr\Log\LoggerInterface;

class SyncCustomers
{
    private CustomerSyncInterface $customerSync;
    private Helper $helper;
    private LoggerInterface $logger;

    public function __construct(
        CustomerSyncInterface $customerSync,
        Helper $helper,
        LoggerInterface $logger
    ) {
        $this->customerSync = $customerSync;
        $this->helper = $helper;
        $this->logger = $logger;
    }

    public function execute(): void
    {
        if (!$this->helper->isCustomerSyncEnabled()) {
            return;
        }

        $this->logger->info('[ERP Cron] Starting customer sync...');
        $result = $this->customerSync->syncAll();
        $this->logger->info('[ERP Cron] Customer sync finished.', $result);
    }
}
