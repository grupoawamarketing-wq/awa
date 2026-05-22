<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class AttendantLog extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('grupoawamotos_b2b_attendant_log', 'log_id');
    }
}
