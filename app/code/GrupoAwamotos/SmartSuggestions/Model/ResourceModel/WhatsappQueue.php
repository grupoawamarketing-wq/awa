<?php

declare(strict_types=1);

namespace GrupoAwamotos\SmartSuggestions\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * WhatsApp Queue Resource Model
 */
class WhatsappQueue extends AbstractDb
{
    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init('smart_suggestions_whatsapp_queue', 'queue_id');
    }

    /**
     * Get pending messages ready to send
     */
    public function getPendingMessages(int $limit = 50): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable())
            ->where('status = ?', 'pending')
            ->where('scheduled_at IS NULL OR scheduled_at <= NOW()')
            ->order('priority DESC')
            ->order('created_at ASC')
            ->limit($limit);

        return $connection->fetchAll($select);
    }

    /**
     * Mark messages as processing
     */
    public function markAsProcessing(array $queueIds): int
    {
        if (empty($queueIds)) {
            return 0;
        }

        return $this->getConnection()->update(
            $this->getMainTable(),
            ['status' => 'processing', 'updated_at' => date('Y-m-d H:i:s')],
            ['queue_id IN (?)' => $queueIds]
        );
    }

    /**
     * Cleanup old processed messages
     */
    public function cleanupOldMessages(int $daysToKeep = 30): int
    {
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysToKeep} days"));

        return $this->getConnection()->delete(
            $this->getMainTable(),
            [
                'status IN (?)' => ['sent', 'delivered', 'cancelled'],
                'updated_at < ?' => $cutoffDate
            ]
        );
    }

    /**
     * Reset stuck processing messages
     */
    public function resetStuckMessages(int $stuckMinutes = 30): int
    {
        $cutoffTime = date('Y-m-d H:i:s', strtotime("-{$stuckMinutes} minutes"));

        return $this->getConnection()->update(
            $this->getMainTable(),
            ['status' => 'pending', 'updated_at' => date('Y-m-d H:i:s')],
            [
                'status = ?' => 'processing',
                'updated_at < ?' => $cutoffTime
            ]
        );
    }

    /**
     * Get queue statistics
     */
    public function getQueueStats(): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), [
                'status',
                'count' => 'COUNT(*)',
                'oldest' => 'MIN(created_at)',
                'newest' => 'MAX(created_at)'
            ])
            ->group('status');

        return $connection->fetchAll($select);
    }
}
