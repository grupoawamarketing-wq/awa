<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Model\Rfm;

use GrupoAwamotos\ERPIntegration\Api\ConnectionInterface;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use Magento\Framework\App\CacheInterface;
use Psr\Log\LoggerInterface;

/**
 * RFM Calculator
 *
 * Calculates RFM (Recency, Frequency, Monetary) scores for customer segmentation
 *
 * R = Recency (days since last purchase) - lower is better
 * F = Frequency (number of orders) - higher is better
 * M = Monetary (total value spent) - higher is better
 */
class Calculator
{
    private const CACHE_KEY = 'erp_rfm_analysis';
    private const CACHE_TTL = 86400; // 24 hours

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
     * Calculate RFM scores for all customers
     *
     * @param int $monthsBack Analysis period in months (default 24)
     * @param bool $forceRefresh Force cache refresh
     * @return array
     */
    public function calculateForAllCustomers(int $monthsBack = 24, bool $forceRefresh = false): array
    {
        // Validate monthsBack to prevent any potential issues
        $monthsBack = max(1, min(120, $monthsBack));

        $cacheKey = self::CACHE_KEY . '_' . $monthsBack;

        if (!$forceRefresh) {
            $cached = $this->cache->load($cacheKey);
            if ($cached) {
                return json_decode($cached, true);
            }
        }

        try {
            // Calculate cutoff date in PHP to avoid SQL interpolation
            $cutoffDate = (new \DateTime())
                ->modify("-{$monthsBack} months")
                ->format('Y-m-d');

            // Get raw RFM data from ERP using parameterized query
            $customers = $this->connection->query("
                SELECT
                    f.CODIGO as customer_id,
                    f.RAZAO as customer_name,
                    f.FANTASIA as trade_name,
                    f.CGC as cnpj,
                    f.CIDADE as city,
                    f.UF as state,
                    c.EMAIL as email,
                    COALESCE(c.FONECEL, c.FONE1) as phone,
                    DATEDIFF(DAY, MAX(p.DTPEDIDO), GETDATE()) as recency,
                    COUNT(DISTINCT p.CODIGO) as frequency,
                    COALESCE(SUM(i.VLRTOTAL), 0) as monetary,
                    MIN(p.DTPEDIDO) as first_purchase,
                    MAX(p.DTPEDIDO) as last_purchase,
                    AVG(i.VLRTOTAL) as avg_order_value
                FROM FN_FORNECEDORES f
                LEFT JOIN FN_CONTATO c ON c.FORNECEDOR = f.CODIGO AND c.PRINCIPAL = 'S'
                LEFT JOIN VE_PEDIDO p ON f.CODIGO = p.CLIENTE
                    AND p.STATUS NOT IN ('C', 'X')
                    AND p.DTPEDIDO >= :cutoff_date
                LEFT JOIN VE_PEDIDOITENS i ON p.CODIGO = i.PEDIDO
                WHERE f.CKCLIENTE = 'S'
                GROUP BY f.CODIGO, f.RAZAO, f.FANTASIA, f.CGC, f.CIDADE, f.UF, c.EMAIL, c.FONECEL, c.FONE1
                HAVING COUNT(DISTINCT p.CODIGO) > 0
                ORDER BY COALESCE(SUM(i.VLRTOTAL), 0) DESC
            ", [':cutoff_date' => $cutoffDate]);

            if (empty($customers)) {
                return [];
            }

            // Calculate quintiles for each metric
            $recencyValues = array_column($customers, 'recency');
            $frequencyValues = array_column($customers, 'frequency');
            $monetaryValues = array_column($customers, 'monetary');

            $rQuintiles = $this->calculateQuintiles($recencyValues);
            $fQuintiles = $this->calculateQuintiles($frequencyValues);
            $mQuintiles = $this->calculateQuintiles($monetaryValues);

            // Assign scores to each customer
            $result = [];
            foreach ($customers as $customer) {
                // Recency: lower is better, so invert the score
                $rScore = 6 - $this->getQuintileScore((float)$customer['recency'], $rQuintiles);
                $fScore = $this->getQuintileScore((float)$customer['frequency'], $fQuintiles);
                $mScore = $this->getQuintileScore((float)$customer['monetary'], $mQuintiles);

                $segment = $this->determineSegment($rScore, $fScore, $mScore);
                $rfmScore = $rScore . $fScore . $mScore;
                $totalScore = $rScore + $fScore + $mScore;

                $result[] = [
                    'customer_id' => (int)$customer['customer_id'],
                    'customer_name' => $customer['customer_name'],
                    'trade_name' => $customer['trade_name'],
                    'cnpj' => $customer['cnpj'],
                    'city' => $customer['city'],
                    'state' => $customer['state'],
                    'email' => $customer['email'],
                    'phone' => $customer['phone'],
                    'recency' => (int)$customer['recency'],
                    'frequency' => (int)$customer['frequency'],
                    'monetary' => (float)$customer['monetary'],
                    'avg_order_value' => (float)$customer['avg_order_value'],
                    'first_purchase' => $customer['first_purchase'],
                    'last_purchase' => $customer['last_purchase'],
                    'r_score' => $rScore,
                    'f_score' => $fScore,
                    'm_score' => $mScore,
                    'rfm_score' => $rfmScore,
                    'total_score' => $totalScore,
                    'segment' => $segment,
                    'segment_label' => $this->getSegmentLabel($segment),
                    'segment_color' => $this->getSegmentColor($segment),
                    'suggested_action' => $this->getSuggestedAction($segment),
                ];
            }

            // Cache results
            $this->cache->save(json_encode($result), $cacheKey, ['erp_rfm'], self::CACHE_TTL);

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('[ERP RFM] Error calculating RFM: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get RFM data for a specific customer
     */
    public function getCustomerRfm(int $customerCode): ?array
    {
        $allCustomers = $this->calculateForAllCustomers();

        foreach ($allCustomers as $customer) {
            if ($customer['customer_id'] === $customerCode) {
                return $customer;
            }
        }

        return null;
    }

    /**
     * Get customers by segment
     */
    public function getCustomersBySegment(string $segment): array
    {
        $allCustomers = $this->calculateForAllCustomers();

        return array_filter($allCustomers, function ($customer) use ($segment) {
            return $customer['segment'] === $segment;
        });
    }

    /**
     * Get segment statistics
     */
    public function getSegmentStats(): array
    {
        $allCustomers = $this->calculateForAllCustomers();

        $segments = [
            'champions' => ['count' => 0, 'revenue' => 0, 'avg_ticket' => 0, 'customers' => []],
            'loyal' => ['count' => 0, 'revenue' => 0, 'avg_ticket' => 0, 'customers' => []],
            'potential' => ['count' => 0, 'revenue' => 0, 'avg_ticket' => 0, 'customers' => []],
            'new_customers' => ['count' => 0, 'revenue' => 0, 'avg_ticket' => 0, 'customers' => []],
            'promising' => ['count' => 0, 'revenue' => 0, 'avg_ticket' => 0, 'customers' => []],
            'need_attention' => ['count' => 0, 'revenue' => 0, 'avg_ticket' => 0, 'customers' => []],
            'about_to_sleep' => ['count' => 0, 'revenue' => 0, 'avg_ticket' => 0, 'customers' => []],
            'at_risk' => ['count' => 0, 'revenue' => 0, 'avg_ticket' => 0, 'customers' => []],
            'cant_lose' => ['count' => 0, 'revenue' => 0, 'avg_ticket' => 0, 'customers' => []],
            'hibernating' => ['count' => 0, 'revenue' => 0, 'avg_ticket' => 0, 'customers' => []],
            'lost' => ['count' => 0, 'revenue' => 0, 'avg_ticket' => 0, 'customers' => []],
        ];

        foreach ($allCustomers as $customer) {
            $seg = $customer['segment'];
            if (isset($segments[$seg])) {
                $segments[$seg]['count']++;
                $segments[$seg]['revenue'] += $customer['monetary'];
                $segments[$seg]['customers'][] = $customer;
            }
        }

        // Calculate averages
        foreach ($segments as $key => &$data) {
            if ($data['count'] > 0) {
                $data['avg_ticket'] = $data['revenue'] / $data['count'];
                $data['percentage'] = round(($data['count'] / count($allCustomers)) * 100, 1);
            }
            $data['label'] = $this->getSegmentLabel($key);
            $data['color'] = $this->getSegmentColor($key);
            $data['action'] = $this->getSuggestedAction($key);
        }

        return $segments;
    }

    /**
     * Get at-risk customers that need immediate attention
     */
    public function getAtRiskCustomers(int $limit = 50): array
    {
        $allCustomers = $this->calculateForAllCustomers();

        $atRisk = array_filter($allCustomers, function ($customer) {
            return in_array($customer['segment'], ['at_risk', 'cant_lose', 'about_to_sleep']);
        });

        // Sort by monetary value (highest first - these are the most valuable to save)
        usort($atRisk, function ($a, $b) {
            return $b['monetary'] <=> $a['monetary'];
        });

        return array_slice($atRisk, 0, $limit);
    }

    /**
     * Get top customers (Champions and Loyal)
     */
    public function getTopCustomers(int $limit = 50): array
    {
        $allCustomers = $this->calculateForAllCustomers();

        $top = array_filter($allCustomers, function ($customer) {
            return in_array($customer['segment'], ['champions', 'loyal']);
        });

        usort($top, function ($a, $b) {
            return $b['monetary'] <=> $a['monetary'];
        });

        return array_slice($top, 0, $limit);
    }

    /**
     * Calculate quintiles for a set of values
     */
    private function calculateQuintiles(array $values): array
    {
        sort($values);
        $count = count($values);

        if ($count === 0) {
            return [0, 0, 0, 0, 0];
        }

        return [
            $values[(int)floor($count * 0.2)] ?? 0,
            $values[(int)floor($count * 0.4)] ?? 0,
            $values[(int)floor($count * 0.6)] ?? 0,
            $values[(int)floor($count * 0.8)] ?? 0,
            $values[$count - 1] ?? 0,
        ];
    }

    /**
     * Get quintile score (1-5) for a value
     */
    private function getQuintileScore(float $value, array $quintiles): int
    {
        if ($value <= $quintiles[0]) {
            return 1;
        }
        if ($value <= $quintiles[1]) {
            return 2;
        }
        if ($value <= $quintiles[2]) {
            return 3;
        }
        if ($value <= $quintiles[3]) {
            return 4;
        }
        return 5;
    }

    /**
     * Determine customer segment based on RFM scores
     */
    private function determineSegment(int $r, int $f, int $m): string
    {
        // Champions: Best customers
        if ($r >= 4 && $f >= 4 && $m >= 4) {
            return 'champions';
        }

        // Loyal: High frequency and monetary, good recency
        if ($r >= 3 && $f >= 4 && $m >= 3) {
            return 'loyal';
        }

        // Potential Loyalist: Recent with good frequency
        if ($r >= 4 && $f >= 2 && $f <= 4) {
            return 'potential';
        }

        // New Customers: Very recent but low frequency
        if ($r >= 4 && $f <= 2) {
            return 'new_customers';
        }

        // Promising: Recent, medium frequency
        if ($r >= 3 && $f >= 2 && $f <= 3) {
            return 'promising';
        }

        // Need Attention: Above average but slipping
        if ($r >= 2 && $r <= 3 && $f >= 2 && $f <= 3 && $m >= 2 && $m <= 3) {
            return 'need_attention';
        }

        // About to Sleep: Below average, risk of leaving
        if ($r <= 2 && $f >= 2 && $f <= 3) {
            return 'about_to_sleep';
        }

        // At Risk: Previously valuable, not buying recently
        if ($r <= 2 && $f >= 3 && $m >= 3) {
            return 'at_risk';
        }

        // Can't Lose: Big spenders who haven't bought recently
        if ($r <= 2 && $f >= 4 && $m >= 4) {
            return 'cant_lose';
        }

        // Hibernating: Low on all metrics but not completely lost
        if ($r <= 2 && $f <= 2 && $m >= 2) {
            return 'hibernating';
        }

        // Lost: Lowest scores
        return 'lost';
    }

    /**
     * Get human-readable segment label
     */
    private function getSegmentLabel(string $segment): string
    {
        return match ($segment) {
            'champions' => 'Champions',
            'loyal' => 'Clientes Fiéis',
            'potential' => 'Potenciais Fiéis',
            'new_customers' => 'Novos Clientes',
            'promising' => 'Promissores',
            'need_attention' => 'Precisam de Atenção',
            'about_to_sleep' => 'Prestes a Dormir',
            'at_risk' => 'Em Risco',
            'cant_lose' => 'Não Pode Perder',
            'hibernating' => 'Hibernando',
            'lost' => 'Perdidos',
            default => ucfirst(str_replace('_', ' ', $segment)),
        };
    }

    /**
     * Get segment color for charts
     */
    private function getSegmentColor(string $segment): string
    {
        return match ($segment) {
            'champions' => '#00E396',      // Green
            'loyal' => '#008FFB',          // Blue
            'potential' => '#00D9E9',      // Cyan
            'new_customers' => '#775DD0',  // Purple
            'promising' => '#FEB019',      // Orange
            'need_attention' => '#FF9800', // Amber
            'about_to_sleep' => '#F9A825', // Yellow-orange
            'at_risk' => '#FF4560',        // Red
            'cant_lose' => '#D50000',      // Dark Red
            'hibernating' => '#9E9E9E',    // Gray
            'lost' => '#546E7A',           // Blue-gray
            default => '#666666',
        };
    }

    /**
     * Get suggested action for segment
     */
    private function getSuggestedAction(string $segment): string
    {
        return match ($segment) {
            'champions' => 'Recompensar e engajar como embaixadores da marca',
            'loyal' => 'Oferecer upsell e programa de fidelidade',
            'potential' => 'Oferecer programa de fidelidade e incentivos',
            'new_customers' => 'Nutrir relacionamento e oferecer suporte',
            'promising' => 'Criar engajamento e oferecer descontos progressivos',
            'need_attention' => 'Reativar com ofertas personalizadas',
            'about_to_sleep' => 'Campanhas de reativação urgentes',
            'at_risk' => 'URGENTE: Contato pessoal e ofertas especiais',
            'cant_lose' => 'CRÍTICO: Contato imediato do time comercial',
            'hibernating' => 'Campanhas de win-back com incentivos fortes',
            'lost' => 'Tentar reconquistar ou descontinuar comunicação',
            default => 'Avaliar caso a caso',
        };
    }

    /**
     * Clear RFM cache
     */
    public function clearCache(): void
    {
        $this->cache->clean(['erp_rfm']);
    }

    /**
     * Get RFM summary statistics
     */
    public function getSummary(): array
    {
        $allCustomers = $this->calculateForAllCustomers();

        if (empty($allCustomers)) {
            return [
                'total_customers' => 0,
                'total_revenue' => 0,
                'avg_order_value' => 0,
                'segments' => [],
            ];
        }

        $totalRevenue = array_sum(array_column($allCustomers, 'monetary'));
        $avgOrderValue = $totalRevenue / count($allCustomers);

        $segmentCounts = [];
        foreach ($allCustomers as $customer) {
            $segment = $customer['segment'];
            if (!isset($segmentCounts[$segment])) {
                $segmentCounts[$segment] = 0;
            }
            $segmentCounts[$segment]++;
        }

        return [
            'total_customers' => count($allCustomers),
            'total_revenue' => $totalRevenue,
            'avg_order_value' => $avgOrderValue,
            'segments' => $segmentCounts,
            'analysis_date' => date('Y-m-d H:i:s'),
        ];
    }
}
