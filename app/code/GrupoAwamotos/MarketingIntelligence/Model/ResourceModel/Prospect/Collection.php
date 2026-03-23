<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Model\ResourceModel\Prospect;

use GrupoAwamotos\MarketingIntelligence\Model\Prospect as ProspectModel;
use GrupoAwamotos\MarketingIntelligence\Model\ResourceModel\Prospect as ProspectResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'prospect_id';

    protected function _construct(): void
    {
        $this->_init(ProspectModel::class, ProspectResource::class);
    }
}
