<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Ui;

use GrupoAwamotos\B2B\Model\Customer\Attribute\Source\ErpCustomerSyncStatus;
use GrupoAwamotos\B2B\Model\Customer\ErpPendingQueueResolver;
use Magento\Framework\App\ResourceConnection;

/**
 * Enriches B2B customer grid rows with attendant, sync and block-reason data.
 */
class CustomerGridEnricher
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly ErpPendingQueueResolver $queueResolver
    ) {
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    public function enrichRows(array $items): array
    {
        if ($items === []) {
            return [];
        }

        $customerIds = array_values(array_filter(array_map(
            static fn (array $row): int => (int) ($row['entity_id'] ?? 0),
            $items
        )));

        $attendants = $this->loadAttendants($customerIds);
        $lastSync = $this->loadLastSyncTimes($customerIds);
        $statusLabels = $this->loadErpStatusLabels();

        foreach ($items as &$item) {
            $customerId = (int) ($item['entity_id'] ?? 0);
            $erpStatus = (string) ($item['erp_customer_sync_status'] ?? '');

            $item['customer_name'] = trim(
                (string) ($item['firstname'] ?? '') . ' ' . (string) ($item['lastname'] ?? '')
            );
            $item['attendant_name'] = $attendants[$customerId]['name'] ?? '';
            $item['attendant_id'] = $attendants[$customerId]['attendant_id'] ?? null;
            $item['last_erp_sync_display'] = $item['b2b_last_erp_sync_at'] ?? ($lastSync[$customerId] ?? '');
            $item['erp_customer_sync_status_label'] = $statusLabels[$erpStatus] ?? ($erpStatus ?: '—');
            $item['erp_block_reason'] = $this->queueResolver->resolveBlockReason(
                $erpStatus,
                (string) ($item['b2b_approval_status'] ?? ''),
                false
            );
        }
        unset($item);

        return $items;
    }

    /**
     * @param int[] $customerIds
     * @return array<int, array{attendant_id: int, name: string}>
     */
    private function loadAttendants(array $customerIds): array
    {
        if ($customerIds === []) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->from(['ca' => $this->resourceConnection->getTableName('grupoawamotos_b2b_customer_attendant')], ['customer_id', 'attendant_id'])
                ->join(
                    ['att' => $this->resourceConnection->getTableName('grupoawamotos_b2b_attendants')],
                    'att.attendant_id = ca.attendant_id',
                    ['name']
                )
                ->where('ca.customer_id IN (?)', $customerIds)
        );

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['customer_id']] = [
                'attendant_id' => (int) $row['attendant_id'],
                'name' => (string) $row['name'],
            ];
        }

        return $result;
    }

    /**
     * @param int[] $customerIds
     * @return array<int, string>
     */
    private function loadLastSyncTimes(array $customerIds): array
    {
        if ($customerIds === []) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $rows = $connection->fetchPairs(
            $connection->select()
                ->from($this->resourceConnection->getTableName('grupoawamotos_b2b_sectra_sync_log'), [
                    'customer_id',
                    'last_at' => new \Magento\Framework\DB\Sql\Expression('MAX(created_at)'),
                ])
                ->where('customer_id IN (?)', $customerIds)
                ->group('customer_id')
        );

        return array_map('strval', $rows);
    }

    /**
     * @return array<string, string>
     */
    private function loadErpStatusLabels(): array
    {
        $source = new ErpCustomerSyncStatus();
        $labels = [];
        foreach ($source->getAllOptions() as $option) {
            $labels[(string) $option['value']] = (string) $option['label'];
        }
        $labels['pending_erp_validation'] = (string) __('Pendente validação ERP');

        return $labels;
    }
}
