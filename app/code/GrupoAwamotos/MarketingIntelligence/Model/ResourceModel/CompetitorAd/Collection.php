<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Model\ResourceModel\CompetitorAd;

use GrupoAwamotos\MarketingIntelligence\Model\CompetitorAd as CompetitorAdModel;
use GrupoAwamotos\MarketingIntelligence\Model\ResourceModel\CompetitorAd as CompetitorAdResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'ad_id';

    protected function _construct(): void
    {
        $this->_init(CompetitorAdModel::class, CompetitorAdResource::class);
    }
}
