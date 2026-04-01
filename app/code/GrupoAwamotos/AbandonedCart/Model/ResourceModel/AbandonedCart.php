<?php

declare(strict_types=1);

namespace GrupoAwamotos\AbandonedCart\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class AbandonedCart extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('grupoawamotos_abandoned_cart', 'entity_id');
    }
}
