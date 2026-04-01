<?php

/**
 * Resource Model da Transportadora
 */

declare(strict_types=1);

namespace GrupoAwamotos\CarrierSelect\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Carrier extends AbstractDb
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init('grupoawamotos_carriers', 'carrier_id');
    }
}
