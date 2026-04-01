<?php

declare(strict_types=1);

namespace GrupoAwamotos\SmartSuggestions\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * RFM Cache Resource Model
 */
class RfmCache extends AbstractDb
{
    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init('smart_suggestions_rfm_cache', 'rfm_id');
    }

    /**
     * Get cache by ERP Customer ID
     */
    public function loadByErpCustomerId(\GrupoAwamotos\SmartSuggestions\Model\RfmCache $object, int $erpCustomerId): self
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable())
            ->where('erp_customer_id = ?', $erpCustomerId);

        $data = $connection->fetchRow($select);
        if ($data) {
            $object->setData($data);
        }

        return $this;
    }

    /**
     * Bulk upsert RFM cache entries
     */
    public function bulkUpsert(array $entries): int
    {
        if (empty($entries)) {
            return 0;
        }

        $connection = $this->getConnection();
        $tableName = $this->getMainTable();
        $tableColumns = array_keys($connection->describeTable($tableName));
        $availableColumns = array_fill_keys($tableColumns, true);

        $normalized = [];
        foreach ($entries as $entry) {
            $row = $this->normalizeEntry($entry);
            $row = array_intersect_key($row, $availableColumns);

            if (empty($row['erp_customer_id'])) {
                continue;
            }

            if (empty($row['customer_name'])) {
                $row['customer_name'] = 'Cliente #' . (int) $row['erp_customer_id'];
            }

            $normalized[] = $row;
        }

        if (empty($normalized)) {
            return 0;
        }

        // Prepare columns for ON DUPLICATE KEY UPDATE
        $updateColumns = array_values(array_filter([
            'customer_name',
            'customer_cnpj',
            'customer_phone',
            'customer_city',
            'customer_uf',
            'r_score',
            'f_score',
            'm_score',
            'rfm_score',
            'segment',
            'recency_days',
            'frequency',
            'monetary',
            'last_order_date',
            'calculated_at'
        ], static fn(string $column): bool => isset($availableColumns[$column])));

        return $connection->insertOnDuplicate(
            $tableName,
            $normalized,
            $updateColumns
        );
    }

    /**
     * Clear expired cache entries
     */
    public function clearExpired(int $maxAgeHours = 48): int
    {
        $connection = $this->getConnection();
        $cutoffTime = date('Y-m-d H:i:s', time() - ($maxAgeHours * 3600));

        return $connection->delete(
            $this->getMainTable(),
            ['calculated_at < ?' => $cutoffTime]
        );
    }

    /**
     * Normalize legacy keys to current db schema keys.
     */
    private function normalizeEntry(array $entry): array
    {
        if (isset($entry['total_orders']) && !isset($entry['frequency'])) {
            $entry['frequency'] = $entry['total_orders'];
        }
        if (isset($entry['total_revenue']) && !isset($entry['monetary'])) {
            $entry['monetary'] = $entry['total_revenue'];
        }
        if (isset($entry['last_purchase_date']) && !isset($entry['last_order_date'])) {
            $entry['last_order_date'] = $entry['last_purchase_date'];
        }
        if (isset($entry['updated_at']) && !isset($entry['calculated_at'])) {
            $entry['calculated_at'] = $entry['updated_at'];
        }
        if (isset($entry['created_at']) && !isset($entry['calculated_at'])) {
            $entry['calculated_at'] = $entry['created_at'];
        }

        return $entry;
    }
}
