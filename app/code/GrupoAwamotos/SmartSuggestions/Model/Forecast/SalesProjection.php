<?php
declare(strict_types=1);

namespace GrupoAwamotos\SmartSuggestions\Model\Forecast;

use GrupoAwamotos\SmartSuggestions\Api\ForecastServiceInterface;
use GrupoAwamotos\ERPIntegration\Api\ConnectionInterface;
use Psr\Log\LoggerInterface;

/**
 * Sales Projection Service
 *
 * Provides sales forecasts using hybrid approach:
 * - Weighted moving average
 * - Trend analysis
 * - Seasonality adjustment
 * - Monte Carlo simulation for confidence intervals
 */
class SalesProjection implements ForecastServiceInterface
{
    private ConnectionInterface $connection;
    private LoggerInterface $logger;

    public function __construct(
        ConnectionInterface $connection,
        LoggerInterface $logger
    ) {
        $this->connection = $connection;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function projectMonthClosing(): array
    {
        try {
            $currentMonth = date('Y-m');
            $daysPassed = (int)date('j');
            $totalDays = (int)date('t');
            $daysRemaining = $totalDays - $daysPassed;

            // Get actual sales this month
            $actualSales = $this->getMonthSales($currentMonth);

            if ($daysPassed === 0) {
                return $this->getEmptyProjection();
            }

            // Calculate daily average
            $dailyAvg = $actualSales / $daysPassed;

            // Get day-of-week weights from historical data
            $dayWeights = $this->getDayOfWeekWeights();

            // Project remaining days with day weights
            $projectedRemaining = 0;
            for ($i = 1; $i <= $daysRemaining; $i++) {
                $date = date('Y-m-d', strtotime("+$i days"));
                $dayOfWeek = (int)date('N', strtotime($date));
                $weight = $dayWeights[$dayOfWeek] ?? 1.0;
                $projectedRemaining += $dailyAvg * $weight;
            }

            // Calculate trend adjustment
            $trend = $this->calculateTrend();
            $trendAdjustment = $projectedRemaining * $trend;

            // Base projection
            $baseProjection = $actualSales + $projectedRemaining + $trendAdjustment;

            // Monte Carlo simulation for confidence intervals
            $scenarios = $this->runMonteCarloSimulation($actualSales, $projectedRemaining, 1000);

            // Get comparison data
            $lastMonth = date('Y-m', strtotime('-1 month'));
            $lastYear = date('Y-m', strtotime('-1 year'));
            $lastMonthSales = $this->getMonthSales($lastMonth);
            $lastYearSales = $this->getMonthSales($lastYear);

            // Calculate daily target to reach goal (if applicable)
            $monthlyGoal = $this->getMonthlyGoal();
            $remainingToGoal = $monthlyGoal - $actualSales;
            $dailyTarget = $daysRemaining > 0 ? $remainingToGoal / $daysRemaining : 0;

            return [
                'current_month' => $currentMonth,
                'days_passed' => $daysPassed,
                'days_remaining' => $daysRemaining,
                'total_days' => $totalDays,
                'actual_sales' => round($actualSales, 2),
                'daily_average' => round($dailyAvg, 2),
                'projected_remaining' => round($projectedRemaining + $trendAdjustment, 2),
                'projection' => [
                    'pessimistic' => round($scenarios['p10'], 2),
                    'realistic' => round($baseProjection, 2),
                    'optimistic' => round($scenarios['p90'], 2)
                ],
                'confidence_interval' => [
                    'lower' => round($scenarios['p5'], 2),
                    'upper' => round($scenarios['p95'], 2)
                ],
                'progress_percentage' => round(($actualSales / max($baseProjection, 1)) * 100, 1),
                'goal' => [
                    'value' => $monthlyGoal,
                    'remaining' => max(0, $remainingToGoal),
                    'daily_target' => round(max(0, $dailyTarget), 2),
                    'achievable' => $dailyTarget <= ($dailyAvg * 1.5)
                ],
                'comparison' => [
                    'vs_last_month' => $lastMonthSales > 0
                        ? round((($baseProjection - $lastMonthSales) / $lastMonthSales) * 100, 1)
                        : 0,
                    'vs_last_year' => $lastYearSales > 0
                        ? round((($baseProjection - $lastYearSales) / $lastYearSales) * 100, 1)
                        : 0,
                    'last_month_value' => round($lastMonthSales, 2),
                    'last_year_value' => round($lastYearSales, 2)
                ],
                'trend' => [
                    'direction' => $trend > 0 ? 'up' : ($trend < 0 ? 'down' : 'stable'),
                    'percentage' => round($trend * 100, 1)
                ]
            ];

        } catch (\Exception $e) {
            $this->logger->error('Forecast Error: ' . $e->getMessage());
            return $this->getEmptyProjection();
        }
    }

    /**
     * @inheritdoc
     */
    public function projectNextMonth(): array
    {
        try {
            // Get last 12 months of sales
            $history = $this->getLast12MonthsSales();

            if (empty($history)) {
                return [];
            }

            // Calculate base value (average of last 3 months)
            $lastThree = array_slice($history, -3);
            if (empty($lastThree)) {
                return [];
            }
            $baseValue = array_sum(array_column($lastThree, 'total')) / count($lastThree);

            // Calculate seasonal index
            $seasonalIndex = $this->calculateSeasonalIndex($history);
            $nextMonthIndex = ((int)date('n') % 12) + 1;
            $seasonalFactor = $seasonalIndex[$nextMonthIndex] ?? 1.0;

            // Calculate growth rate
            $growthRate = $this->calculateGrowthRate($history);

            // Apply seasonality and trend
            $projection = $baseValue * $seasonalFactor * (1 + $growthRate);

            return [
                'month' => date('Y-m', strtotime('+1 month')),
                'month_name' => $this->getMonthName((int)date('n', strtotime('+1 month'))),
                'projection' => round($projection, 2),
                'base_value' => round($baseValue, 2),
                'seasonal_factor' => round($seasonalFactor, 3),
                'growth_factor' => round($growthRate, 3),
                'range' => [
                    'min' => round($projection * 0.85, 2),
                    'max' => round($projection * 1.15, 2)
                ],
                'comparison' => [
                    'vs_current_month' => $baseValue > 0
                        ? round((($projection - $baseValue) / $baseValue) * 100, 1)
                        : 0
                ]
            ];

        } catch (\Exception $e) {
            $this->logger->error('Next Month Forecast Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @inheritdoc
     */
    public function getDailySalesTrend(int $days = 30): array
    {
        try {
            $sql = "
                SELECT
                    CONVERT(VARCHAR(10), p.DTPEDIDO, 120) as date,
                    SUM(i.VLRTOTAL) as total,
                    COUNT(DISTINCT p.CODIGO) as orders
                FROM VE_PEDIDO p
                INNER JOIN VE_PEDIDOITENS i ON p.CODIGO = i.PEDIDO
                WHERE p.STATUS NOT IN ('C', 'X')
                  AND p.DTPEDIDO >= DATEADD(DAY, -{$days}, GETDATE())
                GROUP BY CONVERT(VARCHAR(10), p.DTPEDIDO, 120)
                ORDER BY date ASC
            ";

            $results = $this->connection->query($sql);

            $data = [
                'dates' => [],
                'sales' => [],
                'orders' => [],
                'moving_avg' => []
            ];

            $values = [];
            foreach ($results as $row) {
                $data['dates'][] = $row['date'];
                $data['sales'][] = (float)$row['total'];
                $data['orders'][] = (int)$row['orders'];
                $values[] = (float)$row['total'];

                // 7-day moving average
                if (count($values) >= 7) {
                    $avg = array_sum(array_slice($values, -7)) / 7;
                    $data['moving_avg'][] = round($avg, 2);
                } else {
                    $data['moving_avg'][] = null;
                }
            }

            // Add forecast for next 7 days
            $avgDaily = count($values) > 0 ? array_sum($values) / count($values) : 0;
            $forecast = [];
            for ($i = 1; $i <= 7; $i++) {
                $date = date('Y-m-d', strtotime("+$i days"));
                $forecast[] = [
                    'date' => $date,
                    'projected' => round($avgDaily * (1 + ($this->calculateTrend() * $i / 30)), 2)
                ];
            }

            $data['forecast'] = $forecast;
            $data['summary'] = [
                'total' => round(array_sum($values), 2),
                'average' => round($avgDaily, 2),
                'max' => round(max($values ?: [0]), 2),
                'min' => round(min($values ?: [0]), 2)
            ];

            return $data;

        } catch (\Exception $e) {
            $this->logger->error('Daily Trend Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @inheritdoc
     */
    public function getMonthlyComparison(): array
    {
        try {
            $sql = "
                SELECT
                    YEAR(p.DTPEDIDO) as year,
                    MONTH(p.DTPEDIDO) as month,
                    SUM(i.VLRTOTAL) as total,
                    COUNT(DISTINCT p.CODIGO) as orders,
                    COUNT(DISTINCT p.CLIENTE) as customers
                FROM VE_PEDIDO p
                INNER JOIN VE_PEDIDOITENS i ON p.CODIGO = i.PEDIDO
                WHERE p.STATUS NOT IN ('C', 'X')
                  AND p.DTPEDIDO >= DATEADD(MONTH, -12, GETDATE())
                GROUP BY YEAR(p.DTPEDIDO), MONTH(p.DTPEDIDO)
                ORDER BY year, month
            ";

            $results = $this->connection->query($sql);

            $data = [];
            foreach ($results as $row) {
                $monthKey = sprintf('%d-%02d', $row['year'], $row['month']);
                $data[] = [
                    'month' => $monthKey,
                    'month_name' => $this->getMonthName((int)$row['month']),
                    'year' => (int)$row['year'],
                    'total' => (float)$row['total'],
                    'orders' => (int)$row['orders'],
                    'customers' => (int)$row['customers'],
                    'ticket_medio' => $row['orders'] > 0
                        ? round($row['total'] / $row['orders'], 2)
                        : 0
                ];
            }

            return $data;

        } catch (\Exception $e) {
            $this->logger->error('Monthly Comparison Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get sales for a specific month
     */
    private function getMonthSales(string $month): float
    {
        $sql = "
            SELECT COALESCE(SUM(i.VLRTOTAL), 0) as total
            FROM VE_PEDIDO p
            INNER JOIN VE_PEDIDOITENS i ON p.CODIGO = i.PEDIDO
            WHERE p.STATUS NOT IN ('C', 'X')
              AND CONVERT(VARCHAR(7), p.DTPEDIDO, 120) = ?
        ";

        $result = $this->connection->query($sql, [$month]);
        return (float)($result[0]['total'] ?? 0);
    }

    /**
     * Get day of week sales weights
     */
    private function getDayOfWeekWeights(): array
    {
        $sql = "
            SELECT
                DATEPART(WEEKDAY, p.DTPEDIDO) as day_of_week,
                AVG(daily_total) as avg_sales
            FROM (
                SELECT
                    CONVERT(DATE, p.DTPEDIDO) as sale_date,
                    SUM(i.VLRTOTAL) as daily_total
                FROM VE_PEDIDO p
                INNER JOIN VE_PEDIDOITENS i ON p.CODIGO = i.PEDIDO
                WHERE p.STATUS NOT IN ('C', 'X')
                  AND p.DTPEDIDO >= DATEADD(MONTH, -3, GETDATE())
                GROUP BY CONVERT(DATE, p.DTPEDIDO)
            ) daily
            INNER JOIN VE_PEDIDO p ON CONVERT(DATE, p.DTPEDIDO) = daily.sale_date
            GROUP BY DATEPART(WEEKDAY, p.DTPEDIDO)
        ";

        try {
            $results = $this->connection->query($sql);
            $totalAvg = array_sum(array_column($results, 'avg_sales')) / max(count($results), 1);

            $weights = [];
            foreach ($results as $row) {
                $dayOfWeek = (int)$row['day_of_week'];
                $weights[$dayOfWeek] = $totalAvg > 0 ? $row['avg_sales'] / $totalAvg : 1.0;
            }

            // Fill missing days with 1.0
            for ($i = 1; $i <= 7; $i++) {
                if (!isset($weights[$i])) {
                    $weights[$i] = 1.0;
                }
            }

            return $weights;

        } catch (\Exception $e) {
            return array_fill(1, 7, 1.0);
        }
    }

    /**
     * Calculate sales trend
     */
    private function calculateTrend(): float
    {
        $sql = "
            SELECT
                SUM(CASE WHEN p.DTPEDIDO >= DATEADD(DAY, -30, GETDATE()) THEN i.VLRTOTAL ELSE 0 END) as last_30,
                SUM(CASE WHEN p.DTPEDIDO >= DATEADD(DAY, -60, GETDATE())
                         AND p.DTPEDIDO < DATEADD(DAY, -30, GETDATE()) THEN i.VLRTOTAL ELSE 0 END) as prev_30
            FROM VE_PEDIDO p
            INNER JOIN VE_PEDIDOITENS i ON p.CODIGO = i.PEDIDO
            WHERE p.STATUS NOT IN ('C', 'X')
              AND p.DTPEDIDO >= DATEADD(DAY, -60, GETDATE())
        ";

        try {
            $result = $this->connection->query($sql);
            $last30 = (float)($result[0]['last_30'] ?? 0);
            $prev30 = (float)($result[0]['prev_30'] ?? 0);

            if ($prev30 > 0) {
                return ($last30 - $prev30) / $prev30;
            }

            return 0;

        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Run Monte Carlo simulation
     */
    private function runMonteCarloSimulation(float $actual, float $projected, int $iterations): array
    {
        $results = [];
        $volatility = 0.15; // 15% standard deviation

        for ($i = 0; $i < $iterations; $i++) {
            $variation = $this->normalRandom() * ($projected * $volatility);
            $results[] = $actual + $projected + $variation;
        }

        sort($results);

        return [
            'p5' => $results[(int)($iterations * 0.05)],
            'p10' => $results[(int)($iterations * 0.10)],
            'p50' => $results[(int)($iterations * 0.50)],
            'p90' => $results[(int)($iterations * 0.90)],
            'p95' => $results[(int)($iterations * 0.95)]
        ];
    }

    /**
     * Generate normal random number (Box-Muller transform)
     */
    private function normalRandom(): float
    {
        $u1 = \random_int(1, PHP_INT_MAX) / PHP_INT_MAX;
        $u2 = \random_int(1, PHP_INT_MAX) / PHP_INT_MAX;
        return \sqrt(-2 * \log(\max($u1, 0.0001))) * \cos(2 * M_PI * $u2);
    }

    /**
     * Get last 12 months sales data
     */
    private function getLast12MonthsSales(): array
    {
        return $this->getMonthlyComparison();
    }

    /**
     * Calculate seasonal index for each month
     */
    private function calculateSeasonalIndex(array $history): array
    {
        if (empty($history)) {
            return array_fill(1, 12, 1.0);
        }

        $monthlyTotals = [];
        foreach ($history as $month) {
            $monthNum = isset($month['month_name_num']) ? (int)$month['month_name_num'] : ((int)substr($month['month'], 5, 2));
            if (!isset($monthlyTotals[$monthNum])) {
                $monthlyTotals[$monthNum] = [];
            }
            $monthlyTotals[$monthNum][] = $month['total'];
        }

        $overallAvg = array_sum(array_column($history, 'total')) / max(count($history), 1);

        $seasonalIndex = [];
        for ($i = 1; $i <= 12; $i++) {
            if (isset($monthlyTotals[$i]) && count($monthlyTotals[$i]) > 0) {
                $monthAvg = array_sum($monthlyTotals[$i]) / count($monthlyTotals[$i]);
                $seasonalIndex[$i] = $overallAvg > 0 ? $monthAvg / $overallAvg : 1.0;
            } else {
                $seasonalIndex[$i] = 1.0;
            }
        }

        return $seasonalIndex;
    }

    /**
     * Calculate growth rate from historical data
     */
    private function calculateGrowthRate(array $history): float
    {
        if (count($history) < 2) {
            return 0;
        }

        $firstHalf = array_slice($history, 0, (int)(count($history) / 2));
        $secondHalf = array_slice($history, (int)(count($history) / 2));

        $firstAvg = array_sum(array_column($firstHalf, 'total')) / max(count($firstHalf), 1);
        $secondAvg = array_sum(array_column($secondHalf, 'total')) / max(count($secondHalf), 1);

        if ($firstAvg > 0) {
            return ($secondAvg - $firstAvg) / $firstAvg / (count($history) / 2);
        }

        return 0;
    }

    /**
     * Get monthly goal (can be configured or calculated)
     */
    private function getMonthlyGoal(): float
    {
        // For now, use last month + 10% as goal
        $lastMonth = date('Y-m', strtotime('-1 month'));
        return $this->getMonthSales($lastMonth) * 1.10;
    }

    /**
     * Get month name in Portuguese
     */
    private function getMonthName(int $month): string
    {
        $months = [
            1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março',
            4 => 'Abril', 5 => 'Maio', 6 => 'Junho',
            7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro',
            10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
        ];

        return $months[$month] ?? '';
    }

    /**
     * Return empty projection structure
     */
    private function getEmptyProjection(): array
    {
        return [
            'current_month' => date('Y-m'),
            'days_passed' => 0,
            'days_remaining' => (int)date('t'),
            'actual_sales' => 0,
            'projection' => ['pessimistic' => 0, 'realistic' => 0, 'optimistic' => 0],
            'error' => 'Dados insuficientes para projeção'
        ];
    }
}
