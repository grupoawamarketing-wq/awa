<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\ResourceModel\Followup;

use GrupoAwamotos\B2B\Model\Followup;
use GrupoAwamotos\B2B\Model\ResourceModel\Followup as FollowupResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(Followup::class, FollowupResource::class);
    }
}
