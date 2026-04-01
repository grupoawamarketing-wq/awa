<?php

/**
 * Credit Limit Collection
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\ResourceModel\CreditLimit;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use GrupoAwamotos\B2B\Model\CreditLimit as Model;
use GrupoAwamotos\B2B\Model\ResourceModel\CreditLimit as ResourceModel;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'grupoawamotos_b2b_credit_limit_collection';

    /**
     * @var string
     */
    protected $_idFieldName = 'entity_id';

    /**
     * Initialize collection model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(Model::class, ResourceModel::class);
    }
}
