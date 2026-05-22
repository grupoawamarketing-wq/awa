<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\CommercialPanel\Model\ResourceModel\CommercialTask;

use GrupoAwamotos\B2B\CommercialPanel\Model\CommercialTask;
use GrupoAwamotos\B2B\CommercialPanel\Model\ResourceModel\CommercialTaskResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(CommercialTask::class, CommercialTaskResource::class);
    }
}
