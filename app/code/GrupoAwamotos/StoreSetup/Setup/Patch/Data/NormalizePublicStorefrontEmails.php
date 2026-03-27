<?php

declare(strict_types=1);

namespace GrupoAwamotos\StoreSetup\Setup\Patch\Data;

use GrupoAwamotos\StoreSetup\Model\PublicEmailNormalizer;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class NormalizePublicStorefrontEmails implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly PublicEmailNormalizer $publicEmailNormalizer
    ) {
    }

    public function apply(): self
    {
        $this->moduleDataSetup->startSetup();
        $this->publicEmailNormalizer->normalizeAll();
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
            RepairLegacyB2bLinks::class,
        ];
    }

    public function getAliases(): array
    {
        return [];
    }
}
