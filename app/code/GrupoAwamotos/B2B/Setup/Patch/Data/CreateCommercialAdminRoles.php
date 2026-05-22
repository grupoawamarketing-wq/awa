<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Setup\Patch\Data;

use Magento\Authorization\Model\Acl\Role\Group;
use Magento\Authorization\Model\ResourceModel\Role\CollectionFactory as RoleCollectionFactory;
use Magento\Authorization\Model\RoleFactory;
use Magento\Authorization\Model\RulesFactory;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Acl\RootResource;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class CreateCommercialAdminRoles implements DataPatchInterface
{
    private const ROLE_SELLER = 'AWA Comercial Vendedora';
    private const ROLE_SUPERVISOR = 'AWA Comercial Supervisora';
    private const ROLE_TI = 'AWA TI';

    /** @var string[] */
    private const SELLER_RESOURCES = [
        'Magento_Backend::admin',
        'GrupoAwamotos_B2B::commercial',
        'GrupoAwamotos_B2B::commercial_cockpit_only',
        'GrupoAwamotos_B2B::commercial_dashboard',
        'GrupoAwamotos_B2B::commercial_portfolio',
        'GrupoAwamotos_B2B::commercial_customer_360',
        'GrupoAwamotos_B2B::commercial_contact_save',
    ];

    /** @var string[] */
    private const SUPERVISOR_RESOURCES = [
        'GrupoAwamotos_B2B::commercial_all_portfolios',
        'GrupoAwamotos_B2B::commercial_pending',
    ];

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly RoleFactory $roleFactory,
        private readonly RoleCollectionFactory $roleCollectionFactory,
        private readonly RulesFactory $rulesFactory,
        private readonly RootResource $rootResource
    ) {
    }

    public function apply(): self
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $this->createOrUpdateRole(self::ROLE_SELLER, self::SELLER_RESOURCES);

        $this->createOrUpdateRole(self::ROLE_SUPERVISOR, array_merge(
            self::SELLER_RESOURCES,
            self::SUPERVISOR_RESOURCES
        ));

        $this->createOrUpdateRole(self::ROLE_TI, [$this->rootResource->getId()]);

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    /**
     * @param string[] $resources
     */
    private function createOrUpdateRole(string $roleName, array $resources): void
    {
        $role = $this->findRoleByName($roleName);
        if (!$role) {
            $role = $this->roleFactory->create();
            $role->setRoleName($roleName);
            $role->setName($roleName);
            $role->setRoleType(Group::ROLE_TYPE);
            $role->setUserType((string) UserContextInterface::USER_TYPE_ADMIN);
            $role->save();
        }

        $rules = $this->rulesFactory->create();
        $rules->setRoleId((int) $role->getId());
        $rules->setResources(array_values(array_unique($resources)));
        $rules->saveRel();
    }

    private function findRoleByName(string $roleName): ?\Magento\Authorization\Model\Role
    {
        $collection = $this->roleCollectionFactory->create();
        $collection->addFieldToFilter('role_name', $roleName);
        $collection->setPageSize(1);
        $role = $collection->getFirstItem();

        return $role->getId() ? $role : null;
    }

    /**
     * @return string[]
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @return string[]
     */
    public function getAliases(): array
    {
        return [];
    }
}
