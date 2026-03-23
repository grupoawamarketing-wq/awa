<?php
/**
 * Growth Analyzer Model
 *
 * Decomposes revenue growth into components: new customers, returning customer
 * growth, and churn loss. Provides revenue waterfall and monthly trend data.
 */
declare(strict_types=1);

namespace GrupoAwamotos\SalesIntelligence\Model;

use GrupoAwamotos\ERPIntegration\Api\ConnectionInterface;
use GrupoAwamotos\ERPIntegration\Model\Rfm\Calculator as RfmCalculator;
use Magento\Framework\App\CacheInterface;
use Psr\Log\LoggerInterface;

class GrowthAnalyzer
{
    private const CACHE_PREFIX = 'si_growth_';
    private const CACHE_TTL = 3600; // 1 hour
    private const CACHE_TAG = 'sales_intelligence_growth';

    private ConnectionInterface $connection;
    private RfmCalculator $rfmCalculator;
    private CacheInterface $cache;
    private LoggerInterface $logger;

    public function __construct(
        ConnectionInterface $connection,
        RfmCalculator $rfmCalculator,
        CacheInterface $cache,
        LoggerInterface $logger
    ) {
        $this->connection = $connection;
        $this->rfmCalculator = $rfmCalculator;
        $this->cache = $cache;
        $this->logger = $logger;
    }

    /**
     * Decompose revenue growth into new customer revenue, returning growth, and churn loss
     */
    public function getGrowthDecomposition(int $days = 30): array
    {
        $cacheKey = self::CACHE_PREFIX . "decomposition_{$days}";
        $cached = $this->cache->load($cacheKey);
        if ($cached) {
            return json_decode($cached, true) ?: [];
        }

        try {
            $result = $this->calculateDecomposition($days);

            $this->cache->save(
                json_encode($result),
                $cacheKey,
                [self::CACHE_TAG],
                self::CACHE_TTL
            );

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('[SalesIntelligence] GrowthAnalyzer error: ' . $e->getMessage());
            return $this->getEmptyDecomposition();
        }
    }

    /**
     * Get monthly revenue trend
     */
    public function getGrowthTrend(int $months = 6): array
    {
        $cacheKey = self::CACHE_PREFIX . "trend_{$months}";
        $cached = $this->cache->load($cacheKey);
        if ($cached) {
            return json_decode($cached, true) ?: [];
        }

        try {
            $result = $this->calculateMonthlyTrend($months);

            $this->cache->save(
                json_encode($result),
                $cacheKey,
                [self::CACHE_TAG],
                self::CACHE_TTL
            );

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('[SalesIntelligence] GrowthTrend error: ' . $e->getMessage());
            return [];
        }
    }

    private function calculateDecomposition(int $days): array
    {
        // Current period revenue
        $currentRevenue = $this->getPeriodRevenue($days, 0);

        // Previous period revenue
        $previousRevenue = $this->getPeriodRevenue($days, $days);

        $netChange = $currentRevenue - $previousRevenue;
        $netChangePct = $previousRevenue > 0
            ? ($netChange / $previousRevenue) * 100
            : ($currentRevenue > 0 ? 100 : 0);

        // New customers: first order was within current period
        $newCustomerData = $this->getNewCustomerRevenue($days);

        // Churn analysis via RFM
        $churnData = $this->getChurnData();

        // Returning growth = net_change - new_customer_revenue + churn_loss
        $returningGrowth = $netChange - $newCustomerData['revenue'] + $churnData['value'];

        return [
            'revenue_current' => round($currentRevenue, 2),
            'revenue_previous' => round($previousRevenue, 2),
            'net_change' => round($netChange, 2),
            'net_change_pct' => round($netChangePct, 1),
            'new_customer_revenue' => round($newCustomerData['revenue'], 2),
            'new_customer_count' => $newCustomerData['count'],
            'returning_growth' => round($returningGrowth, 2),
            'churn_loss' => round($churnData['value'], 2),
            'churned_customer_count' => $churnData['count'],
            'churned_customer_value' => round($churnData['value'], 2),
        ];
    }

    /**
     * Get total revenue for a period window
     */
    private function getPeriodRevenue(int $days, int $offset): float
    {
        $startOffset = -(int) ($offset + $days);
        $endOffset = -(int) $offset;
        $sql = "SELECT ISNULL(SUM(VLRTOTAL), 0) AS total
                FROM VE_PEDIDO
                WHERE DTPEDIDO >= DATEADD(day, CAST({$startOffset} AS INT), GETDATE())
                  AND DTPEDIDO < DATEADD(day, CAST({$endOffset} AS INT), GETDATE())
                  AND STATUS NOT IN ('C', 'D')";

        $row = $this->connection->fetchOne($sql);

        return (float) ($row['total'] ?? 0);
    }

