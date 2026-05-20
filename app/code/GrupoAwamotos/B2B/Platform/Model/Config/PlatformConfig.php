<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Platform\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Feature flags e opções da plataforma B2B (Fase 1 + 2).
 */
class PlatformConfig
{
    private const XML_PATH_DESIGN_SYSTEM = 'grupoawamotos_b2b/platform/design_system_enabled';
    private const XML_PATH_UNIFIED_MENU = 'grupoawamotos_b2b/platform/unified_menu_enabled';
    private const XML_PATH_LEGACY_MENU_VISIBLE = 'grupoawamotos_b2b/platform/legacy_menu_visible';
    private const XML_PATH_LEGACY_MENU_BADGE = 'grupoawamotos_b2b/platform/legacy_menu_badge';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function isDesignSystemEnabled(?int $storeId = null): bool
    {
        return $this->isFlag(self::XML_PATH_DESIGN_SYSTEM, $storeId);
    }

    public function isUnifiedMenuEnabled(?int $storeId = null): bool
    {
        return $this->isFlag(self::XML_PATH_UNIFIED_MENU, $storeId);
    }

    public function isLegacyMenuVisible(?int $storeId = null): bool
    {
        return $this->isFlag(self::XML_PATH_LEGACY_MENU_VISIBLE, $storeId);
    }

    public function isLegacyMenuBadgeEnabled(?int $storeId = null): bool
    {
        return $this->isFlag(self::XML_PATH_LEGACY_MENU_BADGE, $storeId);
    }

    private function isFlag(string $path, ?int $storeId): bool
    {
        return $this->scopeConfig->isSetFlag(
            $path,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
