<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Model\Goals;

use GrupoAwamotos\ERPIntegration\Api\ConnectionInterface;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use GrupoAwamotos\ERPIntegration\Model\Forecast\SalesProjection;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\CacheInterface;
use Psr\Log\LoggerInterface;

/**
 * Goals Manager - Handles sales goals/targets
 */
class Manager
{
    private const CACHE_PREFIX = 'erp_goals_';
    private const CACHE_TTL = 1800; // 30 minutes
    private const TABLE_NAME = 'grupoawamotos_erp_goals';

    private ConnectionInterface $erpConnection;
    private ResourceConnection $resourceConnection;
    private Helper $helper;
    private SalesProjection $salesProjection;
    private CacheInterface $cache;
    private LoggerInterface $logger;

    public function __construct(
        ConnectionInterface $erpConnection,
        ResourceConnection $resourceConnection,
        Helper $helper,
        SalesProjection $salesProjection,
        CacheInterface $cache,
        LoggerInterface $logger
    ) {
        $this->erpConnection = $erpConnection;
        $this->resourceConnection = $resourceConnection;
        $this->helper = $helper;
        $this->salesProjection = $salesProjection;
        $this->cache = $cache;
        $this->logger = $logger;
    }

    /**
     * Save a goal for a specific month
     */
    public function saveGoal(string $yearMonth, float $target, string $notes = ''): bool
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName(self::TABLE_NAME);

            $data = [
                'year_month' => $yearMonth,
                'target' => $target,
                'notes' => $notes,
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            // Check if exists
            $existing = $connection->fetchOne(
                "SELECT goal_id FROM {$tableName} WHERE year_month = ?",
                [$yearMonth]
            );

            if ($existing) {
                $connection->update($tableName, $data, ['goal_id = ?' => $existing]);
            } else {
                $data['created_at'] = date('Y-m-d H:i:s');
                $connection->insert($tableName, $data);
            }

            // Clear cache
            $this->cache->clean(['erp_goals']);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('[ERP Goals] Error saving goal: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get goal for a specific month
     */
    public function getGoal(string $yearMonth): ?array
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName(self::TABLE_NAME);

            $result = $connection->fetchRow(
                "SELECT * FROM {$tableName} WHERE year_month = ?",
                [$yearMonth]
            );

            return $result ?: null;
        } catch (\Exception $e) {
            // Table might not exist yet
            return null;
        }
    }

