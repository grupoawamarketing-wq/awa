<?php

/**
 * Instalador de Atributos de Cliente Brasileiros
 * CPF, CNPJ, RG, Inscrição Estadual
 */

declare(strict_types=1);

namespace GrupoAwamotos\BrazilCustomer\Setup\Patch\Data;

use Magento\Customer\Model\Customer;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Eav\Model\Entity\Attribute\SetFactory as AttributeSetFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;

class AddBrazilianCustomerAttributes implements DataPatchInterface, PatchRevertableInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private ModuleDataSetupInterface $moduleDataSetup;

    /**
     * @var CustomerSetupFactory
     */
    private CustomerSetupFactory $customerSetupFactory;

    /**
     * @var AttributeSetFactory
     */
    private AttributeSetFactory $attributeSetFactory;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param CustomerSetupFactory $customerSetupFactory
     * @param AttributeSetFactory $attributeSetFactory
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        CustomerSetupFactory $customerSetupFactory,
        AttributeSetFactory $attributeSetFactory
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->customerSetupFactory = $customerSetupFactory;
        $this->attributeSetFactory = $attributeSetFactory;
    }

    /**
     * @inheritdoc
     */
    public function apply()
    {
        $this->moduleDataSetup->startSetup();

        $customerSetup = $this->customerSetupFactory->create(['setup' => $this->moduleDataSetup]);

        $customerEntity = $customerSetup->getEavConfig()->getEntityType(Customer::ENTITY);
        $attributeSetId = $customerEntity->getDefaultAttributeSetId();

        $attributeSet = $this->attributeSetFactory->create();
        $attributeGroupId = $attributeSet->getDefaultGroupId($attributeSetId);

        // Tipo de Pessoa (PF/PJ)
        $customerSetup->addAttribute(
            Customer::ENTITY,
            'person_type',
            [
                'type' => 'varchar',
                'label' => 'Tipo de Pessoa',
                'input' => 'select',
                'source' => \GrupoAwamotos\BrazilCustomer\Model\Config\Source\PersonType::class,
                'required' => false,
                'visible' => true,
                'user_defined' => true,
                'sort_order' => 80,
                'position' => 80,
                'system' => false,
                'default' => 'pf',
            ]
        );

        // CPF
        $customerSetup->addAttribute(
            Customer::ENTITY,
            'cpf',
            [
                'type' => 'varchar',
                'label' => 'CPF',
                'input' => 'text',
                'required' => false,
                'visible' => true,
                'user_defined' => true,
                'sort_order' => 81,
                'position' => 81,
                'system' => false,
                'validate_rules' => '{"max_text_length":14,"min_text_length":11}',
                'frontend_class' => 'validate-cpf',
            ]
        );

        // RG
        $customerSetup->addAttribute(
            Customer::ENTITY,
            'rg',
            [
                'type' => 'varchar',
                'label' => 'RG',
                'input' => 'text',
                'required' => false,
                'visible' => true,
                'user_defined' => true,
                'sort_order' => 82,
                'position' => 82,
                'system' => false,
            ]
        );

        // CNPJ
        $customerSetup->addAttribute(
            Customer::ENTITY,
            'cnpj',
            [
                'type' => 'varchar',
                'label' => 'CNPJ',
                'input' => 'text',
                'required' => false,
                'visible' => true,
                'user_defined' => true,
                'sort_order' => 83,
                'position' => 83,
                'system' => false,
                'validate_rules' => '{"max_text_length":18,"min_text_length":14}',
                'frontend_class' => 'validate-cnpj',
            ]
        );

        // Inscrição Estadual
        $customerSetup->addAttribute(
            Customer::ENTITY,
            'ie',
            [
                'type' => 'varchar',
                'label' => 'Inscrição Estadual',
                'input' => 'text',
                'required' => false,
                'visible' => true,
                'user_defined' => true,
                'sort_order' => 84,
                'position' => 84,
                'system' => false,
            ]
        );

        // Razão Social (para PJ)
        $customerSetup->addAttribute(
            Customer::ENTITY,
            'company_name',
            [
                'type' => 'varchar',
                'label' => 'Razão Social',
                'input' => 'text',
                'required' => false,
                'visible' => true,
                'user_defined' => true,
                'sort_order' => 85,
                'position' => 85,
                'system' => false,
            ]
        );

        // Nome Fantasia (para PJ)
        $customerSetup->addAttribute(
            Customer::ENTITY,
            'trade_name',
            [
                'type' => 'varchar',
                'label' => 'Nome Fantasia',
                'input' => 'text',
                'required' => false,
                'visible' => true,
                'user_defined' => true,
                'sort_order' => 86,
                'position' => 86,
                'system' => false,
            ]
        );

        // Configurar atributos para serem visíveis em formulários
        $attributes = ['person_type', 'cpf', 'rg', 'cnpj', 'ie', 'company_name', 'trade_name'];

        foreach ($attributes as $attributeCode) {
            $attribute = $customerSetup->getEavConfig()->getAttribute(Customer::ENTITY, $attributeCode);

            $attribute->addData([
                'attribute_set_id' => $attributeSetId,
                'attribute_group_id' => $attributeGroupId,
                'used_in_forms' => [
                    'adminhtml_customer',
                    'customer_account_create',
                    'customer_account_edit',
                    'checkout_register',
                ],
            ]);

            $attribute->save();
        }

        $this->moduleDataSetup->endSetup();

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function revert()
    {
        $this->moduleDataSetup->startSetup();

        $customerSetup = $this->customerSetupFactory->create(['setup' => $this->moduleDataSetup]);

        $attributes = ['person_type', 'cpf', 'rg', 'cnpj', 'ie', 'company_name', 'trade_name'];

        foreach ($attributes as $attributeCode) {
            $customerSetup->removeAttribute(Customer::ENTITY, $attributeCode);
        }

        $this->moduleDataSetup->endSetup();
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
