<?php

declare(strict_types=1);

namespace GrupoAwamotos\SmartSuggestions\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Sales Forecast Resource Model
 */
class SalesForecast extends AbstractDb
{
    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init('smart_suggestions_sales_forecast', 'forecast_id');
    }

    /**
     * Get forecasts for date range
     */
    public function getForecasts(string $startDate, string $endDate, string $type = 'daily'): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable())
            ->where('forecast_date >= ?', $startDate)
            ->where('forecast_date <= ?', $endDate)
            ->where('forecast_type = ?', $type)
            ->order('forecast_date ASC');

        return $connection->fetchAll($select);
    }

    /**
     * Update actual values from ERP data
     */
    public function updateActuals(string $date, float $revenue, int $orders, string $type = 'daily'): int
    {
        return $this->getConnection()->update(
            $this->getMainTable(),
            [
                'actual_revenue' => $revenue,
                'actual_orders' => $orders,
                'updated_at' => date('Y-m-d H:i:s')
            ],
            [
                'forecast_date = ?' => $date,
                'forecast_type = ?' => $type
            ]
        );
    }

    /**
     * Bulk insert forecasts
     */
    public function bulkInsert(array $forecasts): int
    {
        if (empty($forecasts)) {
            return 0;
        }

        $connection = $this->getConnection();

        // Use insertOnDuplicate to handle updates for existing dates
        $updateColumns = ['predicted_revenue', 'predicted_orders', 'confidence_level', 'model_params', 'updated_at'];

        return $connection->insertOnDuplicate(
            $this->getMainTable(),
            $forecasts,
            $updateColumns
        );
    }

    /**
     * Get forecast accuracy statistics
     */
    public function getAccuracyStats(string $type = 'daily', int $days = 30): array
    {
        $connection = $this->getConnection();
        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        $select = $connection->select()
            ->from($this->getMainTable(), [
                'total_forecasts' => 'COUNT(*)',
                'avg_predicted' => 'AVG(predicted_revenue)',
                'avg_actual' => 'AVG(actual_revenue)',
                'avg_variance' => 'AVG(actual_revenue - predicted_revenue)',
                'avg_accuracy' => 'AVG(100 - ABS((actual_revenue - predicted_revenue) / NULLIF(actual_revenue, 0) * 100))',
                'min_accuracy' => 'MIN(100 - ABS((actual_revenue - predicted_revenue) / NULLIF(actual_revenue, 0) * 100))',
                'max_accuracy' => 'MAX(100 - ABS((actual_revenue - predicted_revenue) / NULLIF(actual_revenue, 0) * 100))'
            ])
            ->where('forecast_type = ?', $type)
            ->where('forecast_date >= ?', $startDate)
            ->where('actual_revenue IS NOT NULL');

        $result = $connection->fetchRow($select);

        return $result ?: [
            'total_forecasts' => 0,
            'avg_predicted' => 0,
            'avg_actual' => 0,
            'avg_variance' => 0,
            'avg_accuracy' => 0,
            'min_accuracy' => 0,
            'max_accuracy' => 0
        ];
    }

    /**
     * Get comparison data for chart (predicted vs actual)
     */
    public function getComparisonData(string $startDate, string $endDate, string $type = 'daily'): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), [
                'date' => 'forecast_date',
                'predicted' => 'predicted_revenue',
                'actual' => 'actual_revenue',
                'predicted_orders' => 'predicted_orders',
                'actual_orders' => 'actual_orders',
                'confidence' => 'confidence_level'
            ])
            ->where('forecast_date >= ?', $startDate)
            ->where('forecast_date <= ?', $endDate)
            ->where('forecast_type = ?', $type)
            ->order('forecast_date ASC');

        return $connection->fetchAll($select);
    }

    /**
     * Delete old forecasts
     */
    public function cleanupOldForecasts(int $daysToKeep = 365): int
    {
        $cutoffDate = date('Y-m-d', strtotime("-{$daysToKeep} days"));

        return $this->getConnection()->delete(
            $this->getMainTable(),
            ['forecast_date < ?' => $cutoffDate]
        );
    }
}
