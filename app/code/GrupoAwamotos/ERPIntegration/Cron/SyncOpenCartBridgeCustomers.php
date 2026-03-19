<?php
declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Cron;

use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use GrupoAwamotos\ERPIntegration\Model\OpenCartBridgeCustomerSync;
use GrupoAwamotos\ERPIntegration\Model\ResourceModel\SyncLog as SyncLogResource;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;

class SyncOpenCartBridgeCustomers
{
    public function __construct(
        private readonly Helper $helper,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly OpenCartBridgeCustomerSync $openCartBridgeCustomerSync,
        private readonly SyncLogResource $syncLogResource,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(): void
    {
        if (!$this->helper->isEnabled() || !$this->helper->isOpenCartBridgeMode()) {
            return;
        }

        $criteria = $this->searchCriteriaBuilder
            ->addFilter('state', ['new', 'pending_payment', 'processing'], 'in')
            ->setPageSize(100)
            ->setCurrentPage(1)
            ->create();

        $synced = 0;
        do {
            $items = $this->orderRepository->getList($criteria)->getItems();

            foreach ($items as $order) {
                if (!$order->getCustomerId()) {
                    continue;
                }

                $this->openCartBridgeCustomerSync->syncByOrder($order);
                $synced++;
            }

            $criteria->setCurrentPage(((int) $criteria->getCurrentPage()) + 1);
        } while (count($items) === 100);

        $cleaned = $this->openCartBridgeCustomerSync->cleanupLegacyBridgeState();

        if ($synced > 0 || $cleaned > 0) {
            $message = sprintf(
                'OpenCart bridge customer sync executed for %d pending orders and cleaned %d stale B2B confirmations',
                $synced,
                $cleaned
            );
            $this->syncLogResource->addLog('opencart_bridge_customer', 'sync', 'success', $message);
            $this->logger->info('[ERP Bridge] ' . $message);
        }
    }
}
