<?php

declare(strict_types=1);

namespace GrupoAwamotos\ProductIntelligence\Model\ResourceModel\CustomerClassification;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(
            \GrupoAwamotos\ProductIntelligence\Model\CustomerClassification::class,
            \GrupoAwamotos\ProductIntelligence\Model\ResourceModel\CustomerClassification::class
        );
    }
}
