<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Model\ResourceModel\CampaignInsight;

use GrupoAwamotos\MarketingIntelligence\Model\CampaignInsight as CampaignInsightModel;
use GrupoAwamotos\MarketingIntelligence\Model\ResourceModel\CampaignInsight as CampaignInsightResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'insight_id';

    protected function _construct(): void
    {
        $this->_init(CampaignInsightModel::class, CampaignInsightResource::class);
    }
}
