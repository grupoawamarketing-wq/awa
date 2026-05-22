<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\CommercialPanel\Model;

use GrupoAwamotos\B2B\CommercialPanel\Model\Config\TaskConfig;
use GrupoAwamotos\B2B\CommercialPanel\Model\ResourceModel\CommercialTask\CollectionFactory as TaskCollectionFactory;
use GrupoAwamotos\B2B\CommercialPanel\Model\ResourceModel\ContactLog\CollectionFactory as ContactLogCollectionFactory;
use GrupoAwamotos\B2B\Model\ResourceModel\CustomerAttendant\CollectionFactory as CustomerAttendantCollectionFactory;
use GrupoAwamotos\B2B\Model\ResourceModel\QuoteRequest\CollectionFactory as QuoteRequestCollectionFactory;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;

/**
 * Agregador read-only da Ficha 360° comercial.
 */
class Customer360DataProvider
{
    /** @var string[] */
    private const OPEN_STATUSES = ['open', 'in_progress'];

    /** @var array<string, string> */
    private const CONTACT_TYPE_LABELS = [
        'whatsapp' => 'WhatsApp',
        'phone' => 'Ligação',
        'email' => 'E-mail',
        'visit' => 'Visita',
        'chat' => 'Chat',
        'other' => 'Outro',
    ];

    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly CustomerAttendantCollectionFactory $customerAttendantCollectionFactory,
        private readonly ContactLogCollectionFactory $contactLogCollectionFactory,
        private readonly TaskCollectionFactory $taskCollectionFactory,
        private readonly QuoteRequestCollectionFactory $quoteRequestCollectionFactory,
        private readonly OrderCollectionFactory $orderCollectionFactory,
        private readonly ResourceConnection $resourceConnection,
        private readonly TaskConfig $taskConfig
    ) {
    }

    /**
     * @return array<string, mixed>
     * @throws NoSuchEntityException
     */
    public function getCustomerData(int $customerId): array
    {
        $customer = $this->customerRepository->getById($customerId);
        $purchaseStats = $this->getPurchaseStats($customerId);

        return array_merge([
            'entity_id' => $customerId,
            'firstname' => $customer->getFirstname(),
            'lastname' => $customer->getLastname(),
            'email' => $customer->getEmail(),
            'created_at' => $customer->getCreatedAt(),
            'b2b_cnpj' => $this->getCustomAttribute($customer, 'b2b_cnpj'),
            'b2b_razao_social' => $this->getCustomAttribute($customer, 'b2b_razao_social'),
            'b2b_phone' => $this->getCustomAttribute($customer, 'b2b_phone'),
            'b2b_approval_status' => $this->getCustomAttribute($customer, 'b2b_approval_status'),
            'b2b_approval_score' => $this->getCustomAttribute($customer, 'b2b_approval_score'),
            'erp_code' => $this->getCustomAttribute($customer, 'erp_code'),
            'city' => $this->getCustomerCity($customerId),
        ], $purchaseStats);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPortfolioAssignment(int $customerId): ?array
    {
        $collection = $this->customerAttendantCollectionFactory->create();
        $collection->addFieldToFilter('customer_id', $customerId);
        $collection->setPageSize(1);
        $item = $collection->getFirstItem();

        if (!$item->getId()) {
            return null;
        }

        $attendantName = '';
        $attendantId = (int) $item->getData('attendant_id');
        if ($attendantId > 0) {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName('grupoawamotos_b2b_attendants');
            $attendantName = (string) $connection->fetchOne(
                $connection->select()->from($table, ['name'])->where('attendant_id = ?', $attendantId)
            );
        }

        return [
            'attendant_id' => $attendantId,
            'attendant_name' => $attendantName,
            'commercial_status' => $item->getData('commercial_status'),
            'assigned_at' => $item->getData('assigned_at'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getContactHistory(int $customerId, int $limit = 50): array
    {
        $collection = $this->contactLogCollectionFactory->create();
        $collection->addFieldToFilter('customer_id', $customerId);
        $collection->setOrder('created_at', 'DESC');
        $collection->setPageSize($limit);

        $items = $collection->toArray()['items'] ?? [];
        $now = time();

        foreach ($items as &$item) {
            $type = (string) ($item['contact_type'] ?? '');
            $item['contact_type_label'] = self::CONTACT_TYPE_LABELS[$type] ?? $type;
            $item['status_label'] = $this->resolveContactStatusLabel($item, $now);
        }

        return $items;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRecentOrders(int $customerId, int $limit = 20): array
    {
        $collection = $this->orderCollectionFactory->create();
        $collection->addFieldToFilter('customer_id', $customerId);
        $collection->setOrder('created_at', 'DESC');
        $collection->setPageSize($limit);

        $result = [];
        foreach ($collection as $order) {
            $result[] = [
                'entity_id' => (int) $order->getId(),
                'increment_id' => (string) $order->getIncrementId(),
                'grand_total' => (float) $order->getGrandTotal(),
                'status' => (string) $order->getStatus(),
                'created_at' => (string) $order->getCreatedAt(),
            ];
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getQuoteRequests(int $customerId, int $limit = 20): array
    {
        $collection = $this->quoteRequestCollectionFactory->create();
        $collection->addFieldToFilter('customer_id', $customerId);
        $collection->setOrder('created_at', 'DESC');
        $collection->setPageSize($limit);

        return $collection->toArray()['items'] ?? [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAbandonedCarts(int $customerId, int $limit = 10): array
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('grupoawamotos_abandoned_cart');
        if (!$connection->isTableExists($table)) {
            return [];
        }

        $select = $connection->select()
            ->from($table)
            ->where('customer_id = ?', $customerId)
            ->where('recovered = ?', 0)
            ->where('status != ?', 'recovered')
            ->order('abandoned_at DESC')
            ->limit($limit);

        return $connection->fetchAll($select);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getOpenTasks(int $customerId, int $limit = 20): array
    {
        $collection = $this->taskCollectionFactory->create();
        $collection->addFieldToFilter('customer_id', $customerId);
        $collection->addFieldToFilter('status', ['in' => self::OPEN_STATUSES]);
        $collection->setOrder('due_at', 'ASC');
        $collection->setPageSize($limit);

        $items = $collection->toArray()['items'] ?? [];
        $typeLabels = $this->getTaskTypeLabels();

        foreach ($items as &$item) {
            $type = (string) ($item['task_type'] ?? '');
            $item['task_type_label'] = $typeLabels[$type] ?? $type;
            $item['source_label'] = $this->resolveTaskSourceLabel($item);
        }

        return $items;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRepurchaseSuggestions(int $customerId, int $limit = 10): array
    {
        $connection = $this->resourceConnection->getConnection();
        $orderTable = $this->resourceConnection->getTableName('sales_order');
        $itemTable = $this->resourceConnection->getTableName('sales_order_item');

        $since = (new \DateTimeImmutable())->modify('-90 days')->format('Y-m-d H:i:s');

        $recentSkus = $connection->fetchCol(
            $connection->select()
                ->from(['oi' => $itemTable], ['sku'])
                ->join(['o' => $orderTable], 'oi.order_id = o.entity_id', [])
                ->where('o.customer_id = ?', $customerId)
                ->where('o.created_at >= ?', $since)
                ->where('oi.parent_item_id IS NULL')
                ->group('oi.sku')
        );

        $select = $connection->select()
            ->from(['oi' => $itemTable], [
                'sku',
                'name',
                'total_qty' => new \Magento\Framework\DB\Sql\Expression('SUM(oi.qty_ordered)'),
                'last_purchased' => new \Magento\Framework\DB\Sql\Expression('MAX(o.created_at)'),
            ])
            ->join(['o' => $orderTable], 'oi.order_id = o.entity_id', [])
            ->where('o.customer_id = ?', $customerId)
            ->where('oi.parent_item_id IS NULL')
            ->group(['oi.sku', 'oi.name'])
            ->order('last_purchased DESC')
            ->limit($limit * 3);

        if ($recentSkus !== []) {
            $select->where('oi.sku NOT IN (?)', $recentSkus);
        }

        $rows = $connection->fetchAll($select);

        return array_slice($rows, 0, $limit);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTopProducts(int $customerId, int $limit = 5): array
    {
        $connection = $this->resourceConnection->getConnection();
        $orderTable = $this->resourceConnection->getTableName('sales_order');
        $itemTable = $this->resourceConnection->getTableName('sales_order_item');

        $select = $connection->select()
            ->from(['oi' => $itemTable], [
                'sku',
                'name',
                'total_qty' => new \Magento\Framework\DB\Sql\Expression('SUM(oi.qty_ordered)'),
            ])
            ->join(['o' => $orderTable], 'oi.order_id = o.entity_id', [])
            ->where('o.customer_id = ?', $customerId)
            ->where('oi.parent_item_id IS NULL')
            ->group(['oi.sku', 'oi.name'])
            ->order('total_qty DESC')
            ->limit($limit);

        return $connection->fetchAll($select);
    }

    /**
     * @return array<string, mixed>
     */
    private function getPurchaseStats(int $customerId): array
    {
        $connection = $this->resourceConnection->getConnection();
        $orderTable = $this->resourceConnection->getTableName('sales_order');

        $row = $connection->fetchRow(
            $connection->select()
                ->from($orderTable, [
                    'orders_count' => new \Magento\Framework\DB\Sql\Expression('COUNT(*)'),
                    'total_spent' => new \Magento\Framework\DB\Sql\Expression('COALESCE(SUM(grand_total), 0)'),
                    'last_order_at' => new \Magento\Framework\DB\Sql\Expression('MAX(created_at)'),
                ])
                ->where('customer_id = ?', $customerId)
                ->where('state NOT IN (?)', ['canceled'])
        ) ?: [];

        $ordersCount = (int) ($row['orders_count'] ?? 0);
        $totalSpent = (float) ($row['total_spent'] ?? 0);

        return [
            'orders_count' => $ordersCount,
            'total_spent' => $totalSpent,
            'average_ticket' => $ordersCount > 0 ? round($totalSpent / $ordersCount, 2) : 0.0,
            'last_order_at' => $row['last_order_at'] ?? null,
            'top_products' => $this->getTopProducts($customerId),
        ];
    }

    private function getCustomerCity(int $customerId): ?string
    {
        $connection = $this->resourceConnection->getConnection();
        $addressTable = $this->resourceConnection->getTableName('customer_address_entity');

        $city = $connection->fetchOne(
            $connection->select()
                ->from($addressTable, ['city'])
                ->where('parent_id = ?', $customerId)
                ->order('entity_id ASC')
                ->limit(1)
        );

        return $city !== false && $city !== '' ? (string) $city : null;
    }

    /**
     * @param array<string, mixed> $contact
     */
    private function resolveContactStatusLabel(array $contact, int $now): string
    {
        $nextAt = $contact['next_action_at'] ?? null;
        if ($nextAt === null || $nextAt === '') {
            return (string) __('Realizado');
        }

        $timestamp = strtotime((string) $nextAt);

        return $timestamp !== false && $timestamp > $now
            ? (string) __('Agendado')
            : (string) __('Pendente');
    }

    /**
     * @param array<string, mixed> $task
     */
    private function resolveTaskSourceLabel(array $task): string
    {
        $createdBy = $task['created_by'] ?? null;

        return $createdBy !== null && $createdBy !== ''
            ? (string) __('Manual')
            : (string) __('Automática');
    }

    /**
     * @return array<string, string>
     */
    private function getTaskTypeLabels(): array
    {
        return [
            TaskType::NO_PURCHASE => (string) __('Sem compra'),
            TaskType::PENDING_NO_CONTACT => (string) __('Pendente sem contato'),
            TaskType::QUOTE_NO_RESPONSE => (string) __('Cotação sem retorno'),
            TaskType::ABANDONED_CART => (string) __('Carrinho abandonado'),
            TaskType::NEW_CUSTOMER_NO_CONTACT => (string) __('Novo sem atendimento'),
            TaskType::MANUAL => (string) __('Manual'),
        ];
    }

    private function getCustomAttribute(CustomerInterface $customer, string $code): ?string
    {
        $attribute = $customer->getCustomAttribute($code);
        if ($attribute === null) {
            return null;
        }

        $value = $attribute->getValue();

        return $value !== null && $value !== '' ? (string) $value : null;
    }
}
