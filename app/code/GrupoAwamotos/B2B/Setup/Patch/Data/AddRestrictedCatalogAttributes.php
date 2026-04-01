<?php

/**
 * Add B2B Restricted Catalog Attributes
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Setup\Patch\Data;

use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Category;

class AddRestrictedCatalogAttributes implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @var EavSetupFactory
     */
    private $eavSetupFactory;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        EavSetupFactory $eavSetupFactory
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->eavSetupFactory = $eavSetupFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function apply()
    {
        $this->moduleDataSetup->startSetup();

        /** @var EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        // Product attribute: B2B Customer Groups Allowed
        if (!$eavSetup->getAttributeId(Product::ENTITY, 'b2b_customer_groups')) {
            $eavSetup->addAttribute(
                Product::ENTITY,
                'b2b_customer_groups',
                [
                    'type' => 'text',
                    'label' => 'Grupos B2B Permitidos',
                    'input' => 'multiselect',
                    'source' => \Magento\Eav\Model\Entity\Attribute\Source\Table::class,
                    'backend' => \Magento\Eav\Model\Entity\Attribute\Backend\ArrayBackend::class,
                    'required' => false,
                    'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                    'visible' => true,
                    'user_defined' => true,
                    'searchable' => false,
                    'filterable' => false,
                    'comparable' => false,
                    'visible_on_front' => false,
                    'used_in_product_listing' => true,
                    'unique' => false,
                    'apply_to' => '',
                    'group' => 'B2B Settings',
                    'sort_order' => 10,
                    'note' => 'Deixe vazio para mostrar a todos. Selecione grupos para restringir.',
                    'option' => [
                        'values' => [
                            'B2B Atacado',
                            'B2B VIP',
                            'B2B Revendedor',
                            'Todos os Grupos B2B'
                        ]
                    ]
                ]
            );
        }

        // Product attribute: B2B Exclusive
        if (!$eavSetup->getAttributeId(Product::ENTITY, 'b2b_exclusive')) {
            $eavSetup->addAttribute(
                Product::ENTITY,
                'b2b_exclusive',
                [
                    'type' => 'int',
                    'label' => 'Produto Exclusivo B2B',
                    'input' => 'boolean',
                    'source' => \Magento\Eav\Model\Entity\Attribute\Source\Boolean::class,
                    'required' => false,
                    'default' => 0,
                    'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                    'visible' => true,
                    'user_defined' => true,
                    'searchable' => false,
                    'filterable' => false,
                    'comparable' => false,
                    'visible_on_front' => false,
                    'used_in_product_listing' => true,
                    'unique' => false,
                    'apply_to' => '',
                    'group' => 'B2B Settings',
                    'sort_order' => 5,
                    'note' => 'Se sim, produto só aparece para clientes B2B logados.'
                ]
            );
        }

        // Category attribute: B2B Customer Groups Allowed
        if (!$eavSetup->getAttributeId(Category::ENTITY, 'b2b_customer_groups')) {
            $eavSetup->addAttribute(
                Category::ENTITY,
                'b2b_customer_groups',
                [
                    'type' => 'text',
                    'label' => 'Grupos B2B Permitidos',
                    'input' => 'multiselect',
                    'source' => \Magento\Eav\Model\Entity\Attribute\Source\Table::class,
                    'backend' => \Magento\Eav\Model\Entity\Attribute\Backend\ArrayBackend::class,
                    'required' => false,
                    'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_STORE,
                    'visible' => true,
                    'user_defined' => true,
                    'sort_order' => 100,
                    'group' => 'B2B Settings',
                    'note' => 'Deixe vazio para mostrar a todos. Selecione grupos para restringir.',
                    'option' => [
                        'values' => [
                            'B2B Atacado',
                            'B2B VIP',
                            'B2B Revendedor',
                            'Todos os Grupos B2B'
                        ]
                    ]
                ]
            );
        }

        // Category attribute: B2B Exclusive
        if (!$eavSetup->getAttributeId(Category::ENTITY, 'b2b_exclusive')) {
            $eavSetup->addAttribute(
                Category::ENTITY,
                'b2b_exclusive',
                [
                    'type' => 'int',
                    'label' => 'Categoria Exclusiva B2B',
                    'input' => 'boolean',
                    'source' => \Magento\Eav\Model\Entity\Attribute\Source\Boolean::class,
                    'required' => false,
                    'default' => 0,
                    'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_STORE,
                    'visible' => true,
                    'user_defined' => true,
                    'sort_order' => 99,
                    'group' => 'B2B Settings',
                    'note' => 'Se sim, categoria só aparece para clientes B2B logados.'
                ]
            );
        }

        $this->moduleDataSetup->endSetup();

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases()
    {
        return [];
    }
}
