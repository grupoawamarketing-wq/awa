<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Platform\Dashboard;

use GrupoAwamotos\B2B\Platform\Dashboard\Provider\CommercialKpiProvider;
use GrupoAwamotos\B2B\Platform\Dashboard\Provider\CustomerKpiProvider;
use GrupoAwamotos\B2B\Platform\Dashboard\Provider\OrderKpiProvider;
use GrupoAwamotos\B2B\Platform\Dashboard\Provider\RegistrationHealthKpiProvider;
use GrupoAwamotos\B2B\Platform\Dashboard\Provider\SectraKpiProvider;

/**
 * Orquestrador read-only do Dashboard Executivo B2B.
 */
class ExecutiveDashboardService
{
    public function __construct(
        private readonly OrderKpiProvider $orderKpiProvider,
        private readonly CustomerKpiProvider $customerKpiProvider,
        private readonly SectraKpiProvider $sectraKpiProvider,
        private readonly CommercialKpiProvider $commercialKpiProvider,
        private readonly RegistrationHealthKpiProvider $registrationHealthKpiProvider
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function buildDashboard(DashboardFilter $filter): array
    {
        $started = microtime(true);

        $orders = $this->orderKpiProvider->getData($filter);
        $customers = $this->customerKpiProvider->getData($filter);
        $sectra = $this->sectraKpiProvider->getData($filter);
        $commercial = $this->commercialKpiProvider->getData($filter);
        $health = $this->registrationHealthKpiProvider->getData($filter);

        return [
            'filters' => $filter->toArray(),
            'orders' => $orders,
            'customers' => $customers,
            'sectra' => $sectra,
            'commercial' => $commercial,
            'health' => $health,
            'meta' => [
                'generated_at' => date('Y-m-d H:i:s'),
                'load_time_ms' => (int) round((microtime(true) - $started) * 1000),
            ],
        ];
    }
}
