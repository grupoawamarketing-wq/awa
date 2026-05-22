<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Resource model for ERP order retry tracking table.
 *
 * This table is used by RetryHeldOrders cron to track how many times
 * an order has been retried for ERP code resolution.
 */
class OrderRetry extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('grupoawamotos_erp_order_retry', 'order_id');
    }

    /**
     * Get current retry count for an order.
     */
    public function getRetryCount(int $orderId): int
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), ['retry_count'])
            ->where('order_id = ?', $orderId);
        $count = $connection->fetchOne($select);

        return (int) $count;
    }

    /**
     * Increment retry count (or insert if not exists).
     */
    public function incrementRetryCount(int $orderId, string $incrementId, string $error): void
    {
        $connection = $this->getConnection();
        $table = $connection->getTableName($this->getMainTable());
        $connection->query(
            'INSERT INTO `' . $table . '` '
            . '(order_id, increment_id, retry_count, last_error, next_attempt_at, created_at, updated_at)'
            . ' VALUES (?, ?, 1, ?, NOW(), NOW(), NOW())'
            . ' ON DUPLICATE KEY UPDATE retry_count = retry_count + 1, '
            . 'last_error = VALUES(last_error), updated_at = NOW()',
            [$orderId, $incrementId, $error]
        );
    }

    /**
     * Clear retry record for an order.
     */
    public function clearRetryCount(int $orderId): void
    {
        $this->getConnection()->delete(
            $this->getMainTable(),
            ['order_id = ?' => $orderId]
        );
    }
}
