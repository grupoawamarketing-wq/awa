<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Cron;

use GrupoAwamotos\ERPIntegration\Api\ProductSyncInterface;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use Psr\Log\LoggerInterface;

class SyncProducts
{
    private ProductSyncInterface $productSync;
    private Helper $helper;
    private LoggerInterface $logger;

    public function __construct(
        ProductSyncInterface $productSync,
        Helper $helper,
        LoggerInterface $logger
    ) {
        $this->productSync = $productSync;
        $this->helper = $helper;
        $this->logger = $logger;
    }

    public function execute(): void
    {
        if (!$this->helper->isProductSyncEnabled()) {
            return;
        }

        $result = $this->productSync->syncDelta();
        $this->logger->info('[ERP Cron] Product sync finished.', $result);
    }
}
