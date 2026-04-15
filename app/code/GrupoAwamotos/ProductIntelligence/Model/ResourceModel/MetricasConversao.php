<?php

declare(strict_types=1);

namespace GrupoAwamotos\ProductIntelligence\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class MetricasConversao extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('rexis_metricas_conversao', 'id');
    }
}
