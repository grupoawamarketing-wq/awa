<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Followup extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('grupoawamotos_b2b_followup', 'followup_id');
    }
}
