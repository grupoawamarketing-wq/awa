<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\CommercialPanel\Model\ResourceModel\ContactLog;

use GrupoAwamotos\B2B\CommercialPanel\Model\ContactLog as ContactLogModel;
use GrupoAwamotos\B2B\CommercialPanel\Model\ResourceModel\ContactLogResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(ContactLogModel::class, ContactLogResource::class);
    }
}
