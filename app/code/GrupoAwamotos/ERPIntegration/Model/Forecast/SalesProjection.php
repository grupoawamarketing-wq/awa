<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Model\Forecast;

use GrupoAwamotos\ERPIntegration\Api\ConnectionInterface;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use Magento\Framework\App\CacheInterface;
use Psr\Log\LoggerInterface;

/**
 * Sales Projection Model
 *
 * Provides sales forecasting using multiple algorithms:
 * - Weighted Moving Average
 * - Linear Trend
 * - Seasonality Index
 * - Monte Carlo Simulation for confidence intervals
 */
class SalesProjection
{
    private const CACHE_PREFIX = 'erp_forecast_';
    private const CACHE_TTL = 3600; // 1 hour

    private ConnectionInterface $connection;
    private Helper $helper;
    private CacheInterface $cache;
    private LoggerInterface $logger;

    public function __construct(
        ConnectionInterface $connection,
        Helper $helper,
        CacheInterface $cache,
        LoggerInterface $logger
    ) {
        $this->connection = $connection;
        $this->helper = $helper;
        $this->cache = $cache;
        $this->logger = $logger;
    }

    /**
     * Get current month projection
     */
    public function getCurrentMonthProjection(): array
    {
        $cacheKey = self::CACHE_PREFIX . 'current_month';
        $cached = $this->cache->load($cacheKey);

        if ($cached) {
            return json_decode($cached, true);
        }

        try {
            $currentMonth = date('Y-m');
            $daysPassed = (int)date('j');
            $totalDays = (int)date('t');
            $daysRemaining = $totalDays - $daysPassed;

            // Get sales for current month so far
            $currentSales = $this->getMonthSales($currentMonth);

            // Get historical data for projection
            $lastMonthSales = $this->getMonthSales(date('Y-m', strtotime('-1 month')));
            $lastYearSamePeriod = $this->getMonthSales(date('Y-m', strtotime('-12 months')));
            $last12Months = $this->getLast12MonthsSales();

            // Calculate daily average
            $dailyAvg = $daysPassed > 0 ? $currentSales / $daysPassed : 0;

            // Get day-of-week weights
            $dayWeights = $this->getDayOfWeekWeights();

            // Project remaining days
            $projectedRemaining = 0;
            for ($i = 1; $i <= $daysRemaining; $i++) {
                $date = date('Y-m-d', strtotime("+$i days"));
                $dayOfWeek = (int)date('N', strtotime($date));
                $projectedRemaining += $dailyAvg * ($dayWeights[$dayOfWeek] ?? 1);
            }

            // Calculate trend adjustment
            $trendFactor = $this->calculateTrendFactor($last12Months);
            $trendAdjustment = $projectedRemaining * ($trendFactor - 1);

            // Base projection
            $baseProjection = $currentSales + $projectedRemaining + $trendAdjustment;

            // Monte Carlo simulation for confidence intervals
            $scenarios = $this->runMonteCarloSimulation($currentSales, $projectedRemaining, 1000);

            // Get target (if set) or use last month as reference
            $monthTarget = $this->getMonthTarget() ?: $lastMonthSales * 1.05;

            $result = [
                'current_date' => date('Y-m-d'),
                'days_passed' => $daysPassed,
                'days_remaining' => $daysRemaining,
                'total_days' => $totalDays,
                'actual_sales' => round($currentSales, 2),
                'daily_average' => round($dailyAvg, 2),
                'projected_total' => round($baseProjection, 2),
                'pessimistic' => round($scenarios['p10'], 2),
                'realistic' => round($scenarios['p50'], 2),
                'optimistic' => round($scenarios['p90'], 2),
                'confidence_level' => 0.80,
                'target' => round($monthTarget, 2),
                'target_gap' => round($monthTarget - $currentSales, 2),
                'target_daily_needed' => $daysRemaining > 0 ? round(($monthTarget - $currentSales) / $daysRemaining, 2) : 0,
                'progress_percentage' => $monthTarget > 0 ? round(($currentSales / $monthTarget) * 100, 1) : 0,
                'vs_last_month' => $lastMonthSales > 0 ? round((($currentSales / ($lastMonthSales * ($daysPassed / $totalDays))) - 1) * 100, 1) : 0,
                'vs_last_year' => $lastYearSamePeriod > 0 ? round((($currentSales / ($lastYearSamePeriod * ($daysPassed / $totalDays))) - 1) * 100, 1) : 0,
                'trend_factor' => round($trendFactor, 3),
                'will_hit_target' => $baseProjection >= $monthTarget,
                'alert_level' => $this->getAlertLevel($baseProjection, $monthTarget),
            ];

            $this->cache->save(json_encode($result), $cacheKey, [], self::CACHE_TTL);

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('[ERP Forecast] Error projecting: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get next month projection
     */
    public function getNextMonthProjection(): array
    {
        try {
            $last12Months = $this->getLast12MonthsSales();

            if (empty($last12Months)) {
                return [];
            }

            // Base: average of last 3 months
            $recentMonths = array_slice($last12Months, -3);
            $baseValue = array_sum(array_column($recentMonths, 'value')) / 3;

            // Calculate seasonality index
            $seasonalIndex = $this->calculateSeasonalityIndex($last12Months);
            $nextMonthIndex = ((int)date('n') % 12) + 1;

            // Calculate growth rate
            $growthRate = $this->calculateGrowthRate($last12Months);

            // Apply adjustments
            $seasonalFactor = $seasonalIndex[$nextMonthIndex] ?? 1;
            $projection = $baseValue * $seasonalFactor * (1 + $growthRate);

            return [
                'month' => date('Y-m', strtotime('+1 month')),
                'month_name' => $this->getMonthName((int)date('n', strtotime('+1 month'))),
                'projection' => round($projection, 2),
                'base_value' => round($baseValue, 2),
                'seasonal_factor' => round($seasonalFactor, 3),
                'growth_rate' => round($growthRate, 3),
                'range_min' => round($projection * 0.85, 2),
                'range_max' => round($projection * 1.15, 2),
                'confidence_level' => 0.70,
            ];
        } catch (\Exception $e) {
            $this->logger->error('[ERP Forecast] Error projecting next month: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get daily sales for chart (last 30 days + projection)
     */
    public function getDailySalesChart(int $daysBack = 30, int $daysForward = 7): array
    {
        try {
            // Get actual sales
            // Note: $daysBack is interpolated directly as it's a controlled integer value
            $actualSales = $this->connection->query("
                SELECT
                    CONVERT(VARCHAR(10), p.DTPEDIDO, 120) as date,
                    COUNT(DISTINCT p.CODIGO) as orders,
                    SUM(i.VLRTOTAL) as value
                FROM VE_PEDIDO p
                INNER JOIN VE_PEDIDOITENS i ON p.CODIGO = i.PEDIDO
                WHERE p.STATUS NOT IN ('C', 'X')
                AND p.DTPEDIDO >= DATEADD(day, -{$daysBack}, GETDATE())
                AND p.DTPEDIDO <= GETDATE()
                GROUP BY CONVERT(VARCHAR(10), p.DTPEDIDO, 120)
                ORDER BY date
            ", []);

            $salesByDate = [];
            foreach ($actualSales as $row) {
                $salesByDate[$row['date']] = [
                    'date' => $row['date'],
                    'value' => (float)$row['value'],
                    'orders' => (int)$row['orders'],
                    'type' => 'actual',
                ];
            }

            // Calculate average for projection
            $avgDaily = count($salesByDate) > 0 ? array_sum(array_column($salesByDate, 'value')) / count($salesByDate) : 0;
            $dayWeights = $this->getDayOfWeekWeights();

            // Fill missing days and add projections
            $result = [];
            $startDate = date('Y-m-d', strtotime("-$daysBack days"));
            $endDate = date('Y-m-d', strtotime("+$daysForward days"));

            $current = new \DateTime($startDate);
            $end = new \DateTime($endDate);

            while ($current <= $end) {
                $dateStr = $current->format('Y-m-d');
                $dayOfWeek = (int)$current->format('N');
                $isToday = $dateStr === date('Y-m-d');
                $isFuture = $current > new \DateTime();

                if (isset($salesByDate[$dateStr])) {
                    $result[] = $salesByDate[$dateStr];
                } elseif ($isFuture) {
                    $result[] = [
                        'date' => $dateStr,
                        'value' => round($avgDaily * ($dayWeights[$dayOfWeek] ?? 1), 2),
                        'orders' => 0,
                        'type' => 'projection',
                    ];
                } else {
                    $result[] = [
                        'date' => $dateStr,
                        'value' => 0,
                        'orders' => 0,
                        'type' => 'actual',
                    ];
                }

                $current->modify('+1 day');
            }

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('[ERP Forecast] Error getting daily chart: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get monthly sales for last 12 months
     */
    public function getLast12MonthsSales(): array
    {
        try {
            $sales = $this->connection->query("
                SELECT
                    FORMAT(p.DTPEDIDO, 'yyyy-MM') as month,
                    COUNT(DISTINCT p.CODIGO) as orders,
                    COUNT(DISTINCT p.CLIENTE) as customers,
                    SUM(i.VLRTOTAL) as value
                FROM VE_PEDIDO p
                INNER JOIN VE_PEDIDOITENS i ON p.CODIGO = i.PEDIDO
                WHERE p.STATUS NOT IN ('C', 'X')
                AND p.DTPEDIDO >= DATEADD(month, -12, GETDATE())
                GROUP BY FORMAT(p.DTPEDIDO, 'yyyy-MM')
                ORDER BY month
            ");

            return array_map(function ($row) {
                return [
                    'month' => $row['month'],
                    'month_name' => $this->getMonthName((int)substr($row['month'], 5, 2)),
                    'orders' => (int)$row['orders'],
                    'customers' => (int)$row['customers'],
                    'value' => (float)$row['value'],
                ];
            }, $sales);
        } catch (\Exception $e) {
            $this->logger->error('[ERP Forecast] Error getting monthly sales: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get sales for a specific month
     */
    private function getMonthSales(string $yearMonth): float
    {
        try {
            $result = $this->connection->fetchOne("
                SELECT COALESCE(SUM(i.VLRTOTAL), 0) as total
                FROM VE_PEDIDO p
                INNER JOIN VE_PEDIDOITENS i ON p.CODIGO = i.PEDIDO
                WHERE p.STATUS NOT IN ('C', 'X')
                AND FORMAT(p.DTPEDIDO, 'yyyy-MM') = ?
            ", [$yearMonth]);

            return (float)($result['total'] ?? 0);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get day-of-week weights based on historical data
     */
    private function getDayOfWeekWeights(): array
    {
        try {
            $sales = $this->connection->query("
                SELECT
                    DATEPART(dw, p.DTPEDIDO) as day_of_week,
                    AVG(daily_total) as avg_daily
                FROM (
                    SELECT
                        CONVERT(DATE, p.DTPEDIDO) as order_date,
                        DATEPART(dw, p.DTPEDIDO) as day_of_week,
                        SUM(i.VLRTOTAL) as daily_total
                    FROM VE_PEDIDO p
                    INNER JOIN VE_PEDIDOITENS i ON p.CODIGO = i.PEDIDO
                    WHERE p.STATUS NOT IN ('C', 'X')
                    AND p.DTPEDIDO >= DATEADD(month, -3, GETDATE())
                    GROUP BY CONVERT(DATE, p.DTPEDIDO), DATEPART(dw, p.DTPEDIDO)
                ) sub
                GROUP BY DATEPART(dw, p.DTPEDIDO)
            ");

            if (empty($sales)) {
                return [1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 1, 6 => 0.5, 7 => 0.3];
            }

            // Calculate overall average
            $avgValues = array_column($sales, 'avg_daily');
            $overallAvg = array_sum($avgValues) / count($avgValues);

            $weights = [];
            foreach ($sales as $row) {
                // SQL Server: 1=Sunday, 2=Monday, ..., 7=Saturday
                // Convert to ISO: 1=Monday, ..., 7=Sunday
                $isoDay = $row['day_of_week'] == 1 ? 7 : $row['day_of_week'] - 1;
                $weights[$isoDay] = $overallAvg > 0 ? $row['avg_daily'] / $overallAvg : 1;
            }

            // Fill missing days with default
            for ($i = 1; $i <= 7; $i++) {
                if (!isset($weights[$i])) {
                    $weights[$i] = $i >= 6 ? 0.5 : 1; // Weekend default
                }
            }

            return $weights;
        } catch (\Exception $e) {
            return [1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 1, 6 => 0.5, 7 => 0.3];
        }
    }

    /**
     * Calculate trend factor from historical data
     */
    private function calculateTrendFactor(array $monthlyData): float
    {
        if (count($monthlyData) < 3) {
            return 1.0;
        }

        // Simple linear regression on values
        $values = array_column($monthlyData, 'value');
        $n = count($values);
        $sumX = 0;
        $sumY = 0;
        $sumXY = 0;
        $sumX2 = 0;

        for ($i = 0; $i < $n; $i++) {
            $sumX += $i;
            $sumY += $values[$i];
            $sumXY += $i * $values[$i];
            $sumX2 += $i * $i;
        }

        $denominator = ($n * $sumX2 - $sumX * $sumX);
        if ($denominator == 0) {
            return 1.0;
        }

        $slope = ($n * $sumXY - $sumX * $sumY) / $denominator;
        $avgValue = $sumY / $n;

        // Convert slope to monthly growth factor
        return $avgValue > 0 ? 1 + ($slope / $avgValue) : 1.0;
    }

    /**
     * Calculate seasonality index for each month
     *
     * Uses detrended data to avoid growth bias.
     * With rapid growth, raw value/average produces misleading factors (e.g. 0.03x).
     * Instead, we divide each month's actual value by its trend-line value,
     * producing a ratio close to 1.0 that purely reflects seasonality.
     */
    private function calculateSeasonalityIndex(array $monthlyData): array
    {
        $count = count($monthlyData);

        // Need at least 6 months for meaningful seasonality
        if ($count < 6) {
            return array_fill(1, 12, 1.0);
        }

        // Step 1: Detrend using linear regression
        $values = array_column($monthlyData, 'value');
        $n = count($values);
        $sumX = 0;
        $sumY = 0;
        $sumXY = 0;
        $sumX2 = 0;

        for ($i = 0; $i < $n; $i++) {
            $sumX += $i;
            $sumY += $values[$i];
            $sumXY += $i * $values[$i];
            $sumX2 += $i * $i;
        }

        $denominator = ($n * $sumX2 - $sumX * $sumX);
        if ($denominator == 0) {
            return array_fill(1, 12, 1.0);
        }

        $slope = ($n * $sumXY - $sumX * $sumY) / $denominator;
        $intercept = ($sumY - $slope * $sumX) / $n;

        // Step 2: Calculate detrended ratios per calendar month
        $monthRatios = [];
        $monthCounts = [];

        foreach ($monthlyData as $i => $data) {
            $month = (int)substr($data['month'], 5, 2);
            $trendValue = $intercept + $slope * $i;

            // Skip if trend value is zero or negative (no meaningful ratio)
            if ($trendValue <= 0) {
                continue;
            }

            $ratio = $data['value'] / $trendValue;

            if (!isset($monthRatios[$month])) {
                $monthRatios[$month] = 0.0;
                $monthCounts[$month] = 0;
            }
            $monthRatios[$month] += $ratio;
            $monthCounts[$month]++;
        }

        // Step 3: Average ratios and clamp extreme values
        $index = [];
        foreach ($monthRatios as $month => $totalRatio) {
            $avgRatio = $monthCounts[$month] > 0 ? $totalRatio / $monthCounts[$month] : 1.0;
            // Clamp between 0.5 and 2.0 to avoid extreme distortions
            $index[$month] = max(0.5, min(2.0, $avgRatio));
        }

        // Fill missing months with neutral factor
        for ($i = 1; $i <= 12; $i++) {
            if (!isset($index[$i])) {
                $index[$i] = 1.0;
            }
        }

        return $index;
    }

    /**
     * Calculate growth rate from historical data
     */
    private function calculateGrowthRate(array $monthlyData): float
    {
        if (count($monthlyData) < 2) {
            return 0;
        }

        $first = $monthlyData[0]['value'];
        $last = end($monthlyData)['value'];
        $periods = count($monthlyData) - 1;

        if ($first <= 0 || $periods <= 0) {
            return 0;
        }

        // CAGR formula: (end/start)^(1/periods) - 1
        return pow($last / $first, 1 / $periods) - 1;
    }

    /**
     * Run Monte Carlo simulation for confidence intervals
     */
    private function runMonteCarloSimulation(float $actual, float $projected, int $iterations = 1000): array
    {
        $results = [];
        $stdDev = $projected * 0.15; // 15% standard deviation

        for ($i = 0; $i < $iterations; $i++) {
            // Generate random variation using Box-Muller transform
            $u1 = \random_int(1, PHP_INT_MAX) / PHP_INT_MAX;
            $u2 = \random_int(1, PHP_INT_MAX) / PHP_INT_MAX;
            $z = \sqrt(-2 * \log($u1)) * \cos(2 * M_PI * $u2);

            $results[] = $actual + $projected + ($z * $stdDev);
        }

        sort($results);

        return [
            'p10' => $results[(int)($iterations * 0.1)],
            'p25' => $results[(int)($iterations * 0.25)],
            'p50' => $results[(int)($iterations * 0.5)],
            'p75' => $results[(int)($iterations * 0.75)],
            'p90' => $results[(int)($iterations * 0.9)],
        ];
    }

    /**
     * Get month target from configuration
     */
    private function getMonthTarget(): float
    {
        return $this->helper->getMonthlyTarget();
    }

    /**
     * Get alert level based on projection vs target
     */
    private function getAlertLevel(float $projection, float $target): string
    {
        if ($target <= 0) {
            return 'none';
        }

        $ratio = $projection / $target;

        if ($ratio >= 1) {
            return 'success';
        } elseif ($ratio >= 0.9) {
            return 'warning';
        } elseif ($ratio >= 0.8) {
            return 'danger';
        }

        return 'critical';
    }

    /**
     * Get month name in Portuguese
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
     * Get monthly comparison data
     *
     * Compares months year-over-year for trend analysis
     */
    public function getMonthlyComparison(int $monthsBack = 12): array
    {
        $cacheKey = self::CACHE_PREFIX . 'monthly_comparison_' . $monthsBack;
        $cached = $this->cache->load($cacheKey);

        if ($cached) {
            return json_decode($cached, true);
        }

        try {
            // Get current year data
            $currentYear = (int)date('Y');
            $currentYearData = $this->connection->query("
                SELECT
                    MONTH(p.DTPEDIDO) as month_num,
                    FORMAT(p.DTPEDIDO, 'yyyy-MM') as month,
                    COUNT(DISTINCT p.CODIGO) as orders,
                    COUNT(DISTINCT p.CLIENTE) as customers,
                    SUM(i.VLRTOTAL) as value
                FROM VE_PEDIDO p
                INNER JOIN VE_PEDIDOITENS i ON p.CODIGO = i.PEDIDO
                WHERE p.STATUS NOT IN ('C', 'X')
                AND YEAR(p.DTPEDIDO) = ?
                GROUP BY MONTH(p.DTPEDIDO), FORMAT(p.DTPEDIDO, 'yyyy-MM')
                ORDER BY month_num
            ", [$currentYear]);

            // Get previous year data
            $previousYear = $currentYear - 1;
            $previousYearData = $this->connection->query("
                SELECT
                    MONTH(p.DTPEDIDO) as month_num,
                    FORMAT(p.DTPEDIDO, 'yyyy-MM') as month,
                    COUNT(DISTINCT p.CODIGO) as orders,
                    COUNT(DISTINCT p.CLIENTE) as customers,
                    SUM(i.VLRTOTAL) as value
                FROM VE_PEDIDO p
                INNER JOIN VE_PEDIDOITENS i ON p.CODIGO = i.PEDIDO
                WHERE p.STATUS NOT IN ('C', 'X')
                AND YEAR(p.DTPEDIDO) = ?
                GROUP BY MONTH(p.DTPEDIDO), FORMAT(p.DTPEDIDO, 'yyyy-MM')
                ORDER BY month_num
            ", [$previousYear]);

            // Index by month number
            $currentByMonth = [];
            foreach ($currentYearData as $row) {
                $currentByMonth[(int)$row['month_num']] = [
                    'month' => $row['month'],
                    'orders' => (int)$row['orders'],
                    'customers' => (int)$row['customers'],
                    'value' => (float)$row['value'],
                ];
            }

            $previousByMonth = [];
            foreach ($previousYearData as $row) {
                $previousByMonth[(int)$row['month_num']] = [
                    'month' => $row['month'],
                    'orders' => (int)$row['orders'],
                    'customers' => (int)$row['customers'],
                    'value' => (float)$row['value'],
                ];
            }

            // Build comparison result
            $result = [];
            for ($month = 1; $month <= 12; $month++) {
                $currentData = $currentByMonth[$month] ?? null;
                $previousData = $previousByMonth[$month] ?? null;

                $currentValue = $currentData['value'] ?? 0;
                $previousValue = $previousData['value'] ?? 0;

                $yoyChange = 0;
                if ($previousValue > 0) {
                    $yoyChange = (($currentValue - $previousValue) / $previousValue) * 100;
                }

                $result[] = [
                    'month' => $month,
                    'month_name' => $this->getMonthName($month),
                    'current_year' => $currentYear,
                    'previous_year' => $previousYear,
                    'current_value' => round($currentValue, 2),
                    'previous_value' => round($previousValue, 2),
                    'current_orders' => $currentData['orders'] ?? 0,
                    'previous_orders' => $previousData['orders'] ?? 0,
                    'current_customers' => $currentData['customers'] ?? 0,
                    'previous_customers' => $previousData['customers'] ?? 0,
                    'yoy_change_percent' => round($yoyChange, 1),
                    'yoy_change_value' => round($currentValue - $previousValue, 2),
                ];
            }

            // Calculate YTD totals
            $currentMonth = (int)date('n');
            $ytdCurrent = 0;
            $ytdPrevious = 0;

            for ($m = 1; $m <= $currentMonth; $m++) {
                $ytdCurrent += $result[$m - 1]['current_value'];
                $ytdPrevious += $result[$m - 1]['previous_value'];
            }

            $ytdChange = $ytdPrevious > 0 ? (($ytdCurrent - $ytdPrevious) / $ytdPrevious) * 100 : 0;

            $this->cache->save(json_encode([
                'months' => $result,
                'ytd' => [
                    'current_year' => $currentYear,
                    'previous_year' => $previousYear,
                    'current_total' => round($ytdCurrent, 2),
                    'previous_total' => round($ytdPrevious, 2),
                    'change_percent' => round($ytdChange, 1),
                    'change_value' => round($ytdCurrent - $ytdPrevious, 2),
                ],
            ]), $cacheKey, [], self::CACHE_TTL);

            return [
                'months' => $result,
                'ytd' => [
                    'current_year' => $currentYear,
                    'previous_year' => $previousYear,
                    'current_total' => round($ytdCurrent, 2),
                    'previous_total' => round($ytdPrevious, 2),
                    'change_percent' => round($ytdChange, 1),
                    'change_value' => round($ytdCurrent - $ytdPrevious, 2),
                ],
            ];
        } catch (\Exception $e) {
            $this->logger->error('[ERP Forecast] Error getting monthly comparison: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Clear forecast cache
     */
    public function clearCache(): void
    {
        $this->cache->remove(self::CACHE_PREFIX . 'current_month');
    }
}
