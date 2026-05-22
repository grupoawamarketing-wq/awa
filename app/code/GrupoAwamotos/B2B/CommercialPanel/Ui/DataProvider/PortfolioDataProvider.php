<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\CommercialPanel\Ui\DataProvider;

use GrupoAwamotos\B2B\CommercialPanel\Api\PortfolioScopeInterface;
use GrupoAwamotos\B2B\CommercialPanel\Model\Config\TaskConfig;
use GrupoAwamotos\B2B\Model\ResourceModel\CustomerAttendant\CollectionFactory as CustomerAttendantCollectionFactory;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Ui\DataProvider\AbstractDataProvider;

class PortfolioDataProvider extends AbstractDataProvider
{
    /** @var \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection */
    protected $collection;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        private readonly PortfolioScopeInterface $portfolioScope,
        private readonly CustomerAttendantCollectionFactory $customerAttendantCollectionFactory,
        private readonly CustomerCollectionFactory $customerCollectionFactory,
        private readonly ResourceConnection $resourceConnection,
        private readonly TaskConfig $taskConfig,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $this->customerAttendantCollectionFactory->create();
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        $customerIds = $this->portfolioScope->getVisibleCustomerIds();
        if ($customerIds === []) {
            return ['totalRecords' => 0, 'items' => []];
        }

        $mappingCollection = $this->customerAttendantCollectionFactory->create();
        $mappingCollection->addFieldToFilter('customer_id', ['in' => $customerIds]);
        $mappingCollection->setOrder('assigned_at', 'DESC');

        $mappings = [];
        foreach ($mappingCollection as $item) {
            $mappings[(int) $item->getData('customer_id')] = $item->getData();
        }

        $customerCollection = $this->customerCollectionFactory->create();
        $customerCollection->addFieldToFilter('entity_id', ['in' => $customerIds]);
        $customerCollection->addAttributeToSelect([
            'firstname',
            'lastname',
            'email',
            'b2b_cnpj',
            'b2b_razao_social',
            'b2b_approval_status',
        ]);

        $attendantNames = $this->loadAttendantNames();
        $orderStats = $this->loadOrderStats($customerIds);
        $contactStats = $this->loadLastContactDates($customerIds);
        $cities = $this->loadCustomerCities($customerIds);
        $daysNoPurchase = $this->taskConfig->getDaysNoPurchase();
        $daysNoContact = $this->taskConfig->getDaysNewCustomerNoContact();
        $purchaseThreshold = (new \DateTimeImmutable())->modify(sprintf('-%d days', $daysNoPurchase))->format('Y-m-d H:i:s');
        $contactThreshold = (new \DateTimeImmutable())->modify(sprintf('-%d days', $daysNoContact))->format('Y-m-d H:i:s');

        $items = [];
        foreach ($customerCollection as $customer) {
            $customerId = (int) $customer->getId();
            $mapping = $mappings[$customerId] ?? [];
            $attendantId = (int) ($mapping['attendant_id'] ?? 0);
            $lastOrder = $orderStats[$customerId]['last_order_at'] ?? null;
            $lastContact = $contactStats[$customerId] ?? null;
            $noPurchase = $lastOrder === null || $lastOrder < $purchaseThreshold;
            $noContact = $lastContact === null || $lastContact < $contactThreshold;

            $displayName = trim($customer->getFirstname() . ' ' . $customer->getLastname());
            if ($customer->getData('b2b_razao_social')) {
                $displayName = (string) $customer->getData('b2b_razao_social');
            }

            $items[] = [
                'entity_id' => $customerId,
                'customer_display' => $displayName,
                'firstname' => $customer->getFirstname(),
                'lastname' => $customer->getLastname(),
                'email' => $customer->getEmail(),
                'b2b_cnpj' => $customer->getData('b2b_cnpj'),
                'b2b_razao_social' => $customer->getData('b2b_razao_social'),
                'b2b_approval_status' => $customer->getData('b2b_approval_status'),
                'commercial_status' => $mapping['commercial_status'] ?? null,
                'assigned_at' => $mapping['assigned_at'] ?? null,
                'attendant_name' => $attendantNames[$attendantId] ?? '',
                'city' => $cities[$customerId] ?? '',
                'last_order_at' => $lastOrder,
                'last_contact_at' => $lastContact,
                'no_purchase_flag' => $noPurchase ? 1 : 0,
                'no_contact_flag' => $noContact ? 1 : 0,
            ];
        }

        return [
            'totalRecords' => count($items),
            'items' => $items,
        ];
    }

    /**
     * @param int[] $customerIds
     * @return array<int, array{last_order_at: ?string}>
     */
    private function loadOrderStats(array $customerIds): array
    {
        $connection = $this->resourceConnection->getConnection();
        $orderTable = $this->resourceConnection->getTableName('sales_order');

        $rows = $connection->fetchAll(
            $connection->select()
                ->from($orderTable, [
                    'customer_id',
                    'last_order_at' => new \Magento\Framework\DB\Sql\Expression('MAX(created_at)'),
                ])
                ->where('customer_id IN (?)', $customerIds)
                ->where('state NOT IN (?)', ['canceled'])
                ->group('customer_id')
        );

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['customer_id']] = [
                'last_order_at' => $row['last_order_at'],
            ];
        }

        return $result;
    }

    /**
     * @param int[] $customerIds
     * @return array<int, string>
     */
    private function loadLastContactDates(array $customerIds): array
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('grupoawamotos_b2b_contact_log');
        if (!$connection->isTableExists($table)) {
            return [];
        }

        $rows = $connection->fetchPairs(
            $connection->select()
                ->from($table, [
                    'customer_id',
                    'last_contact' => new \Magento\Framework\DB\Sql\Expression('MAX(created_at)'),
                ])
                ->where('customer_id IN (?)', $customerIds)
                ->group('customer_id')
        );

        $result = [];
        foreach ($rows as $customerId => $date) {
            $result[(int) $customerId] = (string) $date;
        }

        return $result;
    }

    /**
     * @param int[] $customerIds
     * @return array<int, string>
     */
    private function loadCustomerCities(array $customerIds): array
    {
        $connection = $this->resourceConnection->getConnection();
        $addressTable = $this->resourceConnection->getTableName('customer_address_entity');

        $rows = $connection->fetchAll(
            $connection->select()
                ->from($addressTable, ['parent_id', 'city'])
                ->where('parent_id IN (?)', $customerIds)
                ->order('entity_id ASC')
        );

        $result = [];
        foreach ($rows as $row) {
            $parentId = (int) $row['parent_id'];
            if (!isset($result[$parentId]) && !empty($row['city'])) {
                $result[$parentId] = (string) $row['city'];
            }
        }

        return $result;
    }

    /**
     * @return array<int, string>
     */
    private function loadAttendantNames(): array
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('grupoawamotos_b2b_attendants');
        $rows = $connection->fetchPairs(
            $connection->select()->from($table, ['attendant_id', 'name'])
        );

        $result = [];
        foreach ($rows as $id => $name) {
            $result[(int) $id] = (string) $name;
        }

        return $result;
    }
}
