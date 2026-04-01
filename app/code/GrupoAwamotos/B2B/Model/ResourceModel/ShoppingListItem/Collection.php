<?php

/**
 * B2B Shopping List Item Collection
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\ResourceModel\ShoppingListItem;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'item_id';

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(
            \GrupoAwamotos\B2B\Model\ShoppingListItem::class,
            \GrupoAwamotos\B2B\Model\ResourceModel\ShoppingListItem::class
        );
    }

    /**
     * Filter by list
     *
     * @param int $listId
     * @return $this
     */
    public function addListFilter(int $listId): self
    {
        $this->addFieldToFilter('list_id', $listId);
        return $this;
    }
}
