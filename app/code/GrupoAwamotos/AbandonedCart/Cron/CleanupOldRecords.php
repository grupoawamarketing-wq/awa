<?php

declare(strict_types=1);

namespace GrupoAwamotos\AbandonedCart\Cron;

use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

class CleanupOldRecords
{
    private ResourceConnection $resource;
    private LoggerInterface $logger;

    public function __construct(
        ResourceConnection $resource,
        LoggerInterface $logger
    ) {
        $this->resource = $resource;
        $this->logger = $logger;
    }

    public function execute(): void
    {
        try {
            // Remover registros mais antigos que 90 dias
            $cutoffDate = date('Y-m-d H:i:s', strtotime('-90 days'));

            $connection = $this->resource->getConnection();
            $table = $this->resource->getTableName('grupoawamotos_abandoned_cart');

            // Single bulk DELETE — replaces O(N) individual item->delete() calls
            $count = $connection->delete($table, ['created_at <= ?' => $cutoffDate]);

            if ($count > 0) {
                $this->logger->info(sprintf('[AbandonedCart] Cleaned up %d old records', $count));
            }
        } catch (\Throwable $e) {
            $this->logger->error('[AbandonedCart] CleanupOldRecords failed: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
        }
    }
}
