<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\CommercialPanel\Model\Intelligence;

use Magento\Framework\App\ResourceConnection;

/**
 * Agregações read-only de pedidos por cliente (sem alterar fluxo de pedido).
 */
class CustomerOrderInsightService
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * @param int[] $customerIds
     * @return array<int, array{last_order_at: ?string, last_increment_id: ?string, order_count: int, avg_total: float, total_revenue: float}>
     */
    public function getOrderSummaryByCustomer(array $customerIds): array
    {
        $customerIds = array_values(array_filter(array_unique($customerIds)));
        if ($customerIds === []) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $orderTable = $this->resourceConnection->getTableName('sales_order');

        $rows = $connection->fetchAll(
            $connection->select()
                ->from($orderTable, [
                    'customer_id',
                    'last_order_at' => new \Magento\Framework\DB\Sql\Expression('MAX(created_at)'),
                    'order_count' => new \Magento\Framework\DB\Sql\Expression('COUNT(*)'),
                    'avg_total' => new \Magento\Framework\DB\Sql\Expression('AVG(grand_total)'),
                    'total_revenue' => new \Magento\Framework\DB\Sql\Expression('SUM(grand_total)'),
                ])
                ->where('customer_id IN (?)', $customerIds)
                ->where('state NOT IN (?)', ['canceled'])
                ->group('customer_id')
        );

        $result = [];
        foreach ($rows as $row) {
            $customerId = (int) $row['customer_id'];
            $lastOrderAt = $row['last_order_at'] !== null ? (string) $row['last_order_at'] : null;
            $lastIncrement = null;
            if ($lastOrderAt !== null) {
                $lastIncrement = (string) $connection->fetchOne(
                    $connection->select()
                        ->from($orderTable, ['increment_id'])
                        ->where('customer_id = ?', $customerId)
                        ->where('state NOT IN (?)', ['canceled'])
                        ->order('created_at DESC')
                        ->limit(1)
                );
            }

            $result[$customerId] = [
                'last_order_at' => $lastOrderAt,
                'last_increment_id' => $lastIncrement ?: null,
                'order_count' => (int) $row['order_count'],
                'avg_total' => round((float) $row['avg_total'], 2),
                'total_revenue' => round((float) $row['total_revenue'], 2),
            ];
        }

        return $result;
    }

    /**
     * @return array<int, array{sku: string, name: string, qty: float}>
     */
    public function getTopProductsForCustomer(int $customerId, int $limit = 5): array
    {
        if ($customerId <= 0) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $itemTable = $this->resourceConnection->getTableName('sales_order_item');
        $orderTable = $this->resourceConnection->getTableName('sales_order');

        $rows = $connection->fetchAll(
            $connection->select()
                ->from(['oi' => $itemTable], [
                    'sku' => 'oi.sku',
                    'name' => 'oi.name',
                    'qty' => new \Magento\Framework\DB\Sql\Expression('SUM(oi.qty_ordered)'),
                    'last_purchased' => new \Magento\Framework\DB\Sql\Expression('MAX(o.created_at)'),
                ])
                ->join(['o' => $orderTable], 'oi.order_id = o.entity_id', [])
                ->where('o.customer_id = ?', $customerId)
                ->where('o.state NOT IN (?)', ['canceled'])
                ->where('oi.parent_item_id IS NULL')
                ->group(['oi.sku', 'oi.name'])
                ->order('last_purchased DESC')
                ->limit($limit)
        );

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'sku' => (string) $row['sku'],
                'name' => (string) $row['name'],
                'qty' => (float) $row['qty'],
            ];
        }

        return $result;
    }

    public function getDaysSince(?string $dateTime): ?int
    {
        if ($dateTime === null || $dateTime === '') {
            return null;
        }

        try {
            $then = new \DateTimeImmutable($dateTime);
            $now = new \DateTimeImmutable('today');

            return (int) $then->diff($now)->days;
        } catch (\Exception) {
            return null;
        }
    }
}
