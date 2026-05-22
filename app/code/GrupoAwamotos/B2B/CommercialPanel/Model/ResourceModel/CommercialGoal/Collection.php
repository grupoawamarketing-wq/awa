<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\CommercialPanel\Model\ResourceModel\CommercialGoal;

use GrupoAwamotos\B2B\CommercialPanel\Model\CommercialGoal;
use GrupoAwamotos\B2B\CommercialPanel\Model\ResourceModel\CommercialGoalResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(CommercialGoal::class, CommercialGoalResource::class);
    }
}
