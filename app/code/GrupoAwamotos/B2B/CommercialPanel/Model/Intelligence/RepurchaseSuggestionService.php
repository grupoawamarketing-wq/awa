<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\CommercialPanel\Model\Intelligence;

use GrupoAwamotos\B2B\CommercialPanel\Api\PortfolioScopeInterface;
use GrupoAwamotos\B2B\CommercialPanel\Model\Config\TaskConfig;
use GrupoAwamotos\B2B\Model\ResourceModel\CustomerAttendant\CollectionFactory as CustomerAttendantCollectionFactory;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Framework\App\ResourceConnection;

/**
 * Sugestões de recompra baseadas em histórico de compra da carteira visível.
 */
class RepurchaseSuggestionService
{
    public function __construct(
        private readonly PortfolioScopeInterface $portfolioScope,
        private readonly CustomerAttendantCollectionFactory $customerAttendantCollectionFactory,
        private readonly CustomerCollectionFactory $customerCollectionFactory,
        private readonly CustomerOrderInsightService $orderInsightService,
        private readonly ResourceConnection $resourceConnection,
        private readonly TaskConfig $taskConfig
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getSuggestions(int $limit = 200): array
    {
        $customerIds = $this->portfolioScope->getVisibleCustomerIds();
        if ($customerIds === []) {
            return [];
        }

        $mappings = $this->loadCustomerMappings($customerIds);
        $orderStats = $this->orderInsightService->getOrderSummaryByCustomer($customerIds);
        $customerNames = $this->loadCustomerNames($customerIds);
        $attendantNames = $this->loadAttendantNames();
        $minDays = $this->taskConfig->getDaysNoPurchase();

        $items = [];
        foreach ($customerIds as $customerId) {
            $stats = $orderStats[$customerId] ?? null;
            if ($stats === null || $stats['last_order_at'] === null) {
                continue;
            }

            $daysSince = $this->orderInsightService->getDaysSince($stats['last_order_at']);
            if ($daysSince === null || $daysSince < $minDays) {
                continue;
            }

            $mapping = $mappings[$customerId] ?? [];
            $attendantId = (int) ($mapping['attendant_id'] ?? 0);
            $products = $this->orderInsightService->getTopProductsForCustomer($customerId, 3);
            $productLabels = array_map(
                static fn (array $p): string => $p['name'],
                $products
            );

            $name = $customerNames[$customerId] ?? '';
            $items[] = [
                'entity_id' => $customerId,
                'customer_display' => $name,
                'last_order_at' => $stats['last_order_at'],
                'last_increment_id' => $stats['last_increment_id'],
                'days_since_purchase' => $daysSince,
                'avg_order_value' => $stats['avg_total'],
                'products_summary' => implode('; ', $productLabels),
                'attendant_id' => $attendantId,
                'attendant_name' => $attendantNames[$attendantId] ?? '',
                'suggested_action' => $this->resolveSuggestedAction($daysSince, $products),
            ];
        }

        usort($items, static fn (array $a, array $b): int => ($b['days_since_purchase'] ?? 0) <=> ($a['days_since_purchase'] ?? 0));

        return array_slice($items, 0, $limit);
    }

    /**
     * @param array<int, array{sku: string, name: string, qty: float}> $products
     */
    private function resolveSuggestedAction(int $daysSince, array $products): string
    {
        if ($daysSince >= 120) {
            return (string) __('Reativar cliente — sem compra há %1 dias', $daysSince);
        }

        if ($daysSince >= 60) {
            return (string) __('Contato de recompra — verificar necessidade de reposição');
        }

        if ($products !== []) {
            return (string) __('Sugerir recompra: %1', $products[0]['name']);
        }

        return (string) __('Contato de relacionamento e recompra');
    }

    /**
     * @param int[] $customerIds
     * @return array<int, array<string, mixed>>
     */
    private function loadCustomerMappings(array $customerIds): array
    {
        $collection = $this->customerAttendantCollectionFactory->create();
        $collection->addFieldToFilter('customer_id', ['in' => $customerIds]);

        $result = [];
        foreach ($collection as $item) {
            $result[(int) $item->getData('customer_id')] = $item->getData();
        }

        return $result;
    }

    /**
     * @param int[] $customerIds
     * @return array<int, string>
     */
    private function loadCustomerNames(array $customerIds): array
    {
        $collection = $this->customerCollectionFactory->create();
        $collection->addFieldToFilter('entity_id', ['in' => $customerIds]);
        $collection->addAttributeToSelect(['firstname', 'lastname', 'b2b_razao_social']);

        $result = [];
        foreach ($collection as $customer) {
            $id = (int) $customer->getId();
            $name = trim($customer->getFirstname() . ' ' . $customer->getLastname());
            if ($customer->getData('b2b_razao_social')) {
                $name = (string) $customer->getData('b2b_razao_social');
            }
            $result[$id] = $name;
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
