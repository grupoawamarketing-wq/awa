<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\ResourceModel\Attendant;

use GrupoAwamotos\B2B\Model\Attendant;
use GrupoAwamotos\B2B\Model\ResourceModel\Attendant as AttendantResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(Attendant::class, AttendantResource::class);
    }
}
