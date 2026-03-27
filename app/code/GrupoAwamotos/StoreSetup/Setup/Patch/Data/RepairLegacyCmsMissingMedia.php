<?php

declare(strict_types=1);

namespace GrupoAwamotos\StoreSetup\Setup\Patch\Data;

use GrupoAwamotos\StoreSetup\Model\CmsMissingMediaRepairer;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class RepairLegacyCmsMissingMedia implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly CmsMissingMediaRepairer $cmsMissingMediaRepairer
    ) {
    }

    public function apply(): self
    {
        $this->moduleDataSetup->startSetup();
        $this->cmsMissingMediaRepairer->repairAll();
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
            SanitizeEscapedCmsDirectives::class,
        ];
    }

    public function getAliases(): array
    {
        return [];
    }
}
