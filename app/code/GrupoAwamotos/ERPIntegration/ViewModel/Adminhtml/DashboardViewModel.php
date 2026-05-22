<?php

/**
 * ViewModel for ERP Admin Dashboard
 */

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\ViewModel\Adminhtml;

use GrupoAwamotos\ERPIntegration\Api\ConnectionInterface;
use GrupoAwamotos\ERPIntegration\Api\DashboardStatsProviderInterface;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use GrupoAwamotos\ERPIntegration\Model\CircuitBreaker;
use GrupoAwamotos\ERPIntegration\Model\Forecast\SalesProjection;
use GrupoAwamotos\ERPIntegration\Model\ResourceModel\SyncLog;
use GrupoAwamotos\ERPIntegration\Model\Rfm\Calculator as RfmCalculator;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Psr\Log\LoggerInterface;

class DashboardViewModel implements ArgumentInterface
{
    private ConnectionInterface $connection;
    private DashboardStatsProviderInterface $statsProvider;
    private Helper $helper;
    private RfmCalculator $rfmCalculator;
    private SalesProjection $salesProjection;
    private CircuitBreaker $circuitBreaker;
    private SyncLog $syncLogResource;
    private LoggerInterface $logger;

    private ?array $stats = null;
    private ?array $connectionStatus = null;

    public function __construct(
        ConnectionInterface $connection,
        DashboardStatsProviderInterface $statsProvider,
        Helper $helper,
        RfmCalculator $rfmCalculator,
        SalesProjection $salesProjection,
        CircuitBreaker $circuitBreaker,
        SyncLog $syncLogResource,
        LoggerInterface $logger
    ) {
        $this->connection = $connection;
        $this->statsProvider = $statsProvider;
        $this->helper = $helper;
        $this->rfmCalculator = $rfmCalculator;
        $this->salesProjection = $salesProjection;
        $this->circuitBreaker = $circuitBreaker;
        $this->syncLogResource = $syncLogResource;
        $this->logger = $logger;
    }

    /**
     * Check if ERP is enabled
     */
    public function isEnabled(): bool
    {
        return $this->helper->isEnabled();
    }

    /**
     * Get ERP statistics (delegated to StatsProvider)
     */
    public function getStats(): array
    {
        if ($this->stats !== null) {
            return $this->stats;
        }

        if (!$this->isEnabled()) {
            return [];
        }

        $this->stats = $this->statsProvider->getAggregatedStats();
        return $this->stats;
    }

    /**
     * Format price
     */
    public function formatPrice(float $price): string
    {
        return 'R$ ' . number_format($price, 2, ',', '.');
    }

    /**
     * Format number
     */
    public function formatNumber(int $number): string
    {
        return number_format($number, 0, '', '.');
    }

