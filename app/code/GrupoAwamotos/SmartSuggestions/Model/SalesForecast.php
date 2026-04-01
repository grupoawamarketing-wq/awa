<?php

declare(strict_types=1);

namespace GrupoAwamotos\SmartSuggestions\Model;

use Magento\Framework\Model\AbstractModel;
use GrupoAwamotos\SmartSuggestions\Model\ResourceModel\SalesForecast as SalesForecastResource;

/**
 * Sales Forecast Model
 *
 * Stores historical and projected sales data for forecasting
 */
class SalesForecast extends AbstractModel
{
    /**
     * Forecast type constants
     */
    public const TYPE_DAILY = 'daily';
    public const TYPE_WEEKLY = 'weekly';
    public const TYPE_MONTHLY = 'monthly';

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(SalesForecastResource::class);
    }

    /**
     * Get forecast date
     */
    public function getForecastDate(): string
    {
        return (string) $this->getData('forecast_date');
    }

    /**
     * Set forecast date
     */
    public function setForecastDate(string $date): self
    {
        return $this->setData('forecast_date', $date);
    }

    /**
     * Get forecast type
     */
    public function getForecastType(): string
    {
        return (string) ($this->getData('forecast_type') ?? self::TYPE_DAILY);
    }

    /**
     * Set forecast type
     */
    public function setForecastType(string $type): self
    {
        return $this->setData('forecast_type', $type);
    }

    /**
     * Get predicted revenue
     */
    public function getPredictedRevenue(): float
    {
        return (float) $this->getData('predicted_revenue');
    }

    /**
     * Set predicted revenue
     */
    public function setPredictedRevenue(float $revenue): self
    {
        return $this->setData('predicted_revenue', $revenue);
    }

    /**
     * Get actual revenue
     */
    public function getActualRevenue(): ?float
    {
        $value = $this->getData('actual_revenue');
        return $value !== null ? (float) $value : null;
    }

    /**
     * Set actual revenue
     */
    public function setActualRevenue(?float $revenue): self
    {
        return $this->setData('actual_revenue', $revenue);
    }

    /**
     * Get predicted orders count
     */
    public function getPredictedOrders(): int
    {
        return (int) $this->getData('predicted_orders');
    }

    /**
     * Set predicted orders count
     */
    public function setPredictedOrders(int $orders): self
    {
        return $this->setData('predicted_orders', $orders);
    }

    /**
     * Get actual orders count
     */
    public function getActualOrders(): ?int
    {
        $value = $this->getData('actual_orders');
        return $value !== null ? (int) $value : null;
    }

    /**
     * Set actual orders count
     */
    public function setActualOrders(?int $orders): self
    {
        return $this->setData('actual_orders', $orders);
    }

    /**
     * Get confidence level (0-100)
     */
    public function getConfidenceLevel(): float
    {
        return (float) ($this->getData('confidence_level') ?? 0);
    }

    /**
     * Set confidence level
     */
    public function setConfidenceLevel(float $level): self
    {
        return $this->setData('confidence_level', min(100, max(0, $level)));
    }

    /**
     * Get model parameters as array
     */
    public function getModelParams(): array
    {
        $params = $this->getData('model_params');
        if (is_string($params)) {
            return json_decode($params, true) ?? [];
        }
        return is_array($params) ? $params : [];
    }

    /**
     * Set model parameters
     */
    public function setModelParams(array $params): self
    {
        return $this->setData('model_params', json_encode($params));
    }

    /**
     * Calculate forecast accuracy (MAPE - Mean Absolute Percentage Error)
     */
    public function getAccuracy(): ?float
    {
        $actual = $this->getActualRevenue();
        $predicted = $this->getPredictedRevenue();

        if ($actual === null || $actual == 0) {
            return null;
        }

        $error = abs($actual - $predicted) / $actual * 100;
        return round(100 - $error, 2);
    }

    /**
     * Calculate revenue variance
     */
    public function getRevenueVariance(): ?float
    {
        $actual = $this->getActualRevenue();
        $predicted = $this->getPredictedRevenue();

        if ($actual === null) {
            return null;
        }

        return round($actual - $predicted, 2);
    }

    /**
     * Get variance as percentage
     */
    public function getVariancePercentage(): ?float
    {
        $predicted = $this->getPredictedRevenue();
        $variance = $this->getRevenueVariance();

        if ($variance === null || $predicted == 0) {
            return null;
        }

        return round(($variance / $predicted) * 100, 2);
    }

    /**
     * Check if this is a future forecast
     */
    public function isFuture(): bool
    {
        return strtotime($this->getForecastDate()) > time();
    }

    /**
     * Check if actual data is available
     */
    public function hasActualData(): bool
    {
        return $this->getActualRevenue() !== null;
    }

    /**
     * Get available forecast types
     */
    public static function getAvailableTypes(): array
    {
        return [
            self::TYPE_DAILY => __('Diário'),
            self::TYPE_WEEKLY => __('Semanal'),
            self::TYPE_MONTHLY => __('Mensal')
        ];
    }
}
