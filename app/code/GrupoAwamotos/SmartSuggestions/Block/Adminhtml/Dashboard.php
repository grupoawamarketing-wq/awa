<?php

declare(strict_types=1);

namespace GrupoAwamotos\SmartSuggestions\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use GrupoAwamotos\SmartSuggestions\Api\RfmCalculatorInterface;
use GrupoAwamotos\SmartSuggestions\Api\ForecastServiceInterface;

/**
 * Smart Suggestions Dashboard Block
 */
class Dashboard extends Template
{
    protected $_template = 'GrupoAwamotos_SmartSuggestions::dashboard.phtml';

    private RfmCalculatorInterface $rfmCalculator;
    private ForecastServiceInterface $forecastService;

    public function __construct(
        Context $context,
        RfmCalculatorInterface $rfmCalculator,
        ForecastServiceInterface $forecastService,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->rfmCalculator = $rfmCalculator;
        $this->forecastService = $forecastService;
    }

    /**
     * Get RFM segment statistics
     */
    public function getRfmSegments(): array
    {
        return $this->rfmCalculator->getSegmentStatistics();
    }

    /**
     * Get RFM segment data for chart (JSON)
     */
    public function getRfmChartData(): string
    {
        $segments = $this->getRfmSegments();

        $chartData = [
            'labels' => [],
            'series' => [],
            'colors' => []
        ];

        foreach ($segments as $segment) {
            $chartData['labels'][] = $segment['segment'];
            $chartData['series'][] = $segment['count'];
            $chartData['colors'][] = $segment['color'];
        }

        return json_encode($chartData);
    }

    /**
     * Get month closing projection
     */
    public function getMonthProjection(): array
    {
        return $this->forecastService->projectMonthClosing();
    }

    /**
     * Get next month projection
     */
    public function getNextMonthProjection(): array
    {
        return $this->forecastService->projectNextMonth();
    }

    /**
     * Get daily sales trend for chart
     */
    public function getSalesTrendData(): string
    {
        $trend = $this->forecastService->getDailySalesTrend(30);

        return json_encode([
            'dates' => $trend['dates'] ?? [],
            'sales' => $trend['sales'] ?? [],
            'moving_avg' => $trend['moving_avg'] ?? [],
            'forecast' => $trend['forecast'] ?? []
        ]);
    }

    /**
     * Get monthly comparison data
     */
    public function getMonthlyComparisonData(): string
    {
        $comparison = $this->forecastService->getMonthlyComparison();

        $data = [
            'labels' => [],
            'sales' => [],
            'orders' => []
        ];

        foreach ($comparison as $month) {
            $data['labels'][] = $month['month_name'];
            $data['sales'][] = round($month['total'], 2);
            $data['orders'][] = $month['orders'];
        }

        return json_encode($data);
    }

    /**
     * Get top customers by RFM
     */
    public function getTopCustomers(int $limit = 10): array
    {
        $customers = $this->rfmCalculator->calculateAll();
        return array_slice($customers, 0, $limit);
    }

    /**
     * Get at-risk customers
     */
    public function getAtRiskCustomers(int $limit = 10): array
    {
        return $this->rfmCalculator->getCustomersBySegment('At Risk', $limit);
    }

    /**
     * Get customers that can't lose
     */
    public function getCantLoseCustomers(int $limit = 10): array
    {
        return $this->rfmCalculator->getCustomersBySegment("Can't Lose", $limit);
    }

    /**
     * Format price
     */
    public function formatPrice(float $price): string
    {
        return 'R$ ' . number_format($price, 2, ',', '.');
    }

    /**
     * Format number
     */
    public function formatNumber(int $number): string
    {
        return number_format($number, 0, ',', '.');
    }

    /**
     * Get RFM analysis URL
     */
    public function getRfmUrl(): string
    {
        return $this->getUrl('smartsuggestions/rfm/index');
    }

    /**
     * Get suggestions URL
     */
    public function getSuggestionsUrl(): string
    {
        return $this->getUrl('smartsuggestions/suggestions/index');
    }

    /**
     * Get forecast URL
     */
    public function getForecastUrl(): string
    {
        return $this->getUrl('smartsuggestions/forecast/index');
    }
}
