<?php
declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Model;

use GrupoAwamotos\ERPIntegration\Api\ProductSyncInterface;
use GrupoAwamotos\ERPIntegration\Api\ConnectionInterface;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use GrupoAwamotos\ERPIntegration\Model\ResourceModel\SyncLog as SyncLogResource;
use GrupoAwamotos\ERPIntegration\Model\Validator\ProductValidator;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Api\CategoryLinkManagementInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State as AppState;
use Psr\Log\LoggerInterface;

class ProductSync implements ProductSyncInterface
{
    /**
     * Batch size for pagination
     */
    private const BATCH_SIZE = 500;

    /**
     * Memory limit warning threshold (80% of limit)
     */
    private const MEMORY_WARNING_THRESHOLD = 0.8;

    private ConnectionInterface $connection;
    private Helper $helper;
    private ProductRepositoryInterface $productRepository;
    private ProductInterfaceFactory $productFactory;
    private StoreManagerInterface $storeManager;
    private SyncLogResource $syncLogResource;
    private ProductValidator $productValidator;
    private LoggerInterface $logger;
    private AppState $appState;
    private CategoryLinkManagementInterface $categoryLinkManagement;
    private ?int $defaultAttributeSetId = null;

    public function __construct(
        ConnectionInterface $connection,
        Helper $helper,
        ProductRepositoryInterface $productRepository,
        ProductInterfaceFactory $productFactory,
        StoreManagerInterface $storeManager,
        SyncLogResource $syncLogResource,
        ProductValidator $productValidator,
        LoggerInterface $logger,
        AppState $appState,
        CategoryLinkManagementInterface $categoryLinkManagement
    ) {
        $this->connection = $connection;
        $this->helper = $helper;
        $this->productRepository = $productRepository;
        $this->productFactory = $productFactory;
        $this->storeManager = $storeManager;
        $this->syncLogResource = $syncLogResource;
        $this->productValidator = $productValidator;
        $this->logger = $logger;
        $this->appState = $appState;
        $this->categoryLinkManagement = $categoryLinkManagement;
    }

    /**
     * Get total count of ERP products
     */
    public function getErpProductCount(): int
    {
        $sql = "SELECT COUNT(*) as total
                FROM MT_MATERIAL m
                WHERE m.CCKATIVO = 'S'";

        if ($this->helper->filterComercializa()) {
            $sql .= " AND m.CKCOMERCIALIZA = 'S'";
        }

        $result = $this->connection->fetchOne($sql, []);

        // fetchOne() returns a scalar (first column of first row)
        return $result ? (int) $result : 0;
    }

    public function getErpProducts(int $limit = 0, int $offset = 0): array
    {
        $sql = "SELECT m.CODIGO, m.DESCRICAO, m.COMPLEMENTO, m.CODINTERNO,
                       m.NCM, m.CPESO, m.VPESO, m.DIMENSOES, m.UNDVENDA,
                       m.CKCOMERCIALIZA, m.CCKATIVO, m.VCKATIVO, m.TPMATERIAL,
                       m.GRUPOCOMERCIAL, m.EDITDATE,
                       c.VLRCUSTO, c.MARGEMSUG,
                       p.VLRVENDA
                FROM MT_MATERIAL m
                LEFT JOIN MT_MATERIALCUSTO c ON c.MATERIAL = m.CODIGO AND c.FILIAL = :filial1
                LEFT JOIN MT_COMPOSICAOPRECO p ON p.MATERIAL = m.CODIGO AND p.FILIAL = :filial2
                WHERE m.CCKATIVO = 'S'";

        if ($this->helper->filterComercializa()) {
            $sql .= " AND m.CKCOMERCIALIZA = 'S'";
        }

        $sql .= " ORDER BY m.CODIGO";

        // SQL Server requires integer literals for OFFSET/FETCH, not parameters
        if ($limit > 0) {
            $sql .= " OFFSET {$offset} ROWS FETCH NEXT {$limit} ROWS ONLY";
        }

        $params = [
            ':filial1' => $this->helper->getStockFilial(),
            ':filial2' => $this->helper->getStockFilial(),
        ];

        return $this->connection->query($sql, $params);
    }

