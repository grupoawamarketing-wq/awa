<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Company extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('grupoawamotos_b2b_company', 'company_id');
    }
}
