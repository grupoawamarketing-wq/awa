<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Setup\Patch\Data;

use Magento\Customer\Model\Customer;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Eav\Model\Entity\Attribute\SetFactory as AttributeSetFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * UTM/CNAME e metadados Receita Federal no cadastro B2B.
 */
class AddRegistrationAttributionAttributes implements DataPatchInterface
{
    private const ATTRIBUTES = [
        'b2b_registration_campaign' => [
            'label' => 'Campanha (CNAME)',
            'position' => 130,
        ],
        'b2b_utm_source' => [
            'label' => 'UTM Source',
            'position' => 131,
        ],
        'b2b_utm_medium' => [
            'label' => 'UTM Medium',
            'position' => 132,
        ],
        'b2b_utm_campaign' => [
            'label' => 'UTM Campaign',
            'position' => 133,
        ],
        'b2b_utm_content' => [
            'label' => 'UTM Content',
            'position' => 134,
        ],
        'b2b_utm_term' => [
            'label' => 'UTM Term',
            'position' => 135,
        ],
        'b2b_registration_landing' => [
            'label' => 'Landing de Cadastro',
            'position' => 136,
        ],
        'b2b_receita_situacao' => [
            'label' => 'Situação Cadastral (Receita)',
            'position' => 137,
        ],
        'b2b_receita_validated' => [
            'label' => 'CNPJ Validado Receita',
            'position' => 138,
        ],
    ];

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

        foreach (self::ATTRIBUTES as $code => $meta) {
            if ($customerSetup->getAttributeId(Customer::ENTITY, $code)) {
                continue;
            }

            $customerSetup->addAttribute(
                Customer::ENTITY,
                $code,
                [
                    'type' => 'varchar',
                    'label' => $meta['label'],
                    'input' => 'text',
                    'required' => false,
                    'visible' => true,
                    'user_defined' => true,
                    'system' => false,
                    'position' => $meta['position'],
                    'is_used_in_grid' => str_starts_with($code, 'b2b_utm') || $code === 'b2b_registration_campaign',
                    'is_visible_in_grid' => str_starts_with($code, 'b2b_utm') || $code === 'b2b_registration_campaign',
                    'is_filterable_in_grid' => $code === 'b2b_utm_source' || $code === 'b2b_registration_campaign',
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
        return [AddErpCustomerSyncStatusAttribute::class];
    }

    /**
     * @return string[]
     */
    public function getAliases(): array
    {
        return [];
    }
}
