<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\CommercialPanel\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class CommercialTaskResource extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('grupoawamotos_b2b_commercial_task', 'task_id');
    }
}
