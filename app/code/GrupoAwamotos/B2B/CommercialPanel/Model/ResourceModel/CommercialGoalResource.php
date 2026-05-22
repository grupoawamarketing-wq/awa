<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\CommercialPanel\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class CommercialGoalResource extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('grupoawamotos_b2b_commercial_goal', 'goal_id');
    }
}
