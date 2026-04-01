<?php

/**
 * Collection de Transportadoras
 */

declare(strict_types=1);

namespace GrupoAwamotos\CarrierSelect\Model\ResourceModel\Carrier;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use GrupoAwamotos\CarrierSelect\Model\Carrier;
use GrupoAwamotos\CarrierSelect\Model\ResourceModel\Carrier as CarrierResource;

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
        $this->_init(Carrier::class, CarrierResource::class);
    }

    /**
     * Filter by active carriers
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
        $this->setOrder('name', 'ASC');
        return $this;
    }
}
