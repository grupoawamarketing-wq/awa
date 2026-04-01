<?php

/**
 * B2B Shopping List Collection
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\ResourceModel\ShoppingList;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'list_id';

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(
            \GrupoAwamotos\B2B\Model\ShoppingList::class,
            \GrupoAwamotos\B2B\Model\ResourceModel\ShoppingList::class
        );
    }

    /**
     * Filter by customer
     *
     * @param int $customerId
     * @return $this
     */
    public function addCustomerFilter(int $customerId): self
    {
        $this->addFieldToFilter('customer_id', $customerId);
        return $this;
    }
}
