<?php

declare(strict_types=1);

namespace GrupoAwamotos\StoreSetup\Setup\Patch\Data;

use GrupoAwamotos\StoreSetup\Model\TransactionalEmailConfigSynchronizer;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class SyncTransactionalEmailIdentities implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly TransactionalEmailConfigSynchronizer $transactionalEmailConfigSynchronizer
    ) {
    }

    public function apply(): self
    {
        $this->moduleDataSetup->startSetup();
        $this->transactionalEmailConfigSynchronizer->synchronizeDefaultScope();
        $this->moduleDataSetup->endSetup();

        return $this;
    }

    public static function getDependencies(): array
    {
        return [
            NormalizePublicStorefrontEmails::class,
        ];
    }

    public function getAliases(): array
    {
        return [];
    }
}
