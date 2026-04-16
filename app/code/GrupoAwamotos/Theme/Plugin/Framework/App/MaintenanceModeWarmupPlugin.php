<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\Plugin\Framework\App;

use GrupoAwamotos\Theme\Model\CacheWarmupLauncher;
use Magento\Framework\App\MaintenanceMode;

class MaintenanceModeWarmupPlugin
{
    public function __construct(
        private readonly CacheWarmupLauncher $cacheWarmupLauncher
    ) {
    }

    public function afterSet(MaintenanceMode $subject, mixed $result, bool $isOn): mixed
    {
        if ($result !== false && $isOn === false) {
            $this->cacheWarmupLauncher->runIfEligible('maintenance_disable', false);
        }

        return $result;
    }
}
