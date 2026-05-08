<?php

declare(strict_types=1);

namespace GrupoAwamotos\TawkIntegration\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Attendant extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('awamotos_tawk_attendant_assignment', 'entity_id');
    }
}
