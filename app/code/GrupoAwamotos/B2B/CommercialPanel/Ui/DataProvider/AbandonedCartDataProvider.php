<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\CommercialPanel\Ui\DataProvider;

use GrupoAwamotos\AbandonedCart\Model\ResourceModel\AbandonedCart\CollectionFactory as AbandonedCartCollectionFactory;
use GrupoAwamotos\B2B\CommercialPanel\Api\PortfolioScopeInterface;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Ui\DataProvider\AbstractDataProvider;

class AbandonedCartDataProvider extends AbstractDataProvider
{
    /** @var array<int, array<string, mixed>>|null */
    private ?array $loadedItems = null;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        AbandonedCartCollectionFactory $collectionFactory,
        private readonly PortfolioScopeInterface $portfolioScope,
        private readonly ResourceConnection $resourceConnection,
        private readonly CustomerCollectionFactory $customerCollectionFactory,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        if ($this->loadedItems === null) {
            $this->loadedItems = $this->loadItems();
        }

        return [
            'totalRecords' => count($this->loadedItems),
            'items' => array_values($this->loadedItems),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadItems(): array
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('grupoawamotos_abandoned_cart');
        if (!$connection->isTableExists($table)) {
            return [];
        }

        $customerIds = $this->portfolioScope->getVisibleCustomerIds();
        if (!$this->portfolioScope->canBypassPortfolioScope() && $customerIds === []) {
            return [];
        }

        $attendantTable = $this->resourceConnection->getTableName('grupoawamotos_b2b_attendants');
        $select = $connection->select()
            ->from(['ac' => $table])
            ->joinLeft(
                ['a' => $attendantTable],
                'ac.attendant_id = a.attendant_id',
                ['attendant_name' => 'name']
            )
            ->where('ac.recovered = ?', 0)
            ->where('ac.status != ?', 'recovered')
            ->order('ac.abandoned_at DESC');

        if (!$this->portfolioScope->canBypassPortfolioScope()) {
            $select->where('ac.customer_id IN (?)', $customerIds);
        }

        $rows = $connection->fetchAll($select);
        if ($rows === []) {
            return [];
        }

        $ids = array_values(array_filter(array_map(
            static fn (array $row): int => (int) ($row['customer_id'] ?? 0),
            $rows
        )));
        $customerData = $this->loadCustomerData($ids);

        $items = [];
        foreach ($rows as $row) {
            $customerId = (int) ($row['customer_id'] ?? 0);
            $extra = $customerData[$customerId] ?? [];
            $items[] = array_merge($row, [
                'entity_id' => (int) $row['entity_id'],
                'b2b_cnpj' => $extra['b2b_cnpj'] ?? null,
                'b2b_phone' => $extra['b2b_phone'] ?? null,
                'customer_display' => $row['customer_name'] ?? $extra['name'] ?? $row['customer_email'] ?? '',
            ]);
        }

        return $items;
    }

    /**
     * @param int[] $customerIds
     * @return array<int, array<string, string|null>>
     */
    private function loadCustomerData(array $customerIds): array
    {
        $customerIds = array_values(array_filter(array_unique($customerIds)));
        if ($customerIds === []) {
            return [];
        }

        $collection = $this->customerCollectionFactory->create();
        $collection->addFieldToFilter('entity_id', ['in' => $customerIds]);
        $collection->addAttributeToSelect(['firstname', 'lastname', 'b2b_cnpj', 'b2b_phone']);

        $result = [];
        foreach ($collection as $customer) {
            $result[(int) $customer->getId()] = [
                'name' => trim($customer->getFirstname() . ' ' . $customer->getLastname()),
                'b2b_cnpj' => $customer->getData('b2b_cnpj'),
                'b2b_phone' => $customer->getData('b2b_phone'),
            ];
        }

        return $result;
    }
}
