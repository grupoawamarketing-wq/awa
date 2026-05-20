<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Platform\Plugin\Menu;

use GrupoAwamotos\B2B\Platform\Model\Config\PlatformConfig;
use Magento\Backend\Model\Menu\Config\Reader;

/**
 * Alterna action do menu Dashboard B2B conforme feature flag.
 */
class PlatformDashboardMenuPlugin
{
    public function __construct(
        private readonly PlatformConfig $platformConfig
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $result
     * @return array<int, array<string, mixed>>
     */
    public function afterRead(Reader $subject, array $result, ?string $scope = null): array
    {
        foreach ($result as &$item) {
            if (($item['id'] ?? '') !== 'GrupoAwamotos_B2B::platform_dashboard') {
                continue;
            }

            if ($this->platformConfig->isExecutiveDashboardEnabled()) {
                $item['action'] = 'awa_b2b/dashboard/index';
                $item['resource'] = 'GrupoAwamotos_B2B::platform_dashboard_view';
            } else {
                $item['action'] = 'awa_commercial/commercialdashboard/index';
                $item['resource'] = 'GrupoAwamotos_B2B::commercial_dashboard';
            }
        }
        unset($item);

        return $result;
    }
}
