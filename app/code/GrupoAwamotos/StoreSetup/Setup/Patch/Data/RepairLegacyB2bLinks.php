<?php

declare(strict_types=1);

namespace GrupoAwamotos\StoreSetup\Setup\Patch\Data;

use GrupoAwamotos\StoreSetup\Model\LegacyB2bLinkRepairer;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class RepairLegacyB2bLinks implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly LegacyB2bLinkRepairer $legacyB2bLinkRepairer
    ) {
    }

    public function apply(): self
    {
        $this->moduleDataSetup->startSetup();
        $this->legacyB2bLinkRepairer->repairAll();
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
            AyoBlogPostsSeed::class,
            SanitizeEscapedCmsDirectives::class,
            RepairLegacyCmsMissingMedia::class,
        ];
    }

    public function getAliases(): array
    {
        return [];
    }
}
