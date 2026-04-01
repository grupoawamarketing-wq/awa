<?php

declare(strict_types=1);

namespace GrupoAwamotos\SmartSuggestions\Api;

/**
 * RFM Calculator Interface
 *
 * Calculates Recency, Frequency, Monetary scores for customer segmentation
 */
interface RfmCalculatorInterface
{
    /**
     * Calculate RFM scores for all customers
     *
     * @return array
     */
    public function calculateAll(): array;

    /**
     * Calculate RFM for a specific customer
     *
     * @param int $customerId
     * @return array|null
     */
    public function calculateForCustomer(int $customerId): ?array;

    /**
     * Get segment statistics
     *
     * @return array
     */
    public function getSegmentStatistics(): array;

    /**
     * Get customers by segment
     *
     * @param string $segment
     * @param int $limit
     * @return array
     */
    public function getCustomersBySegment(string $segment, int $limit = 100): array;

    /**
     * Get marketing recommendations for a segment
     *
     * @param string $segment
     * @return array
     */
    public function getRecommendations(string $segment): array;
}
