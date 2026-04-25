<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Cron;

use GrupoAwamotos\ERPIntegration\Api\CategorySyncInterface;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use Psr\Log\LoggerInterface;

class SyncCategories
{
    private CategorySyncInterface $categorySync;
    private Helper $helper;
    private LoggerInterface $logger;

    public function __construct(
        CategorySyncInterface $categorySync,
        Helper $helper,
        LoggerInterface $logger
    ) {
        $this->categorySync = $categorySync;
        $this->helper = $helper;
        $this->logger = $logger;
    }

    public function execute(): void
    {
        if (!$this->helper->isCategorySyncEnabled()) {
            return;
        }

        $result = $this->categorySync->syncAll();
        $this->logger->info('[ERP Cron] Category sync finished.', $result);
    }
}
