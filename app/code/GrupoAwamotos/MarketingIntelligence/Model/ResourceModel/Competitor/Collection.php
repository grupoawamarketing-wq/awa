<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Model\ResourceModel\Competitor;

use GrupoAwamotos\MarketingIntelligence\Model\Competitor as CompetitorModel;
use GrupoAwamotos\MarketingIntelligence\Model\ResourceModel\Competitor as CompetitorResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'competitor_id';

    protected function _construct(): void
    {
        $this->_init(CompetitorModel::class, CompetitorResource::class);
    }
}
