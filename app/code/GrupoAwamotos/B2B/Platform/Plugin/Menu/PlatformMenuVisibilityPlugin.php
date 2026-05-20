<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Platform\Plugin\Menu;

use GrupoAwamotos\B2B\Platform\Model\Config\PlatformConfig;
use Magento\Backend\Model\Menu;
use Magento\Backend\Model\Menu\Config;

/**
 * Oculta o menu unificado B2B quando unified_menu_enabled=0 (rollback Fase 2).
 */
class PlatformMenuVisibilityPlugin
{
    private const PLATFORM_ID_PREFIX = 'GrupoAwamotos_B2B::platform';

    private const LEGACY_COMMERCIAL_ROOT = 'GrupoAwamotos_B2B::commercial';
    private const LEGACY_B2B_ROOT = 'GrupoAwamotos_B2B::b2b';

    public function __construct(
        private readonly PlatformConfig $platformConfig
    ) {
    }

    public function afterGetMenu(Config $subject, Menu $menu): Menu
    {
        if (!$this->platformConfig->isUnifiedMenuEnabled()) {
            $this->removePlatformTree($menu);
            return $menu;
        }

        if (!$this->platformConfig->isLegacyMenuVisible()) {
            $menu->remove(self::LEGACY_COMMERCIAL_ROOT);
            $menu->remove(self::LEGACY_B2B_ROOT);
        }

        return $menu;
    }

    private function removePlatformTree(Menu $menu): void
    {
        foreach ($menu as $item) {
            $id = (string) $item->getId();
            if (str_starts_with($id, self::PLATFORM_ID_PREFIX)) {
                $menu->remove($id);
            }
        }
    }
}
