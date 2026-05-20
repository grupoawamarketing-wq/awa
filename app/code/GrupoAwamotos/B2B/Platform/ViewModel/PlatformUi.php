<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Platform\ViewModel;

use GrupoAwamotos\B2B\Platform\Model\Config\PlatformConfig;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * ViewModel para templates piloto do design system.
 */
class PlatformUi implements ArgumentInterface
{
    public function __construct(
        private readonly PlatformConfig $platformConfig
    ) {
    }

    public function isDesignSystemEnabled(): bool
    {
        return $this->platformConfig->isDesignSystemEnabled();
    }

    public function isUnifiedMenuEnabled(): bool
    {
        return $this->platformConfig->isUnifiedMenuEnabled();
    }
}
