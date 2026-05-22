<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Setup\Patch\Data;

use Magento\Authorization\Model\ResourceModel\Role\CollectionFactory as RoleCollectionFactory;
use Magento\Authorization\Model\RulesFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class UpdateCommercialAdminRolesPhase2 implements DataPatchInterface
{
    private const ROLE_SELLER = 'AWA Comercial Vendedora';
    private const ROLE_SUPERVISOR = 'AWA Comercial Supervisora';

    /** @var string[] */
    private const PHASE2_SELLER_RESOURCES = [
        'GrupoAwamotos_B2B::commercial_tasks',
        'GrupoAwamotos_B2B::commercial_tasks_manage',
        'GrupoAwamotos_B2B::commercial_abandoned_cart',
        'GrupoAwamotos_B2B::commercial_abandoned_cart_treat',
    ];

    /** @var string[] */
    private const EXISTING_SELLER_RESOURCES = [
        'Magento_Backend::admin',
        'GrupoAwamotos_B2B::commercial',
        'GrupoAwamotos_B2B::commercial_cockpit_only',
        'GrupoAwamotos_B2B::commercial_dashboard',
        'GrupoAwamotos_B2B::commercial_portfolio',
        'GrupoAwamotos_B2B::commercial_customer_360',
        'GrupoAwamotos_B2B::commercial_contact_save',
    ];

    /** @var string[] */
    private const SUPERVISOR_EXTRA = [
        'GrupoAwamotos_B2B::commercial_all_portfolios',
        'GrupoAwamotos_B2B::commercial_pending',
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

        $this->mergeRoleResources(self::ROLE_SELLER, array_merge(
            self::EXISTING_SELLER_RESOURCES,
            self::PHASE2_SELLER_RESOURCES
        ));

        $this->mergeRoleResources(self::ROLE_SUPERVISOR, array_merge(
            self::EXISTING_SELLER_RESOURCES,
            self::PHASE2_SELLER_RESOURCES,
            self::SUPERVISOR_EXTRA
        ));

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    /**
     * @param string[] $resources
     */
    private function mergeRoleResources(string $roleName, array $resources): void
    {
        $collection = $this->roleCollectionFactory->create();
        $collection->addFieldToFilter('role_name', $roleName);
        $collection->setPageSize(1);
        $role = $collection->getFirstItem();

        if (!$role->getId()) {
            return;
        }

        $rules = $this->rulesFactory->create();
        $rules->setRoleId((int) $role->getId());
        $rules->setResources(array_values(array_unique($resources)));
        $rules->saveRel();
    }

    /**
     * @return string[]
     */
    public static function getDependencies(): array
    {
        return [CreateCommercialAdminRoles::class];
    }

    /**
     * @return string[]
     */
    public function getAliases(): array
    {
        return [];
    }
}