    /**
     * Get monthly goals with actual sales data
     */
    public function getMonthlyGoalsWithActuals(int $monthsBack = 3, int $monthsForward = 6): array
    {
        $cacheKey = self::CACHE_PREFIX . "monthly_{$monthsBack}_{$monthsForward}";
        $cached = $this->cache->load($cacheKey);

        if ($cached) {
            return json_decode($cached, true);
        }

        try {
            $result = [];
            $currentDate = new \DateTime();
            $currentYearMonth = $currentDate->format('Y-m');

            // Generate month range
            $startDate = (clone $currentDate)->modify("-{$monthsBack} months");
            $endDate = (clone $currentDate)->modify("+{$monthsForward} months");

            // Get actual sales from ERP
            $actualSales = $this->getActualSalesByMonth($startDate->format('Y-m'), $endDate->format('Y-m'));

            // Get saved goals
            $savedGoals = $this->getAllGoals();

            // Get default monthly target from config
            $defaultTarget = $this->helper->getMonthlyTarget();

            // Build month-by-month data
            $current = clone $startDate;
            while ($current <= $endDate) {
                $yearMonth = $current->format('Y-m');
                $monthNum = (int)$current->format('n');
                $year = (int)$current->format('Y');

                $actual = $actualSales[$yearMonth] ?? 0;
                $goal = $savedGoals[$yearMonth] ?? null;
                $target = $goal ? (float)$goal['target'] : $defaultTarget;

                // Calculate projection for future months
                $projection = 0;
                $isPast = $yearMonth < $currentYearMonth;
                $isCurrent = $yearMonth === $currentYearMonth;
                $isFuture = $yearMonth > $currentYearMonth;

                if ($isCurrent) {
                    $projData = $this->salesProjection->getCurrentMonthProjection();
                    $projection = $projData['projected_total'] ?? 0;
                    $actual = $projData['actual_sales'] ?? 0;
                } elseif ($isFuture) {
                    // Use seasonal projection
                    $projection = $this->calculateFutureProjection($yearMonth, $actualSales);
                }

                $progress = $target > 0 ? ($actual / $target) * 100 : 0;
                $gap = $target - $actual;

                $result[] = [
                    'year_month' => $yearMonth,
                    'year' => $year,
                    'month' => $monthNum,
                    'month_name' => $this->getMonthName($monthNum),
                    'short_name' => $this->getShortMonthName($monthNum),
                    'target' => round($target, 2),
                    'actual' => round($actual, 2),
                    'projection' => round($projection, 2),
                    'progress' => round($progress, 1),
                    'gap' => round($gap, 2),
                    'status' => $this->getStatus($progress, $isPast),
                    'is_past' => $isPast,
                    'is_current' => $isCurrent,
                    'is_future' => $isFuture,
                    'notes' => $goal['notes'] ?? '',
                    'is_custom_target' => $goal !== null,
                ];

                $current->modify('+1 month');
            }

            $this->cache->save(json_encode($result), $cacheKey, ['erp_goals'], self::CACHE_TTL);

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('[ERP Goals] Error getting monthly goals: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get goals data with filters
     */
    public function getGoalsData(array $filters): array
    {
        try {
            $periodStart = $filters['period_start'] ?? date('Y-m', strtotime('-6 months'));
            $periodEnd = $filters['period_end'] ?? date('Y-m', strtotime('+6 months'));

            // Build WHERE clause based on filters
            $whereClause = "WHERE p.STATUS NOT IN ('C', 'X')";
            $params = [];

            if (!empty($filters['seller'])) {
                $whereClause .= " AND p.VENDEDOR = ?";
                $params[] = $filters['seller'];
            }

            if (!empty($filters['region'])) {
                $whereClause .= " AND f.UF = ?";
                $params[] = $filters['region'];
            }

            // Get filtered sales data
            $sales = $this->erpConnection->query("
                SELECT
                    FORMAT(p.DTPEDIDO, 'yyyy-MM') as year_month,
                    COUNT(DISTINCT p.CODIGO) as orders,
                    COUNT(DISTINCT p.CLIENTE) as customers,
                    SUM(i.VLRTOTAL) as total_value,
                    AVG(i.VLRTOTAL) as avg_ticket
                FROM VE_PEDIDO p
                INNER JOIN VE_PEDIDOITENS i ON p.CODIGO = i.PEDIDO
                LEFT JOIN FN_FORNECEDORES f ON p.CLIENTE = f.CODIGO
                {$whereClause}
                AND FORMAT(p.DTPEDIDO, 'yyyy-MM') >= ?
                AND FORMAT(p.DTPEDIDO, 'yyyy-MM') <= ?
                GROUP BY FORMAT(p.DTPEDIDO, 'yyyy-MM')
                ORDER BY year_month
            ", array_merge($params, [$periodStart, $periodEnd]));

            // Get saved goals
            $savedGoals = $this->getAllGoals();
            $defaultTarget = $this->helper->getMonthlyTarget();

            // Build result
            $result = [
                'months' => [],
                'summary' => [
                    'total_actual' => 0,
                    'total_target' => 0,
                    'total_orders' => 0,
                    'total_customers' => 0,
                    'avg_ticket' => 0,
                ],
                'by_region' => [],
                'by_seller' => [],
                'trends' => [],
            ];

            foreach ($sales as $row) {
                $yearMonth = $row['year_month'];
                $goal = $savedGoals[$yearMonth] ?? null;
                $target = $goal ? (float)$goal['target'] : $defaultTarget;
                $actual = (float)$row['total_value'];

                $result['months'][] = [
                    'year_month' => $yearMonth,
                    'target' => $target,
                    'actual' => $actual,
                    'orders' => (int)$row['orders'],
                    'customers' => (int)$row['customers'],
                    'avg_ticket' => (float)$row['avg_ticket'],
                    'progress' => $target > 0 ? round(($actual / $target) * 100, 1) : 0,
                ];

                $result['summary']['total_actual'] += $actual;
                $result['summary']['total_target'] += $target;
                $result['summary']['total_orders'] += (int)$row['orders'];
                $result['summary']['total_customers'] += (int)$row['customers'];
            }

            // Calculate average ticket
            if ($result['summary']['total_orders'] > 0) {
                $result['summary']['avg_ticket'] = $result['summary']['total_actual'] / $result['summary']['total_orders'];
            }

            // Overall progress
            if ($result['summary']['total_target'] > 0) {
                $result['summary']['overall_progress'] = round(
                    ($result['summary']['total_actual'] / $result['summary']['total_target']) * 100,
                    1
                );
            }

            // Get breakdown by region
            $result['by_region'] = $this->getSalesByRegion($periodStart, $periodEnd, $filters);

            // Get breakdown by seller
            $result['by_seller'] = $this->getSalesBySeller($periodStart, $periodEnd, $filters);

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('[ERP Goals] Error getting goals data: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get available filters from ERP data
     */
    public function getAvailableFilters(): array
    {
        try {
            // Get sellers
            $sellers = $this->erpConnection->query("
                SELECT DISTINCT v.CODIGO, v.NOME
                FROM VE_VENDEDOR v
                WHERE v.ATIVO = 'S'
                ORDER BY v.NOME
            ");

            // Get regions (states)
            $regions = $this->erpConnection->query("
                SELECT DISTINCT f.UF as code, f.UF as name
                FROM FN_FORNECEDORES f
                WHERE f.CKCLIENTE = 'S' AND f.UF IS NOT NULL AND f.UF <> ''
                ORDER BY f.UF
            ");

            // Get categories
            $categories = $this->erpConnection->query("
                SELECT DISTINCT gc.CODIGO, gc.DESCRICAO
                FROM MT_GRUPOCADASTRAL gc
                ORDER BY gc.DESCRICAO
            ");

            return [
                'sellers' => $sellers,
                'regions' => $regions,
                'categories' => $categories,
                'segments' => [
                    ['code' => 'champions', 'name' => 'Champions'],
                    ['code' => 'loyal', 'name' => 'Clientes Fiéis'],
                    ['code' => 'at_risk', 'name' => 'Em Risco'],
                    ['code' => 'lost', 'name' => 'Perdidos'],
                ],
            ];
        } catch (\Exception $e) {
            $this->logger->error('[ERP Goals] Error getting filters: ' . $e->getMessage());
            return [
                'sellers' => [],
                'regions' => [],
                'categories' => [],
                'segments' => [],
            ];
        }
    }

    /**
     * Get yearly summary
     */
    public function getYearlySummary(): array
    {
        try {
            $currentYear = (int)date('Y');
            $years = [$currentYear - 2, $currentYear - 1, $currentYear];

            $result = [];

            foreach ($years as $year) {
                $sales = $this->erpConnection->fetchOne("
                    SELECT
                        COUNT(DISTINCT p.CODIGO) as orders,
                        COUNT(DISTINCT p.CLIENTE) as customers,
                        SUM(i.VLRTOTAL) as total_value
                    FROM VE_PEDIDO p
                    INNER JOIN VE_PEDIDOITENS i ON p.CODIGO = i.PEDIDO
                    WHERE p.STATUS NOT IN ('C', 'X')
                    AND YEAR(p.DTPEDIDO) = ?
                ", [$year]);

                // Calculate yearly target (sum of monthly targets)
                $yearlyTarget = $this->helper->getMonthlyTarget() * 12;

                $result[] = [
                    'year' => $year,
                    'target' => $yearlyTarget,
                    'actual' => (float)($sales['total_value'] ?? 0),
                    'orders' => (int)($sales['orders'] ?? 0),
                    'customers' => (int)($sales['customers'] ?? 0),
                    'progress' => $yearlyTarget > 0
                        ? round(((float)($sales['total_value'] ?? 0) / $yearlyTarget) * 100, 1)
                        : 0,
                ];
            }

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('[ERP Goals] Error getting yearly summary: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get quarterly data
     */
    public function getQuarterlyData(): array
    {
        try {
            $currentYear = (int)date('Y');

            $quarters = $this->erpConnection->query("
                SELECT
                    YEAR(p.DTPEDIDO) as year,
                    DATEPART(QUARTER, p.DTPEDIDO) as quarter,
                    COUNT(DISTINCT p.CODIGO) as orders,
                    COUNT(DISTINCT p.CLIENTE) as customers,
                    SUM(i.VLRTOTAL) as total_value
                FROM VE_PEDIDO p
                INNER JOIN VE_PEDIDOITENS i ON p.CODIGO = i.PEDIDO
                WHERE p.STATUS NOT IN ('C', 'X')
                AND YEAR(p.DTPEDIDO) >= ?
                GROUP BY YEAR(p.DTPEDIDO), DATEPART(QUARTER, p.DTPEDIDO)
                ORDER BY year, quarter
            ", [$currentYear - 1]);

            $quarterlyTarget = $this->helper->getMonthlyTarget() * 3;

            return array_map(function ($q) use ($quarterlyTarget) {
                $actual = (float)($q['total_value'] ?? 0);
                return [
                    'year' => (int)$q['year'],
                    'quarter' => (int)$q['quarter'],
                    'label' => "Q{$q['quarter']}/{$q['year']}",
                    'target' => $quarterlyTarget,
                    'actual' => $actual,
                    'orders' => (int)($q['orders'] ?? 0),
                    'customers' => (int)($q['customers'] ?? 0),
                    'progress' => $quarterlyTarget > 0 ? round(($actual / $quarterlyTarget) * 100, 1) : 0,
                ];
            }, $quarters);
        } catch (\Exception $e) {
            $this->logger->error('[ERP Goals] Error getting quarterly data: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get actual sales grouped by month
     */
    private function getActualSalesByMonth(string $startMonth, string $endMonth): array
    {
        try {
            $sales = $this->erpConnection->query("
                SELECT
                    FORMAT(p.DTPEDIDO, 'yyyy-MM') as year_month,
                    SUM(i.VLRTOTAL) as total_value
                FROM VE_PEDIDO p
                INNER JOIN VE_PEDIDOITENS i ON p.CODIGO = i.PEDIDO
                WHERE p.STATUS NOT IN ('C', 'X')
                AND FORMAT(p.DTPEDIDO, 'yyyy-MM') >= ?
                AND FORMAT(p.DTPEDIDO, 'yyyy-MM') <= ?
                GROUP BY FORMAT(p.DTPEDIDO, 'yyyy-MM')
            ", [$startMonth, $endMonth]);

            $result = [];
            foreach ($sales as $row) {
                $result[$row['year_month']] = (float)$row['total_value'];
            }

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get sales breakdown by region
     */
    private function getSalesByRegion(string $startMonth, string $endMonth, array $filters): array
    {
        try {
            $sales = $this->erpConnection->query("
                SELECT
                    f.UF as region,
                    COUNT(DISTINCT p.CODIGO) as orders,
                    SUM(i.VLRTOTAL) as total_value
                FROM VE_PEDIDO p
                INNER JOIN VE_PEDIDOITENS i ON p.CODIGO = i.PEDIDO
                LEFT JOIN FN_FORNECEDORES f ON p.CLIENTE = f.CODIGO
                WHERE p.STATUS NOT IN ('C', 'X')
                AND FORMAT(p.DTPEDIDO, 'yyyy-MM') >= ?
                AND FORMAT(p.DTPEDIDO, 'yyyy-MM') <= ?
                AND f.UF IS NOT NULL AND f.UF <> ''
                GROUP BY f.UF
                ORDER BY SUM(i.VLRTOTAL) DESC
            ", [$startMonth, $endMonth]);

            return array_map(function ($row) {
                return [
                    'region' => $row['region'],
                    'orders' => (int)$row['orders'],
                    'value' => (float)$row['total_value'],
                ];
            }, $sales);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get sales breakdown by seller
     */
    private function getSalesBySeller(string $startMonth, string $endMonth, array $filters): array
    {
        try {
            $sales = $this->erpConnection->query("
                SELECT TOP 10
                    v.CODIGO as seller_id,
                    v.NOME as seller_name,
                    COUNT(DISTINCT p.CODIGO) as orders,
                    SUM(i.VLRTOTAL) as total_value
                FROM VE_PEDIDO p
                INNER JOIN VE_PEDIDOITENS i ON p.CODIGO = i.PEDIDO
                LEFT JOIN VE_VENDEDOR v ON p.VENDEDOR = v.CODIGO
                WHERE p.STATUS NOT IN ('C', 'X')
                AND FORMAT(p.DTPEDIDO, 'yyyy-MM') >= ?
                AND FORMAT(p.DTPEDIDO, 'yyyy-MM') <= ?
                GROUP BY v.CODIGO, v.NOME
                ORDER BY SUM(i.VLRTOTAL) DESC
            ", [$startMonth, $endMonth]);

            return array_map(function ($row) {
                return [
                    'seller_id' => $row['seller_id'],
                    'seller_name' => $row['seller_name'] ?? 'Não informado',
                    'orders' => (int)$row['orders'],
                    'value' => (float)$row['total_value'],
                ];
            }, $sales);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get all saved goals
     */
    private function getAllGoals(): array
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName(self::TABLE_NAME);

            $goals = $connection->fetchAll("SELECT * FROM {$tableName}");

            $result = [];
            foreach ($goals as $goal) {
                $result[$goal['year_month']] = $goal;
            }

            return $result;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Calculate future projection based on historical data
     */
    private function calculateFutureProjection(string $yearMonth, array $historicalSales): float
    {
        // Get same month from last year
        $lastYear = (int)substr($yearMonth, 0, 4) - 1;
        $month = substr($yearMonth, 5, 2);
        $lastYearMonth = "{$lastYear}-{$month}";

        $lastYearValue = $historicalSales[$lastYearMonth] ?? 0;

        // Get growth rate from recent months
        $recentValues = array_slice($historicalSales, -3, 3, true);
        $avgRecent = count($recentValues) > 0 ? array_sum($recentValues) / count($recentValues) : 0;

        // If we have last year's data, apply growth
        if ($lastYearValue > 0 && $avgRecent > 0) {
            // Simple growth rate from last 3 months average
            return $lastYearValue * 1.05; // 5% growth assumption
        }

        // Fallback to recent average
        return $avgRecent;
    }

    /**
     * Get status based on progress
     */
    private function getStatus(float $progress, bool $isPast): string
    {
        if ($isPast) {
            return $progress >= 100 ? 'achieved' : 'missed';
        }

        if ($progress >= 100) {
            return 'achieved';
        }
        if ($progress >= 80) {
            return 'on_track';
        }
        if ($progress >= 60) {
            return 'attention';
        }
        return 'critical';
    }

    /**
     * Get month name
     */
    private function getMonthName(int $month): string
    {
        $months = [
            1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
            5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
            9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
        ];
        return $months[$month] ?? '';
    }

    /**
     * Get short month name
     */
    private function getShortMonthName(int $month): string
    {
        $months = [
            1 => 'Jan', 2 => 'Fev', 3 => 'Mar', 4 => 'Abr',
            5 => 'Mai', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago',
            9 => 'Set', 10 => 'Out', 11 => 'Nov', 12 => 'Dez',
        ];
        return $months[$month] ?? '';
    }
}
