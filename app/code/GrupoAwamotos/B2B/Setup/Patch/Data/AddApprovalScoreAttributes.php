<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Setup\Patch\Data;

use GrupoAwamotos\B2B\Model\Customer\Attribute\Source\ApprovalScore;
use Magento\Customer\Model\Customer;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Eav\Model\Entity\Attribute\SetFactory as AttributeSetFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class AddApprovalScoreAttributes implements DataPatchInterface
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
        $attributeGroupId = $this->attributeSetFactory->create()->getDefaultGroupId($attributeSetId);

        $attributes = [
            'b2b_approval_score' => [
                'type' => 'varchar',
                'label' => 'Score de Aprovação',
                'input' => 'select',
                'source' => ApprovalScore::class,
                'position' => 118,
            ],
            'b2b_approval_score_reason' => [
                'type' => 'text',
                'label' => 'Motivo da Triagem',
                'input' => 'textarea',
                'position' => 119,
            ],
            'b2b_suggested_group_id' => [
                'type' => 'int',
                'label' => 'Grupo B2B Sugerido',
                'input' => 'text',
                'position' => 120,
            ],
        ];

        foreach ($attributes as $code => $config) {
            if ($customerSetup->getAttributeId(Customer::ENTITY, $code)) {
                continue;
            }

            $customerSetup->addAttribute(
                Customer::ENTITY,
                $code,
                array_merge([
                    'required' => false,
                    'visible' => true,
                    'user_defined' => true,
                    'system' => false,
                    'is_used_in_grid' => true,
                    'is_visible_in_grid' => true,
                    'is_filterable_in_grid' => $code === 'b2b_approval_score',
                    'is_searchable_in_grid' => false,
                ], $config)
            );

            $attribute = $customerSetup->getEavConfig()->getAttribute(Customer::ENTITY, $code);
            $attribute->addData([
                'attribute_set_id' => $attributeSetId,
                'attribute_group_id' => $attributeGroupId,
                'used_in_forms' => ['adminhtml_customer'],
            ]);
            $attribute->save();
        }

        $this->moduleDataSetup->endSetup();

        return $this;
    }

    public static function getDependencies(): array
    {
        return [AddCnaeProfileAttributes::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
