<?php

declare(strict_types=1);

namespace GrupoAwamotos\SmartSuggestions\Api;

/**
 * Forecast Service Interface
 *
 * Provides sales projections and forecasts
 */
interface ForecastServiceInterface
{
    /**
     * Project current month closing
     *
     * @return array
     */
    public function projectMonthClosing(): array;

    /**
     * Project next month sales
     *
     * @return array
     */
    public function projectNextMonth(): array;

    /**
     * Get daily sales trend
     *
     * @param int $days Number of days to analyze
     * @return array
     */
    public function getDailySalesTrend(int $days = 30): array;

    /**
     * Get monthly comparison
     *
     * @return array
     */
    public function getMonthlyComparison(): array;
}
