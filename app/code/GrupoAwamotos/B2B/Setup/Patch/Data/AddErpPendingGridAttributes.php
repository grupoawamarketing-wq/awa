<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Setup\Patch\Data;

use Magento\Customer\Model\Customer;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Eav\Model\Entity\Attribute\SetFactory as AttributeSetFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class AddErpPendingGridAttributes implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly CustomerSetupFactory $customerSetupFactory,
        private readonly AttributeSetFactory $attributeSetFactory
    ) {
    }

    public function apply(): void
    {
        $this->moduleDataSetup->startSetup();

        $customerSetup = $this->customerSetupFactory->create(['setup' => $this->moduleDataSetup]);
        $customerEntity = $customerSetup->getEavConfig()->getEntityType(Customer::ENTITY);
        $attributeSetId = $customerEntity->getDefaultAttributeSetId();
        $attributeGroupId = $this->attributeSetFactory->create()->getDefaultGroupId($attributeSetId);

        $attributes = [
            'b2b_origin_host' => [
                'type' => 'varchar',
                'label' => 'Host de Cadastro',
                'input' => 'text',
                'position' => 139,
                'grid' => true,
            ],
            'b2b_last_erp_sync_at' => [
                'type' => 'datetime',
                'label' => 'Última Sync ERP',
                'input' => 'date',
                'position' => 140,
                'grid' => false,
            ],
        ];

        foreach ($attributes as $code => $meta) {
            if ($customerSetup->getAttributeId(Customer::ENTITY, $code)) {
                continue;
            }

            $customerSetup->addAttribute(
                Customer::ENTITY,
                $code,
                [
                    'type' => $meta['type'],
                    'label' => $meta['label'],
                    'input' => $meta['input'],
                    'required' => false,
                    'visible' => true,
                    'user_defined' => true,
                    'system' => false,
                    'position' => $meta['position'],
                    'is_used_in_grid' => $meta['grid'],
                    'is_visible_in_grid' => $meta['grid'],
                    'is_filterable_in_grid' => $meta['grid'],
                    'is_searchable_in_grid' => false,
                ]
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
    }

    /**
     * @return string[]
     */
    public static function getDependencies(): array
    {
        return [AddRegistrationAttributionAttributes::class];
    }

    /**
     * @return string[]
     */
    public function getAliases(): array
    {
        return [];
    }
}
