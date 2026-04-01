<?php

/**
 * Modifier to load B2B credit data into customer edit form
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Ui\Component\Customer\Form;

use Magento\Framework\App\ResourceConnection;
use Magento\Ui\DataProvider\Modifier\ModifierInterface;

class CreditModifier implements ModifierInterface
{
    private ResourceConnection $resource;

    public function __construct(ResourceConnection $resource)
    {
        $this->resource = $resource;
    }

    /**
     * @inheritDoc
     */
    public function modifyData(array $data): array
    {
        foreach ($data as $customerId => &$customerData) {
            if (!is_numeric($customerId)) {
                continue;
            }

            $creditData = $this->getCreditData((int)$customerId);
            $customerData['b2b_credit'] = $creditData;
        }

        return $data;
    }

    /**
     * @inheritDoc
     */
    public function modifyMeta(array $meta): array
    {
        return $meta;
    }

    private function getCreditData(int $customerId): array
    {
        $connection = $this->resource->getConnection();
        $tableName = $this->resource->getTableName('grupoawamotos_b2b_credit_limit');

        $select = $connection->select()
            ->from($tableName)
            ->where('customer_id = ?', $customerId);

        $row = $connection->fetchRow($select);

        if (!$row) {
            return [
                'b2b_credit_limit' => '0.00',
                'b2b_credit_balance' => '0.00',
                'b2b_credit_used' => '0.00',
                'b2b_credit_status' => 'active',
            ];
        }

        $limit = (float)($row['credit_limit'] ?? 0);
        $used = (float)($row['used_credit'] ?? 0);
        $available = $limit - $used;

        return [
            'b2b_credit_limit' => number_format($limit, 2, '.', ''),
            'b2b_credit_balance' => number_format(max(0, $available), 2, '.', ''),
            'b2b_credit_used' => number_format($used, 2, '.', ''),
            'b2b_credit_status' => $available > 0 ? 'active' : 'exceeded',
        ];
    }
}
