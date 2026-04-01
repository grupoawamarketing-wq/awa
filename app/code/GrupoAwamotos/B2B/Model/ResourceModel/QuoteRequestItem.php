<?php

/**
 * Quote Request Item Resource Model
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class QuoteRequestItem extends AbstractDb
{
    protected $_eventPrefix = 'grupoawamotos_b2b_quote_request_item_resource';

    protected function _construct()
    {
        $this->_init('grupoawamotos_b2b_quote_request_item', 'item_id');
    }
}
