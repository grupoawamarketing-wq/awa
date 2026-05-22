<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\ResourceModel\CustomerAttendant;

use GrupoAwamotos\B2B\Model\CustomerAttendant as Model;
use GrupoAwamotos\B2B\Model\ResourceModel\CustomerAttendant as ResourceModel;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(Model::class, ResourceModel::class);
    }

    public function addCustomerFilter(int $customerId): self
    {
        return $this->addFieldToFilter('customer_id', (string)$customerId);
    }

    public function addAttendantFilter(int $attendantId): self
    {
        return $this->addFieldToFilter('attendant_id', (string)$attendantId);
    }
}
