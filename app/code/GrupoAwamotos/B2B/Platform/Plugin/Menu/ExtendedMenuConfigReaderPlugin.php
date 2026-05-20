<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Platform\Plugin\Menu;

use Magento\Backend\Model\Menu\Config\Converter;
use Magento\Backend\Model\Menu\Config\Reader;
use Magento\Framework\Module\Dir;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;

/**
 * Mescla arquivos menu adicionais (menu_platform.xml, menu_commercial*.xml).
 * O core Magento só lê etc/adminhtml/menu.xml por módulo.
 */
class ExtendedMenuConfigReaderPlugin
{
    /** @var list<string> */
    private const EXTRA_MENU_FILES = [
        'menu_commercial.xml',
        'menu_commercial_intelligence.xml',
        'menu_platform.xml',
    ];

    public function __construct(
        private readonly ModuleDirReader $moduleDirReader,
        private readonly Converter $menuConverter
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $result
     * @return array<int, array<string, mixed>>
     */
    public function afterRead(Reader $subject, array $result, ?string $scope = null): array
    {
        $adminhtmlDir = $this->moduleDirReader->getModuleDir(Dir::MODULE_ETC_DIR, 'GrupoAwamotos_B2B')
            . '/adminhtml/';

        foreach (self::EXTRA_MENU_FILES as $fileName) {
            $path = $adminhtmlDir . $fileName;
            if (!is_readable($path)) {
                continue;
            }

            $dom = new \DOMDocument();
            $dom->load($path);
            $result = array_merge($result, $this->menuConverter->convert($dom));
        }

        return $result;
    }
}
