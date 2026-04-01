<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Api;

/**
 * Suggested Cart API Interface
 *
 * Provides REST endpoints for product suggestions
 */
interface SuggestedCartInterface
{
    /**
     * Get suggested cart for current customer
     *
     * @return array
     */
    public function getSuggestedCart(): array;

    /**
     * Get suggested cart for specific customer
     *
     * @param int $customerId
     * @return array
     */
    public function getSuggestedCartForCustomer(int $customerId): array;

    /**
     * Get reorder suggestions for current customer
     *
     * @param int $limit
     * @return array
     */
    public function getReorderSuggestions(int $limit = 10): array;

    /**
     * Get cross-sell suggestions for current customer
     *
     * @param int $limit
     * @return array
     */
    public function getCrossSellSuggestions(int $limit = 8): array;
}
