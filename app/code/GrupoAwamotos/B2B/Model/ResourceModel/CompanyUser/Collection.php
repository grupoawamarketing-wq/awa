<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\ResourceModel\CompanyUser;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'user_id';

    protected function _construct()
    {
        $this->_init(
            \GrupoAwamotos\B2B\Model\CompanyUser::class,
            \GrupoAwamotos\B2B\Model\ResourceModel\CompanyUser::class
        );
    }

    public function filterByCompany(int $companyId): self
    {
        $this->addFieldToFilter('company_id', ['eq' => $companyId]);
        return $this;
    }
}