    /**
     * Get RFM segment statistics
     */
    public function getRfmSegmentStats(): array
    {
        try {
            return $this->rfmCalculator->getSegmentStats();
        } catch (\Exception $e) {
            $this->logger->error('[ERP Dashboard] getRfmSegmentStats failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get at-risk customers
     */
    public function getAtRiskCustomers(int $limit = 10): array
    {
        try {
            return $this->rfmCalculator->getAtRiskCustomers($limit);
        } catch (\Exception $e) {
            $this->logger->error('[ERP Dashboard] getAtRiskCustomers failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get top customers (Champions)
     */
    public function getTopCustomers(int $limit = 10): array
    {
        try {
            return $this->rfmCalculator->getTopCustomers($limit);
        } catch (\Exception $e) {
            $this->logger->error('[ERP Dashboard] getTopCustomers failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get current month sales projection
     */
    public function getSalesProjection(): array
    {
        try {
            return $this->salesProjection->getCurrentMonthProjection();
        } catch (\Exception $e) {
            $this->logger->error('[ERP Dashboard] getSalesProjection failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get next month projection
     */
    public function getNextMonthProjection(): array
    {
        try {
            return $this->salesProjection->getNextMonthProjection();
        } catch (\Exception $e) {
            $this->logger->error('[ERP Dashboard] getNextMonthProjection failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get daily sales chart data (JSON)
     */
    public function getDailySalesChartJson(): string
    {
        try {
            $data = $this->salesProjection->getDailySalesChart(30, 7);
            return json_encode($data);
        } catch (\Exception $e) {
            $this->logger->error('[ERP Dashboard] getDailySalesChartJson failed: ' . $e->getMessage());
            return '[]';
        }
    }

    /**
     * Get monthly sales chart data (JSON)
     */
    public function getMonthlySalesChartJson(): string
    {
        try {
            $data = $this->salesProjection->getLast12MonthsSales();
            return json_encode($data);
        } catch (\Exception $e) {
            $this->logger->error('[ERP Dashboard] getMonthlySalesChartJson failed: ' . $e->getMessage());
            return '[]';
        }
    }

    /**
     * Get RFM chart data (JSON)
     */
    public function getRfmChartJson(): string
    {
        try {
            $stats = $this->rfmCalculator->getSegmentStats();
            $chartData = [];

            foreach ($stats as $key => $segment) {
                if ($segment['count'] > 0) {
                    $chartData[] = [
                        'segment' => $segment['label'],
                        'count' => $segment['count'],
                        'revenue' => $segment['revenue'],
                        'color' => $segment['color'],
                    ];
                }
            }

            return json_encode($chartData);
        } catch (\Exception $e) {
            $this->logger->error('[ERP Dashboard] getRfmChartJson failed: ' . $e->getMessage());
            return '[]';
        }
    }

    /**
     * Get alert class based on level
     */
    public function getAlertClass(string $level): string
    {
        return match ($level) {
            'success' => 'message-success',
            'warning' => 'message-warning',
            'danger' => 'message-error',
            'critical' => 'message-error',
            default => 'message-notice',
        };
    }

    /**
     * Get current month label in Portuguese (e.g. "FEVEREIRO/2026")
     */
    public function getCurrentMonthLabel(): string
    {
        $months = [
            1 => 'JANEIRO', 2 => 'FEVEREIRO', 3 => 'MARÇO', 4 => 'ABRIL',
            5 => 'MAIO', 6 => 'JUNHO', 7 => 'JULHO', 8 => 'AGOSTO',
            9 => 'SETEMBRO', 10 => 'OUTUBRO', 11 => 'NOVEMBRO', 12 => 'DEZEMBRO',
        ];

        return $months[(int) date('n')] . '/' . date('Y');
    }

    /**
     * Format percentage with sign
     */
    public function formatPercentage(float $value): string
    {
        $sign = $value >= 0 ? '+' : '';
        return $sign . number_format($value, 1, ',', '.') . '%';
    }

    /**
     * Get connection status and test result
     */
    public function getConnectionStatus(): array
    {
        if ($this->connectionStatus !== null) {
            return $this->connectionStatus;
        }

        $status = [
            'connected' => false,
            'message' => 'Não testado',
            'latency' => null,
            'driver' => null,
            'server_version' => null,
        ];

        try {
            $startTime = microtime(true);
            $testResult = $this->connection->testConnection();
            $latency = round((microtime(true) - $startTime) * 1000, 2);

            if ($testResult['success'] ?? false) {
                $status = [
                    'connected' => true,
                    'message' => 'Conectado',
                    'latency' => $latency,
                    'driver' => $testResult['driver'] ?? 'unknown',
                    'server_version' => $testResult['server_version'] ?? null,
                    'database' => $testResult['database'] ?? null,
                ];
            } else {
                $status['message'] = $testResult['error'] ?? 'Falha na conexão';
            }
        } catch (\Exception $e) {
            $this->logger->error('[ERP Dashboard] getConnectionStatus failed: ' . $e->getMessage());
            $status['message'] = $e->getMessage();
        }

        $this->connectionStatus = $status;
        return $status;
    }

    /**
     * Get circuit breaker status
     */
    public function getCircuitBreakerStatus(): array
    {
        return $this->circuitBreaker->getStats();
    }

    /**
     * Get circuit breaker state label
     */
    public function getCircuitBreakerStateLabel(string $state): string
    {
        return match ($state) {
            'CLOSED' => 'Normal',
            'OPEN' => 'Bloqueado',
            'HALF_OPEN' => 'Testando',
            default => $state,
        };
    }

    /**
     * Get circuit breaker state class
     */
    public function getCircuitBreakerStateClass(string $state): string
    {
        return match ($state) {
            'CLOSED' => 'success',
            'OPEN' => 'danger',
            'HALF_OPEN' => 'warning',
            default => 'info',
        };
    }

    /**
     * Get last sync status for each entity type
     */
    public function getLastSyncStatus(): array
    {
        $entityTypes = ['product', 'stock', 'customer', 'order', 'price'];
        $result = [];

        foreach ($entityTypes as $type) {
            $logs = $this->syncLogResource->getRecentLogs(1, $type);
            $stats = $this->syncLogResource->getSyncStats($type, 1);

            $lastLog = $logs[0] ?? null;
            $successCount = 0;
            $errorCount = 0;
            $totalRecords = 0;

            foreach ($stats as $stat) {
                if ($stat['status'] === 'success') {
                    $successCount = (int) $stat['total'];
                    $totalRecords = (int) ($stat['total_records'] ?? 0);
                } elseif ($stat['status'] === 'error') {
                    $errorCount = (int) $stat['total'];
                }
            }

            $result[$type] = [
                'label' => $this->getEntityTypeLabel($type),
                'icon' => $this->getEntityTypeIcon($type),
                'last_sync' => $lastLog ? $lastLog['created_at'] : null,
                'last_status' => $lastLog ? $lastLog['status'] : null,
                'last_message' => $lastLog ? $lastLog['message'] : null,
                'last_records' => $lastLog ? (int) ($lastLog['records_processed'] ?? 0) : 0,
                'success_24h' => $successCount,
                'error_24h' => $errorCount,
                'records_24h' => $totalRecords,
                'enabled' => $this->isSyncEnabled($type),
            ];
        }

        return $result;
    }

    /**
     * Check if sync is enabled for entity type
     */
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

    /**
     * Get entity type label
     */
    private function getEntityTypeLabel(string $type): string
    {
        return match ($type) {
            'product' => 'Produtos',
            'stock' => 'Estoque',
            'customer' => 'Clientes',
            'order' => 'Pedidos',
            'price' => 'Preços',
            default => ucfirst($type),
        };
    }

    /**
     * Get entity type icon
     */
    private function getEntityTypeIcon(string $type): string
    {
        return match ($type) {
            'product' => '📦',
            'stock' => '📊',
            'customer' => '👥',
            'order' => '🛒',
            'price' => '💰',
            default => '📄',
        };
    }

    /**
     * Get recent errors
     */
    public function getRecentErrors(int $limit = 5): array
    {
        return $this->syncLogResource->getRecentErrorLogs($limit);
    }

    /**
     * Format relative time
     */
    public function formatRelativeTime(?string $datetime): string
    {
        if (!$datetime) {
            return 'Nunca';
        }

        $timestamp = strtotime($datetime);
        $diff = time() - $timestamp;

        if ($diff < 60) {
            return 'Agora';
        } elseif ($diff < 3600) {
            $mins = floor($diff / 60);
            return $mins . ' min atrás';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . 'h atrás';
        } else {
            $days = floor($diff / 86400);
            return $days . 'd atrás';
        }
    }
}
