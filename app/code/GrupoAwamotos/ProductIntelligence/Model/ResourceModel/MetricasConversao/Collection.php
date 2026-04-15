<?php

declare(strict_types=1);

namespace GrupoAwamotos\ProductIntelligence\Model\ResourceModel\MetricasConversao;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use GrupoAwamotos\ProductIntelligence\Model\MetricasConversao;
use GrupoAwamotos\ProductIntelligence\Model\ResourceModel\MetricasConversao as MetricasConversaoResource;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(MetricasConversao::class, MetricasConversaoResource::class);
    }
}
