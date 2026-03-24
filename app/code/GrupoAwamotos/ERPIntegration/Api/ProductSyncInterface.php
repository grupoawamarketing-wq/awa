<?php
declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Api;

interface ProductSyncInterface
{
    /**
     * Sync all products from ERP to Magento
     *
     * @return array Result with 'created', 'updated', 'errors', 'skipped', 'batches_processed', 'total_products', 'execution_time'
     */
    public function syncAll(): array;

    /**
     * Sync a single product by SKU
     *
     * @param string $sku
     * @return bool True if product was found in ERP and processed successfully
     */
    public function syncBySku(string $sku): bool;

    /**
     * Get products from ERP with optional pagination
     *
     * @param int $limit Maximum products to return (0 = no limit)
     * @param int $offset Starting position
     * @return array Array of ERP product data
     */
    public function getErpProducts(int $limit = 0, int $offset = 0): array;

    /**
     * Get total count of products in ERP
     *
     * @return int Total product count
     */
    public function getErpProductCount(): int;
}
