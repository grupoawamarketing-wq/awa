<?php

/**
 * Install Data: Create B2B Customer Attributes and Groups
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Setup\Patch\Data;

use Magento\Customer\Model\Customer;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Eav\Model\Entity\Attribute\SetFactory as AttributeSetFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Customer\Api\Data\GroupInterfaceFactory;

class CreateB2BCustomerAttributes implements DataPatchInterface, PatchRevertableInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @var CustomerSetupFactory
     */
    private $customerSetupFactory;

    /**
     * @var AttributeSetFactory
     */
    private $attributeSetFactory;

    /**
     * @var GroupRepositoryInterface
     */
    private $groupRepository;

    /**
     * @var GroupInterfaceFactory
     */
    private $groupFactory;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        CustomerSetupFactory $customerSetupFactory,
        AttributeSetFactory $attributeSetFactory,
        GroupRepositoryInterface $groupRepository,
        GroupInterfaceFactory $groupFactory
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->customerSetupFactory = $customerSetupFactory;
        $this->attributeSetFactory = $attributeSetFactory;
        $this->groupRepository = $groupRepository;
        $this->groupFactory = $groupFactory;
    }

    public function apply()
    {
        $this->moduleDataSetup->startSetup();

        $customerSetup = $this->customerSetupFactory->create(['setup' => $this->moduleDataSetup]);
        $customerEntity = $customerSetup->getEavConfig()->getEntityType(Customer::ENTITY);
        $attributeSetId = $customerEntity->getDefaultAttributeSetId();

        $attributeSet = $this->attributeSetFactory->create();
        $attributeGroupId = $attributeSet->getDefaultGroupId($attributeSetId);

        // Atributo: Status de Aprovação B2B
        $customerSetup->addAttribute(
            Customer::ENTITY,
            'b2b_approval_status',
            [
                'type' => 'varchar',
                'label' => 'Status de Aprovação B2B',
                'input' => 'select',
                'source' => \GrupoAwamotos\B2B\Model\Customer\Attribute\Source\ApprovalStatus::class,
                'required' => false,
                'visible' => true,
                'user_defined' => true,
                'position' => 100,
                'system' => false,
                'is_used_in_grid' => true,
                'is_visible_in_grid' => true,
                'is_filterable_in_grid' => true,
                'is_searchable_in_grid' => true,
                'default' => 'pending',
            ]
        );

        // Atributo: CNPJ
        $customerSetup->addAttribute(
            Customer::ENTITY,
            'b2b_cnpj',
            [
                'type' => 'varchar',
                'label' => 'CNPJ',
                'input' => 'text',
                'required' => false,
                'visible' => true,
                'user_defined' => true,
                'position' => 101,
                'system' => false,
                'is_used_in_grid' => true,
                'is_visible_in_grid' => true,
                'is_filterable_in_grid' => true,
                'is_searchable_in_grid' => false,
                'frontend_class' => 'validate-cnpj',
            ]
        );

        // Atributo: Razão Social
        $customerSetup->addAttribute(
            Customer::ENTITY,
            'b2b_razao_social',
            [
                'type' => 'varchar',
                'label' => 'Razão Social',
                'input' => 'text',
                'required' => false,
                'visible' => true,
                'user_defined' => true,
                'position' => 102,
                'system' => false,
                'is_used_in_grid' => true,
                'is_visible_in_grid' => true,
                'is_filterable_in_grid' => false,
                'is_searchable_in_grid' => false,
            ]
        );

        // Atributo: Nome Fantasia
        $customerSetup->addAttribute(
            Customer::ENTITY,
            'b2b_nome_fantasia',
            [
                'type' => 'varchar',
                'label' => 'Nome Fantasia',
                'input' => 'text',
                'required' => false,
                'visible' => true,
                'user_defined' => true,
                'position' => 103,
                'system' => false,
                'is_used_in_grid' => false,
                'is_visible_in_grid' => false,
            ]
        );

        // Atributo: Inscrição Estadual
        $customerSetup->addAttribute(
            Customer::ENTITY,
            'b2b_inscricao_estadual',
            [
                'type' => 'varchar',
                'label' => 'Inscrição Estadual',
                'input' => 'text',
                'required' => false,
                'visible' => true,
                'user_defined' => true,
                'position' => 104,
                'system' => false,
                'is_used_in_grid' => true,
                'is_visible_in_grid' => true,
                'is_filterable_in_grid' => false,
                'is_searchable_in_grid' => false,
            ]
        );

        // Atributo: Inscrição Municipal
        $customerSetup->addAttribute(
            Customer::ENTITY,
            'b2b_inscricao_municipal',
            [
                'type' => 'varchar',
                'label' => 'Inscrição Municipal',
                'input' => 'text',
                'required' => false,
                'visible' => true,
                'user_defined' => true,
                'position' => 105,
                'system' => false,
            ]
        );

        // Atributo: Telefone Comercial
        $customerSetup->addAttribute(
            Customer::ENTITY,
            'b2b_phone',
            [
                'type' => 'varchar',
                'label' => 'Telefone Comercial',
                'input' => 'text',
                'required' => false,
                'visible' => true,
                'user_defined' => true,
                'position' => 106,
                'system' => false,
                'is_used_in_grid' => false,
                'is_visible_in_grid' => false,
            ]
        );

        // Atributo: Tipo de Pessoa (PF/PJ)
        $customerSetup->addAttribute(
            Customer::ENTITY,
            'b2b_person_type',
            [
                'type' => 'varchar',
                'label' => 'Tipo de Pessoa',
                'input' => 'select',
                'source' => \GrupoAwamotos\B2B\Model\Customer\Attribute\Source\PersonType::class,
                'required' => false,
                'visible' => true,
                'user_defined' => true,
                'position' => 107,
                'system' => false,
                'is_used_in_grid' => true,
                'is_visible_in_grid' => true,
                'is_filterable_in_grid' => true,
                'default' => 'pj',
            ]
        );

        // Atributo: Data de Aprovação
        $customerSetup->addAttribute(
            Customer::ENTITY,
            'b2b_approved_at',
            [
                'type' => 'datetime',
                'label' => 'Data de Aprovação',
                'input' => 'date',
                'required' => false,
                'visible' => true,
                'user_defined' => true,
                'position' => 108,
                'system' => false,
                'is_used_in_grid' => true,
                'is_visible_in_grid' => true,
                'is_filterable_in_grid' => true,
            ]
        );

        // Atributo: Limite de Crédito
        $customerSetup->addAttribute(
            Customer::ENTITY,
            'b2b_credit_limit',
            [
                'type' => 'decimal',
                'label' => 'Limite de Crédito (R$)',
                'input' => 'text',
                'required' => false,
                'visible' => true,
                'user_defined' => true,
                'position' => 109,
                'system' => false,
                'is_used_in_grid' => true,
                'is_visible_in_grid' => true,
                'frontend_class' => 'validate-number',
            ]
        );

        // Atributo: Observações Internas
        $customerSetup->addAttribute(
            Customer::ENTITY,
            'b2b_admin_notes',
            [
                'type' => 'text',
                'label' => 'Observações Internas',
                'input' => 'textarea',
                'required' => false,
                'visible' => true,
                'user_defined' => true,
                'position' => 110,
                'system' => false,
            ]
        );

        // Configurar atributos para uso em formulários
        $attributes = [
            'b2b_approval_status',
            'b2b_cnpj',
            'b2b_razao_social',
            'b2b_nome_fantasia',
            'b2b_inscricao_estadual',
            'b2b_inscricao_municipal',
            'b2b_phone',
            'b2b_person_type',
            'b2b_approved_at',
            'b2b_credit_limit',
            'b2b_admin_notes',
        ];

        foreach ($attributes as $attributeCode) {
            $attribute = $customerSetup->getEavConfig()->getAttribute(Customer::ENTITY, $attributeCode);

            $attribute->addData([
                'attribute_set_id' => $attributeSetId,
                'attribute_group_id' => $attributeGroupId,
                'used_in_forms' => [
                    'adminhtml_customer',
                    'customer_account_create',
                    'customer_account_edit',
                ],
            ]);

            $attribute->save();
        }

        // Criar grupos de clientes B2B se não existirem
        $this->createCustomerGroups();

        $this->moduleDataSetup->endSetup();

        return $this;
    }

    /**
     * Create B2B customer groups
     */
    private function createCustomerGroups(): void
    {
        $groups = [
            ['code' => 'B2B Atacado', 'tax_class_id' => 3],
            ['code' => 'B2B VIP', 'tax_class_id' => 3],
            ['code' => 'B2B Revendedor', 'tax_class_id' => 3],
            ['code' => 'B2B Pendente', 'tax_class_id' => 3],
        ];

        foreach ($groups as $groupData) {
            try {
                // Verificar se grupo já existe
                $searchCriteria = new \Magento\Framework\Api\SearchCriteria();
                $existingGroups = $this->groupRepository->getList($searchCriteria)->getItems();
                $exists = false;

                foreach ($existingGroups as $existingGroup) {
                    if ($existingGroup->getCode() === $groupData['code']) {
                        $exists = true;
                        break;
                    }
                }

                if (!$exists) {
                    $group = $this->groupFactory->create();
                    $group->setCode($groupData['code']);
                    $group->setTaxClassId($groupData['tax_class_id']);
                    $this->groupRepository->save($group);
                }
            } catch (\Exception $e) {
                // Log error but continue
                continue;
            }
        }
    }

    public function revert()
    {
        $this->moduleDataSetup->startSetup();

        $customerSetup = $this->customerSetupFactory->create(['setup' => $this->moduleDataSetup]);

        $attributes = [
            'b2b_approval_status',
            'b2b_cnpj',
            'b2b_razao_social',
            'b2b_nome_fantasia',
            'b2b_inscricao_estadual',
            'b2b_inscricao_municipal',
            'b2b_phone',
            'b2b_person_type',
            'b2b_approved_at',
            'b2b_credit_limit',
            'b2b_admin_notes',
        ];

        foreach ($attributes as $attributeCode) {
            $customerSetup->removeAttribute(Customer::ENTITY, $attributeCode);
        }

        $this->moduleDataSetup->endSetup();
    }

    public static function getDependencies()
    {
        return [];
    }

    public function getAliases()
    {
        return [];
    }
}
