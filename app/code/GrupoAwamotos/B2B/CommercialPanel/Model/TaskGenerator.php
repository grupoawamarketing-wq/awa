<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\CommercialPanel\Model;

use GrupoAwamotos\B2B\CommercialPanel\Api\CommercialTaskManagementInterface;
use GrupoAwamotos\B2B\CommercialPanel\Api\TaskGeneratorInterface;
use GrupoAwamotos\B2B\CommercialPanel\Model\Config\TaskConfig;
use GrupoAwamotos\B2B\Model\Customer\Attribute\Source\ApprovalStatus;
use GrupoAwamotos\B2B\Model\ResourceModel\CustomerAttendant\CollectionFactory as CustomerAttendantCollectionFactory;
use GrupoAwamotos\B2B\Model\ResourceModel\QuoteRequest\CollectionFactory as QuoteRequestCollectionFactory;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Psr\Log\LoggerInterface;

class TaskGenerator implements TaskGeneratorInterface
{
    public function __construct(
        private readonly CommercialTaskManagementInterface $taskManagement,
        private readonly TaskConfig $taskConfig,
        private readonly CustomerAttendantCollectionFactory $customerAttendantCollectionFactory,
        private readonly OrderCollectionFactory $orderCollectionFactory,
        private readonly CustomerCollectionFactory $customerCollectionFactory,
        private readonly QuoteRequestCollectionFactory $quoteRequestCollectionFactory,
        private readonly ResourceConnection $resourceConnection,
        private readonly LoggerInterface $logger
    ) {
    }