    public function syncAll(): array
    {
        $startTime = microtime(true);
        $result = [
            'created' => 0,
            'updated' => 0,
            'errors' => 0,
            'skipped' => 0,
            'validation_failed' => 0,
            'batches_processed' => 0,
            'total_products' => 0,
            'execution_time' => 0,
        ];

        // Set area code for CLI execution
        try {
            $this->appState->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            // Area code already set, ignore
        }

        try {
            // Get total count for progress tracking
            $totalCount = $this->getErpProductCount();
            $result['total_products'] = $totalCount;

            if ($totalCount === 0) {
                $this->logger->info('[ERP] No products to sync from ERP');
                return $result;
            }

            $this->logger->info('[ERP] Starting product sync', [
                'total_products' => $totalCount,
                'batch_size' => self::BATCH_SIZE,
                'estimated_batches' => ceil($totalCount / self::BATCH_SIZE),
            ]);

            $websiteIds = [$this->storeManager->getDefaultStoreView()->getWebsiteId()];
            $offset = 0;

            // Process in batches
            while ($offset < $totalCount) {
                // Check memory usage before processing batch
                $this->checkMemoryUsage();

                $batchStartTime = microtime(true);
                $batchResult = $this->processBatch($offset, self::BATCH_SIZE, $websiteIds);

                $result['created'] += $batchResult['created'];
                $result['updated'] += $batchResult['updated'];
                $result['errors'] += $batchResult['errors'];
                $result['skipped'] += $batchResult['skipped'];
                $result['validation_failed'] += $batchResult['validation_failed'] ?? 0;
                $result['batches_processed']++;

                $batchTime = round((microtime(true) - $batchStartTime) * 1000, 2);

                $this->logger->info('[ERP] Batch processed', [
                    'batch_number' => $result['batches_processed'],
                    'offset' => $offset,
                    'batch_created' => $batchResult['created'],
                    'batch_updated' => $batchResult['updated'],
                    'batch_errors' => $batchResult['errors'],
                    'batch_time_ms' => $batchTime,
                    'progress' => round(min(100, (($offset + self::BATCH_SIZE) / $totalCount) * 100), 1) . '%',
                ]);

                $offset += self::BATCH_SIZE;

                // Clear entity manager to free memory
                $this->clearEntityCache();
            }

            $result['execution_time'] = round((microtime(true) - $startTime) * 1000, 2);

            $this->syncLogResource->addLog(
                'product',
                'import',
                $result['errors'] > 0 ? 'partial' : 'success',
                sprintf(
                    'Criados: %d, Atualizados: %d, Erros: %d, Ignorados: %d | Batches: %d | Tempo: %dms',
                    $result['created'],
                    $result['updated'],
                    $result['errors'],
                    $result['skipped'],
                    $result['batches_processed'],
                    $result['execution_time']
                ),
                null,
                null,
                $result['created'] + $result['updated']
            );

            $this->logger->info('[ERP] Product sync completed', $result);
        } catch (\Exception $e) {
            $result['execution_time'] = round((microtime(true) - $startTime) * 1000, 2);

            $this->logger->error('[ERP] Product sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'partial_result' => $result,
            ]);

            $this->syncLogResource->addLog('product', 'import', 'error', $e->getMessage());
            $result['errors']++;
        }

