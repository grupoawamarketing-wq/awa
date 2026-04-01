<?php

/**
 * B2B Order Approval Service
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\LocalizedException;
use GrupoAwamotos\B2B\Model\OrderApprovalFactory;
use GrupoAwamotos\B2B\Model\ResourceModel\OrderApproval as OrderApprovalResource;
use GrupoAwamotos\B2B\Model\ResourceModel\OrderApproval\CollectionFactory;
use GrupoAwamotos\B2B\Helper\Data as B2BHelper;
use Psr\Log\LoggerInterface;

class OrderApprovalService
{
    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var OrderApprovalFactory
     */
    private $approvalFactory;

    /**
     * @var OrderApprovalResource
     */
    private $approvalResource;

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var B2BHelper
     */
    private $b2bHelper;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        CustomerSession $customerSession,
        OrderApprovalFactory $approvalFactory,
        OrderApprovalResource $approvalResource,
        CollectionFactory $collectionFactory,
        B2BHelper $b2bHelper,
        LoggerInterface $logger
    ) {
        $this->orderRepository = $orderRepository;
        $this->customerSession = $customerSession;
        $this->approvalFactory = $approvalFactory;
        $this->approvalResource = $approvalResource;
        $this->collectionFactory = $collectionFactory;
        $this->b2bHelper = $b2bHelper;
        $this->logger = $logger;
    }

    /**
     * Create approval request for order
     *
     * @param int $orderId
     * @param int $requiredLevel
     * @return OrderApproval
     * @throws LocalizedException
     */
    public function createApprovalRequest(int $orderId, int $requiredLevel = OrderApproval::LEVEL_MANAGER): OrderApproval
    {
        $order = $this->orderRepository->get($orderId);

        $approval = $this->approvalFactory->create();
        $approval->setData([
            'order_id' => $orderId,
            'customer_id' => $order->getCustomerId(),
            'status' => OrderApproval::STATUS_PENDING,
            'current_level' => OrderApproval::LEVEL_BUYER,
            'required_level' => $requiredLevel,
            'order_total' => $order->getGrandTotal(),
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $this->approvalResource->save($approval);

        // Put order on hold
        $order->hold();
        $order->addCommentToStatusHistory(
            __('Pedido aguardando aprovação B2B. Nível requerido: %1', $this->getLevelName($requiredLevel))
        );
        $this->orderRepository->save($order);

        $this->logger->info("B2B Order Approval created for order #$orderId, required level: $requiredLevel");

        return $approval;
    }

    /**
     * Approve order at current level
     *
     * @param int $approvalId
     * @param int $approverId
     * @param string $comment
     * @return bool
     * @throws LocalizedException
     */
    public function approve(int $approvalId, int $approverId, string $comment = ''): bool
    {
        $approval = $this->approvalFactory->create();
        $this->approvalResource->load($approval, $approvalId);

        if (!$approval->getId()) {
            throw new LocalizedException(__('Solicitação de aprovação não encontrada.'));
        }

        if ($approval->getData('status') !== OrderApproval::STATUS_PENDING) {
            throw new LocalizedException(__('Esta solicitação já foi processada.'));
        }

        $currentLevel = (int) $approval->getData('current_level');
        $requiredLevel = (int) $approval->getData('required_level');
        $nextLevel = $approval->getNextLevel($currentLevel);

        // Record this approval
        $history = json_decode($approval->getData('approval_history') ?: '[]', true);
        $history[] = [
            'level' => $currentLevel,
            'approver_id' => $approverId,
            'action' => 'approved',
            'comment' => $comment,
            'date' => date('Y-m-d H:i:s')
        ];
        $approval->setData('approval_history', json_encode($history));

        if ($nextLevel && $nextLevel <= $requiredLevel) {
            // Move to next level
            $approval->setData('current_level', $nextLevel);
            $approval->setData('updated_at', date('Y-m-d H:i:s'));
        } else {
            // Fully approved
            $approval->setData('status', OrderApproval::STATUS_APPROVED);
            $approval->setData('approved_at', date('Y-m-d H:i:s'));

            // Release order from hold
            $this->releaseOrder((int) $approval->getData('order_id'));
        }

        $this->approvalResource->save($approval);

        return true;
    }

    /**
     * Reject order approval
     *
     * @param int $approvalId
     * @param int $rejectorId
     * @param string $reason
     * @return bool
     * @throws LocalizedException
     */
    public function reject(int $approvalId, int $rejectorId, string $reason): bool
    {
        $approval = $this->approvalFactory->create();
        $this->approvalResource->load($approval, $approvalId);

        if (!$approval->getId()) {
            throw new LocalizedException(__('Solicitação de aprovação não encontrada.'));
        }

        $history = json_decode($approval->getData('approval_history') ?: '[]', true);
        $history[] = [
            'level' => $approval->getData('current_level'),
            'approver_id' => $rejectorId,
            'action' => 'rejected',
            'comment' => $reason,
            'date' => date('Y-m-d H:i:s')
        ];

        $approval->setData('approval_history', json_encode($history));
        $approval->setData('status', OrderApproval::STATUS_REJECTED);
        $approval->setData('rejection_reason', $reason);
        $approval->setData('updated_at', date('Y-m-d H:i:s'));

        $this->approvalResource->save($approval);

        // Cancel the order
        $this->cancelOrder((int) $approval->getData('order_id'), $reason);

        return true;
    }

    /**
     * Get pending approvals for approver level
     *
     * @param int $approverLevel
     * @param int|null $customerId
     * @return \GrupoAwamotos\B2B\Model\ResourceModel\OrderApproval\Collection
     */
    public function getPendingApprovals(int $approverLevel, ?int $customerId = null)
    {
        $collection = $this->collectionFactory->create();
        $collection->filterPending()
                   ->filterByLevel($approverLevel);

        if ($customerId) {
            $collection->filterByCustomer($customerId);
        }

        return $collection;
    }

    /**
     * Get approval by order ID
     *
     * @param int $orderId
     * @return OrderApproval|null
     */
    public function getByOrderId(int $orderId): ?OrderApproval
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('order_id', $orderId);

        $approval = $collection->getFirstItem();
        return $approval->getId() ? $approval : null;
    }

    /**
     * Determine required approval level based on order total
     *
     * @param float $orderTotal
     * @return int
     */
    public function determineRequiredLevel(float $orderTotal): int
    {
        $thresholdDirector = $this->b2bHelper->getThresholdDirector();
        $thresholdFinance = $this->b2bHelper->getThresholdFinance();
        $thresholdManager = $this->b2bHelper->getThresholdManager();

        if ($thresholdDirector > 0 && $orderTotal >= $thresholdDirector) {
            return OrderApproval::LEVEL_DIRECTOR;
        } elseif ($thresholdFinance > 0 && $orderTotal >= $thresholdFinance) {
            return OrderApproval::LEVEL_FINANCE;
        } elseif ($thresholdManager > 0 && $orderTotal >= $thresholdManager) {
            return OrderApproval::LEVEL_MANAGER;
        }

        return OrderApproval::LEVEL_BUYER; // Auto-approved
    }

    /**
     * Release order from hold
     *
     * @param int $orderId
     * @return void
     */
    private function releaseOrder(int $orderId): void
    {
        try {
            $order = $this->orderRepository->get($orderId);
            $order->unhold();
            $order->addCommentToStatusHistory(__('Pedido aprovado pelo fluxo B2B.'));
            $this->orderRepository->save($order);
        } catch (\Exception $e) {
            $this->logger->error("Error releasing order $orderId: " . $e->getMessage());
        }
    }

    /**
     * Cancel order
     *
     * @param int $orderId
     * @param string $reason
     * @return void
     */
    private function cancelOrder(int $orderId, string $reason): void
    {
        try {
            $order = $this->orderRepository->get($orderId);
            if ($order->canCancel()) {
                $order->cancel();
                $order->addCommentToStatusHistory(__('Pedido rejeitado no fluxo B2B: %1', $reason));
                $this->orderRepository->save($order);
            }
        } catch (\Exception $e) {
            $this->logger->error("Error canceling order $orderId: " . $e->getMessage());
        }
    }

    /**
     * Get level name
     *
     * @param int $level
     * @return string
     */
    private function getLevelName(int $level): string
    {
        $levels = OrderApproval::getLevels();
        return (string) ($levels[$level] ?? __('Desconhecido'));
    }
}