    public function generateAll(): array
    {
        $created = 0;
        $skipped = 0;
        $period = date('Y-m');

        foreach ($this->getPortfolioMappings() as $mapping) {
            $customerId = (int) $mapping['customer_id'];
            $attendantId = (int) $mapping['attendant_id'];

            $rules = [
                fn () => $this->ruleNoPurchase($customerId, $attendantId, $period),
                fn () => $this->ruleNewCustomerNoContact($customerId, $attendantId, $mapping['assigned_at'], $period),
                fn () => $this->rulePendingNoContact($customerId, $attendantId, $period),
            ];

            foreach ($rules as $rule) {
                $result = $rule();
                if ($result === 'created') {
                    $created++;
                } elseif ($result === 'skipped') {
                    $skipped++;
                }
            }
        }

        $created += $this->generateQuoteNoResponseTasks($period);
        $created += $this->generateAbandonedCartTasks($period);

        $this->logger->info('[AWA Commercial Task Cron] Geração concluída', [
            'created' => $created,
            'skipped' => $skipped,
            'period' => $period,
        ]);

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * @return array<int, array{customer_id: int, attendant_id: int, assigned_at: string|null}>
     */
    private function getPortfolioMappings(): array
    {
        $collection = $this->customerAttendantCollectionFactory->create();
        $result = [];
        foreach ($collection as $item) {
            $result[] = [
                'customer_id' => (int) $item->getData('customer_id'),
                'attendant_id' => (int) $item->getData('attendant_id'),
                'assigned_at' => $item->getData('assigned_at'),
            ];
        }

        return $result;
    }

    private function ruleNoPurchase(int $customerId, int $attendantId, string $period): string
    {
        $days = $this->taskConfig->getDaysNoPurchase();
        $since = (new \DateTimeImmutable())->modify(sprintf('-%d days', $days))->format('Y-m-d H:i:s');

        $orders = $this->orderCollectionFactory->create();
        $orders->addFieldToFilter('customer_id', $customerId);
        $orders->addFieldToFilter('created_at', ['gteq' => $since]);
        if ($orders->getSize() > 0) {
            return 'none';
        }

        $dedupKey = sprintf('%s:%d:%s', TaskType::NO_PURCHASE, $customerId, $period);
        if ($this->taskManagement->existsByDedupKey($dedupKey)) {
            return 'skipped';
        }

        $task = $this->taskManagement->createAutomatic([
            'dedup_key' => $dedupKey,
            'customer_id' => $customerId,
            'attendant_id' => $attendantId,
            'task_type' => TaskType::NO_PURCHASE,
            'priority' => 'normal',
            'title' => (string) __('Cliente sem compra há %1 dias', $days),
            'observation' => (string) __('Recompra pendente — última compra anterior a %1 dias.', $days),
            'due_at' => date('Y-m-d H:i:s', strtotime('+3 days')),
        ]);

        return $task ? 'created' : 'skipped';
    }

    private function ruleNewCustomerNoContact(
        int $customerId,
        int $attendantId,
        ?string $assignedAt,
        string $period
    ): string {
        if ($assignedAt === null || $assignedAt === '') {
            return 'none';
        }

        $days = $this->taskConfig->getDaysNewCustomerNoContact();
        $threshold = (new \DateTimeImmutable())->modify(sprintf('-%d days', $days));
        $assigned = new \DateTimeImmutable($assignedAt);
        if ($assigned > $threshold) {
            return 'none';
        }

        if ($this->hasRecentContact($customerId, $days)) {
            return 'none';
        }

        $dedupKey = sprintf('%s:%d:%s', TaskType::NEW_CUSTOMER_NO_CONTACT, $customerId, $period);
        if ($this->taskManagement->existsByDedupKey($dedupKey)) {
            return 'skipped';
        }

        $task = $this->taskManagement->createAutomatic([
            'dedup_key' => $dedupKey,
            'customer_id' => $customerId,
            'attendant_id' => $attendantId,
            'task_type' => TaskType::NEW_CUSTOMER_NO_CONTACT,
            'priority' => 'high',
            'title' => (string) __('Primeiro atendimento pendente'),
            'observation' => (string) __('Cliente novo na carteira sem contato registrado.'),
            'due_at' => date('Y-m-d H:i:s', strtotime('+1 day')),
        ]);

        return $task ? 'created' : 'skipped';
    }

    private function rulePendingNoContact(int $customerId, int $attendantId, string $period): string
    {
        $customer = $this->customerCollectionFactory->create();
        $customer->addFieldToFilter('entity_id', $customerId);
        $customer->addAttributeToSelect('b2b_approval_status');
        $item = $customer->getFirstItem();
        $status = (string) $item->getData('b2b_approval_status');
        if (!in_array($status, [ApprovalStatus::STATUS_PENDING, ApprovalStatus::STATUS_DATA_REVIEW], true)) {
            return 'none';
        }

        $days = $this->taskConfig->getDaysPendingNoContact();
        if ($this->hasRecentContact($customerId, $days)) {
            return 'none';
        }

        $dedupKey = sprintf('%s:%d:%s', TaskType::PENDING_NO_CONTACT, $customerId, $period);
        if ($this->taskManagement->existsByDedupKey($dedupKey)) {
            return 'skipped';
        }

        $task = $this->taskManagement->createAutomatic([
            'dedup_key' => $dedupKey,
            'customer_id' => $customerId,
            'attendant_id' => $attendantId,
            'task_type' => TaskType::PENDING_NO_CONTACT,
            'priority' => 'high',
            'title' => (string) __('Cliente B2B pendente sem contato'),
            'observation' => (string) __('Cliente aguardando aprovação B2B sem contato recente.'),
            'due_at' => date('Y-m-d H:i:s', strtotime('+1 day')),
        ]);

        return $task ? 'created' : 'skipped';
    }

    private function generateQuoteNoResponseTasks(string $period): int
    {
        $days = $this->taskConfig->getDaysQuoteNoResponse();
        $threshold = (new \DateTimeImmutable())->modify(sprintf('-%d days', $days))->format('Y-m-d H:i:s');
        $created = 0;

        $quotes = $this->quoteRequestCollectionFactory->create();
        $quotes->addFieldToFilter('status', ['in' => ['quoted', 'processing']]);
        $quotes->addFieldToFilter('updated_at', ['lteq' => $threshold]);

        foreach ($quotes as $quote) {
            $customerId = (int) $quote->getData('customer_id');
            if ($customerId <= 0) {
                continue;
            }

            $attendantId = $this->resolveAttendantForCustomer($customerId);
            if ($attendantId <= 0) {
                continue;
            }

            $requestId = (int) $quote->getData('request_id');
            $dedupKey = sprintf('%s:%d:%d:%s', TaskType::QUOTE_NO_RESPONSE, $customerId, $requestId, $period);
            if ($this->taskManagement->existsByDedupKey($dedupKey)) {
                continue;
            }

            $task = $this->taskManagement->createAutomatic([
                'dedup_key' => $dedupKey,
                'customer_id' => $customerId,
                'attendant_id' => $attendantId,
                'task_type' => TaskType::QUOTE_NO_RESPONSE,
                'priority' => 'normal',
                'title' => (string) __('Cotação sem retorno'),
                'observation' => (string) __('Cotação #%1 sem resposta há %2 dias.', $requestId, $days),
                'source_entity_type' => 'quote_request',
                'source_entity_id' => $requestId,
                'due_at' => date('Y-m-d H:i:s', strtotime('+2 days')),
            ]);

            if ($task) {
                $created++;
            }
        }

        return $created;
    }

    private function generateAbandonedCartTasks(string $period): int
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('grupoawamotos_abandoned_cart');
        if (!$connection->isTableExists($table)) {
            return 0;
        }

        $created = 0;
        $select = $connection->select()
            ->from($table)
            ->where('recovered = ?', 0)
            ->where('status != ?', 'recovered')
            ->where('commercial_contact_status != ?', 'treated');

        foreach ($connection->fetchAll($select) as $row) {
            $customerId = (int) ($row['customer_id'] ?? 0);
            $entityId = (int) ($row['entity_id'] ?? 0);
            if ($customerId <= 0 || $entityId <= 0) {
                continue;
            }

            $attendantId = (int) ($row['attendant_id'] ?? 0);
            if ($attendantId <= 0) {
                $attendantId = $this->resolveAttendantForCustomer($customerId);
            }
            if ($attendantId <= 0) {
                continue;
            }

            $dedupKey = sprintf('%s:%d:%d:%s', TaskType::ABANDONED_CART, $customerId, $entityId, $period);
            if ($this->taskManagement->existsByDedupKey($dedupKey)) {
                continue;
            }

            $task = $this->taskManagement->createAutomatic([
                'dedup_key' => $dedupKey,
                'customer_id' => $customerId,
                'attendant_id' => $attendantId,
                'task_type' => TaskType::ABANDONED_CART,
                'priority' => 'high',
                'title' => (string) __('Recuperar carrinho abandonado'),
                'observation' => (string) __('Carrinho abandonado no valor de R$ %1.', number_format((float) ($row['cart_value'] ?? 0), 2, ',', '.')),
                'source_entity_type' => 'abandoned_cart',
                'source_entity_id' => $entityId,
                'due_at' => date('Y-m-d H:i:s', strtotime('+1 day')),
            ]);

            if ($task) {
                $created++;
            }
        }

        return $created;
    }

    private function hasRecentContact(int $customerId, int $days): bool
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('grupoawamotos_b2b_contact_log');
        if (!$connection->isTableExists($table)) {
            return false;
        }

        $since = (new \DateTimeImmutable())->modify(sprintf('-%d days', $days))->format('Y-m-d H:i:s');
        $select = $connection->select()
            ->from($table, ['cnt' => new \Magento\Framework\DB\Sql\Expression('COUNT(*)')])
            ->where('customer_id = ?', $customerId)
            ->where('created_at >= ?', $since);

        return (int) $connection->fetchOne($select) > 0;
    }

    private function resolveAttendantForCustomer(int $customerId): int
    {
        $collection = $this->customerAttendantCollectionFactory->create();
        $collection->addFieldToFilter('customer_id', $customerId);
        $collection->setPageSize(1);
        $item = $collection->getFirstItem();

        return (int) $item->getData('attendant_id');
    }
}
