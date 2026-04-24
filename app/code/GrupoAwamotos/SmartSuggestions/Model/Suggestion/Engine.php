<?php

declare(strict_types=1);

namespace GrupoAwamotos\SmartSuggestions\Model\Suggestion;

use GrupoAwamotos\SmartSuggestions\Api\SuggestionEngineInterface;
use GrupoAwamotos\SmartSuggestions\Api\RfmCalculatorInterface;
use GrupoAwamotos\ERPIntegration\Api\ConnectionInterface;
use Psr\Log\LoggerInterface;

/**
 * Suggestion Engine
 *
 * Generates personalized cart suggestions using:
 * - Repurchase prediction (based on customer buying cycle)
 * - Cross-selling (frequently bought together)
 * - Collaborative filtering (similar customers)
 */
class Engine implements SuggestionEngineInterface
{
    private ConnectionInterface $connection;
    private RfmCalculatorInterface $rfmCalculator;
    private LoggerInterface $logger;

    public function __construct(
        ConnectionInterface $connection,
        RfmCalculatorInterface $rfmCalculator,
        LoggerInterface $logger
    ) {
        $this->connection = $connection;
        $this->rfmCalculator = $rfmCalculator;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function generateCartSuggestion(int $customerId): array
    {
        try {
            $customerInfo = $this->getCustomerInfo($customerId);

            if (empty($customerInfo)) {
                return ['error' => 'Cliente não encontrado'];
            }

            $repurchase = $this->getRepurchaseSuggestions($customerId);
            $crossSell = $this->getCrossSellingProducts($customerId);
            $similar = $this->getFromSimilarCustomers($customerId);

            // Calculate cart value
            $cartValue = array_sum(array_column($repurchase, 'suggested_value'))
                + array_sum(array_column($crossSell, 'suggested_value'))
                + array_sum(array_column($similar, 'suggested_value'));

            // Get RFM data
            $rfmData = $this->rfmCalculator->calculateForCustomer($customerId);

            return [
                'customer' => $customerInfo,
                'rfm' => $rfmData,
                'suggestions' => [
                    'repurchase' => $repurchase,
                    'cross_sell' => $crossSell,
                    'similar_customers' => $similar
                ],
                'cart_summary' => [
                    'total_products' => count($repurchase) + count($crossSell) + count($similar),
                    'total_value' => round($cartValue, 2),
                    'repurchase_value' => round(array_sum(array_column($repurchase, 'suggested_value')), 2),
                    'cross_sell_value' => round(array_sum(array_column($crossSell, 'suggested_value')), 2),
                    'similar_value' => round(array_sum(array_column($similar, 'suggested_value')), 2)
                ],
                'generated_at' => date('Y-m-d H:i:s')
            ];
        } catch (\Exception $e) {
            $this->logger->error('Cart Suggestion Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * @inheritdoc
     */
    public function getRepurchaseSuggestions(int $customerId, int $limit = 10): array
    {
        try {
            // Get products customer buys regularly and calculate expected repurchase time
            $sql = "
                SELECT
                    i.MATERIAL as product_id,
                    MAX(i.DESCRICAO) as product_name,
                    i.MATERIAL as sku,
                    COUNT(DISTINCT p.CODIGO) as order_count,
                    SUM(i.QTDE) as total_qty,
                    AVG(i.QTDE) as avg_qty,
                    MAX(i.VLRUNITARIO) as last_price,
                    MAX(p.DTPEDIDO) as last_purchase,
                    DATEDIFF(DAY, MAX(p.DTPEDIDO), GETDATE()) as days_since_purchase,
                    CASE
                        WHEN COUNT(DISTINCT p.CODIGO) > 1 THEN
                            DATEDIFF(DAY, MIN(p.DTPEDIDO), MAX(p.DTPEDIDO)) / (COUNT(DISTINCT p.CODIGO) - 1)
                        ELSE 30
                    END as avg_cycle_days
                FROM VE_PEDIDOITENS i
                INNER JOIN VE_PEDIDO p ON i.PEDIDO = p.CODIGO
                WHERE p.CLIENTE = ?
                  AND p.STATUS NOT IN ('C', 'X')
                  AND p.DTPEDIDO >= DATEADD(YEAR, -2, GETDATE())
                GROUP BY i.MATERIAL
                HAVING COUNT(DISTINCT p.CODIGO) >= 2
                ORDER BY
                    (DATEDIFF(DAY, MAX(p.DTPEDIDO), GETDATE()) * 1.0 /
                     NULLIF(CASE
                        WHEN COUNT(DISTINCT p.CODIGO) > 1 THEN
                            DATEDIFF(DAY, MIN(p.DTPEDIDO), MAX(p.DTPEDIDO)) / (COUNT(DISTINCT p.CODIGO) - 1)
                        ELSE 30
                    END, 0)) DESC
            ";

            $products = $this->connection->query($sql, [$customerId]);

            // Filter by cycle ratio across all products, then cap at $limit
            $suggestions = [];
            foreach ($products as $product) {
                $cycleRatio = $product['avg_cycle_days'] > 0
                    ? $product['days_since_purchase'] / $product['avg_cycle_days']
                    : 0;

                // Suggest when at least 50% of expected cycle has elapsed
                if ($cycleRatio >= 0.5) {
                    $suggestions[] = [
                        'product_id' => (int)$product['product_id'],
                        'sku' => $product['sku'],
                        'name' => $product['product_name'],
                        'suggested_qty' => max(1, (int)round($product['avg_qty'])),
                        'unit_price' => (float)$product['last_price'],
                        'suggested_value' => round($product['avg_qty'] * $product['last_price'], 2),
                        'days_since_purchase' => (int)$product['days_since_purchase'],
                        'avg_cycle_days' => (int)$product['avg_cycle_days'],
                        'cycle_ratio' => round($cycleRatio, 2),
                        'urgency' => $cycleRatio >= 1.2 ? 'high' : ($cycleRatio >= 1.0 ? 'medium' : 'low'),
                        'reason' => 'repurchase'
                    ];

                    if (count($suggestions) >= $limit) {
                        break;
                    }
                }
            }

            return $suggestions;
        } catch (\Exception $e) {
            $this->logger->error('Repurchase Suggestions Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @inheritdoc
     */
    public function getCrossSellingProducts(int $customerId, int $limit = 5): array
    {
        try {
            // Find products frequently bought together with customer's recent purchases
            $sql = "
                WITH CustomerProducts AS (
                    SELECT DISTINCT i.MATERIAL
                    FROM VE_PEDIDOITENS i
                    INNER JOIN VE_PEDIDO p ON i.PEDIDO = p.CODIGO
                    WHERE p.CLIENTE = ?
                      AND p.STATUS NOT IN ('C', 'X')
                      AND p.DTPEDIDO >= DATEADD(MONTH, -6, GETDATE())
                ),
                RelatedProducts AS (
                    SELECT
                        i2.MATERIAL as related_product,
                        COUNT(DISTINCT i1.PEDIDO) as cooccurrence_count
                    FROM VE_PEDIDOITENS i1
                    INNER JOIN VE_PEDIDOITENS i2 ON i1.PEDIDO = i2.PEDIDO AND i1.MATERIAL <> i2.MATERIAL
                    WHERE i1.MATERIAL IN (SELECT MATERIAL FROM CustomerProducts)
                      AND i2.MATERIAL NOT IN (SELECT MATERIAL FROM CustomerProducts)
                    GROUP BY i2.MATERIAL
                    HAVING COUNT(DISTINCT i1.PEDIDO) >= 2
                )
                SELECT
                    rp.related_product as product_id,
                    MAX(i.DESCRICAO) as product_name,
                    rp.related_product as sku,
                    rp.cooccurrence_count,
                    MAX(i.VLRUNITARIO) as avg_price
                FROM RelatedProducts rp
                INNER JOIN VE_PEDIDOITENS i ON rp.related_product = i.MATERIAL
                GROUP BY rp.related_product, rp.cooccurrence_count
                ORDER BY rp.cooccurrence_count DESC
            ";

            $products = $this->connection->query($sql, [$customerId]);

            $suggestions = [];
            foreach (array_slice($products, 0, $limit) as $product) {
                $suggestions[] = [
                    'product_id' => (int)$product['product_id'],
                    'sku' => $product['sku'],
                    'name' => $product['product_name'],
                    'suggested_qty' => 1,
                    'unit_price' => (float)$product['avg_price'],
                    'suggested_value' => (float)$product['avg_price'],
                    'cooccurrence_score' => (int)$product['cooccurrence_count'],
                    'reason' => 'cross_sell'
                ];
            }

            return $suggestions;
        } catch (\Exception $e) {
            $this->logger->error('Cross-Sell Suggestions Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @inheritdoc
     */
    public function getFromSimilarCustomers(int $customerId, int $limit = 5): array
    {
        try {
            // Find customers with similar purchase patterns (Jaccard similarity)
            $sql = "
                WITH CustomerProducts AS (
                    SELECT DISTINCT i.MATERIAL
                    FROM VE_PEDIDOITENS i
                    INNER JOIN VE_PEDIDO p ON i.PEDIDO = p.CODIGO
                    WHERE p.CLIENTE = ?
                      AND p.STATUS NOT IN ('C', 'X')
                ),
                SimilarCustomers AS (
                    SELECT TOP 20
                        p.CLIENTE as similar_customer,
                        COUNT(DISTINCT CASE WHEN i.MATERIAL IN (SELECT MATERIAL FROM CustomerProducts) THEN i.MATERIAL END) as common_products,
                        COUNT(DISTINCT i.MATERIAL) as total_products
                    FROM VE_PEDIDO p
                    INNER JOIN VE_PEDIDOITENS i ON p.CODIGO = i.PEDIDO
                    WHERE p.CLIENTE <> ?
                      AND p.STATUS NOT IN ('C', 'X')
                      AND p.DTPEDIDO >= DATEADD(YEAR, -1, GETDATE())
                    GROUP BY p.CLIENTE
                    HAVING COUNT(DISTINCT CASE WHEN i.MATERIAL IN (SELECT MATERIAL FROM CustomerProducts) THEN i.MATERIAL END) >= 2
                    ORDER BY
                        CAST(COUNT(DISTINCT CASE WHEN i.MATERIAL IN (SELECT MATERIAL FROM CustomerProducts) THEN i.MATERIAL END) AS FLOAT) /
                        COUNT(DISTINCT i.MATERIAL) DESC
                ),
                SimilarProducts AS (
                    SELECT
                        i.MATERIAL as product_id,
                        COUNT(DISTINCT p.CLIENTE) as customer_count,
                        SUM(i.QTDE) as total_qty,
                        AVG(i.VLRUNITARIO) as avg_price
                    FROM VE_PEDIDO p
                    INNER JOIN VE_PEDIDOITENS i ON p.CODIGO = i.PEDIDO
                    WHERE p.CLIENTE IN (SELECT similar_customer FROM SimilarCustomers)
                      AND i.MATERIAL NOT IN (SELECT MATERIAL FROM CustomerProducts)
                      AND p.STATUS NOT IN ('C', 'X')
                    GROUP BY i.MATERIAL
                    HAVING COUNT(DISTINCT p.CLIENTE) >= 2
                )
                SELECT
                    sp.product_id,
                    MAX(i.DESCRICAO) as product_name,
                    sp.product_id as sku,
                    sp.customer_count,
                    sp.avg_price
                FROM SimilarProducts sp
                INNER JOIN VE_PEDIDOITENS i ON sp.product_id = i.MATERIAL
                GROUP BY sp.product_id, sp.customer_count, sp.avg_price
                ORDER BY sp.customer_count DESC, sp.total_qty DESC
            ";

            $products = $this->connection->query($sql, [$customerId, $customerId]);

            $suggestions = [];
            foreach (array_slice($products, 0, $limit) as $product) {
                $suggestions[] = [
                    'product_id' => (int)$product['product_id'],
                    'sku' => $product['sku'],
                    'name' => $product['product_name'],
                    'suggested_qty' => 1,
                    'unit_price' => (float)$product['avg_price'],
                    'suggested_value' => (float)$product['avg_price'],
                    'similar_customers_count' => (int)$product['customer_count'],
                    'reason' => 'similar_customers'
                ];
            }

            return $suggestions;
        } catch (\Exception $e) {
            $this->logger->error('Similar Customers Suggestions Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @inheritdoc
     */
    public function getTopOpportunities(int $limit = 10): array
    {
        try {
            // Get customers with high RFM scores who are due for repurchase
            $rfmCustomers = $this->rfmCalculator->calculateAll();

            // Filter to target segments first
            $targetSegments = ['Champions', 'Loyal', 'Potential Loyalist', 'Need Attention'];
            $candidates = array_filter(
                $rfmCustomers,
                fn($c) => in_array($c['segment'], $targetSegments, true)
            );

            if (empty($candidates)) {
                return [];
            }

            // Batch-fetch purchase cycles for ALL candidates in a single query (eliminates N individual queries)
            $candidateIds = array_column($candidates, 'customer_id');
            $purchaseCycles = $this->getBatchPurchaseCycles($candidateIds);

            // Pre-filter by cycle ratio and score WITHOUT hitting ERP per-customer
            $preScored = [];
            foreach ($candidates as $customer) {
                $avgCycle = $purchaseCycles[$customer['customer_id']] ?? 30;

                if ($avgCycle > 0 && $customer['recency_days'] >= $avgCycle * 0.8) {
                    $preScored[] = [
                        'customer' => $customer,
                        'avg_cycle' => $avgCycle,
                        'priority_score' => $this->calculatePriorityScore($customer, $avgCycle),
                    ];
                }
            }

            // Sort by priority and take only top N — THEN generate full cart suggestions
            usort($preScored, fn($a, $b) => $b['priority_score'] <=> $a['priority_score']);
            $topCandidates = array_slice($preScored, 0, $limit);

            $opportunities = [];
            foreach ($topCandidates as $entry) {
                $customer = $entry['customer'];
                $avgCycle = $entry['avg_cycle'];
                $cartSuggestion = $this->generateCartSuggestion($customer['customer_id']);

                $opportunities[] = [
                    'customer_id' => $customer['customer_id'],
                    'customer_name' => $customer['trade_name'] ?: $customer['customer_name'],
                    'segment' => $customer['segment'],
                    'rfm_score' => $customer['rfm_score'],
                    'days_since_purchase' => $customer['recency_days'],
                    'avg_cycle_days' => $avgCycle,
                    'overdue_ratio' => round($customer['recency_days'] / $avgCycle, 2),
                    'estimated_cart_value' => $cartSuggestion['cart_summary']['total_value'] ?? 0,
                    'historical_value' => $customer['monetary'],
                    'priority_score' => $entry['priority_score'],
                ];
            }

            return $opportunities;
        } catch (\Exception $e) {
            $this->logger->error('Top Opportunities Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Batch-fetch purchase cycles for multiple customers in a single ERP query.
     *
     * @param int[] $customerIds
     * @return array<int, int> customerId => avgCycleDays
     */
    private function getBatchPurchaseCycles(array $customerIds): array
    {
        if (empty($customerIds)) {
            return [];
        }

        $cycles = [];
        foreach (array_chunk($customerIds, 500) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $sql = "
                SELECT
                    p.CLIENTE as customer_id,
                    CASE
                        WHEN COUNT(DISTINCT p.CODIGO) > 1 THEN
                            DATEDIFF(DAY, MIN(p.DTPEDIDO), MAX(p.DTPEDIDO)) / (COUNT(DISTINCT p.CODIGO) - 1)
                        ELSE 30
                    END as avg_cycle
                FROM VE_PEDIDO p
                WHERE p.CLIENTE IN ({$placeholders})
                  AND p.STATUS NOT IN ('C', 'X')
                  AND p.DTPEDIDO >= DATEADD(YEAR, -2, GETDATE())
                GROUP BY p.CLIENTE
            ";

            $results = $this->connection->query($sql, $chunk);
            foreach ($results as $row) {
                $cycles[(int)$row['customer_id']] = (int)$row['avg_cycle'];
            }
        }

        return $cycles;
    }

    /**
     * Get customer info
     */
    private function getCustomerInfo(int $customerId): ?array
    {
        $sql = "
            SELECT
                f.CODIGO as customer_id,
                f.RAZAO as customer_name,
                f.FANTASIA as trade_name,
                f.CGC as cnpj,
                f.CIDADE as city,
                f.UF as state,
                COALESCE(c.FONECEL, c.FONE1) as phone,
                c.EMAIL as email
            FROM FN_FORNECEDORES f
            LEFT JOIN FN_CONTATO c ON c.FORNECEDOR = f.CODIGO AND c.PRINCIPAL = 'S'
            WHERE f.CODIGO = ?
        ";

        $result = $this->connection->query($sql, [$customerId]);
        return $result[0] ?? null;
    }

    /**
     * Get customer's average purchase cycle
     */
    private function getCustomerPurchaseCycle(int $customerId): int
    {
        $sql = "
            SELECT
                CASE
                    WHEN COUNT(DISTINCT p.CODIGO) > 1 THEN
                        DATEDIFF(DAY, MIN(p.DTPEDIDO), MAX(p.DTPEDIDO)) / (COUNT(DISTINCT p.CODIGO) - 1)
                    ELSE 30
                END as avg_cycle
            FROM VE_PEDIDO p
            WHERE p.CLIENTE = ?
              AND p.STATUS NOT IN ('C', 'X')
              AND p.DTPEDIDO >= DATEADD(YEAR, -2, GETDATE())
        ";

        $result = $this->connection->query($sql, [$customerId]);
        return (int)($result[0]['avg_cycle'] ?? 30);
    }

    /**
     * Calculate priority score for opportunity ranking
     */
    private function calculatePriorityScore(array $customer, int $avgCycle): float
    {
        // Factors:
        // - RFM total score (higher is better)
        // - How overdue they are (higher ratio = more urgent)
        // - Historical value (more valuable customers = higher priority)

        $rfmWeight = $customer['rfm_total'] / 15; // Normalized to 0-1
        $overdueRatio = min($customer['recency_days'] / max($avgCycle, 1), 2); // Capped at 2
        $valueWeight = min($customer['monetary'] / 100000, 1); // Normalized

        return ($rfmWeight * 0.3) + ($overdueRatio * 0.4) + ($valueWeight * 0.3);
    }
}
