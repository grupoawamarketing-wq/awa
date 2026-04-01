<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\ResourceModel\CreditTransaction;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'transaction_id';

    protected function _construct()
    {
        $this->_init(
            \GrupoAwamotos\B2B\Model\CreditTransaction::class,
            \GrupoAwamotos\B2B\Model\ResourceModel\CreditTransaction::class
        );
    }

    public function filterByCustomer(int $customerId): self
    {
        $this->addFieldToFilter('customer_id', ['eq' => $customerId]);
        return $this;
    }
}
