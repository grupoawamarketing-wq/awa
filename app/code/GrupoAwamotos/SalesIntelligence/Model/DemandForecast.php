<?php

/**
 * Demand Forecast Model
 *
 * Per-product and per-category demand forecasting using weighted moving averages,
 * trend detection, and seasonality indices from ERP historical data.
 */

declare(strict_types=1);

namespace GrupoAwamotos\SalesIntelligence\Model;

use GrupoAwamotos\ERPIntegration\Api\ConnectionInterface;
use GrupoAwamotos\ERPIntegration\Model\StockSync;
use Magento\Framework\App\CacheInterface;
use Psr\Log\LoggerInterface;

class DemandForecast
{
    private const CACHE_PREFIX = 'si_demand_';
    private const CACHE_TTL = 21600; // 6 hours
    private const CACHE_TAG = 'sales_intelligence_demand';

    private ConnectionInterface $connection;
    private StockSync $stockSync;
    private CacheInterface $cache;
    private LoggerInterface $logger;

    public function __construct(
        ConnectionInterface $connection,
        StockSync $stockSync,
        CacheInterface $cache,
        LoggerInterface $logger
    ) {
        $this->connection = $connection;
        $this->stockSync = $stockSync;
        $this->cache = $cache;
        $this->logger = $logger;
    }

