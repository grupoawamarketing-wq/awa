<?php

declare(strict_types=1);

namespace GrupoAwamotos\Fitment\Model\ResourceModel\MotorcycleModel;

use GrupoAwamotos\Fitment\Model\MotorcycleModel as Model;
use GrupoAwamotos\Fitment\Model\ResourceModel\MotorcycleModel as ResourceModel;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * MotorcycleModel Collection
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'grupoawamotos_fitment_model_collection';

    /**
     * @var string
     */
    protected $_idFieldName = 'model_id';

    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init(Model::class, ResourceModel::class);
    }

    /**
     * Add active filter
     *
     * @return $this
     */
    public function addActiveFilter(): self
    {
        $this->addFieldToFilter('is_active', '1');
        return $this;
    }

    /**
     * Filter by brand
     *
     * @param int $brandId
     * @return $this
     */
    public function addBrandFilter(int $brandId): self
    {
        $this->addFieldToFilter('brand_id', (string)$brandId);
        return $this;
    }

    /**
     * Join brand data
     *
     * @return $this
     */
    public function joinBrand(): self
    {
        $this->getSelect()->joinLeft(
            ['brand' => $this->getTable('grupoawamotos_fitment_brand')],
            'main_table.brand_id = brand.brand_id',
            ['brand_name' => 'name', 'brand_code' => 'code']
        );
        return $this;
    }

    /**
     * Set default order by sort_order and name
     *
     * @return $this
     */
    public function setDefaultOrder(): self
    {
        $this->setOrder('sort_order', self::SORT_ORDER_ASC);
        $this->setOrder('name', self::SORT_ORDER_ASC);
        return $this;
    }

    /**
     * Get options array for dropdown
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return $this->_toOptionArray('model_id', 'name');
    }

    /**
     * Get options array grouped by brand
     *
     * @return array
     */
    public function toOptionArrayGrouped(): array
    {
        $this->joinBrand();
        $this->setDefaultOrder();

        $options = [];
        foreach ($this as $item) {
            $brandName = $item->getData('brand_name') ?? 'Sem Marca';
            if (!isset($options[$brandName])) {
                $options[$brandName] = [
                    'label' => $brandName,
                    'value' => [],
                ];
            }
            $options[$brandName]['value'][] = [
                'value' => $item->getModelId(),
                'label' => $item->getName() . ' ' . $item->getFormattedYears(),
            ];
        }

        return array_values($options);
    }
}
