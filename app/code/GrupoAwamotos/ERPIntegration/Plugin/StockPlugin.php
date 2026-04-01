<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Plugin;

use GrupoAwamotos\ERPIntegration\Api\StockSyncInterface;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Psr\Log\LoggerInterface;

class StockPlugin
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

    public function afterGetStockItemBySku(
        StockRegistryInterface $subject,
        StockItemInterface $result,
        string $productSku
    ): StockItemInterface {
        if (!$this->helper->isStockSyncEnabled() || !$this->helper->isStockRealtime()) {
            return $result;
        }

        try {
            $erpStock = $this->stockSync->getStockBySku($productSku);
            if ($erpStock !== null) {
                $qty = $erpStock['qty'];
                $result->setQty($qty);
                $result->setIsInStock($qty > 0);
            }
        } catch (\Exception $e) {
            $this->logger->error('[ERP] Stock plugin error for ' . $productSku . ': ' . $e->getMessage());
        }

        return $result;
    }
}
