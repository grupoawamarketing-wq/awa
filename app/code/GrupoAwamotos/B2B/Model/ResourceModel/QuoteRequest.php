<?php

/**
 * Quote Request Resource Model
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class QuoteRequest extends AbstractDb
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'grupoawamotos_b2b_quote_request_resource';

    /**
     * Initialize resource model
     */
    protected function _construct()
    {
        $this->_init('grupoawamotos_b2b_quote_request', 'request_id');
    }
}
