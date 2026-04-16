<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Swatches\Model\Swatch;
use Psr\Log\LoggerInterface;

/**
 * Evolui o atributo nativo `color` para:
 *  - swatch visual (bolinha de cor)
 *  - visível na storefront
 *  - filtrável em navegação e busca
 *  - atribuído a todos os attribute sets do catálogo
 *  - adiciona dados de swatch (hex) para as opções canônicas
 */
class ConfigureColorAttribute implements DataPatchInterface
{
    /**
     * Mapeamento canonical: label da opção → cor hexadecimal
     *
     * @var array<string, string>
     */
    private const SWATCH_COLORS = [
        'Preto'           => '#000000',
        'Preto Brilhante' => '#111111',
        'Branco'          => '#FFFFFF',
        'Azul'            => '#1E88E5',
        'Vermelho'        => '#E53935',
        'Amarelo'         => '#FDD835',
        'Laranja'         => '#FB8C00',
        'Rosa'            => '#E91E8C',
        'Dourado'         => '#FFD700',
    ];

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavSetupFactory $eavSetupFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function apply(): void
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        // 1. Atualizar propriedades do atributo color
        $eavSetup->updateAttribute(
            Product::ENTITY,
            'color',
            [
                'is_visible_on_front'      => 1,
                'is_filterable'            => 1,
                'is_filterable_in_search'  => 1,
                'used_in_product_listing'  => 1,
                'used_for_sort_by'         => 0,
                'is_searchable'            => 1,
                'is_comparable'            => 0,
                'is_html_allowed_on_front' => 0,
                'is_used_for_promo_rules'  => 1,
            ]
        );

        $connection       = $this->moduleDataSetup->getConnection();
        $eavAttributeTable = $this->moduleDataSetup->getTable('eav_attribute');

        $attrId = (int) $connection->fetchOne(
            $connection->select()
                ->from($eavAttributeTable, ['attribute_id'])
                ->where('attribute_code = ?', 'color')
                ->where('entity_type_id = ?', $eavSetup->getEntityTypeId(Product::ENTITY))
        );

        if ($attrId === 0) {
            $this->logger->warning('[ConfigureColorAttribute] Atributo color não encontrado, patch abortado.');
            $this->moduleDataSetup->getConnection()->endSetup();
            return;
        }

        // 2. Definir swatch_input_type = visual em catalog_eav_attribute.additional_data (JSON)
        $catalogEavAttrTable = $this->moduleDataSetup->getTable('catalog_eav_attribute');
        $existingAdditional = $connection->fetchOne(
            $connection->select()
                ->from($catalogEavAttrTable, ['additional_data'])
                ->where('attribute_id = ?', $attrId)
        );
        $additionalData = [];
        if (!empty($existingAdditional)) {
            $decoded = json_decode((string) $existingAdditional, true);
            if (is_array($decoded)) {
                $additionalData = $decoded;
            }
        }
        $additionalData[Swatch::SWATCH_INPUT_TYPE_KEY] = Swatch::SWATCH_INPUT_TYPE_VISUAL;
        $connection->update(
            $catalogEavAttrTable,
            ['additional_data' => json_encode($additionalData)],
            ['attribute_id = ?' => $attrId]
        );

        // 3. Atribuir color a todos os attribute sets de catalog_product que ainda não o têm
        $this->assignToAllAttributeSets($eavSetup, $attrId);

        // 4. Aplicar dados de swatch visual (hex) nas opções canônicas
        $this->applySwatchColors($connection, $attrId);

        $this->moduleDataSetup->getConnection()->endSetup();

