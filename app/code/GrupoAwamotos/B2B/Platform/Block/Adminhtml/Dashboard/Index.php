<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Platform\Block\Adminhtml\Dashboard;

use GrupoAwamotos\B2B\Platform\Dashboard\DashboardFilter;
use GrupoAwamotos\B2B\Platform\Dashboard\ExecutiveDashboardService;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

class Index extends Template
{
    /** @var array<string, mixed>|null */
    private ?array $dashboardCache = null;

    public function __construct(
        Context $context,
        private readonly ExecutiveDashboardService $dashboardService,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return array<string, mixed>
     */
    public function getDashboard(): array
    {
        if ($this->dashboardCache === null) {
            $this->dashboardCache = $this->dashboardService->buildDashboard($this->getFilter());
        }

        return $this->dashboardCache;
    }

    public function getFilter(): DashboardFilter
    {
        return DashboardFilter::fromRequestParams(
            $this->getRequest()->getParam('date_from'),
            $this->getRequest()->getParam('date_to')
        );
    }

    /**
     * @return array{date_from: string, date_to: string}
     */
    public function getFilterArray(): array
    {
        return $this->getFilter()->toArray();
    }

    public function getRefreshUrl(): string
    {
        return $this->getUrl('*/*/*', ['_current' => true]);
    }

    public function getExportUrl(): string
    {
        return $this->getUrl('awa_b2b/dashboard/exportcsv', [
            'date_from' => $this->getFilter()->getDateFrom(),
            'date_to' => $this->getFilter()->getDateTo(),
        ]);
    }

    public function canExport(): bool
    {
        return $this->_authorization->isAllowed('GrupoAwamotos_B2B::platform_dashboard_export')
            || $this->_authorization->isAllowed('GrupoAwamotos_B2B::commercial_reports_export');
    }

    /**
     * @param array<string, mixed>|null $kpi
     */
    public function formatKpi(?array $kpi): string
    {
        if ($kpi === null || empty($kpi['available'])) {
            return (string) __('Fonte indisponível');
        }

        return (string) ($kpi['formatted'] ?? $kpi['value'] ?? '0');
    }

    /**
     * @param array<string, mixed>|null $kpi
     */
    public function kpiBadgeClass(?array $kpi, string $default = 'neutral'): string
    {
        if ($kpi === null || empty($kpi['available'])) {
            return 'awa-b2b-status-badge--neutral';
        }

        return match ($default) {
            'warning' => 'awa-b2b-status-badge--warning',
            'danger' => 'awa-b2b-status-badge--danger',
            'success' => 'awa-b2b-status-badge--success',
            'info' => 'awa-b2b-status-badge--info',
            default => 'awa-b2b-status-badge--neutral',
        };
    }
}
