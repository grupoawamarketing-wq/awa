<?php

/**
 * B2B Shopping List Item Resource Model
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class ShoppingListItem extends AbstractDb
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init('grupoawamotos_b2b_shopping_list_item', 'item_id');
    }
}
