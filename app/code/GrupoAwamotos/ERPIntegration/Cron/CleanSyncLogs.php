<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Cron;

use GrupoAwamotos\ERPIntegration\Model\ResourceModel\SyncLog as SyncLogResource;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use Magento\Framework\App\Filesystem\DirectoryList;
use Psr\Log\LoggerInterface;

class CleanSyncLogs
{
    /**
     * Default days to keep DB sync log records
     */
    private const DEFAULT_DAYS_TO_KEEP = 30;

    /**
     * Days to keep auto-generated SQL register files (generated ~3x/day; 7 days = ~21 files)
     */
    private const SQL_FILES_DAYS_TO_KEEP = 7;

    /**
     * Prefix for auto-generated SQL register files
     */
    private const SQL_EXPORT_PREFIX = 'erp_register_clients_auto_';

    private SyncLogResource $syncLogResource;
    private Helper $helper;
    private DirectoryList $directoryList;
    private LoggerInterface $logger;

    public function __construct(
        SyncLogResource $syncLogResource,
        Helper $helper,
        DirectoryList $directoryList,
        LoggerInterface $logger
    ) {
        $this->syncLogResource = $syncLogResource;
        $this->helper = $helper;
        $this->directoryList = $directoryList;
        $this->logger = $logger;
    }

    public function execute(): void
    {
        if (!$this->helper->isEnabled()) {
            return;
        }

        try {
            $daysToKeep = self::DEFAULT_DAYS_TO_KEEP;
            $deleted = $this->syncLogResource->cleanOldLogs($daysToKeep);

            if ($deleted > 0) {
                $this->logger->info('[ERP] Sync logs cleanup completed', [
                    'deleted_records' => $deleted,
                    'days_kept' => $daysToKeep,
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('[ERP] Sync logs cleanup failed: ' . $e->getMessage());
        }

        $this->cleanOldSqlExportFiles();
    }

    /**
     * Remove auto-generated SQL register files older than DEFAULT_DAYS_TO_KEEP days
     */
    private function cleanOldSqlExportFiles(): void
    {
        try {
            $logDir = $this->directoryList->getPath('var') . '/log';
            $cutoffTime = time() - (self::SQL_FILES_DAYS_TO_KEEP * 86400);
            $pattern = $logDir . '/' . self::SQL_EXPORT_PREFIX . '*.sql';
            $files = glob($pattern);

            if (empty($files)) {
                return;
            }

            $removed = 0;
            foreach ($files as $file) {
                if (is_file($file) && filemtime($file) < $cutoffTime) {
                    unlink($file);
                    $removed++;
                }
            }

            if ($removed > 0) {
                $this->logger->info('[ERP] SQL export files cleanup completed', [
                    'removed_files' => $removed,
                    'days_kept' => self::DEFAULT_DAYS_TO_KEEP,
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('[ERP] SQL export files cleanup failed: ' . $e->getMessage());
        }
    }
}
