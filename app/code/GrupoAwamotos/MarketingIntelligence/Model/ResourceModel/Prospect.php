<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Prospect extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('grupoawamotos_mktg_prospects', 'prospect_id');
    }
}
