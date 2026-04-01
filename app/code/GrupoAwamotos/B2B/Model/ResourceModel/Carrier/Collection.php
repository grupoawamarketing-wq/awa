<?php

/**
 * B2B Carrier Collection
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\ResourceModel\Carrier;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'carrier_id';

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(
            \GrupoAwamotos\B2B\Model\Carrier::class,
            \GrupoAwamotos\B2B\Model\ResourceModel\Carrier::class
        );
    }

    /**
     * Filter active only
     *
     * @return $this
     */
    public function addActiveFilter(): self
    {
        $this->addFieldToFilter('is_active', 1);
        return $this;
    }

    /**
     * Order by sort order
     *
     * @return $this
     */
    public function addSortOrder(): self
    {
        $this->setOrder('sort_order', 'ASC');
        return $this;
    }
}
