<?php

declare(strict_types=1);

namespace GrupoAwamotos\Fitment\Model\ResourceModel\Brand;

use GrupoAwamotos\Fitment\Model\Brand as Model;
use GrupoAwamotos\Fitment\Model\ResourceModel\Brand as ResourceModel;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Brand Collection
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'grupoawamotos_fitment_brand_collection';

    /**
     * @var string
     */
    protected $_idFieldName = 'brand_id';

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
        return $this->_toOptionArray('brand_id', 'name');
    }
}
