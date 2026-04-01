<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Api;

/**
 * Sales Forecast API Interface
 *
 * Provides REST endpoints for sales projections and forecasting
 */
interface ForecastInterface
{
    /**
     * Get current month sales projection
     *
     * @return array
     */
    public function getCurrentMonthProjection(): array;

    /**
     * Get next month sales projection
     *
     * @return array
     */
    public function getNextMonthProjection(): array;

    /**
     * Get daily sales chart data
     *
     * @param int $daysBack
     * @param int $daysForward
     * @return array
     */
    public function getDailySalesChart(int $daysBack = 30, int $daysForward = 7): array;

    /**
     * Get monthly comparison data
     *
     * @param int $monthsBack
     * @return array
     */
    public function getMonthlyComparison(int $monthsBack = 12): array;
}
