<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Model\Cart;

use GrupoAwamotos\ERPIntegration\Api\ConnectionInterface;
use GrupoAwamotos\ERPIntegration\Model\ProductSuggestion;
use GrupoAwamotos\ERPIntegration\Model\PurchaseHistory;
use GrupoAwamotos\ERPIntegration\Model\Rfm\Calculator as RfmCalculator;
use GrupoAwamotos\ERPIntegration\Model\StockSync;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use Magento\Framework\App\CacheInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Psr\Log\LoggerInterface;

/**
 * Suggested Cart Builder
 *
 * Builds complete suggested carts for customers based on:
 * - Repurchase cycle (products due for reorder)
 * - Cross-sell (frequently bought together)
 * - Collaborative filtering (similar customers)
 * - RFM segment-based recommendations
 */
class SuggestedCart
{
    private const CACHE_PREFIX = 'erp_suggested_cart_';
    private const CACHE_TTL = 1800; // 30 minutes

    private ConnectionInterface $connection;
    private ProductSuggestion $productSuggestion;
    private PurchaseHistory $purchaseHistory;
    private RfmCalculator $rfmCalculator;
    private Helper $helper;
    private CacheInterface $cache;
    private ProductRepositoryInterface $productRepository;
    private SearchCriteriaBuilder $searchCriteriaBuilder;
    private StockRegistryInterface $stockRegistry;
    private StockSync $stockSync;
    private LoggerInterface $logger;

    public function __construct(
        ConnectionInterface $connection,
        ProductSuggestion $productSuggestion,
        PurchaseHistory $purchaseHistory,
        RfmCalculator $rfmCalculator,
        Helper $helper,
        CacheInterface $cache,
        ProductRepositoryInterface $productRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        StockRegistryInterface $stockRegistry,
        StockSync $stockSync,
        LoggerInterface $logger
    ) {
        $this->connection = $connection;
        $this->productSuggestion = $productSuggestion;
        $this->purchaseHistory = $purchaseHistory;
        $this->rfmCalculator = $rfmCalculator;
        $this->helper = $helper;
        $this->cache = $cache;
        $this->productRepository = $productRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->stockRegistry = $stockRegistry;
        $this->stockSync = $stockSync;
        $this->logger = $logger;
    }

