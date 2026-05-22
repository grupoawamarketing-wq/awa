<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\CommercialPanel\Ui\DataProvider;

use GrupoAwamotos\B2B\CommercialPanel\Api\PortfolioScopeInterface;
use GrupoAwamotos\B2B\CommercialPanel\Model\TaskType;
use GrupoAwamotos\B2B\CommercialPanel\Model\ResourceModel\CommercialTask\CollectionFactory as TaskCollectionFactory;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Ui\DataProvider\AbstractDataProvider;

class TaskDataProvider extends AbstractDataProvider
{
    /** @var \GrupoAwamotos\B2B\CommercialPanel\Model\ResourceModel\CommercialTask\Collection */
    protected $collection;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        TaskCollectionFactory $collectionFactory,
        private readonly PortfolioScopeInterface $portfolioScope,
        private readonly CustomerCollectionFactory $customerCollectionFactory,
        private readonly ResourceConnection $resourceConnection,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
        $this->applyScopeFilter();
        $this->collection->setOrder('due_at', 'ASC');
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        if (!$this->collection->isLoaded()) {
            $this->collection->load();
        }

        $items = [];
        foreach ($this->collection as $task) {
            $row = $task->getData();
            $row['customer_name'] = $this->resolveCustomerName((int) $task->getData('customer_id'));
            $row['attendant_name'] = $this->resolveAttendantName((int) $task->getData('attendant_id'));
            $row['task_type_label'] = $this->resolveTaskTypeLabel((string) $task->getData('task_type'));
            $row['source_label'] = ($task->getData('created_by') !== null && $task->getData('created_by') !== '')
                ? (string) __('Manual')
                : (string) __('Automática');
            $items[] = $row;
        }

        return [
            'totalRecords' => $this->collection->getSize(),
            'items' => $items,
        ];
    }

    private function applyScopeFilter(): void
    {
        if ($this->portfolioScope->canBypassPortfolioScope()) {
            return;
        }

        $attendantIds = $this->portfolioScope->getVisibleAttendantIds();
        if ($attendantIds === []) {
            $this->collection->addFieldToFilter('task_id', 0);
            return;
        }

        $this->collection->addFieldToFilter('attendant_id', ['in' => $attendantIds]);
    }

    private function resolveCustomerName(int $customerId): string
    {
        static $cache = [];
        if (isset($cache[$customerId])) {
            return $cache[$customerId];
        }

        $collection = $this->customerCollectionFactory->create();
        $collection->addFieldToFilter('entity_id', $customerId);
        $collection->addAttributeToSelect(['firstname', 'lastname']);
        $customer = $collection->getFirstItem();
        $cache[$customerId] = trim($customer->getFirstname() . ' ' . $customer->getLastname());

        return $cache[$customerId];
    }

    private function resolveAttendantName(int $attendantId): string
    {
        static $cache = [];
        if (isset($cache[$attendantId])) {
            return $cache[$attendantId];
        }

        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('grupoawamotos_b2b_attendants');
        $name = (string) $connection->fetchOne(
            $connection->select()->from($table, ['name'])->where('attendant_id = ?', $attendantId)
        );
        $cache[$attendantId] = $name;

        return $name;
    }

    private function resolveTaskTypeLabel(string $type): string
    {
        $labels = [
            TaskType::NO_PURCHASE => (string) __('Sem compra'),
            TaskType::PENDING_NO_CONTACT => (string) __('Pendente sem contato'),
            TaskType::QUOTE_NO_RESPONSE => (string) __('Cotação sem retorno'),
            TaskType::ABANDONED_CART => (string) __('Carrinho abandonado'),
            TaskType::NEW_CUSTOMER_NO_CONTACT => (string) __('Novo sem atendimento'),
            TaskType::MANUAL => (string) __('Manual'),
        ];

        return $labels[$type] ?? $type;
    }
}
