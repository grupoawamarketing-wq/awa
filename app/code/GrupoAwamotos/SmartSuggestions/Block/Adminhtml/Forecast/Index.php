<?php

declare(strict_types=1);

namespace GrupoAwamotos\SmartSuggestions\Block\Adminhtml\Forecast;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use GrupoAwamotos\SmartSuggestions\Api\ForecastServiceInterface;

/**
 * Forecast Index Block
 */
class Index extends Template
{
    protected $_template = 'GrupoAwamotos_SmartSuggestions::forecast/index.phtml';

    private ForecastServiceInterface $forecastService;

    public function __construct(
        Context $context,
        ForecastServiceInterface $forecastService,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->forecastService = $forecastService;
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
     * Get daily sales trend
     */
    public function getDailySalesTrend(): array
    {
        return $this->forecastService->getDailySalesTrend(60);
    }

    /**
     * Get monthly comparison
     */
    public function getMonthlyComparison(): array
    {
        return $this->forecastService->getMonthlyComparison();
    }

    /**
     * Get chart data as JSON
     */
    public function getSalesTrendChartData(): string
    {
        $trend = $this->getDailySalesTrend();
        return json_encode([
            'dates' => $trend['dates'] ?? [],
            'sales' => $trend['sales'] ?? [],
            'moving_avg' => $trend['moving_avg'] ?? [],
            'forecast' => $trend['forecast'] ?? []
        ]);
    }

    /**
     * Get monthly chart data as JSON
     */
    public function getMonthlyChartData(): string
    {
        $comparison = $this->getMonthlyComparison();
        return json_encode([
            'labels' => array_column($comparison, 'month_name'),
            'sales' => array_column($comparison, 'total'),
            'orders' => array_column($comparison, 'orders'),
            'ticket' => array_column($comparison, 'ticket_medio')
        ]);
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
    public function formatNumber($number): string
    {
        return number_format((int)$number, 0, ',', '.');
    }

    /**
     * Get current month name
     */
    public function getCurrentMonthName(): string
    {
        $months = [
            1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março',
            4 => 'Abril', 5 => 'Maio', 6 => 'Junho',
            7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro',
            10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
        ];
        return $months[(int)date('n')] . ' ' . date('Y');
    }
}
