<?php

/**
 * Data Patch: Add CNAE profiling customer attributes
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Setup\Patch\Data;

use Magento\Customer\Model\Customer;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Eav\Model\Entity\Attribute\SetFactory as AttributeSetFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;

class AddCnaeProfileAttributes implements DataPatchInterface, PatchRevertableInterface
{
    private ModuleDataSetupInterface $moduleDataSetup;
    private CustomerSetupFactory $customerSetupFactory;
    private AttributeSetFactory $attributeSetFactory;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        CustomerSetupFactory $customerSetupFactory,
        AttributeSetFactory $attributeSetFactory
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->customerSetupFactory = $customerSetupFactory;
        $this->attributeSetFactory = $attributeSetFactory;
    }

    public function apply()
    {
        $this->moduleDataSetup->startSetup();

        $customerSetup = $this->customerSetupFactory->create(['setup' => $this->moduleDataSetup]);
        $customerEntity = $customerSetup->getEavConfig()->getEntityType(Customer::ENTITY);
        $attributeSetId = $customerEntity->getDefaultAttributeSetId();

        $attributeSet = $this->attributeSetFactory->create();
        $attributeGroupId = $attributeSet->getDefaultGroupId($attributeSetId);

        // Atributo: Código CNAE
        $customerSetup->addAttribute(
            Customer::ENTITY,
            'b2b_cnae_code',
            [
                'type' => 'varchar',
                'label' => 'Código CNAE',
                'input' => 'text',
                'required' => false,
                'visible' => true,
                'user_defined' => true,
                'position' => 115,
                'system' => false,
                'is_used_in_grid' => true,
                'is_visible_in_grid' => true,
                'is_filterable_in_grid' => true,
                'is_searchable_in_grid' => false,
            ]
        );

        // Atributo: Descrição CNAE
        $customerSetup->addAttribute(
            Customer::ENTITY,
            'b2b_cnae_description',
            [
                'type' => 'varchar',
                'label' => 'Atividade Principal (CNAE)',
                'input' => 'text',
                'required' => false,
                'visible' => true,
                'user_defined' => true,
                'position' => 116,
                'system' => false,
                'is_used_in_grid' => true,
                'is_visible_in_grid' => true,
                'is_filterable_in_grid' => false,
                'is_searchable_in_grid' => true,
            ]
        );

        // Atributo: Perfil CNAE (classificação)
        $customerSetup->addAttribute(
            Customer::ENTITY,
            'b2b_cnae_profile',
            [
                'type' => 'varchar',
                'label' => 'Perfil CNAE',
                'input' => 'select',
                'source' => \GrupoAwamotos\B2B\Model\Customer\Attribute\Source\CnaeProfile::class,
                'required' => false,
                'visible' => true,
                'user_defined' => true,
                'position' => 117,
                'system' => false,
                'is_used_in_grid' => true,
                'is_visible_in_grid' => true,
                'is_filterable_in_grid' => true,
                'is_searchable_in_grid' => false,
            ]
        );

        // Assign attributes to forms
        $attributes = ['b2b_cnae_code', 'b2b_cnae_description', 'b2b_cnae_profile'];

        foreach ($attributes as $attributeCode) {
            $attribute = $customerSetup->getEavConfig()->getAttribute(Customer::ENTITY, $attributeCode);

            $attribute->addData([
                'attribute_set_id' => $attributeSetId,
                'attribute_group_id' => $attributeGroupId,
                'used_in_forms' => [
                    'adminhtml_customer',
                ],
            ]);

            $attribute->save();
        }

        $this->moduleDataSetup->endSetup();

        return $this;
    }

    public function revert()
    {
        $this->moduleDataSetup->startSetup();

        $customerSetup = $this->customerSetupFactory->create(['setup' => $this->moduleDataSetup]);

        foreach (['b2b_cnae_code', 'b2b_cnae_description', 'b2b_cnae_profile'] as $attributeCode) {
            $customerSetup->removeAttribute(Customer::ENTITY, $attributeCode);
        }

        $this->moduleDataSetup->endSetup();
    }

    public static function getDependencies()
    {
        return [CreateB2BCustomerAttributes::class];
    }

    public function getAliases()
    {
        return [];
    }
}