    /**
     * Get product-level demand forecast
     *
     * @return array Products with forecast data, sorted by forecast_30d_revenue DESC
     */
    public function getProductForecast(int $monthsBack = 12, int $forecastDays = 30): array
    {
        $cacheKey = self::CACHE_PREFIX . "product_{$monthsBack}_{$forecastDays}";
        $cached = $this->cache->load($cacheKey);
        if ($cached) {
            return json_decode($cached, true) ?: [];
        }

        try {
            $salesData = $this->fetchMonthlySalesByProduct($monthsBack);
            if (empty($salesData)) {
                return [];
            }

            $products = $this->buildProductForecasts($salesData, $forecastDays);

            // Sort by forecast revenue descending first
            usort($products, fn($a, $b) => ($b['forecast_30d_revenue'] ?? 0) <=> ($a['forecast_30d_revenue'] ?? 0));

            // Enrich only top 50 with stock data (avoid N+1 ERP queries)
            $products = $this->enrichWithStockData($products, 50);

            $this->cache->save(
                json_encode($products),
                $cacheKey,
                [self::CACHE_TAG],
                self::CACHE_TTL
            );

            return $products;
        } catch (\Exception $e) {
            $this->logger->error('[SalesIntelligence] DemandForecast error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get category-level forecast
     */
    public function getCategoryForecast(): array
    {
        $cacheKey = self::CACHE_PREFIX . 'category';
        $cached = $this->cache->load($cacheKey);
        if ($cached) {
            return json_decode($cached, true) ?: [];
        }

        try {
            $result = $this->fetchCategorySales();

            $this->cache->save(
                json_encode($result),
                $cacheKey,
                [self::CACHE_TAG],
                self::CACHE_TTL
            );

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('[SalesIntelligence] CategoryForecast error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get products with stockout risk
     */
    public function getStockoutRisks(int $thresholdDays = 15): array
    {
        $products = $this->getProductForecast();
        return array_values(array_filter($products, function ($p) use ($thresholdDays) {
            return ($p['stockout_risk'] ?? false) === true
                && ($p['days_of_stock'] ?? 999) <= $thresholdDays
                && ($p['forecast_30d_qty'] ?? 0) > 0;
        }));
    }

    /**
     * Fetch monthly sales grouped by product from ERP
     */
    private function fetchMonthlySalesByProduct(int $monthsBack): array
    {
        $offset = -(int) $monthsBack;
        $sql = "SELECT
                    I.MATERIAL AS sku,
                    M.DESCRICAO AS name,
                    ISNULL(GC.DESCRICAO, 'Sem Categoria') AS category,
                    SUM(I.QTDE) AS total_qty,
                    SUM(I.VLRTOTAL) AS total_revenue,
                    MONTH(P.DTPEDIDO) AS sale_month,
                    YEAR(P.DTPEDIDO) AS sale_year
                FROM VE_PEDIDOITENS I
                INNER JOIN VE_PEDIDO P ON P.CODIGO = I.PEDIDO
                INNER JOIN MT_MATERIAL M ON M.CODIGO = I.MATERIAL
                LEFT JOIN MT_GRUPOCOMERCIAL GC ON GC.CODIGO = M.GRUPOCOMERCIAL
                WHERE P.DTPEDIDO >= DATEADD(month, CAST({$offset} AS INT), GETDATE())
                  AND P.STATUS NOT IN ('C', 'D')
                  AND I.QTDE > 0
                GROUP BY I.MATERIAL, M.DESCRICAO, GC.DESCRICAO,
                         MONTH(P.DTPEDIDO), YEAR(P.DTPEDIDO)
                ORDER BY I.MATERIAL, YEAR(P.DTPEDIDO), MONTH(P.DTPEDIDO)";

        return $this->connection->query($sql);
    }

    /**
     * Build per-product forecast from monthly sales data
     */
    private function buildProductForecasts(array $salesData, int $forecastDays): array
    {
        // Group by SKU
        $grouped = [];
        foreach ($salesData as $row) {
            $sku = $row['sku'];
            if (!isset($grouped[$sku])) {
                $grouped[$sku] = [
                    'sku' => $sku,
                    'name' => $row['name'],
                    'category' => $row['category'],
                    'monthly' => [],
                ];
            }
            $key = sprintf('%04d-%02d', (int) $row['sale_year'], (int) $row['sale_month']);
            $grouped[$sku]['monthly'][$key] = [
                'qty' => (float) $row['total_qty'],
                'revenue' => (float) $row['total_revenue'],
            ];
        }

        $now = new \DateTime();
        $currentKey = $now->format('Y-m');
        $currentMonth = (int) $now->format('n');

        $products = [];
        foreach ($grouped as $sku => $data) {
            $monthly = $data['monthly'];
            $monthKeys = array_keys($monthly);
            sort($monthKeys);
            $monthCount = count($monthKeys);

            // Calculate last 30d and last 90d sales
            $last1 = $this->getRecentMonthsTotal($monthly, 1);
            $last3 = $this->getRecentMonthsTotal($monthly, 3);

            // Weighted moving average (last 3 months: 50%, 30%, 20%)
            $wma = $this->calculateWeightedMovingAverage($monthly);

            // Trend: compare last 3 months avg vs previous 3 months avg
            $trendPct = $this->calculateTrend($monthly);

            // Seasonality index for current month
            $seasonalIndex = $this->calculateSeasonalIndex($monthly, $currentMonth);

            // Forecast
            $forecastQty = $wma['qty'] * (1 + $trendPct / 100) * $seasonalIndex * ($forecastDays / 30);
            $forecastRevenue = $wma['revenue'] * (1 + $trendPct / 100) * $seasonalIndex * ($forecastDays / 30);

            // Determine trend label
            $trend = 'stable';
            if ($trendPct > 10) {
                $trend = 'up';
            } elseif ($trendPct < -10) {
                $trend = 'down';
            }

            $products[] = [
                'sku' => $sku,
                'name' => $data['name'],
                'category' => $data['category'],
                'sales_last_30d_qty' => round($last1['qty'], 1),
                'sales_last_30d_revenue' => round($last1['revenue'], 2),
                'sales_last_90d_qty' => round($last3['qty'], 1),
                'sales_last_90d_revenue' => round($last3['revenue'], 2),
                'forecast_30d_qty' => max(0, round($forecastQty, 1)),
                'forecast_30d_revenue' => max(0, round($forecastRevenue, 2)),
                'trend' => $trend,
                'trend_pct' => round($trendPct, 1),
                'seasonal_index' => round($seasonalIndex, 2),
                'months_with_sales' => $monthCount,
                'current_stock' => 0,
                'days_of_stock' => 0,
                'stockout_risk' => false,
            ];
        }

        return $products;
    }

    /**
     * Get total for the N most recent months
     */
    private function getRecentMonthsTotal(array $monthly, int $n): array
    {
        $keys = array_keys($monthly);
        sort($keys);
        $recent = array_slice($keys, -$n);

        $qty = 0;
        $revenue = 0;
        foreach ($recent as $key) {
            $qty += $monthly[$key]['qty'];
            $revenue += $monthly[$key]['revenue'];
        }
        return ['qty' => $qty, 'revenue' => $revenue];
    }

    /**
     * Weighted moving average of last 3 months (monthly rate)
     * Weights: most recent = 0.5, second = 0.3, third = 0.2
     */
    private function calculateWeightedMovingAverage(array $monthly): array
    {
        $keys = array_keys($monthly);
        sort($keys);
        $count = count($keys);

        $weights = [0.5, 0.3, 0.2];
        $wmaQty = 0;
        $wmaRevenue = 0;
        $totalWeight = 0;

        for ($i = 0; $i < min(3, $count); $i++) {
            $key = $keys[$count - 1 - $i];
            $weight = $weights[$i] ?? 0.2;
            $wmaQty += $monthly[$key]['qty'] * $weight;
            $wmaRevenue += $monthly[$key]['revenue'] * $weight;
            $totalWeight += $weight;
        }

        if ($totalWeight > 0) {
            $wmaQty /= $totalWeight;
            $wmaRevenue /= $totalWeight;
        }

        return ['qty' => $wmaQty, 'revenue' => $wmaRevenue];
    }

    /**
     * Calculate trend: compare last 3 months average vs previous 3 months average
     * Returns percentage change
     */
    private function calculateTrend(array $monthly): float
    {
        $keys = array_keys($monthly);
        sort($keys);
        $count = count($keys);

        if ($count < 4) {
            return 0.0;
        }

        // Last 3 months
        $recentTotal = 0;
        for ($i = 0; $i < min(3, $count); $i++) {
            $recentTotal += $monthly[$keys[$count - 1 - $i]]['revenue'];
        }
        $recentAvg = $recentTotal / min(3, $count);

        // Previous 3 months
        $prevTotal = 0;
        $prevCount = 0;
        for ($i = 3; $i < min(6, $count); $i++) {
            $prevTotal += $monthly[$keys[$count - 1 - $i]]['revenue'];
            $prevCount++;
        }

        if ($prevCount === 0 || $prevTotal === 0.0) {
            return 0.0;
        }

        $prevAvg = $prevTotal / $prevCount;
        return (($recentAvg - $prevAvg) / $prevAvg) * 100;
    }

    /**
     * Calculate seasonal index for a given month
     * seasonal_index = average_of_that_month / overall_monthly_average
     */
    private function calculateSeasonalIndex(array $monthly, int $targetMonth): float
    {
        if (count($monthly) < 6) {
            return 1.0; // Not enough data for seasonality
        }

        $overallTotal = 0;
        $overallCount = 0;
        $monthTotal = 0;
        $monthCount = 0;

        foreach ($monthly as $key => $data) {
            $m = (int) substr($key, 5, 2);
            $overallTotal += $data['revenue'];
            $overallCount++;
            if ($m === $targetMonth) {
                $monthTotal += $data['revenue'];
                $monthCount++;
            }
        }

        if ($overallCount === 0 || $monthCount === 0) {
            return 1.0;
        }

        $overallAvg = $overallTotal / $overallCount;
        $monthAvg = $monthTotal / $monthCount;

        if ($overallAvg <= 0) {
            return 1.0;
        }

        // Clamp between 0.5 and 2.0 to avoid extreme swings
        return max(0.5, min(2.0, $monthAvg / $overallAvg));
    }

    /**
     * Enrich product forecasts with current stock data
     */
    private function enrichWithStockData(array $products, int $limit = 50): array
    {
        $count = 0;
        foreach ($products as &$product) {
            if ($count >= $limit) {
                break;
            }
            $count++;
            try {
                $stockData = $this->stockSync->getStockBySku((string) $product['sku']);
                $currentStock = $stockData ? (float) ($stockData['qty'] ?? 0) : 0;
                $product['current_stock'] = round($currentStock, 1);

                $dailyDemand = $product['forecast_30d_qty'] / 30;
                if ($dailyDemand > 0) {
                    $daysOfStock = $currentStock / $dailyDemand;
                    $product['days_of_stock'] = round($daysOfStock, 0);
                    $product['stockout_risk'] = $daysOfStock < 15;
                } else {
                    $product['days_of_stock'] = $currentStock > 0 ? 999 : 0;
                    $product['stockout_risk'] = false;
                }
            } catch (\Exception $e) {
                // Stock lookup failed — skip enrichment
                $product['current_stock'] = 0;
                $product['days_of_stock'] = 0;
                $product['stockout_risk'] = false;
            }
        }

        return $products;
    }

    /**
     * Fetch category-level sales comparison (current period vs previous)
     */
    private function fetchCategorySales(): array
    {
        $sql = "SELECT
                    ISNULL(GC.DESCRICAO, 'Sem Categoria') AS category,
                    SUM(CASE WHEN P.DTPEDIDO >= DATEADD(day, CAST(-30 AS INT), GETDATE()) THEN I.VLRTOTAL ELSE 0 END) AS revenue_current,
                    SUM(CASE WHEN P.DTPEDIDO >= DATEADD(day, CAST(-60 AS INT), GETDATE()) AND P.DTPEDIDO < DATEADD(day, CAST(-30 AS INT), GETDATE()) THEN I.VLRTOTAL ELSE 0 END) AS revenue_previous,
                    COUNT(DISTINCT CASE WHEN P.DTPEDIDO >= DATEADD(day, CAST(-30 AS INT), GETDATE()) THEN I.MATERIAL END) AS product_count
                FROM VE_PEDIDOITENS I
                INNER JOIN VE_PEDIDO P ON P.CODIGO = I.PEDIDO
                INNER JOIN MT_MATERIAL M ON M.CODIGO = I.MATERIAL
                LEFT JOIN MT_GRUPOCOMERCIAL GC ON GC.CODIGO = M.GRUPOCOMERCIAL
                WHERE P.DTPEDIDO >= DATEADD(day, CAST(-60 AS INT), GETDATE())
                  AND P.STATUS NOT IN ('C', 'D')
                GROUP BY GC.DESCRICAO
                HAVING SUM(I.VLRTOTAL) > 0
                ORDER BY SUM(CASE WHEN P.DTPEDIDO >= DATEADD(day, CAST(-30 AS INT), GETDATE()) THEN I.VLRTOTAL ELSE 0 END) DESC";

        $rows = $this->connection->query($sql);
        $categories = [];

        foreach ($rows as $row) {
            $current = (float) ($row['revenue_current'] ?? 0);
            $previous = (float) ($row['revenue_previous'] ?? 0);
            $growthPct = $previous > 0 ? (($current - $previous) / $previous) * 100 : ($current > 0 ? 100 : 0);

            $trend = 'stable';
            if ($growthPct > 10) {
                $trend = 'up';
            } elseif ($growthPct < -10) {
                $trend = 'down';
            }

            $categories[] = [
                'category' => $row['category'],
                'revenue_current' => round($current, 2),
                'revenue_previous' => round($previous, 2),
                'growth_pct' => round($growthPct, 1),
                'trend' => $trend,
                'product_count' => (int) ($row['product_count'] ?? 0),
            ];
        }

        // Sort by growth descending
        usort($categories, fn($a, $b) => $b['growth_pct'] <=> $a['growth_pct']);

        return $categories;
    }

    public function clearCache(): void
    {
        $this->cache->clean([self::CACHE_TAG]);
    }
}
