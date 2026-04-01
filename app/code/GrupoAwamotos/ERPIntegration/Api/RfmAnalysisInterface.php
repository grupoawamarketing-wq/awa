<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Api;

/**
 * RFM Analysis API Interface
 *
 * Provides REST endpoints for RFM customer analysis
 */
interface RfmAnalysisInterface
{
    /**
     * Get RFM analysis for current customer
     *
     * @return array
     */
    public function getCurrentCustomerRfm(): array;

    /**
     * Get RFM analysis for specific customer
     *
     * @param int $customerId
     * @return array
     */
    public function getCustomerRfm(int $customerId): array;

    /**
     * Get RFM segment statistics (admin only)
     *
     * @return array
     */
    public function getSegmentStats(): array;

    /**
     * Get at-risk customers (admin only)
     *
     * @param int $limit
     * @return array
     */
    public function getAtRiskCustomers(int $limit = 50): array;
}
