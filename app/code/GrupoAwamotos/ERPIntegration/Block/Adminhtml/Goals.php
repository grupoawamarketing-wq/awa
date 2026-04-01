<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use GrupoAwamotos\ERPIntegration\Api\ConnectionInterface;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use GrupoAwamotos\ERPIntegration\Model\Goals\Manager as GoalsManager;
use GrupoAwamotos\ERPIntegration\Model\Forecast\SalesProjection;

/**
 * Goals Dashboard Block
 */
class Goals extends Template
{
    protected $_template = 'GrupoAwamotos_ERPIntegration::goals.phtml';

    private ConnectionInterface $connection;
    private Helper $helper;
    private GoalsManager $goalsManager;
    private SalesProjection $salesProjection;

    public function __construct(
        Context $context,
        ConnectionInterface $connection,
        Helper $helper,
        GoalsManager $goalsManager,
        SalesProjection $salesProjection,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->connection = $connection;
        $this->helper = $helper;
        $this->goalsManager = $goalsManager;
        $this->salesProjection = $salesProjection;
    }

    /**
     * Check if ERP is enabled
     */
    public function isEnabled(): bool
    {
        return $this->helper->isEnabled();
    }

    /**
     * Get current month data
     */
    public function getCurrentMonthData(): array
    {
        return $this->salesProjection->getCurrentMonthProjection();
    }

    /**
     * Get next month projection
     */
    public function getNextMonthProjection(): array
    {
        return $this->salesProjection->getNextMonthProjection();
    }

    /**
     * Get goals for multiple months
     */
    public function getMonthlyGoals(int $monthsBack = 3, int $monthsForward = 6): array
    {
        return $this->goalsManager->getMonthlyGoalsWithActuals($monthsBack, $monthsForward);
    }

    /**
     * Get available filters
     */
    public function getAvailableFilters(): array
    {
        return $this->goalsManager->getAvailableFilters();
    }

    /**
     * Get yearly summary
     */
    public function getYearlySummary(): array
    {
        return $this->goalsManager->getYearlySummary();
    }

    /**
     * Get quarterly data
     */
    public function getQuarterlyData(): array
    {
        return $this->goalsManager->getQuarterlyData();
    }

    /**
     * Get save URL
     */
    public function getSaveUrl(): string
    {
        return $this->getUrl('erpintegration/goals/save');
    }

    /**
     * Get data URL (AJAX)
     */
    public function getDataUrl(): string
    {
        return $this->getUrl('erpintegration/goals/data');
    }

    /**
     * Format price in BRL
     */
    public function formatPrice(float $value): string
    {
        return 'R$ ' . number_format($value, 2, ',', '.');
    }

    /**
     * Format percentage
     */
    public function formatPercentage(float $value, bool $withSign = true): string
    {
        $sign = ($withSign && $value >= 0) ? '+' : '';
        return $sign . number_format($value, 1, ',', '.') . '%';
    }

    /**
     * Get month name in Portuguese
     */
    public function getMonthName(int $month): string
    {
        $months = [
            1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
            5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
            9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
        ];
        return $months[$month] ?? '';
    }

    /**
     * Get short month name
     */
    public function getShortMonthName(int $month): string
    {
        $months = [
            1 => 'Jan', 2 => 'Fev', 3 => 'Mar', 4 => 'Abr',
            5 => 'Mai', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago',
            9 => 'Set', 10 => 'Out', 11 => 'Nov', 12 => 'Dez',
        ];
        return $months[$month] ?? '';
    }

    /**
     * Get alert class based on progress
     */
    public function getProgressClass(float $progress): string
    {
        if ($progress >= 100) {
            return 'success';
        }
        if ($progress >= 80) {
            return 'warning';
        }
        if ($progress >= 60) {
            return 'info';
        }
        return 'danger';
    }

    /**
     * Get goals chart data as JSON
     */
    public function getGoalsChartJson(): string
    {
        $goals = $this->getMonthlyGoals(6, 6);
        return json_encode($goals);
    }

    /**
     * Get quarterly chart data as JSON
     */
    public function getQuarterlyChartJson(): string
    {
        return json_encode($this->getQuarterlyData());
    }

    /**
     * Get yearly chart data as JSON
     */
    public function getYearlyChartJson(): string
    {
        return json_encode($this->getYearlySummary());
    }
}
