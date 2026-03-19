<?php
declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Model;

use GrupoAwamotos\ERPIntegration\Api\StockSyncInterface;
use GrupoAwamotos\ERPIntegration\Api\ConnectionInterface;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use GrupoAwamotos\ERPIntegration\Model\ResourceModel\SyncLog as SyncLogResource;
use GrupoAwamotos\ERPIntegration\Model\Validator\StockValidator;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

class StockSync implements StockSyncInterface
{
    private const CACHE_PREFIX = 'erp_stock_';
    private const CACHE_NEGATIVE_PREFIX = 'erp_stock_neg_';
    private const BATCH_SIZE = 1000;

    private ConnectionInterface $connection;
    private Helper $helper;
    private ProductRepositoryInterface $productRepository;
    private StockRegistryInterface $stockRegistry;
    private SyncLogResource $syncLogResource;
    private StockValidator $stockValidator;
    private CacheInterface $cache;
    private LoggerInterface $logger;
    private ResourceConnection $resourceConnection;
    private ?array $existingSkuCache = null;

    public function __construct(
        ConnectionInterface $connection,
        Helper $helper,
        ProductRepositoryInterface $productRepository,
        StockRegistryInterface $stockRegistry,
        SyncLogResource $syncLogResource,
        StockValidator $stockValidator,
        CacheInterface $cache,
        LoggerInterface $logger,
        ResourceConnection $resourceConnection
    ) {
        $this->connection = $connection;
        $this->helper = $helper;
        $this->productRepository = $productRepository;
        $this->stockRegistry = $stockRegistry;
        $this->syncLogResource = $syncLogResource;
        $this->stockValidator = $stockValidator;
        $this->cache = $cache;
        $this->logger = $logger;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * Generate cache keys for stock data
     */
    private function generateCacheKeys(string $sku, array $filiais): array
    {
        $suffix = md5($sku . '_' . implode(',', $filiais));
        return [
            'positive' => self::CACHE_PREFIX . $suffix,
            'negative' => self::CACHE_NEGATIVE_PREFIX . $suffix,
        ];
    }

    /**
     * Try to get stock from cache
     *
     * @return array|null|false Returns cached data, null if negative cache hit, false if no cache
     */
    private function getFromCache(string $cacheKey, string $negativeCacheKey, string $sku): array|null|false
    {
        if ($this->cache->load($negativeCacheKey) !== false) {
            $this->logger->debug('[ERP] Stock negative cache hit for SKU: ' . $sku);
            return null;
        }

        $cached = $this->cache->load($cacheKey);
        if ($cached !== false) {
            $decoded = json_decode($cached, true);
            if (is_array($decoded)) {
                $this->logger->debug('[ERP] Stock cache hit for SKU: ' . $sku);
                return $decoded;
            }
            $this->cache->remove($cacheKey);
        }

        return false;
    }

    /**
     * Save stock result to cache
     */
    private function saveToCache(string $cacheKey, string $negativeCacheKey, ?array $result, string $sku): void
    {
        if ($result) {
            $result['cached_at'] = date('Y-m-d H:i:s');
            $result['cache_ttl'] = $this->helper->getStockCacheTtl();
            $tags = $this->buildCacheTags($result);
            $this->cache->save(json_encode($result), $cacheKey, $tags, $this->helper->getStockCacheTtl());
            $this->logger->debug('[ERP] Stock cached for SKU: ' . $sku);
        } else {
            $this->cache->save('not_found', $negativeCacheKey, ['erp_stock', 'erp_stock_negative'], $this->helper->getNegativeCacheTtl());
            $this->logger->debug('[ERP] Stock negative cache set for SKU: ' . $sku);
        }
    }

    /**
     * Build cache tags from result
     */
    private function buildCacheTags(?array $result): array
    {
        $tags = ['erp_stock'];
        if (isset($result['branches']) && is_array($result['branches'])) {
            foreach (array_keys($result['branches']) as $filialId) {
                $tags[] = 'erp_stock_filial_' . $filialId;
            }
        }
        return $tags;
    }

    /**
     * Build SQL bindings for filial filters.
     *
     * @return array{params: array<string, int|string>, list: string}
     */
    private function buildFilialBindings(array $filiais, array $params = []): array
    {
        $placeholders = [];
        foreach ($filiais as $i => $filial) {
            $key = ':filial' . $i;
            $placeholders[] = $key;
            $params[$key] = (int) $filial;
        }

        return [
            'params' => $params,
            'list' => implode(',', $placeholders),
        ];
    }

    private function isMultiBranch(array $filiais): bool
    {
        return $this->helper->isMultiBranchEnabled() && count($filiais) > 1;
    }

    public function getStockBySku(string $sku): ?array
    {
        $filiais = $this->helper->getStockFiliais();
        $cacheKeys = $this->generateCacheKeys($sku, $filiais);

        $cachedResult = $this->getFromCache($cacheKeys['positive'], $cacheKeys['negative'], $sku);
        if ($cachedResult !== false) {
            return $cachedResult;
        }

        try {
            $result = $this->isMultiBranch($filiais)
                ? $this->getMultiBranchStock($sku, $filiais)
                : $this->getSingleBranchStock($sku, (int) $filiais[0]);

            $this->saveToCache($cacheKeys['positive'], $cacheKeys['negative'], $result, $sku);
            return $result;
        } catch (\Exception $e) {
            $this->logger->error('[ERP] Stock query error for SKU ' . $sku . ': ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Get stock from a single branch
     */
    private function getSingleBranchStock(string $sku, int $filial): ?array
    {
        $sql = "SELECT TOP 1 QTDE, VLRMEDIA, DATA
                FROM MT_ESTOQUEMEDIA
                WHERE MATERIAL = :sku AND FILIAL = :filial
                ORDER BY DATA DESC, CODIGO DESC";

        $row = $this->connection->fetchOne($sql, [
            ':sku' => $sku,
            ':filial' => $filial,
        ]);

        if ($row) {
            return [
                'qty' => (float) $row['QTDE'],
                'cost' => (float) $row['VLRMEDIA'],
                'date' => $row['DATA'],
                'filial' => $filial,
                'branches' => [$filial => (float) $row['QTDE']],
            ];
        }

        return null;
    }

    /**
     * Get aggregated stock from multiple branches
     */
    private function getMultiBranchStock(string $sku, array $filiais): ?array
    {
        $bindings = $this->buildFilialBindings($filiais, [':sku' => $sku, ':sku2' => $sku]);
        $params = $bindings['params'];
        $filialList = $bindings['list'];

        // Query to get latest stock for each branch
        $sql = "SELECT m.FILIAL, m.QTDE, m.VLRMEDIA, m.DATA
                FROM MT_ESTOQUEMEDIA m
                INNER JOIN (
                    SELECT FILIAL, MAX(CODIGO) AS MaxCodigo
                    FROM MT_ESTOQUEMEDIA
                    WHERE MATERIAL = :sku AND FILIAL IN ({$filialList})
                    GROUP BY FILIAL
                ) latest ON m.FILIAL = latest.FILIAL AND m.CODIGO = latest.MaxCodigo
                WHERE m.MATERIAL = :sku2";

        $rows = $this->connection->query($sql, $params);

        if (empty($rows)) {
            return null;
        }

        $branches = [];
        $quantities = [];
        $totalCost = 0;
        $costCount = 0;
        $latestDate = null;

        foreach ($rows as $row) {
            $filial = (int) $row['FILIAL'];
            $qty = (float) $row['QTDE'];
            $branches[$filial] = $qty;
            $quantities[] = $qty;

            if ((float) $row['VLRMEDIA'] > 0) {
                $totalCost += (float) $row['VLRMEDIA'];
                $costCount++;
            }

            if ($latestDate === null || $row['DATA'] > $latestDate) {
                $latestDate = $row['DATA'];
            }
        }

        // Aggregate quantity based on mode
        $aggregatedQty = $this->aggregateQuantities($quantities);

        return [
            'qty' => $aggregatedQty,
            'cost' => $costCount > 0 ? $totalCost / $costCount : 0,
            'date' => $latestDate,
            'filial' => 'multi',
            'branches' => $branches,
            'aggregation_mode' => $this->helper->getStockAggregationMode(),
        ];
    }

    /**
     * Aggregate quantities based on configured mode
     */
    private function aggregateQuantities(array $quantities): float
    {
        if (empty($quantities)) {
            return 0;
        }

        $mode = $this->helper->getStockAggregationMode();

        switch ($mode) {
            case 'sum':
                return array_sum($quantities);
            case 'min':
                return min($quantities);
            case 'max':
                return max($quantities);
            case 'avg':
                return array_sum($quantities) / count($quantities);
            default:
                return array_sum($quantities);
        }
    }

    public function invalidateCache(string $sku): void
    {
        $filiais = $this->helper->getStockFiliais();
        $cacheKey = self::CACHE_PREFIX . md5($sku . '_' . implode(',', $filiais));
        $negativeCacheKey = self::CACHE_NEGATIVE_PREFIX . md5($sku . '_' . implode(',', $filiais));

        // Remove both positive and negative cache
        $this->cache->remove($cacheKey);
        $this->cache->remove($negativeCacheKey);

        // Also invalidate single-branch cache key for backwards compatibility
        if (count($filiais) === 1) {
            $singleKey = self::CACHE_PREFIX . md5($sku . '_' . $filiais[0]);
            $singleNegativeKey = self::CACHE_NEGATIVE_PREFIX . md5($sku . '_' . $filiais[0]);
            $this->cache->remove($singleKey);
            $this->cache->remove($singleNegativeKey);
        }

        $this->logger->debug('[ERP] Stock cache invalidated for SKU: ' . $sku);
    }

    public function invalidateAllCache(): void
    {
        $this->cache->clean(['erp_stock']);
        $this->logger->info('[ERP] All stock cache invalidated');
    }

    /**
     * Invalidate cache by branch/filial
     */
    public function invalidateCacheByFilial(int $filialId): void
    {
        $this->cache->clean(['erp_stock_filial_' . $filialId]);
        $this->logger->info('[ERP] Stock cache invalidated for filial: ' . $filialId);
    }

    /**
     * Invalidate negative cache only (when new SKUs are added to ERP)
     */
    public function invalidateNegativeCache(): void
    {
        $this->cache->clean(['erp_stock_negative']);
        $this->logger->info('[ERP] Stock negative cache invalidated');
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array
    {
        // Note: This is a simplified implementation
        // Full implementation would require counting cache entries
        return [
            'ttl' => $this->helper->getStockCacheTtl(),
            'negative_ttl' => $this->helper->getNegativeCacheTtl(),
            'tags' => ['erp_stock', 'erp_stock_negative', 'erp_stock_filial_*'],
        ];
    }

    public function syncAll(): array
    {
        $startTime = microtime(true);
        $result = $this->initializeSyncResult();
        $this->existingSkuCache = null; // Reset cache for fresh data

        try {
            $filiais = $this->helper->getStockFiliais();
            $result = $this->runBranchSync($filiais, $result);
            $result = $this->finalizeSyncResult($result, $startTime);
            $this->logSyncSummary($filiais, $result);

            $this->logger->info('[ERP] Stock sync completed', $result);
        } catch (\Exception $e) {
            $result = $this->finalizeSyncResult($result, $startTime);
            $this->logger->error('[ERP] Stock sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'partial_result' => $result,
            ]);

            $this->syncLogResource->addLog('stock', 'import', 'error', $e->getMessage());
        }

        return $result;
    }

    /**
     * @return array{
     *     updated: int,
     *     skipped: int,
     *     errors: int,
     *     not_found: int,
     *     unchanged: int,
     *     validation_failed: int,
     *     anomalies_detected: int,
     *     total_erp_records: int,
     *     execution_time: float
     * }
     */
    private function initializeSyncResult(): array
    {
        return [
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'not_found' => 0,
            'unchanged' => 0,
            'validation_failed' => 0,
            'anomalies_detected' => 0,
            'total_erp_records' => 0,
            'execution_time' => 0.0,
        ];
    }

    private function finalizeSyncResult(array $result, float $startTime): array
    {
        $result['execution_time'] = round((microtime(true) - $startTime) * 1000, 2);
        return $result;
    }

    private function runBranchSync(array $filiais, array $result): array
    {
        if ($this->isMultiBranch($filiais)) {
            return $this->syncMultiBranch($filiais, $result);
        }

        return $this->syncSingleBranch((int) $filiais[0], $result);
    }

    private function determineSyncStatus(array $result): string
    {
        if ($result['errors'] <= 0) {
            return 'success';
        }

        return $result['errors'] > ($result['total_erp_records'] * 0.1) ? 'error' : 'partial';
    }

    private function buildSyncSummaryMessage(array $filiais, array $result): string
    {
        $branchInfo = $this->isMultiBranch($filiais)
            ? 'Filiais: ' . implode(',', $filiais)
            : 'Filial: ' . (int) $filiais[0];

        return sprintf(
            '%s | Atualizados: %d, Não encontrados: %d, Sem alteração: %d, Ignorados: %d, Erros: %d | Tempo: %dms',
            $branchInfo,
            $result['updated'],
            $result['not_found'],
            $result['unchanged'],
            $result['skipped'],
            $result['errors'],
            $result['execution_time']
        );
    }

    private function logSyncSummary(array $filiais, array $result): void
    {
        $this->syncLogResource->addLog(
            'stock',
            'import',
            $this->determineSyncStatus($result),
            $this->buildSyncSummaryMessage($filiais, $result),
            null,
            null,
            $result['updated']
        );
    }

    /**
     * Sync stock from a single branch
     */
    private function syncSingleBranch(int $filial, array $result): array
    {
        $sql = "SELECT m.MATERIAL, m.QTDE, m.VLRMEDIA
                FROM MT_ESTOQUEMEDIA m
                INNER JOIN (
                    SELECT MATERIAL, MAX(CODIGO) AS MaxCodigo
                    FROM MT_ESTOQUEMEDIA
                    WHERE FILIAL = :filial
                    GROUP BY MATERIAL
                ) latest ON m.MATERIAL = latest.MATERIAL AND m.CODIGO = latest.MaxCodigo
                WHERE m.FILIAL = :filial2";

        $rows = $this->connection->query($sql, [
            ':filial' => $filial,
            ':filial2' => $filial,
        ]);

        $result['total_erp_records'] = count($rows);

        $this->logger->info('[ERP] Starting stock sync', [
            'total_records' => $result['total_erp_records'],
            'filial' => $filial,
        ]);

        foreach ($rows as $row) {
            $syncResult = $this->syncSingleStock($row);
            $result = $this->updateResultCounter($result, $syncResult);
        }

        return $result;
    }

    /**
     * Sync aggregated stock from multiple branches
     */
    private function syncMultiBranch(array $filiais, array $result): array
    {
        $bindings = $this->buildFilialBindings($filiais);
        $params = $bindings['params'];
        $filialList = $bindings['list'];

        // Get aggregated stock per material across all branches
        $sql = "SELECT m.MATERIAL,
                       SUM(m.QTDE) AS QTDE_TOTAL,
                       AVG(m.VLRMEDIA) AS VLRMEDIA,
                       MAX(m.DATA) AS DATA
                FROM MT_ESTOQUEMEDIA m
                INNER JOIN (
                    SELECT MATERIAL, FILIAL, MAX(CODIGO) AS MaxCodigo
                    FROM MT_ESTOQUEMEDIA
                    WHERE FILIAL IN ({$filialList})
                    GROUP BY MATERIAL, FILIAL
                ) latest ON m.MATERIAL = latest.MATERIAL
                        AND m.FILIAL = latest.FILIAL
                        AND m.CODIGO = latest.MaxCodigo
                GROUP BY m.MATERIAL";

        $rows = $this->connection->query($sql, $params);

        $result['total_erp_records'] = count($rows);

        $this->logger->info('[ERP] Starting multi-branch stock sync', [
            'total_records' => $result['total_erp_records'],
            'filiais' => $filiais,
            'aggregation_mode' => $this->helper->getStockAggregationMode(),
        ]);

        foreach ($rows as $row) {
            // For multi-branch, we use QTDE_TOTAL which is already aggregated
            $row['QTDE'] = $row['QTDE_TOTAL'];
            $syncResult = $this->syncSingleStock($row);
            $result = $this->updateResultCounter($result, $syncResult);
        }

        return $result;
    }

    /**
     * Sync stock for a single product
     *
     * @return array ['status' => string, 'anomaly' => bool]
     */
    private function syncSingleStock(array $row): array
    {
        $result = ['status' => 'skipped', 'anomaly' => false];
        $sku = trim($row['MATERIAL'] ?? '');

        if (empty($sku)) {
            return $result;
        }

        try {
            $validationResult = $this->validateStockData($row, $sku);
            if ($validationResult === null) {
                $result['status'] = 'validation_failed';
                return $result;
            }

            $result['anomaly'] = $this->checkAndLogAnomaly($validationResult, $sku);

            // Try exact SKU first, then base-SKU fallback
            $targetSku = $sku;
            if (!$this->productExists($sku)) {
                $baseSku = $this->getBaseSku($sku);
                if ($baseSku !== $sku && $this->productExists($baseSku)) {
                    $targetSku = $baseSku;
                } else {
                    $result['status'] = 'not_found';
                    return $result;
                }
            }

            $result['status'] = $this->syncValidatedStock($targetSku, $validationResult);
            return $result;
        } catch (\Exception $e) {
            $this->logger->error('[ERP] Stock sync error', ['sku' => $sku, 'error' => $e->getMessage()]);
            $result['status'] = 'error';
            return $result;
        }
    }

    private function syncValidatedStock(string $sku, object $validationResult): string
    {
        return $this->updateStockIfChanged($sku, (float) $validationResult->getField('quantity', 0));
    }

    /**
     * Validate stock data and return result or null if invalid
     */
    private function validateStockData(array $row, string $sku): ?object
    {
        $validationResult = $this->stockValidator->validate($row, $sku);

        if (!$validationResult->isValid()) {
            $this->logger->warning('[ERP] Stock validation failed', [
                'sku' => $sku,
                'errors' => $validationResult->getErrors(),
            ]);
            return null;
        }

        return $validationResult;
    }

    /**
     * Check for anomalies and log if detected
     */
    private function checkAndLogAnomaly(object $validationResult, string $sku): bool
    {
        if (!$validationResult->getField('anomaly_detected', false)) {
            return false;
        }

        $this->logger->warning('[ERP] Stock anomaly detected', [
            'sku' => $sku,
            'change_percent' => $validationResult->getField('anomaly_percent_change'),
            'previous_qty' => $validationResult->getField('previous_quantity'),
            'new_qty' => $validationResult->getField('quantity'),
        ]);

        return true;
    }

    /**
     * Check if product exists in catalog using pre-loaded SKU cache
     */
    private function productExists(string $sku): bool
    {
        if ($this->existingSkuCache === null) {
            $this->loadExistingSkus();
        }

        return isset($this->existingSkuCache[$sku]);
    }

    /**
     * Pre-load all existing SKUs in a single query for performance
     */
    private function loadExistingSkus(): void
    {
        $conn = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('catalog_product_entity');
        $skus = $conn->fetchCol("SELECT sku FROM {$table}");
        $this->existingSkuCache = array_flip($skus);
        $this->logger->info('[ERP] Pre-loaded SKU cache', ['total_skus' => count($this->existingSkuCache)]);
    }

    /**
     * Update stock if quantity changed
     */
    private function updateStockIfChanged(string $sku, float $qty): string
    {
        $stockItem = $this->stockRegistry->getStockItemBySku($sku);
        $currentQty = (float) $stockItem->getQty();

        if (abs($currentQty - $qty) < 0.001) {
            return 'unchanged';
        }

        $stockItem->setQty($qty);
        $stockItem->setIsInStock($qty > 0);
        $this->stockRegistry->updateStockItemBySku($sku, $stockItem);
        $this->invalidateCache($sku);

        return 'updated';
    }

    /**
     * Update result counters based on sync result
     */
    private function updateResultCounter(array $result, array $syncResult): array
    {
        $statusCounterMap = [
            'updated' => 'updated',
            'unchanged' => 'unchanged',
            'not_found' => 'not_found',
            'skipped' => 'skipped',
            'validation_failed' => 'validation_failed',
            'error' => 'errors',
        ];

        $status = $syncResult['status'] ?? 'skipped';
        $counterKey = $statusCounterMap[$status] ?? 'skipped';
        $result[$counterKey]++;

        if ($syncResult['anomaly'] ?? false) {
            $result['anomalies_detected']++;
        }

        return $result;
    }

    public function syncBySku(string $sku): bool
    {
        try {
            $stockData = $this->getStockBySku($sku);

            if ($stockData === null) {
                $this->logger->info('[ERP] No stock data found in ERP for SKU: ' . $sku);
                return false;
            }

            try {
                $this->productRepository->get($sku);
            } catch (NoSuchEntityException $e) {
                $this->logger->warning('[ERP] Product not found in Magento for stock sync: ' . $sku);
                return false;
            }

            $qty = $stockData['qty'];
            if ($qty < 0) {
                $qty = 0;
            }

            $stockItem = $this->stockRegistry->getStockItemBySku($sku);
            $stockItem->setQty($qty);
            $stockItem->setIsInStock($qty > 0);
            $this->stockRegistry->updateStockItemBySku($sku, $stockItem);

            $this->logger->info('[ERP] Stock synced for SKU', [
                'sku' => $sku,
                'qty' => $qty,
                'branches' => $stockData['branches'] ?? [],
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('[ERP] Single stock sync error', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get stock breakdown by branch for a SKU
     */
    public function getStockBreakdownBySku(string $sku): array
    {
        $filiais = $this->helper->getStockFiliais();

        if (count($filiais) === 1) {
            $data = $this->getSingleBranchStock($sku, $filiais[0]);
            return $data ? $data['branches'] : [];
        }

        $data = $this->getMultiBranchStock($sku, $filiais);
        return $data ? $data['branches'] : [];
    }

    /**
     * Extract base SKU from variant SKU.
     *
     * Same logic as PriceSync to keep SKU matching consistent.
     */
    private function getBaseSku(string $sku): string
    {
        $sku = trim($sku);

        // Space-separated variant: "1119 RS" → "1119"
        if (str_contains($sku, ' ')) {
            return trim(explode(' ', $sku)[0]);
        }

        // Dot-separated variant: "0045.01" → "0045", "2213.00" → "2213"
        if (preg_match('/^(\d{3,})\.\d+$/', $sku, $m)) {
            return $m[1];
        }

        // Alpha suffix variant: "1125NAO" → "1125"
        if (preg_match('/^(\d{3,})[A-Z]{2,3}$/i', $sku, $m)) {
            return $m[1];
        }

        return $sku;
    }

    /**
     * Get available branches from ERP
     */
    public function getAvailableBranches(): array
    {
        try {
            $sql = "SELECT DISTINCT f.CODIGO, f.FANTASIA, f.CIDADE
                    FROM CD_FILIAL f
                    WHERE f.ATIVO = 'S'
                    ORDER BY f.CODIGO";

            return $this->connection->query($sql);
        } catch (\Exception $e) {
            $this->logger->error('[ERP] Get branches error: ' . $e->getMessage());
            return [];
        }
    }
}
