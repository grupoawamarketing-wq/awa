<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Api;

interface CategorySyncInterface
{
    /**
     * Sync all categories from ERP to Magento
     *
     * @return array Result with 'created', 'updated', 'deactivated', 'skipped', 'errors'
     */
    public function syncAll(): array;

    /**
     * Get categories from ERP
     *
     * @return array Array of ERP category data
     */
    public function getErpCategories(): array;

    /**
     * Get total count of active categories in ERP
     *
     * @return int
     */
    public function getErpCategoryCount(): int;

    /**
     * Get or create the ERP root category in Magento
     *
     * @return int The Magento category ID of the ERP root
     */
    public function getOrCreateErpRootCategory(): int;
}
