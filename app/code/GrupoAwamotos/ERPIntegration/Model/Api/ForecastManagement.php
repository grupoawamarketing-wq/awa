<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Model\Api;

use GrupoAwamotos\ERPIntegration\Api\ForecastInterface;
use GrupoAwamotos\ERPIntegration\Model\Forecast\SalesProjection;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use Magento\Framework\Exception\LocalizedException;

/**
 * Sales Forecast API Implementation
 */
class ForecastManagement implements ForecastInterface
{
    private SalesProjection $salesProjection;
    private Helper $helper;

    public function __construct(
        SalesProjection $salesProjection,
        Helper $helper
    ) {
        $this->salesProjection = $salesProjection;
        $this->helper = $helper;
    }

    /**
     * @inheritdoc
     */
    public function getCurrentMonthProjection(): array
    {
        $this->validateEnabled();

        return $this->salesProjection->getCurrentMonthProjection();
    }

    /**
     * @inheritdoc
     */
    public function getNextMonthProjection(): array
    {
        $this->validateEnabled();

        return $this->salesProjection->getNextMonthProjection();
    }

    /**
     * @inheritdoc
     */
    public function getDailySalesChart(int $daysBack = 30, int $daysForward = 7): array
    {
        $this->validateEnabled();

        // Validate parameters
        $daysBack = min(max($daysBack, 7), 90);
        $daysForward = min(max($daysForward, 1), 30);

        return $this->salesProjection->getDailySalesChart($daysBack, $daysForward);
    }

    /**
     * @inheritdoc
     */
    public function getMonthlyComparison(int $monthsBack = 12): array
    {
        $this->validateEnabled();

        // Validate parameters
        $monthsBack = min(max($monthsBack, 3), 24);

        return $this->salesProjection->getMonthlyComparison($monthsBack);
    }

    /**
     * Validate feature is enabled
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function validateEnabled(): void
    {
        if (!$this->helper->isEnabled() || !$this->helper->isForecastEnabled()) {
            throw new LocalizedException(__('Sales Forecast feature is disabled'));
        }
    }
}
