<?php

declare(strict_types=1);

namespace GrupoAwamotos\CarrierSelect\Setup\Patch\Data;

use GrupoAwamotos\CarrierSelect\Model\Customer\Attribute\Source\CarrierOptions;
use Magento\Customer\Model\Customer;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class AddCustomerPreferredCarrierAttribute implements DataPatchInterface
{
    private ModuleDataSetupInterface $moduleDataSetup;
    private CustomerSetupFactory $customerSetupFactory;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        CustomerSetupFactory $customerSetupFactory
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->customerSetupFactory = $customerSetupFactory;
    }

    public function apply(): void
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $customerSetup = $this->customerSetupFactory->create(['setup' => $this->moduleDataSetup]);

        $customerSetup->addAttribute(
            Customer::ENTITY,
            'b2b_carrier_code',
            [
                'type' => 'varchar',
                'label' => 'Transportadora (B2B)',
                'input' => 'select',
                'source' => CarrierOptions::class,
                'required' => false,
                'visible' => true,
                'user_defined' => true,
                'system' => 0,
                'position' => 999,
                'sort_order' => 999,
                'global' => ScopedAttributeInterface::SCOPE_WEBSITE,
                'default' => '',
            ]
        );

        $attribute = $customerSetup->getEavConfig()->getAttribute(Customer::ENTITY, 'b2b_carrier_code');
        $attribute->setData(
            'used_in_forms',
            [
                'adminhtml_customer',
            ]
        );
        $attribute->save();

        $this->moduleDataSetup->getConnection()->endSetup();
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