    /**
     * Get new customer revenue and count for the current period
     */
    private function getNewCustomerRevenue(int $days): array
    {
        $daysBack = -(int) $days;
        // Customers whose first order was within the last $days days
        $sql = "SELECT
                    COUNT(DISTINCT nc.CLIENTE) AS new_count,
                    ISNULL(SUM(P.VLRTOTAL), 0) AS new_revenue
                FROM VE_PEDIDO P
                INNER JOIN (
                    SELECT CLIENTE, MIN(DTPEDIDO) AS first_order
                    FROM VE_PEDIDO
                    WHERE STATUS NOT IN ('C', 'D')
                    GROUP BY CLIENTE
                    HAVING MIN(DTPEDIDO) >= DATEADD(day, CAST({$daysBack} AS INT), GETDATE())
                ) nc ON nc.CLIENTE = P.CLIENTE
                WHERE P.DTPEDIDO >= DATEADD(day, CAST({$daysBack} AS INT), GETDATE())
                  AND P.STATUS NOT IN ('C', 'D')";

        $row = $this->connection->fetchOne($sql);

        return [
            'count' => (int) ($row['new_count'] ?? 0),
            'revenue' => (float) ($row['new_revenue'] ?? 0),
        ];
    }

    /**
     * Get churn data from RFM analysis
     */
    private function getChurnData(): array
    {
        try {
            $atRisk = $this->rfmCalculator->getAtRiskCustomers(100);
            $stats = $this->rfmCalculator->getSegmentStats();

            $churnCount = 0;
            $churnValue = 0;

            // Count at_risk + cant_lose + lost segments
            $churnSegments = ['at_risk', 'cant_lose', 'lost', 'hibernating'];
            foreach ($stats as $segment) {
                if (in_array($segment['segment'] ?? '', $churnSegments)) {
                    $churnCount += (int) ($segment['count'] ?? 0);
                }
            }

            // Estimate monthly value at risk from top at-risk customers
            foreach ($atRisk as $customer) {
                $monetary = (float) ($customer['monetary'] ?? 0);
                $frequency = max(1, (int) ($customer['frequency'] ?? 1));
                // Estimate monthly: total_value / months_active (approximate)
                $recencyDays = max(1, (int) ($customer['recency'] ?? 365));
                $monthsActive = max(1, $recencyDays / 30);
                $monthlyEstimate = $monetary / $monthsActive;
                $churnValue += $monthlyEstimate;
            }

            return [
                'count' => $churnCount,
                'value' => $churnValue,
            ];
        } catch (\Exception $e) {
            $this->logger->warning('[SalesIntelligence] Churn data unavailable: ' . $e->getMessage());
            return ['count' => 0, 'value' => 0];
        }
    }

    /**
     * Calculate monthly revenue trend
     */
    private function calculateMonthlyTrend(int $months): array
    {
        $sql = "SELECT
                    YEAR(DTPEDIDO) AS yr,
                    MONTH(DTPEDIDO) AS mo,
                    ISNULL(SUM(VLRTOTAL), 0) AS revenue,
                    COUNT(*) AS order_count,
                    COUNT(DISTINCT CLIENTE) AS customer_count
                FROM VE_PEDIDO
                WHERE DTPEDIDO >= DATEADD(month, -?, GETDATE())
                  AND STATUS NOT IN ('C', 'D')
                GROUP BY YEAR(DTPEDIDO), MONTH(DTPEDIDO)
                ORDER BY YEAR(DTPEDIDO), MONTH(DTPEDIDO)";

        $rows = $this->connection->query($sql, [(int) $months]);
        $trend = [];
        $prevRevenue = null;

        foreach ($rows as $row) {
            $revenue = (float) ($row['revenue'] ?? 0);
            $momGrowth = 0;
            if ($prevRevenue !== null && $prevRevenue > 0) {
                $momGrowth = (($revenue - $prevRevenue) / $prevRevenue) * 100;
            }

            $trend[] = [
                'month' => sprintf('%04d-%02d', (int) $row['yr'], (int) $row['mo']),
                'month_label' => $this->getMonthLabel((int) $row['mo'], (int) $row['yr']),
                'revenue' => round($revenue, 2),
                'order_count' => (int) ($row['order_count'] ?? 0),
                'customer_count' => (int) ($row['customer_count'] ?? 0),
                'mom_growth_pct' => round($momGrowth, 1),
            ];

            $prevRevenue = $revenue;
        }

        return $trend;
    }

    private function getMonthLabel(int $month, int $year): string
    {
        $months = [
            1 => 'Jan', 2 => 'Fev', 3 => 'Mar', 4 => 'Abr',
            5 => 'Mai', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago',
            9 => 'Set', 10 => 'Out', 11 => 'Nov', 12 => 'Dez',
        ];
        return ($months[$month] ?? '') . '/' . substr((string) $year, 2);
    }

    private function getEmptyDecomposition(): array
    {
        return [
            'revenue_current' => 0,
            'revenue_previous' => 0,
            'net_change' => 0,
            'net_change_pct' => 0,
            'new_customer_revenue' => 0,
            'new_customer_count' => 0,
            'returning_growth' => 0,
            'churn_loss' => 0,
            'churned_customer_count' => 0,
            'churned_customer_value' => 0,
        ];
    }

    public function clearCache(): void
    {
        $this->cache->clean([self::CACHE_TAG]);
    }
}
