<?php

declare(strict_types=1);

namespace GrupoAwamotos\StoreSetup\Setup\Patch\Data;

use GrupoAwamotos\StoreSetup\Model\CmsDirectiveSanitizer;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class SanitizeEscapedCmsDirectives implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly CmsDirectiveSanitizer $cmsDirectiveSanitizer
    ) {
    }

    public function apply(): self
    {
        $this->moduleDataSetup->startSetup();
        $this->cmsDirectiveSanitizer->sanitizeAll();
        $this->moduleDataSetup->endSetup();

        return $this;
    }

    public static function getDependencies(): array
    {
        return [
            AyoContentSetup::class,
            AyoSeedContent::class,
            AyoSeedContentV2::class,
            AyoHomepageCmsBlocks::class,
            EnsureHomepageCmsPage::class,
            ConfigureAyoHome5Parity::class,
            UpdateInstitutionalPages::class,
        ];
    }

    public function getAliases(): array
    {
        return [];
    }
}