        return $result;
    }

    /**
     * Process a single batch of products
     */
    private function processBatch(int $offset, int $limit, array $websiteIds): array
    {
        $batchResult = ['created' => 0, 'updated' => 0, 'errors' => 0, 'skipped' => 0, 'validation_failed' => 0];

        $erpProducts = $this->getErpProducts($limit, $offset);

        foreach ($erpProducts as $erpProduct) {
            try {
                // Validate product data before sync
                $validationResult = $this->productValidator->validate($erpProduct);

                if (!$validationResult->isValid()) {
                    $batchResult['validation_failed']++;
                    $this->logger->warning('[ERP] Product validation failed', [
                        'sku' => $erpProduct['CODIGO'] ?? '?',
                        'errors' => $validationResult->getErrors(),
                    ]);
                    continue;
                }

                // Log warnings if any
                if ($validationResult->hasWarnings()) {
                    $this->logger->info('[ERP] Product validation warnings', [
                        'sku' => $erpProduct['CODIGO'] ?? '?',
                        'warnings' => $validationResult->getWarnings(),
                    ]);
                }

                $syncResult = $this->syncSingleProduct($erpProduct, $websiteIds, $validationResult);

                switch ($syncResult) {
                    case 'created':
                        $batchResult['created']++;
                        break;
                    case 'updated':
                        $batchResult['updated']++;
                        break;
                    case 'skipped':
                        $batchResult['skipped']++;
                        // For skipped products, still ensure category assignment
                        $this->ensureCategoryAssignment($erpProduct);
                        break;
                }
            } catch (\Exception $e) {
                $batchResult['errors']++;
                $this->logger->error('[ERP] Product sync error', [
                    'sku' => $erpProduct['CODIGO'] ?? '?',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $batchResult;
    }

    /**
     * Sync a single product
     *
     * @return string 'created', 'updated', or 'skipped'
     */
    private function syncSingleProduct(
        array $erpProduct,
        array $websiteIds,
        ?\GrupoAwamotos\ERPIntegration\Model\Validator\ValidationResult $validationResult = null
    ): string {
        // Use validated data if available, otherwise use raw data
        $sku = $validationResult
            ? $validationResult->getField('sku', trim($erpProduct['CODIGO'] ?? ''))
            : trim($erpProduct['CODIGO'] ?? '');

        if (empty($sku)) {
            return 'skipped';
        }

        $name = $validationResult
            ? $validationResult->getField('name', trim($erpProduct['DESCRICAO'] ?? ''))
            : trim($erpProduct['DESCRICAO'] ?? '');

        if (empty($name)) {
            return 'skipped';
        }

        // Check if data has changed using hash
        $dataHash = md5(json_encode($erpProduct));
        $existingHash = $this->syncLogResource->getEntityMapHash('product', $sku);

        if ($existingHash === $dataHash) {
            // Data hasn't changed, skip update
            return 'skipped';
        }

        try {
            $product = $this->productRepository->get($sku);
            $isNew = false;
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            $product = $this->productFactory->create();
            $product->setSku($sku);
            $product->setTypeId(Type::TYPE_SIMPLE);
            $product->setAttributeSetId($this->getDefaultAttributeSetId());
            $product->setWebsiteIds($websiteIds);
            $product->setVisibility(Visibility::VISIBILITY_BOTH);
            $isNew = true;
        }

        $product->setName($name);

        // Generate unique URL key using SKU to avoid duplicates
        $urlKey = $this->generateUrlKey($name, $sku);
        $product->setUrlKey($urlKey);

        if (!empty($erpProduct['COMPLEMENTO'])) {
            $product->setShortDescription(trim($erpProduct['COMPLEMENTO']));
        }

        // Use validated price if available
        $price = $validationResult
            ? $validationResult->getField('price', (float) ($erpProduct['VLRVENDA'] ?? 0))
            : (float) ($erpProduct['VLRVENDA'] ?? 0);

        // Magento requires a price. Set minimum price if none defined.
        if ($price <= 0) {
            $price = 0.01; // Minimum placeholder price
        }
        $product->setPrice($price);

        // Use validated weight if available
        $weight = $validationResult
            ? $validationResult->getField('weight', (float) ($erpProduct['VPESO'] ?? $erpProduct['CPESO'] ?? 0))
            : (float) ($erpProduct['VPESO'] ?? $erpProduct['CPESO'] ?? 0);

        if ($weight > 0) {
            $product->setWeight($weight);
        }

        // Use validated status if available
        $isActive = $validationResult
            ? $validationResult->getField('is_active', ($erpProduct['CCKATIVO'] ?? 'N') === 'S')
            : ($erpProduct['CCKATIVO'] ?? 'N') === 'S';

        $product->setStatus($isActive ? Status::STATUS_ENABLED : Status::STATUS_DISABLED);

        if (!empty($erpProduct['CODINTERNO'])) {
            $product->setCustomAttribute('erp_internal_code', $erpProduct['CODINTERNO']);
        }
        if (!empty($erpProduct['NCM'])) {
            $product->setCustomAttribute('erp_ncm', $erpProduct['NCM']);
        }

        $this->productRepository->save($product);

        // Assign product to ERP category via GRUPOCOMERCIAL
        $grupoComercial = $erpProduct['GRUPOCOMERCIAL'] ?? null;
        if ($grupoComercial !== null && $grupoComercial !== '' && (int) $grupoComercial !== 0) {
            $this->assignProductToErpCategory($product, (string) $grupoComercial);
        }

        $this->syncLogResource->setEntityMap(
            'product',
            $sku,
            (int) $product->getId(),
            $dataHash
        );

        return $isNew ? 'created' : 'updated';
    }

    /**
     * Ensure category assignment for products that were skipped (hash unchanged)
     */
    private function ensureCategoryAssignment(array $erpProduct): void
    {
        $grupoComercial = $erpProduct['GRUPOCOMERCIAL'] ?? null;
        if ($grupoComercial === null || $grupoComercial === '' || (int) $grupoComercial === 0) {
            return;
        }

        $sku = trim($erpProduct['CODIGO'] ?? '');
        if (empty($sku)) {
            return;
        }

        try {
            $product = $this->productRepository->get($sku);
            $this->assignProductToErpCategory($product, (string) $grupoComercial);
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            // Product doesn't exist in Magento
        } catch (\Exception $e) {
            // Non-critical, just log
            $this->logger->debug('[ERP] Category assignment skipped', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Assign product to ERP category WITHOUT removing existing manual categories
     */
    private function assignProductToErpCategory($product, string $erpCategoryCode): void
    {
        try {
            $magentoCategoryId = $this->syncLogResource->getEntityMap('category', $erpCategoryCode);

            if (!$magentoCategoryId) {
                // Category not yet synced — will be assigned on next run
                return;
            }

            $currentCategoryIds = $product->getCategoryIds();

            if (in_array($magentoCategoryId, $currentCategoryIds)) {
                return; // Already assigned
            }

            // Non-destructive merge: add ERP category to existing ones
            $newCategoryIds = array_unique(array_merge($currentCategoryIds, [$magentoCategoryId]));

            $this->categoryLinkManagement->assignProductToCategories(
                $product->getSku(),
                $newCategoryIds
            );

            $this->logger->info('[ERP] Product assigned to ERP category', [
                'sku' => $product->getSku(),
                'erp_category' => $erpCategoryCode,
                'magento_category_id' => $magentoCategoryId,
            ]);
        } catch (\Exception $e) {
            // Never fail product sync due to category assignment error
            $this->logger->warning('[ERP] Category assignment failed', [
                'sku' => $product->getSku(),
                'erp_category' => $erpCategoryCode,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generate unique URL key for product
     * Combines product name with SKU to ensure uniqueness
     */
    private function generateUrlKey(string $name, string $sku): string
    {
        // Transliterate accented characters
        $urlKey = $this->transliterate($name);

        // Convert to lowercase
        $urlKey = strtolower($urlKey);

        // Replace non-alphanumeric characters with hyphens
        $urlKey = preg_replace('/[^a-z0-9]+/', '-', $urlKey);

        // Remove leading/trailing hyphens
        $urlKey = trim($urlKey, '-');

        // Append SKU to ensure uniqueness
        $skuSlug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $sku));
        $skuSlug = trim($skuSlug, '-');

        // Combine name and SKU
        $urlKey = $urlKey . '-' . $skuSlug;

        // Limit length (Magento limit is 255, but we keep it shorter for readability)
        if (strlen($urlKey) > 200) {
            $urlKey = substr($urlKey, 0, 200);
            $urlKey = rtrim($urlKey, '-');
        }

        return $urlKey;
    }

    /**
     * Transliterate accented characters to ASCII
     */
    private function transliterate(string $string): string
    {
        $transliterations = [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
            'Á' => 'A', 'À' => 'A', 'Ã' => 'A', 'Â' => 'A', 'Ä' => 'A',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ó' => 'O', 'Ò' => 'O', 'Õ' => 'O', 'Ô' => 'O', 'Ö' => 'O',
            'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ç' => 'C', 'Ñ' => 'N',
        ];

        return strtr($string, $transliterations);
    }

    /**
     * Get default attribute set ID
     */
    private function getDefaultAttributeSetId(): int
    {
        if ($this->defaultAttributeSetId === null) {
            // Get from default product or use 4 as fallback
            $this->defaultAttributeSetId = 4;
        }

        return $this->defaultAttributeSetId;
    }

    /**
     * Check memory usage and log warning if high
     */
    private function checkMemoryUsage(): void
    {
        $memoryLimit = $this->getMemoryLimitBytes();
        $currentUsage = memory_get_usage(true);

        if ($memoryLimit > 0 && ($currentUsage / $memoryLimit) > self::MEMORY_WARNING_THRESHOLD) {
            $this->logger->warning('[ERP] High memory usage during product sync', [
                'current_usage_mb' => round($currentUsage / 1024 / 1024, 2),
                'limit_mb' => round($memoryLimit / 1024 / 1024, 2),
                'percentage' => round(($currentUsage / $memoryLimit) * 100, 1),
            ]);
        }
    }

    /**
     * Get PHP memory limit in bytes
     */
    private function getMemoryLimitBytes(): int
    {
        $limit = ini_get('memory_limit');
        if ($limit === '-1') {
            return 0; // No limit
        }

        $unit = strtolower(substr($limit, -1));
        $value = (int) $limit;

        switch ($unit) {
            case 'g':
                $value *= 1024;
                // fall through
            case 'm':
                $value *= 1024;
                // fall through
            case 'k':
                $value *= 1024;
        }

        return $value;
    }

    /**
     * Clear entity cache to free memory
     */
    private function clearEntityCache(): void
    {
        // Force garbage collection
        gc_collect_cycles();
    }

    public function syncBySku(string $sku): bool
    {
        try {
            $sql = "SELECT m.CODIGO, m.DESCRICAO, m.COMPLEMENTO, m.CODINTERNO,
                           m.NCM, m.CPESO, m.VPESO, m.DIMENSOES, m.UNDVENDA,
                           m.CKCOMERCIALIZA, m.CCKATIVO,
                           c.VLRCUSTO, c.MARGEMSUG,
                           p.VLRVENDA
                    FROM MT_MATERIAL m
                    LEFT JOIN MT_MATERIALCUSTO c ON c.MATERIAL = m.CODIGO AND c.FILIAL = :filial1
                    LEFT JOIN MT_COMPOSICAOPRECO p ON p.MATERIAL = m.CODIGO AND p.FILIAL = :filial2
                    WHERE m.CODIGO = :sku";

            $row = $this->connection->fetchOne($sql, [
                ':filial1' => $this->helper->getStockFilial(),
                ':filial2' => $this->helper->getStockFilial(),
                ':sku' => $sku,
            ]);

            return $row !== null;
        } catch (\Exception $e) {
            $this->logger->error('[ERP] Single product sync error: ' . $e->getMessage());
            return false;
        }
    }
}
