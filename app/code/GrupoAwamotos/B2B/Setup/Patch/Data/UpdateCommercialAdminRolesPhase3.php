<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Setup\Patch\Data;

use Magento\Authorization\Model\ResourceModel\Role\CollectionFactory as RoleCollectionFactory;
use Magento\Authorization\Model\RulesFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Concede recursos da Fase 3 (Inteligência Comercial) às roles comerciais existentes.
 */
class UpdateCommercialAdminRolesPhase3 implements DataPatchInterface
{
    private const ROLE_SELLER = 'AWA Comercial Vendedora';
    private const ROLE_SUPERVISOR = 'AWA Comercial Supervisora';

    /** @var string[] */
    private const PHASE3_SELLER_RESOURCES = [
        'GrupoAwamotos_B2B::commercial_repurchase',
        'GrupoAwamotos_B2B::commercial_inactive',
        'GrupoAwamotos_B2B::commercial_inactive_manage',
        'GrupoAwamotos_B2B::commercial_goals',
        'GrupoAwamotos_B2B::commercial_reports',
        'GrupoAwamotos_B2B::commercial_reports_export',
    ];

    /** @var string[] */
    private const PHASE3_SUPERVISOR_EXTRA = [
        'GrupoAwamotos_B2B::commercial_goals_manage',
        'GrupoAwamotos_B2B::commercial_ranking',
    ];

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly RoleCollectionFactory $roleCollectionFactory,
        private readonly RulesFactory $rulesFactory
    ) {
    }

    public function apply(): self
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $this->appendResources(self::ROLE_SELLER, self::PHASE3_SELLER_RESOURCES);
        $this->appendResources(
            self::ROLE_SUPERVISOR,
            array_merge(self::PHASE3_SELLER_RESOURCES, self::PHASE3_SUPERVISOR_EXTRA)
        );

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    /**
     * @param string[] $newResources
     */
    private function appendResources(string $roleName, array $newResources): void
    {
        $collection = $this->roleCollectionFactory->create();
        $collection->addFieldToFilter('role_name', $roleName);
        $collection->setPageSize(1);
        $role = $collection->getFirstItem();

        if (!$role->getId()) {
            return;
        }

        $connection = $this->moduleDataSetup->getConnection();
        $ruleTable = $this->moduleDataSetup->getTable('authorization_rule');
        $existing = $connection->fetchCol(
            $connection->select()
                ->from($ruleTable, ['resource_id'])
                ->where('role_id = ?', (int) $role->getId())
                ->where('permission = ?', 'allow')
        );

        $merged = array_values(array_unique(array_merge($existing, $newResources)));

        $rules = $this->rulesFactory->create();
        $rules->setRoleId((int) $role->getId());
        $rules->setResources($merged);
        $rules->saveRel();
    }

    /**
     * @return string[]
     */
    public static function getDependencies(): array
    {
        return [UpdateCommercialAdminRolesPhase2::class];
    }

    /**
     * @return string[]
     */
    public function getAliases(): array
    {
        return [];
    }
}
