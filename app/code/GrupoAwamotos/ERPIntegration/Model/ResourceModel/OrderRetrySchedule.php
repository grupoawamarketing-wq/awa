<?php
declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class OrderRetrySchedule extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('grupoawamotos_erp_order_retry', 'retry_id');
    }

    public function scheduleRetry(
        int $orderId,
        string $incrementId,
        int $retryCount,
        ?string $lastError,
        string $nextAttemptAt
    ): void {
        $this->getConnection()->insertOnDuplicate(
            $this->getMainTable(),
            [
                'order_id' => $orderId,
                'increment_id' => $incrementId,
                'retry_count' => $retryCount,
                'last_error' => $lastError,
                'next_attempt_at' => $nextAttemptAt,
            ],
            ['increment_id', 'retry_count', 'last_error', 'next_attempt_at', 'updated_at']
        );
    }

    public function getDueRetries(int $limit): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable())
            ->where('next_attempt_at <= ?', (new \DateTimeImmutable())->format('Y-m-d H:i:s'))
            ->order('next_attempt_at ASC')
            ->limit($limit);

        return $connection->fetchAll($select);
    }

    public function deleteRetry(int $retryId): void
    {
        $this->getConnection()->delete($this->getMainTable(), ['retry_id = ?' => $retryId]);
    }
}