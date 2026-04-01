<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use GrupoAwamotos\B2B\Helper\Data as B2BHelper;
use GrupoAwamotos\B2B\Model\OrderApproval;
use GrupoAwamotos\B2B\Model\OrderApprovalService;
use Psr\Log\LoggerInterface;

class OrderPlaceAfterObserver implements ObserverInterface
{
    private ScopeConfigInterface $scopeConfig;
    private B2BHelper $b2bHelper;
    private OrderApprovalService $approvalService;
    private LoggerInterface $logger;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        B2BHelper $b2bHelper,
        OrderApprovalService $approvalService,
        LoggerInterface $logger
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->b2bHelper = $b2bHelper;
        $this->approvalService = $approvalService;
        $this->logger = $logger;
    }

    public function execute(Observer $observer): void
    {
        if (!$this->isApprovalEnabled()) {
            return;
        }

        $order = $observer->getEvent()->getOrder();
        if (!$order || !$order->getCustomerId()) {
            return;
        }

        if (!$this->b2bHelper->isB2BCustomerById((int) $order->getCustomerId())) {
            return;
        }

        $total = (float) $order->getGrandTotal();
        $requiredLevel = $this->determineLevel($total);

        if ($requiredLevel <= OrderApproval::LEVEL_BUYER) {
            return; // Auto-approved, no workflow needed
        }

        try {
            $this->approvalService->createApprovalRequest(
                (int) $order->getEntityId(),
                $requiredLevel
            );
            $this->logger->info(sprintf(
                'B2B Order Approval triggered: order=%s total=%.2f level=%d',
                $order->getIncrementId(),
                $total,
                $requiredLevel
            ));
        } catch (\Exception $e) {
            $this->logger->error('B2B Order Approval error: ' . $e->getMessage());
        }
    }

    private function isApprovalEnabled(): bool
    {
        return (bool) $this->scopeConfig->getValue('grupoawamotos_b2b/order_approval/enabled');
    }

    private function determineLevel(float $total): int
    {
        $directorThreshold = (float) $this->scopeConfig->getValue('grupoawamotos_b2b/order_approval/threshold_director');
        $financeThreshold = (float) $this->scopeConfig->getValue('grupoawamotos_b2b/order_approval/threshold_finance');
        $managerThreshold = (float) $this->scopeConfig->getValue('grupoawamotos_b2b/order_approval/threshold_manager');

        if ($directorThreshold > 0 && $total >= $directorThreshold) {
            return OrderApproval::LEVEL_DIRECTOR;
        }
        if ($financeThreshold > 0 && $total >= $financeThreshold) {
            return OrderApproval::LEVEL_FINANCE;
        }
        if ($managerThreshold > 0 && $total >= $managerThreshold) {
            return OrderApproval::LEVEL_MANAGER;
        }

        return OrderApproval::LEVEL_BUYER;
    }
}
