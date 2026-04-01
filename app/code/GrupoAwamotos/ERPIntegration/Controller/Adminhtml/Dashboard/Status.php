<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Controller\Adminhtml\Dashboard;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use GrupoAwamotos\ERPIntegration\Api\ConnectionInterface;
use GrupoAwamotos\ERPIntegration\Model\CircuitBreaker;
use GrupoAwamotos\ERPIntegration\Model\ResourceModel\SyncLog;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use Psr\Log\LoggerInterface;

class Status extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_ERPIntegration::dashboard';

    private JsonFactory $jsonFactory;
    private ConnectionInterface $connection;
    private CircuitBreaker $circuitBreaker;
    private SyncLog $syncLogResource;
    private Helper $helper;
    private LoggerInterface $logger;

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        ConnectionInterface $connection,
        CircuitBreaker $circuitBreaker,
        SyncLog $syncLogResource,
        Helper $helper,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->connection = $connection;
        $this->circuitBreaker = $circuitBreaker;
        $this->syncLogResource = $syncLogResource;
        $this->helper = $helper;
        $this->logger = $logger;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        try {
            $connectionStatus = $this->testConnection();
            $circuitStatus = $this->circuitBreaker->getStats();
            $syncStatus = $this->getSyncStatus();
            $recentErrors = $this->getRecentErrors();

            return $result->setData([
                'success' => true,
                'timestamp' => date('Y-m-d H:i:s'),
                'connection' => $connectionStatus,
                'circuit_breaker' => $circuitStatus,
                'sync_status' => $syncStatus,
                'recent_errors' => $recentErrors,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('[ERP Dashboard] Status fetch failed', ['exception' => $e]);
            return $result->setData([
                'success' => false,
                'message' => __('Erro ao carregar status do ERP. Verifique os logs.'),
            ]);
        }
    }

    private function testConnection(): array
    {
        try {
            $startTime = microtime(true);
            $testResult = $this->connection->testConnection();
            $latency = round((microtime(true) - $startTime) * 1000, 2);

            return [
                'connected' => $testResult['success'] ?? false,
                'latency' => $latency,
                'driver' => $testResult['driver'] ?? null,
                'server_version' => $testResult['server_version'] ?? null,
                'database' => $testResult['database'] ?? null,
                'message' => $testResult['error'] ?? null,
            ];
        } catch (\Exception $e) {
            $this->logger->warning('[ERP Dashboard] Connection test failed', ['error' => $e->getMessage()]);
            return [
                'connected' => false,
                'message' => __('Falha na conex\u00e3o com o ERP.'),
            ];
        }
    }

    private function getSyncStatus(): array
    {
        $entityTypes = ['product', 'stock', 'customer', 'order', 'price'];
        $result = [];

        foreach ($entityTypes as $type) {
            $logs = $this->syncLogResource->getRecentLogs(1, $type);
            $lastLog = $logs[0] ?? null;

            $result[$type] = [
                'enabled' => $this->isSyncEnabled($type),
                'last_sync' => $lastLog ? $lastLog['created_at'] : null,
                'last_status' => $lastLog ? $lastLog['status'] : null,
                'last_records' => $lastLog ? (int)($lastLog['records_processed'] ?? 0) : 0,
            ];
        }

        return $result;
    }

    private function isSyncEnabled(string $entityType): bool
    {
        return match ($entityType) {
            'product' => $this->helper->isProductSyncEnabled(),
            'stock' => $this->helper->isStockSyncEnabled(),
            'customer' => $this->helper->isCustomerSyncEnabled(),
            'order' => $this->helper->isOrderSyncEnabled(),
            'price' => $this->helper->isPriceSyncEnabled(),
            default => false,
        };
    }

    private function getRecentErrors(): array
    {
        $connection = $this->syncLogResource->getConnection();
        $select = $connection->select()
            ->from($this->syncLogResource->getMainTable(), ['entity_type', 'message', 'created_at'])
            ->where('status = ?', 'error')
            ->order('created_at DESC')
            ->limit(5);

        return $connection->fetchAll($select);
    }
}
