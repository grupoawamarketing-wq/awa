<?php

declare(strict_types=1);

namespace GrupoAwamotos\SmartSuggestions\Cron;

use GrupoAwamotos\SmartSuggestions\Api\ForecastServiceInterface;
use GrupoAwamotos\SmartSuggestions\Helper\Config;
use GrupoAwamotos\SmartSuggestions\Model\ResourceModel\SalesForecast as SalesForecastResource;
use Psr\Log\LoggerInterface;

/**
 * Cron job to update sales forecasts
 *
 * Runs daily at 23:00 to update forecast data for dashboard
 * Stores historical projections for accuracy analysis
 */
class UpdateForecasts
{
    private Config $config;
    private LoggerInterface $logger;
    private ForecastServiceInterface $forecastService;
    private SalesForecastResource $forecastResource;

    public function __construct(
        Config $config,
        LoggerInterface $logger,
        ForecastServiceInterface $forecastService,
        SalesForecastResource $forecastResource
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->forecastService = $forecastService;
        $this->forecastResource = $forecastResource;
    }

    /**
     * Execute forecast update cron
     */
    public function execute(): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        try {
            $startTime = microtime(true);

            // Update daily forecast data
            $this->updateDailyForecast();

            // Update monthly forecast data
            $this->updateMonthlyForecast();

            // Calculate forecast accuracy (for historical projections)
            $this->calculateForecastAccuracy();

            $duration = round(microtime(true) - $startTime, 2);

            $this->logger->info(sprintf(
                'SmartSuggestions: Forecast update completed in %ss',
                $duration
            ));
        } catch (\Exception $e) {
            $this->logger->error('SmartSuggestions: Forecast update failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Update daily forecast data
     */
    private function updateDailyForecast(): void
    {
        try {
            // Get daily sales trend (last 30 days + projections)
            $dailyData = $this->forecastService->getDailySalesTrend(30);

            if (empty($dailyData)) {
                $this->logger->warning('SmartSuggestions: No daily sales data available');
                return;
            }

            $connection = $this->forecastResource->getConnection();
            $tableName = $this->forecastResource->getMainTable();
            $now = date('Y-m-d H:i:s');

            // Prepare batch insert/update
            $records = [];
            foreach ($dailyData as $day) {
                $records[] = [
                    'period_type' => 'daily',
                    'period_date' => $day['date'],
                    'actual_sales' => $day['total'] ?? null,
                    'orders_count' => $day['orders'] ?? null,
                    'avg_ticket' => ($day['orders'] ?? 0) > 0
                        ? ($day['total'] ?? 0) / $day['orders']
                        : null,
                    'calculated_at' => $now
                ];
            }

            // Add projections for next 7 days
            $projections = $this->forecastService->projectDailyForward(7);
            foreach ($projections as $projection) {
                $records[] = [
                    'period_type' => 'daily',
                    'period_date' => $projection['date'],
                    'projected_sales' => $projection['projected'] ?? null,
                    'projection_low' => $projection['low'] ?? null,
                    'projection_high' => $projection['high'] ?? null,
                    'confidence' => $projection['confidence'] ?? 0.85,
                    'calculated_at' => $now
                ];
            }

            // Upsert records
            foreach ($records as $record) {
                $connection->insertOnDuplicate(
                    $tableName,
                    $record,
                    array_keys($record)
                );
            }

            $this->logger->info(sprintf(
                'SmartSuggestions: Updated %d daily forecast records',
                count($records)
            ));
        } catch (\Exception $e) {
            $this->logger->error('SmartSuggestions: Daily forecast update failed', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Update monthly forecast data
     */
    private function updateMonthlyForecast(): void
    {
        try {
            // Get current month projection
            $currentProjection = $this->forecastService->projectMonthClosing();

            // Get next month projection
            $nextMonthProjection = $this->forecastService->projectNextMonth();

            $connection = $this->forecastResource->getConnection();
            $tableName = $this->forecastResource->getMainTable();
            $now = date('Y-m-d H:i:s');

            // Current month
            if (!empty($currentProjection)) {
                $currentMonth = date('Y-m-01');
                $monthlyGoal = $this->config->getMonthlyGoal();

                $connection->insertOnDuplicate(
                    $tableName,
                    [
                        'period_type' => 'monthly',
                        'period_date' => $currentMonth,
                        'actual_sales' => $currentProjection['actual_sales'] ?? null,
                        'projected_sales' => $currentProjection['projection']['realistic'] ?? null,
                        'projection_low' => $currentProjection['projection']['pessimistic'] ?? null,
                        'projection_high' => $currentProjection['projection']['optimistic'] ?? null,
                        'target' => $monthlyGoal,
                        'orders_count' => null, // Would need separate query
                        'avg_ticket' => null,
                        'trend_factor' => $currentProjection['trend']['percentage'] ?? null,
                        'confidence' => 0.85,
                        'calculated_at' => $now
                    ],
                    ['actual_sales', 'projected_sales', 'projection_low', 'projection_high', 'trend_factor', 'calculated_at']
                );
            }

            // Next month
            if (!empty($nextMonthProjection)) {
                $nextMonth = date('Y-m-01', strtotime('+1 month'));

                $connection->insertOnDuplicate(
                    $tableName,
                    [
                        'period_type' => 'monthly',
                        'period_date' => $nextMonth,
                        'projected_sales' => $nextMonthProjection['projection'] ?? null,
                        'projection_low' => $nextMonthProjection['range']['min'] ?? null,
                        'projection_high' => $nextMonthProjection['range']['max'] ?? null,
                        'seasonal_index' => $nextMonthProjection['seasonal_factor'] ?? null,
                        'trend_factor' => $nextMonthProjection['growth_factor'] ?? null,
                        'confidence' => 0.75, // Lower confidence for next month
                        'calculated_at' => $now
                    ],
                    ['projected_sales', 'projection_low', 'projection_high', 'seasonal_index', 'trend_factor', 'calculated_at']
                );
            }

            $this->logger->info('SmartSuggestions: Updated monthly forecast records');
        } catch (\Exception $e) {
            $this->logger->error('SmartSuggestions: Monthly forecast update failed', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Calculate forecast accuracy for past projections
     */
    private function calculateForecastAccuracy(): void
    {
        try {
            $connection = $this->forecastResource->getConnection();
            $tableName = $this->forecastResource->getMainTable();

            // Find records where we have both projection and actual values
            $select = $connection->select()
                ->from($tableName)
                ->where('projected_sales IS NOT NULL')
                ->where('actual_sales IS NOT NULL')
                ->where('period_date < ?', date('Y-m-d'))
                ->order('period_date DESC')
                ->limit(60); // Last ~2 months of daily data

            $records = $connection->fetchAll($select);

            if (empty($records)) {
                return;
            }

            $totalError = 0;
            $count = 0;

            foreach ($records as $record) {
                $projected = (float) $record['projected_sales'];
                $actual = (float) $record['actual_sales'];

                if ($actual > 0 && $projected > 0) {
                    $error = abs($actual - $projected) / $actual;
                    $totalError += $error;
                    $count++;
                }
            }

            if ($count > 0) {
                $mape = ($totalError / $count) * 100; // Mean Absolute Percentage Error
                $accuracy = max(0, 100 - $mape);

                $this->logger->info(sprintf(
                    'SmartSuggestions: Forecast accuracy: %.1f%% (MAPE: %.1f%%) based on %d records',
                    $accuracy,
                    $mape,
                    $count
                ));
            }
        } catch (\Exception $e) {
            $this->logger->error('SmartSuggestions: Accuracy calculation failed', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
