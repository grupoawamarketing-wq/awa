<?php

declare(strict_types=1);

namespace GrupoAwamotos\Fitment\Setup\Patch\Data;

use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Catalog\Model\Product;

class AddFitmentAttributes implements DataPatchInterface
{
    private ModuleDataSetupInterface $moduleDataSetup;
    private EavSetupFactory $eavSetupFactory;

    public function __construct(ModuleDataSetupInterface $moduleDataSetup, EavSetupFactory $eavSetupFactory)
    {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->eavSetupFactory = $eavSetupFactory;
    }

    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);
        $this->addAttribute($eavSetup, 'marca_moto', 'Marca da Moto');
        $this->addAttribute($eavSetup, 'modelo_moto', 'Modelo da Moto');
        $this->addAttribute($eavSetup, 'ano_moto', 'Ano da Moto', ['frontend_class' => 'validate-digits']);
        $this->moduleDataSetup->getConnection()->endSetup();
    }

    private function addAttribute($eavSetup, string $code, string $label, array $extra = [])
    {
        if ($eavSetup->getAttributeId(Product::ENTITY, $code)) {
            return; // já existe
        }
        $eavSetup->addAttribute(Product::ENTITY, $code, array_merge([
            'group' => 'Fitment',
            'type' => 'varchar',
            'label' => $label,
            'input' => 'text',
            'required' => false,
            'visible_on_front' => true,
            'global' => 1,
            'searchable' => true,
            'comparable' => false,
            'unique' => false,
            'filterable' => true,
            'filterable_in_search' => true,
            'used_in_product_listing' => true,
            'is_used_in_grid' => true,
            'is_visible_in_grid' => true,
            'is_filterable_in_grid' => true,
        ], $extra));
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
