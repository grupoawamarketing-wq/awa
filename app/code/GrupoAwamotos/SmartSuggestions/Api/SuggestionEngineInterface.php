<?php

declare(strict_types=1);

namespace GrupoAwamotos\SmartSuggestions\Api;

/**
 * Suggestion Engine Interface
 *
 * Generates personalized product/cart suggestions
 */
interface SuggestionEngineInterface
{
    /**
     * Generate cart suggestion for a customer
     *
     * @param int $customerId
     * @param array|null $prefetchedInfo
     * @return array
     */
    public function generateCartSuggestion(int $customerId, ?array $prefetchedInfo = null): array;

    /**
     * Get repurchase suggestions based on customer cycle
     *
     * @param int $customerId
     * @param int $limit
     * @return array
     */
    public function getRepurchaseSuggestions(int $customerId, int $limit = 10): array;

    /**
     * Get cross-sell suggestions
     *
     * @param int $customerId
     * @param int $limit
     * @return array
     */
    public function getCrossSellingProducts(int $customerId, int $limit = 5): array;

    /**
     * Get products from similar customers (collaborative filtering)
     *
     * @param int $customerId
     * @param int $limit
     * @return array
     */
    public function getFromSimilarCustomers(int $customerId, int $limit = 5): array;

    /**
     * Get top opportunities (customers most likely to purchase)
     *
     * @param int $limit
     * @return array
     */
    public function getTopOpportunities(int $limit = 10): array;

    /**
     * Generate cart suggestions for multiple customers in batch
     *
     * @param int[] $customerIds
     * @return array customerId => suggestion
     */
    public function generateBatchCartSuggestions(array $customerIds): array;
}
