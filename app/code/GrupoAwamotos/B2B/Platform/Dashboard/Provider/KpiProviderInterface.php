<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Platform\Dashboard\Provider;

use GrupoAwamotos\B2B\Platform\Dashboard\DashboardFilter;

/**
 * Provider read-only de KPIs para o dashboard executivo.
 */
interface KpiProviderInterface
{
    /**
     * @return array<string, mixed>
     */
    public function getData(DashboardFilter $filter): array;
}
