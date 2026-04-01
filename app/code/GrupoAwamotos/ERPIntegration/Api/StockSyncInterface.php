<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Api;

interface StockSyncInterface
{
    /**
     * Get stock data for a specific SKU
     *
     * @param string $sku
     * @return array|null Array with 'qty', 'cost', 'date' or null if not found
     */
    public function getStockBySku(string $sku): ?array;

    /**
     * Sync all stock from ERP to Magento
     *
     * @return array Result with 'updated', 'skipped', 'errors', 'not_found', 'unchanged', 'total_erp_records', 'execution_time'
     */
    public function syncAll(): array;

    /**
     * Sync stock for a specific SKU
     *
     * @param string $sku
     * @return bool True if synced successfully
     */
    public function syncBySku(string $sku): bool;

    /**
     * Invalidate stock cache for a specific SKU
     *
     * @param string $sku
     * @return void
     */
    public function invalidateCache(string $sku): void;

    /**
     * Invalidate all stock cache
     *
     * @return void
     */
    public function invalidateAllCache(): void;
}
