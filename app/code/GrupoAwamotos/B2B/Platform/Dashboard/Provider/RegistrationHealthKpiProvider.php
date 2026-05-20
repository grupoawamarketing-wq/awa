<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Platform\Dashboard\Provider;

use GrupoAwamotos\B2B\Model\Registration\RegistrationHealthAuditService;
use GrupoAwamotos\B2B\Platform\Dashboard\B2bDashboardScopeHelper;
use GrupoAwamotos\B2B\Platform\Dashboard\DashboardFilter;
use GrupoAwamotos\B2B\Platform\Dashboard\KpiValue;

/**
 * Saúde cadastral — delega RegistrationHealthAuditService (read-only).
 */
class RegistrationHealthKpiProvider implements KpiProviderInterface
{
    private const TABLE_LIMIT = 10;

    public function __construct(
        private readonly RegistrationHealthAuditService $auditService,
        private readonly B2bDashboardScopeHelper $scopeHelper
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getData(DashboardFilter $filter): array
    {
        try {
            $summary = $this->auditService->collectSummary();
        } catch (\Throwable) {
            return [
                'no_phone' => KpiValue::unavailable()->toArray(),
                'no_razao_social' => KpiValue::unavailable()->toArray(),
                'no_erp_status' => KpiValue::unavailable()->toArray(),
                'total_with_cnpj' => KpiValue::unavailable()->toArray(),
                'pending_customers' => [],
            ];
        }

        $pendingCustomers = $this->filterPendingCustomers(
            $this->auditService->fetchCommercialPendingCustomers()
        );

        return [
            'no_phone' => KpiValue::available((int) ($summary['no_b2b_phone'] ?? 0))->toArray(),
            'no_razao_social' => KpiValue::available((int) ($summary['no_b2b_razao_social'] ?? 0))->toArray(),
            'no_erp_status' => KpiValue::available((int) ($summary['no_erp_customer_sync_status'] ?? 0))->toArray(),
            'total_with_cnpj' => KpiValue::available((int) ($summary['total_with_cnpj'] ?? 0))->toArray(),
            'pending_customers' => array_slice($pendingCustomers, 0, self::TABLE_LIMIT),
        ];
    }

    /**
     * @param list<array<string, mixed>> $customers
     * @return list<array<string, mixed>>
     */
    private function filterPendingCustomers(array $customers): array
    {
        if ($this->scopeHelper->canBypassPortfolioScope()) {
            return $customers;
        }

        $visible = array_flip($this->scopeHelper->getVisibleCustomerIds());
        if ($visible === []) {
            return [];
        }

        return array_values(array_filter(
            $customers,
            static fn (array $row): bool => isset($visible[(int) ($row['entity_id'] ?? 0)])
        ));
    }
}
