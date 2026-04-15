<?php
declare(strict_types=1);

namespace GrupoAwamotos\LogMonitoring\Cron;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Psr\Log\LoggerInterface;

class DataCleanup
{
    private ResourceConnection $resourceConnection;
    private DateTime $dateTime;
    private LoggerInterface $logger;

    public function __construct(
        ResourceConnection $resourceConnection,
        DateTime $dateTime,
        LoggerInterface $logger
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->dateTime = $dateTime;
        $this->logger = $logger;
    }

    public function execute(): void
    {
        $this->logger->info('Starting log monitoring data cleanup');

        try {
            $connection = $this->resourceConnection->getConnection();
            
            // Cleanup old log metrics (keep 30 days)
            $oldDate = $this->dateTime->gmtDate('Y-m-d H:i:s', strtotime('-30 days'));
            $metricsTable = $this->resourceConnection->getTableName('awa_log_metrics');
            
            $deleted = $connection->delete(
                $metricsTable,
                ['created_at < ?' => $oldDate]
            );
            
            $this->logger->info("Cleaned up {$deleted} old log metrics records");
            
            // Cleanup resolved alerts (keep 7 days after resolution)
            $alertsTable = $this->resourceConnection->getTableName('awa_log_alerts');
            $alertCleanupDate = $this->dateTime->gmtDate('Y-m-d H:i:s', strtotime('-7 days'));
            
            $deletedAlerts = $connection->delete(
                $alertsTable,
                [
                    'status = ?' => 'resolved',
                    'resolved_at < ?' => $alertCleanupDate
                ]
            );
            
            $this->logger->info("Cleaned up {$deletedAlerts} resolved alert records");
            
            // Cleanup old system health records (keep 14 days)
            $healthTable = $this->resourceConnection->getTableName('awa_system_health');
            $healthCleanupDate = $this->dateTime->gmtDate('Y-m-d H:i:s', strtotime('-14 days'));
            
            $deletedHealth = $connection->delete(
                $healthTable,
                ['created_at < ?' => $healthCleanupDate]
            );
            
            $this->logger->info("Cleaned up {$deletedHealth} old system health records");
            
            $this->logger->info('Completed log monitoring data cleanup');
            
        } catch (\Exception $e) {
            $this->logger->error('Error in data cleanup: ' . $e->getMessage());
        }
    }
}