    /**
     * Build complete suggested cart for a customer
     */
    public function buildSuggestedCart(int $customerCode, bool $forceRefresh = false): array
    {
        $cacheKey = self::CACHE_PREFIX . $customerCode;

        if (!$forceRefresh) {
            $cached = $this->cache->load($cacheKey);
            if ($cached) {
                return json_decode($cached, true);
            }
        }

        try {
            // Get customer info and RFM segment
            $customerInfo = $this->purchaseHistory->getCustomerInfo($customerCode);
            $rfmData = $this->rfmCalculator->getCustomerRfm($customerCode);
            $purchaseStats = $this->getCustomerPurchaseStats($customerCode);

            // Build each section of the cart
            $reorderItems = $this->getReorderItems($customerCode);
            $crossSellItems = $this->getCrossSellItems($customerCode, $reorderItems);
            $similarCustomerItems = $this->getSimilarCustomerItems($customerCode, $reorderItems, $crossSellItems);
            $dormantItems = $this->getDormantItems($customerCode, $reorderItems, $crossSellItems, $similarCustomerItems);

            // Calculate totals
            $allItems = array_merge($reorderItems, $crossSellItems, $similarCustomerItems, $dormantItems);
            $cartTotal = array_sum(array_map(function ($item) {
                return ($item['suggested_quantity'] ?? 1) * ($item['unit_price'] ?? 0);
            }, $allItems));

            $result = [
                'customer' => [
                    'code' => $customerCode,
                    'name' => $customerInfo['razao'] ?? '',
                    'trade_name' => $customerInfo['fantasia'] ?? '',
                    'cnpj' => $customerInfo['cgc'] ?? '',
                    'segment' => $rfmData['segment'] ?? 'unknown',
                    'segment_label' => $rfmData['segment_label'] ?? '',
                    'rfm_score' => $rfmData['rfm_score'] ?? '',
                ],
                'stats' => $purchaseStats,
                'sections' => [
                    'reorder' => [
                        'title' => 'Reposição',
                        'subtitle' => 'Baseado no seu ciclo de compra',
                        'icon' => 'refresh',
                        'items' => $reorderItems,
                        'subtotal' => $this->calculateSectionTotal($reorderItems),
                    ],
                    'cross_sell' => [
                        'title' => 'Frequentemente Comprados Juntos',
                        'subtitle' => 'Complementos para seus produtos',
                        'icon' => 'link',
                        'items' => $crossSellItems,
                        'subtotal' => $this->calculateSectionTotal($crossSellItems),
                    ],
                    'similar_customers' => [
                        'title' => 'Clientes Similares Também Compraram',
                        'subtitle' => 'Baseado em perfis de compra similares',
                        'icon' => 'users',
                        'items' => $similarCustomerItems,
                        'subtotal' => $this->calculateSectionTotal($similarCustomerItems),
                    ],
                    'dormant' => [
                        'title' => 'Faz Tempo que Você Não Compra',
                        'subtitle' => 'Produtos que você já comprou mas não pede há mais de 3 meses',
                        'icon' => 'clock',
                        'items' => $dormantItems,
                        'subtotal' => $this->calculateSectionTotal($dormantItems),
                    ],
                ],
                'summary' => [
                    'total_items' => count($allItems),
                    'total_quantity' => array_sum(array_column($allItems, 'suggested_quantity')),
                    'subtotal' => round($cartTotal, 2),
                    'estimated_shipping' => $this->estimateShipping($cartTotal),
                    'free_shipping_threshold' => 1500.00,
                    'free_shipping_eligible' => $cartTotal >= 1500,
                ],
                'generated_at' => date('Y-m-d H:i:s'),
                'expires_at' => date('Y-m-d H:i:s', time() + self::CACHE_TTL),
            ];

            $this->cache->save(json_encode($result), $cacheKey, [], self::CACHE_TTL);

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('[ERP Cart] Error building cart: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get items due for reorder based on purchase cycle
     * OTIMIZADO: Query simplificada sem window functions complexas
     */
    private function getReorderItems(int $customerCode, int $limit = 10): array
    {
        try {
            // Query simplificada: busca produtos comprados múltiplas vezes recentemente
            $items = $this->connection->query("
                SELECT TOP " . (int)$limit . "
                    i.MATERIAL as sku,
                    MAX(i.DESCRICAO) as name,
                    COUNT(DISTINCT i.PEDIDO) as order_count,
                    CEILING(AVG(i.QTDE)) as suggested_quantity,
                    MAX(i.VLRUNITARIO) as unit_price,
                    MAX(p.DTPEDIDO) as last_order_date,
                    DATEDIFF(DAY, MAX(p.DTPEDIDO), GETDATE()) as days_since_last,
                    CASE
                        WHEN DATEDIFF(DAY, MAX(p.DTPEDIDO), GETDATE()) >= 90 THEN 'overdue'
                        WHEN DATEDIFF(DAY, MAX(p.DTPEDIDO), GETDATE()) >= 60 THEN 'due_soon'
                        ELSE 'on_track'
                    END as reorder_status,
                    30 as avg_cycle_days
                FROM VE_PEDIDOITENS i
                INNER JOIN VE_PEDIDO p ON i.PEDIDO = p.CODIGO
                WHERE p.CLIENTE = ?
                AND p.STATUS NOT IN ('C', 'X')
                GROUP BY i.MATERIAL
                HAVING COUNT(DISTINCT i.PEDIDO) >= 2
                AND DATEDIFF(DAY, MAX(p.DTPEDIDO), GETDATE()) >= 30
                ORDER BY
                    CASE
                        WHEN DATEDIFF(DAY, MAX(p.DTPEDIDO), GETDATE()) >= 90 THEN 1
                        WHEN DATEDIFF(DAY, MAX(p.DTPEDIDO), GETDATE()) >= 60 THEN 2
                        ELSE 3
                    END,
                    COUNT(DISTINCT i.PEDIDO) DESC
            ", [$customerCode]);

            return $this->enrichAndFilterItems($items, 'reorder');
        } catch (\Exception $e) {
            $this->logger->error('[ERP Cart] Error getting reorder items: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get cross-sell items based on frequently bought together
     * OTIMIZADO: Query simplificada com limites
     */
    private function getCrossSellItems(int $customerCode, array $reorderItems, int $limit = 8): array
    {
        if (empty($reorderItems)) {
            return [];
        }

        try {
            // Usar apenas os TOP 5 SKUs de reorder para buscar cross-sell
            $skus = array_slice(array_column($reorderItems, 'sku'), 0, 5);
            $placeholders = implode(',', array_fill(0, count($skus), '?'));

            // Query otimizada com limite de pedidos
            $items = $this->connection->query("
                WITH RecentOrders AS (
                    SELECT TOP 100 i.PEDIDO
                    FROM VE_PEDIDOITENS i
                    INNER JOIN VE_PEDIDO p ON i.PEDIDO = p.CODIGO
                    WHERE i.MATERIAL IN ($placeholders)
                    AND p.STATUS NOT IN ('C', 'X')
                    AND p.DTPEDIDO >= DATEADD(year, -1, GETDATE())
                    ORDER BY p.DTPEDIDO DESC
                ),
                CustomerProducts AS (
                    SELECT DISTINCT i.MATERIAL
                    FROM VE_PEDIDOITENS i
                    INNER JOIN VE_PEDIDO p ON i.PEDIDO = p.CODIGO
                    WHERE p.CLIENTE = ?
                )
                SELECT TOP " . (int)$limit . "
                    i2.MATERIAL as sku,
                    MAX(i2.DESCRICAO) as name,
                    COUNT(DISTINCT i2.PEDIDO) as bought_together_count,
                    1 as suggested_quantity,
                    AVG(i2.VLRUNITARIO) as unit_price
                FROM VE_PEDIDOITENS i2
                WHERE i2.PEDIDO IN (SELECT PEDIDO FROM RecentOrders)
                AND i2.MATERIAL NOT IN (SELECT MATERIAL FROM CustomerProducts)
                AND i2.MATERIAL NOT IN ($placeholders)
                GROUP BY i2.MATERIAL
                HAVING COUNT(DISTINCT i2.PEDIDO) >= 2
                ORDER BY COUNT(DISTINCT i2.PEDIDO) DESC
            ", array_merge($skus, [$customerCode], $skus));

            return $this->enrichAndFilterItems($items, 'cross_sell');
        } catch (\Exception $e) {
            $this->logger->error('[ERP Cart] Error getting cross-sell items: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get items from similar customers (collaborative filtering)
     * OTIMIZADO: Query simplificada com limites agressivos
     */
    private function getSimilarCustomerItems(int $customerCode, array $reorderItems, array $crossSellItems, int $limit = 6): array
    {
        try {
            // Collect SKUs to exclude
            $excludeSkus = array_merge(
                array_column($reorderItems, 'sku'),
                array_column($crossSellItems, 'sku')
            );

            if (empty($excludeSkus)) {
                $excludeSkus = ['__NONE__'];
            }
            $excludePlaceholders = implode(',', array_fill(0, count($excludeSkus), '?'));

            // Query otimizada: usa apenas TOP 10 produtos do cliente para encontrar similares
            $items = $this->connection->query("
                WITH TopCustomerProducts AS (
                    SELECT TOP 10 i.MATERIAL
                    FROM VE_PEDIDOITENS i
                    INNER JOIN VE_PEDIDO p ON i.PEDIDO = p.CODIGO
                    WHERE p.CLIENTE = ?
                    AND p.STATUS NOT IN ('C', 'X')
                    GROUP BY i.MATERIAL
                    ORDER BY SUM(i.QTDE) DESC
                ),
                SimilarCustomers AS (
                    SELECT TOP 30 p.CLIENTE
                    FROM VE_PEDIDO p
                    INNER JOIN VE_PEDIDOITENS i ON p.CODIGO = i.PEDIDO
                    WHERE i.MATERIAL IN (SELECT MATERIAL FROM TopCustomerProducts)
                    AND p.CLIENTE <> ?
                    AND p.STATUS NOT IN ('C', 'X')
                    AND p.DTPEDIDO >= DATEADD(year, -1, GETDATE())
                    GROUP BY p.CLIENTE
                    HAVING COUNT(DISTINCT i.MATERIAL) >= 2
                    ORDER BY COUNT(DISTINCT i.MATERIAL) DESC
                ),
                CustomerAllProducts AS (
                    SELECT DISTINCT i.MATERIAL
                    FROM VE_PEDIDOITENS i
                    INNER JOIN VE_PEDIDO p ON i.PEDIDO = p.CODIGO
                    WHERE p.CLIENTE = ?
                )
                SELECT TOP " . (int)$limit . "
                    i2.MATERIAL as sku,
                    MAX(i2.DESCRICAO) as name,
                    COUNT(DISTINCT p2.CLIENTE) as similar_customers_bought,
                    1 as suggested_quantity,
                    AVG(i2.VLRUNITARIO) as unit_price
                FROM VE_PEDIDOITENS i2
                INNER JOIN VE_PEDIDO p2 ON i2.PEDIDO = p2.CODIGO
                WHERE p2.CLIENTE IN (SELECT CLIENTE FROM SimilarCustomers)
                AND i2.MATERIAL NOT IN (SELECT MATERIAL FROM CustomerAllProducts)
                AND i2.MATERIAL NOT IN ($excludePlaceholders)
                AND p2.STATUS NOT IN ('C', 'X')
                GROUP BY i2.MATERIAL
                HAVING COUNT(DISTINCT p2.CLIENTE) >= 2
                ORDER BY COUNT(DISTINCT p2.CLIENTE) DESC
            ", array_merge([$customerCode, $customerCode, $customerCode], $excludeSkus));

            return $this->enrichAndFilterItems($items, 'similar_customers');
        } catch (\Exception $e) {
            $this->logger->error('[ERP Cart] Error getting similar customer items: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get products the customer bought before but hasn't purchased in 90+ days.
     * Excludes products already present in other sections.
     */
    private function getDormantItems(
        int $customerCode,
        array $reorderItems,
        array $crossSellItems,
        array $similarCustomerItems,
        int $minDays = 90,
        int $limit = 10
    ): array {
        try {
            // Collect SKUs already in other sections to avoid duplicates
            $excludeSkus = array_merge(
                array_column($reorderItems, 'sku'),
                array_column($crossSellItems, 'sku'),
                array_column($similarCustomerItems, 'sku')
            );

            if (empty($excludeSkus)) {
                $excludeSkus = ['__NONE__'];
            }
            $excludePlaceholders = implode(',', array_fill(0, count($excludeSkus), '?'));

            $items = $this->connection->query("
                SELECT TOP " . (int)$limit . "
                    i.MATERIAL as sku,
                    MAX(i.DESCRICAO) as name,
                    COUNT(DISTINCT i.PEDIDO) as order_count,
                    SUM(i.QTDE) as total_qty,
                    CEILING(AVG(i.QTDE)) as suggested_quantity,
                    MAX(i.VLRUNITARIO) as unit_price,
                    MAX(p.DTPEDIDO) as last_order_date,
                    DATEDIFF(DAY, MAX(p.DTPEDIDO), GETDATE()) as days_since_last
                FROM VE_PEDIDOITENS i
                INNER JOIN VE_PEDIDO p ON i.PEDIDO = p.CODIGO
                WHERE p.CLIENTE = ?
                AND p.STATUS NOT IN ('C', 'X')
                AND i.MATERIAL NOT IN ($excludePlaceholders)
                GROUP BY i.MATERIAL
                HAVING DATEDIFF(DAY, MAX(p.DTPEDIDO), GETDATE()) >= ?
                ORDER BY DATEDIFF(DAY, MAX(p.DTPEDIDO), GETDATE()) DESC
            ", array_merge([$customerCode], $excludeSkus, [$minDays]));

            return $this->enrichAndFilterItems($items, 'dormant');
        } catch (\Exception $e) {
            $this->logger->error('[ERP Cart] Error getting dormant items: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get customer purchase statistics
     */
    private function getCustomerPurchaseStats(int $customerCode): array
    {
        try {
            $stats = $this->connection->fetchOne("
                SELECT
                    COUNT(DISTINCT p.CODIGO) as total_orders,
                    SUM(i.VLRTOTAL) as total_spent,
                    AVG(order_total) as avg_order_value,
                    COUNT(DISTINCT i.MATERIAL) as unique_products,
                    MAX(p.DTPEDIDO) as last_order_date,
                    DATEDIFF(DAY, MAX(p.DTPEDIDO), GETDATE()) as days_since_last,
                    AVG(days_between) as avg_order_frequency
                FROM VE_PEDIDO p
                INNER JOIN VE_PEDIDOITENS i ON p.CODIGO = i.PEDIDO
                CROSS APPLY (
                    SELECT SUM(i2.VLRTOTAL) as order_total
                    FROM VE_PEDIDOITENS i2
                    WHERE i2.PEDIDO = p.CODIGO
                ) ot
                OUTER APPLY (
                    SELECT DATEDIFF(DAY, prev.DTPEDIDO, p.DTPEDIDO) as days_between
                    FROM VE_PEDIDO prev
                    WHERE prev.CLIENTE = p.CLIENTE
                    AND prev.DTPEDIDO < p.DTPEDIDO
                    AND prev.STATUS NOT IN ('C', 'X')
                    AND prev.DTPEDIDO = (
                        SELECT MAX(p3.DTPEDIDO)
                        FROM VE_PEDIDO p3
                        WHERE p3.CLIENTE = p.CLIENTE
                        AND p3.DTPEDIDO < p.DTPEDIDO
                        AND p3.STATUS NOT IN ('C', 'X')
                    )
                ) freq
                WHERE p.CLIENTE = ?
                AND p.STATUS NOT IN ('C', 'X')
            ", [$customerCode]);

            return [
                'total_orders' => (int)($stats['total_orders'] ?? 0),
                'total_spent' => round((float)($stats['total_spent'] ?? 0), 2),
                'avg_order_value' => round((float)($stats['avg_order_value'] ?? 0), 2),
                'unique_products' => (int)($stats['unique_products'] ?? 0),
                'last_order_date' => $stats['last_order_date'] ?? null,
                'days_since_last' => (int)($stats['days_since_last'] ?? 0),
                'avg_order_frequency' => round((float)($stats['avg_order_frequency'] ?? 30), 0),
            ];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Enrich items with Magento data and filter unavailable
     */
    private function enrichAndFilterItems(array $items, string $type): array
    {
        if (empty($items)) {
            return [];
        }

        $skus = array_column($items, 'sku');

        // Get Magento products
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('sku', $skus, 'in')
            ->addFilter('status', 1) // Enabled only
            ->create();

        try {
            $magentoProducts = $this->productRepository->getList($searchCriteria)->getItems();
            $productsBySku = [];
            $stockDataBySku = $this->loadStockDataForProducts($magentoProducts);

            foreach ($magentoProducts as $product) {
                $sku = $product->getSku();
                $stockData = $stockDataBySku[$sku] ?? null;

                if ($stockData === null) {
                    continue;
                }

                $productsBySku[$sku] = [
                    'entity_id' => $product->getId(),
                    'name' => $product->getName(),
                    'url_key' => $product->getUrlKey(),
                    'product_url' => $product->getProductUrl(),
                    'price' => $product->getPrice(),
                    'final_price' => $product->getFinalPrice(),
                    'image' => $product->getImage(),
                    'in_stock' => $stockData['in_stock'],
                    'qty' => $stockData['qty'],
                ];
            }
        } catch (\Exception $e) {
            $this->logger->warning('[ERP Cart] Could not enrich items: ' . $e->getMessage());
            $productsBySku = [];
        }

        // Enrich and filter
        $enriched = [];
        foreach ($items as $item) {
            $sku = $item['sku'];

            if (!isset($productsBySku[$sku])) {
                continue; // Skip products not in Magento
            }

            $magentoData = $productsBySku[$sku];

            if (!$magentoData['in_stock']) {
                continue; // Skip out of stock
            }

            $suggestedQty = (int)($item['suggested_quantity'] ?? 1);
            $unitPrice = $magentoData['final_price'] ?: $magentoData['price'] ?: (float)($item['unit_price'] ?? 0);

            $enriched[] = [
                'sku' => $sku,
                'name' => $magentoData['name'] ?: $item['name'],
                'erp_name' => $item['name'],
                'suggested_quantity' => $suggestedQty,
                'unit_price' => round($unitPrice, 2),
                'line_total' => round($unitPrice * $suggestedQty, 2),
                'url' => $magentoData['product_url'] ?? null,
                'image' => $magentoData['image'],
                'product_id' => $magentoData['entity_id'],
                'in_stock' => true,
                'available_qty' => (int)$magentoData['qty'],
                'type' => $type,
                'metadata' => array_filter([
                    'order_count' => $item['order_count'] ?? null,
                    'avg_cycle_days' => $item['avg_cycle_days'] ?? null,
                    'days_since_last' => $item['days_since_last'] ?? null,
                    'reorder_status' => $item['reorder_status'] ?? null,
                    'bought_together_count' => $item['bought_together_count'] ?? null,
                    'similar_customers_bought' => $item['similar_customers_bought'] ?? null,
                ]),
            ];
        }

        return $enriched;
    }

    /**
     * Resolve stock for cart suggestions with a batch ERP query when realtime stock is enabled.
     *
     * @param array<int, \Magento\Catalog\Api\Data\ProductInterface> $magentoProducts
     * @return array<string, array{in_stock: bool, qty: float}>
     */
    private function loadStockDataForProducts(array $magentoProducts): array
    {
        if (empty($magentoProducts)) {
            return [];
        }

        $stockDataBySku = [];
        $skus = [];
        foreach ($magentoProducts as $product) {
            $skus[] = $product->getSku();
        }

        $useRealtimeBatch = $this->helper->isStockSyncEnabled() && $this->helper->isStockRealtime();
        $realtimeStockBySku = [];

        if ($useRealtimeBatch) {
            try {
                $realtimeStockBySku = $this->stockSync->getStocksBySkus($skus);
            } catch (\Exception $e) {
                $this->logger->warning('[ERP Cart] Could not batch-load realtime stock: ' . $e->getMessage());
            }
        }

        foreach ($magentoProducts as $product) {
            $sku = $product->getSku();
            $stockData = $realtimeStockBySku[$sku] ?? null;

            if ($stockData !== null) {
                $qty = (float) ($stockData['qty'] ?? 0);
                $stockDataBySku[$sku] = [
                    'in_stock' => $qty > 0,
                    'qty' => $qty,
                ];
                continue;
            }

            $stockItem = $this->stockRegistry->getStockItemBySku($sku);
            $stockDataBySku[$sku] = [
                'in_stock' => (bool) $stockItem->getIsInStock(),
                'qty' => (float) $stockItem->getQty(),
            ];
        }

        return $stockDataBySku;
    }

    /**
     * Calculate section total
     */
    private function calculateSectionTotal(array $items): float
    {
        return round(array_sum(array_column($items, 'line_total')), 2);
    }

    /**
     * Estimate shipping cost
     */
    private function estimateShipping(float $cartTotal): float
    {
        // Free shipping above threshold
        if ($cartTotal >= 1500) {
            return 0;
        }

        // Simple estimation - can be enhanced with actual shipping calculation
        return 50.00;
    }

    /**
     * Clear cart cache for a customer
     */
    public function clearCache(int $customerCode): void
    {
        $this->cache->remove(self::CACHE_PREFIX . $customerCode);
    }

    /**
     * Add suggested cart to Magento cart
     */
    public function addToMagentoCart(int $customerCode, array $selectedItems = []): array
    {
        // This would be implemented to add items to the actual Magento cart
        // For now, return the items that would be added
        $cart = $this->buildSuggestedCart($customerCode);

        if (isset($cart['error'])) {
            return $cart;
        }

        $itemsToAdd = [];
        foreach ($cart['sections'] as $section) {
            foreach ($section['items'] as $item) {
                if (empty($selectedItems) || in_array($item['sku'], $selectedItems)) {
                    $itemsToAdd[] = [
                        'sku' => $item['sku'],
                        'qty' => $item['suggested_quantity'],
                        'price' => $item['unit_price'],
                    ];
                }
            }
        }

        return [
            'success' => true,
            'items_added' => count($itemsToAdd),
            'items' => $itemsToAdd,
        ];
    }
}
