<?php

/**
 * B2B Attendant Resource Model
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Attendant extends AbstractDb
{
    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init('grupoawamotos_b2b_attendants', 'attendant_id');
    }
}
