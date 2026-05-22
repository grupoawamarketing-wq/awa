<?php

/**
 * ViewModel for Meta B2B Funnel Dashboard (Admin)
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\ViewModel\Adminhtml\Dashboard;

use GrupoAwamotos\B2B\Model\ResourceModel\Company\CollectionFactory as CompanyCollectionFactory;
use GrupoAwamotos\B2B\Model\ResourceModel\CreditLimit\CollectionFactory as CreditLimitCollectionFactory;
use GrupoAwamotos\B2B\Model\ResourceModel\CustomerApprovalLog as ApprovalLogResource;
use GrupoAwamotos\B2B\Model\ResourceModel\CustomerApprovalLog\CollectionFactory as LogCollectionFactory;
use GrupoAwamotos\B2B\Model\ResourceModel\QuoteRequest\CollectionFactory as QuoteCollectionFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class MetaFunnelViewModel implements ArgumentInterface
{
    private const META_EVENT_MAP = [
        'registered' => 'CompleteRegistration',
        'approved'   => 'SubmitApplication ✓',
        'rejected'   => 'SubmitApplication ✗',
        'suspended'  => '—',
    ];

    private const ALLOWED_PERIODS = [7, 30, 90, 0];

    private LogCollectionFactory $logCollectionFactory;
    private QuoteCollectionFactory $quoteCollectionFactory;
    private CompanyCollectionFactory $companyCollectionFactory;
    private CreditLimitCollectionFactory $creditLimitCollectionFactory;
    private ApprovalLogResource $approvalLogResource;
    private RequestInterface $request;

    public function __construct(
        LogCollectionFactory $logCollectionFactory,
        QuoteCollectionFactory $quoteCollectionFactory,
        CompanyCollectionFactory $companyCollectionFactory,
        CreditLimitCollectionFactory $creditLimitCollectionFactory,
        ApprovalLogResource $approvalLogResource,
        RequestInterface $request
    ) {
        $this->logCollectionFactory = $logCollectionFactory;
        $this->quoteCollectionFactory = $quoteCollectionFactory;
        $this->companyCollectionFactory = $companyCollectionFactory;
        $this->creditLimitCollectionFactory = $creditLimitCollectionFactory;
        $this->approvalLogResource = $approvalLogResource;
        $this->request = $request;
    }

    /**
     * Returns requested period in days. 0 = all time.
     */
    public function getPeriod(): int
    {
        $raw = (int) $this->request->getParam('period', 30);
        return in_array($raw, self::ALLOWED_PERIODS, true) ? $raw : 30;
    }

    /**
     * Returns a human-readable label for the current period.
     */
    public function getPeriodLabel(): string
    {
        return match ($this->getPeriod()) {
            7       => 'Últimos 7 dias',
            30      => 'Últimos 30 dias',
            90      => 'Últimos 90 dias',
            0       => 'Todo o período',
            default => 'Últimos 30 dias',
        };
    }

    /**
     * Returns the Meta CAPI event name for a given approval action.
     */
    public function getMetaEventLabel(string $action): string
    {
        return self::META_EVENT_MAP[$action] ?? '—';
    }

    /**
     * Returns the CSS badge class for a given approval action.
     */
    public function getActionBadgeClass(string $action): string
    {
        return match ($action) {
            'approved'   => 'b2b-meta-badge-success',
            'rejected'   => 'b2b-meta-badge-danger',
            'registered' => 'b2b-meta-badge-info',
            'suspended'  => 'b2b-meta-badge-warning',
            default      => 'b2b-meta-badge-neutral',
        };
    }

    /**
     * Formats a datetime string to Brazilian d/m/Y H:i format.
     */
    public function formatBrDate(string $datetime): string
    {
        try {
            return (new \DateTime($datetime))->format('d/m/Y H:i');
        } catch (\Exception) {
            return $datetime;
        }
    }

    /**
     * Returns the six KPI cards for the funnel header.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getKpis(): array
    {
        $period = $this->getPeriod();
        $fromDate = $this->buildFromDate($period);

        $registered = $this->countLogsByAction('registered', $fromDate);
        $approved   = $this->countLogsByAction('approved', $fromDate);
        $rejected   = $this->countLogsByAction('rejected', $fromDate);
        $pending    = max(0, $registered - $approved - $rejected);
        $total      = $approved + $rejected;
        $taxaAprov  = $total > 0 ? round(($approved / $total) * 100, 1) : 0.0;

        $companies = $this->companyCollectionFactory->create();
        $companies->addFieldToFilter('is_active', 1);
        $empresas = $companies->getSize();

        return [
            [
                'label'       => 'Cadastros B2B',
                'value'       => $registered,
                'meta_event'  => 'CompleteRegistration',
                'color'       => '#6366f1',
                'icon'        => '📋',
                'description' => 'Formulários B2B enviados',
            ],
            [
                'label'       => 'Aprovados',
                'value'       => $approved,
                'meta_event'  => 'SubmitApplication ✓',
                'color'       => '#059669',
                'icon'        => '✅',
                'description' => 'Clientes liberados para compra',
            ],
            [
                'label'       => 'Rejeitados',
                'value'       => $rejected,
                'meta_event'  => 'SubmitApplication ✗',
                'color'       => '#dc2626',
                'icon'        => '❌',
                'description' => 'Cadastros não aprovados',
            ],
            [
                'label'       => 'Em Análise',
                'value'       => $pending,
                'meta_event'  => 'Lead',
                'color'       => '#d97706',
                'icon'        => '⏳',
                'description' => 'Aguardando revisão admin',
            ],
            [
                'label'       => 'Taxa de Aprovação',
                'value'       => $taxaAprov . '%',
                'meta_event'  => '—',
                'color'       => $taxaAprov >= 70 ? '#059669' : ($taxaAprov >= 40 ? '#d97706' : '#dc2626'),
                'icon'        => '📊',
                'description' => 'Aprovados ÷ (Aprovados + Rejeitados)',
            ],
            [
                'label'       => 'Empresas Ativas',
                'value'       => $empresas,
                'meta_event'  => '—',
                'color'       => '#0ea5e9',
                'icon'        => '🏢',
                'description' => 'Total geral (sem filtro de período)',
            ],
        ];
    }

    /**
     * Returns aggregate quote stats + total credit granted.
     */
    public function getQuoteStats(): array
    {
        $period   = $this->getPeriod();
        $fromDate = $this->buildFromDate($period);

        $abertas     = $this->countQuotesByStatus(['pending', 'processing'], $fromDate);
        $negociacao  = $this->countQuotesByStatus('quoted', $fromDate);
        $convertidas = $this->countQuotesByStatus(['accepted', 'converted'], $fromDate);

        $creditCollection = $this->creditLimitCollectionFactory->create();
        $creditCollection->getSelect()->columns(['total' => new \Magento\Framework\DB\Sql\Expression('COALESCE(SUM(credit_limit), 0)')]);
        $creditRow = $creditCollection->getFirstItem();
        $creditoTotal = (float) ($creditRow->getTotal() ?? 0);

        return [
            'abertas'       => $abertas,
            'negociacao'    => $negociacao,
            'convertidas'   => $convertidas,
            'credito_total' => 'R$ ' . number_format($creditoTotal, 0, ',', '.'),
        ];
    }

    /**
     * Returns advanced KPI highlights based on funnel and quote totals.
     */
    public function getAdvancedKpis(): array
    {
        $period   = $this->getPeriod();
        $fromDate = $this->buildFromDate($period);

        $registered = $this->countLogsByAction('registered', $fromDate);
        $approved   = $this->countLogsByAction('approved', $fromDate);
        $rejected   = $this->countLogsByAction('rejected', $fromDate);

        $quotesTotal     = $this->countQuotesByStatus([], $fromDate);
        $quotesConverted = $this->countQuotesByStatus(['accepted', 'converted'], $fromDate);

        $approvalRate = $registered > 0 ? round(($approved / $registered) * 100, 1) : 0.0;
        $rejectionRate = $registered > 0 ? round(($rejected / $registered) * 100, 1) : 0.0;
        $quoteConversionRate = $quotesTotal > 0 ? round(($quotesConverted / $quotesTotal) * 100, 1) : 0.0;

        return [
            'approval_rate' => number_format($approvalRate, 1, ',', '.') . '%',
            'rejection_rate' => number_format($rejectionRate, 1, ',', '.') . '%',
            'quote_conversion_rate' => number_format($quoteConversionRate, 1, ',', '.') . '%',
            'total_interactions' => (string) ($registered + $approved + $rejected),
        ];
    }

    /**
     * Returns daily trend data for chart rendering.
     */
    public function getDailyTimeline(): array
    {
        $days = $this->getChartWindowDays();
        $rows = $this->approvalLogResource->getDailyTimeline($days);
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(string) $row['day']] = [
                'registered' => (int) ($row['registered'] ?? 0),
                'approved' => (int) ($row['approved'] ?? 0),
                'rejected' => (int) ($row['rejected'] ?? 0),
            ];
        }

        $labels = [];
        $registeredSeries = [];
        $approvedSeries = [];
        $rejectedSeries = [];

        $start = new \DateTimeImmutable(sprintf('-%d days', $days - 1));
        $today = new \DateTimeImmutable('today');

        for ($date = $start; $date <= $today; $date = $date->modify('+1 day')) {
            $key = $date->format('Y-m-d');
            $labels[] = $date->format('d/m');
            $registeredSeries[] = $indexed[$key]['registered'] ?? 0;
            $approvedSeries[] = $indexed[$key]['approved'] ?? 0;
            $rejectedSeries[] = $indexed[$key]['rejected'] ?? 0;
        }

        return [
            'labels' => $labels,
            'registered' => $registeredSeries,
            'approved' => $approvedSeries,
            'rejected' => $rejectedSeries,
        ];
    }

    /**
     * Returns the 25 most recent approval_log entries with customer and company data.
     */
    public function getRecentLog(): array
    {
        $period   = $this->getPeriod();
        $fromDate = $this->buildFromDate($period);
        return $this->approvalLogResource->getRecentLogsWithJoin($fromDate, 25);
    }

    /**
     * Uses last 30 days as chart window when "all time" is selected.
     */
    private function getChartWindowDays(): int
    {
        $period = $this->getPeriod();
        return $period === 0 ? 30 : $period;
    }

    private function buildFromDate(int $period): ?string
    {
        if ($period === 0) {
            return null;
        }
        return date('Y-m-d H:i:s', strtotime("-{$period} days"));
    }

    private function countLogsByAction(string $action, ?string $fromDate): int
    {
        $collection = $this->logCollectionFactory->create();
        $collection->addFieldToFilter('action', $action);
        if ($fromDate) {
            $collection->addFieldToFilter('created_at', ['gteq' => $fromDate]);
        }
        return $collection->getSize();
    }

    private function countQuotesByStatus(?array $status, ?string $fromDate): int
    {
        $collection = $this->quoteCollectionFactory->create();
        if ($status !== null) {
            if (count($status) === 1) {
                $collection->addFieldToFilter('status', $status[0]);
            } else {
                $collection->addFieldToFilter('status', ['in' => $status]);
            }
        }
        if ($fromDate) {
            $collection->addFieldToFilter('created_at', ['gteq' => $fromDate]);
        }
        return $collection->getSize();
    }
}
