<?php

/**
 * Credit Limit Resource Model
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class CreditLimit extends AbstractDb
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'grupoawamotos_b2b_credit_limit_resource';

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('grupoawamotos_b2b_credit_limit', 'entity_id');
    }
}
