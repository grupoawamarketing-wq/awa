<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\ResourceModel\Company;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'company_id';

    protected function _construct()
    {
        $this->_init(
            \GrupoAwamotos\B2B\Model\Company::class,
            \GrupoAwamotos\B2B\Model\ResourceModel\Company::class
        );
    }
}