        $this->logger->info('[ConfigureColorAttribute] Atributo color configurado como swatch visual com sucesso.');
    }

    /**
     * Atribui o atributo color a todos os attribute sets de catalog_product.
     */
    private function assignToAllAttributeSets(\Magento\Eav\Setup\EavSetup $eavSetup, int $attrId): void
    {
        $connection   = $this->moduleDataSetup->getConnection();
        $entityTypeId = $eavSetup->getEntityTypeId(Product::ENTITY);

        $setIds = $connection->fetchCol(
            $connection->select()
                ->from($this->moduleDataSetup->getTable('eav_attribute_set'), ['attribute_set_id'])
                ->where('entity_type_id = ?', $entityTypeId)
        );

        foreach ($setIds as $setId) {
            $setId = (int) $setId;

            $alreadyAssigned = (int) $connection->fetchOne(
                $connection->select()
                    ->from($this->moduleDataSetup->getTable('eav_entity_attribute'), ['COUNT(*)'])
                    ->where('attribute_set_id = ?', $setId)
                    ->where('attribute_id = ?', $attrId)
            );

            if ($alreadyAssigned > 0) {
                continue;
            }

            $groupId = (int) $connection->fetchOne(
                $connection->select()
                    ->from($this->moduleDataSetup->getTable('eav_attribute_group'), ['attribute_group_id'])
                    ->where('attribute_set_id = ?', $setId)
                    ->order('sort_order ASC')
                    ->limit(1)
            );

            if ($groupId === 0) {
                continue;
            }

            $maxSortOrder = (int) $connection->fetchOne(
                $connection->select()
                    ->from($this->moduleDataSetup->getTable('eav_entity_attribute'), ['MAX(sort_order)'])
                    ->where('attribute_set_id = ?', $setId)
                    ->where('attribute_group_id = ?', $groupId)
            );

            $connection->insert(
                $this->moduleDataSetup->getTable('eav_entity_attribute'),
                [
                    'entity_type_id'     => $entityTypeId,
                    'attribute_set_id'   => $setId,
                    'attribute_group_id' => $groupId,
                    'attribute_id'       => $attrId,
                    'sort_order'         => $maxSortOrder + 10,
                ]
            );

            $this->logger->info(
                sprintf('[ConfigureColorAttribute] color atribuído ao attribute set ID %d', $setId)
            );
        }
    }

    /**
     * Configura swatch visual (hex) para opções canônicas existentes.
     * Não cria opções novas — apenas associa hex às opções já cadastradas.
     */
    private function applySwatchColors(\Magento\Framework\DB\Adapter\AdapterInterface $connection, int $attrId): void
    {
        $optionTable = $this->moduleDataSetup->getTable('eav_attribute_option');
        $valueTable  = $this->moduleDataSetup->getTable('eav_attribute_option_value');
        $swatchTable = $this->moduleDataSetup->getTable('eav_attribute_option_swatch');

        $options = $connection->fetchAll(
            $connection->select()
                ->from(['o' => $optionTable], ['option_id'])
                ->join(
                    ['v' => $valueTable],
                    'v.option_id = o.option_id AND v.store_id = 0',
                    ['value']
                )
                ->where('o.attribute_id = ?', $attrId)
        );

        foreach ($options as $option) {
            $optionId = (int) $option['option_id'];
            $label    = (string) $option['value'];

            $hexColor = self::SWATCH_COLORS[$label] ?? null;
            if ($hexColor === null) {
                continue;
            }

            $existing = (int) $connection->fetchOne(
                $connection->select()
                    ->from($swatchTable, ['COUNT(*)'])
                    ->where('option_id = ?', $optionId)
                    ->where('store_id = 0')
            );

            if ($existing > 0) {
                $connection->update(
                    $swatchTable,
                    ['value' => $hexColor, 'type' => Swatch::SWATCH_TYPE_VISUAL_COLOR],
                    ['option_id = ?' => $optionId, 'store_id = ?' => 0]
                );
            } else {
                $connection->insert(
                    $swatchTable,
                    [
                        'option_id' => $optionId,
                        'store_id'  => 0,
                        'type'      => Swatch::SWATCH_TYPE_VISUAL_COLOR,
                        'value'     => $hexColor,
                    ]
                );
            }
        }
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
