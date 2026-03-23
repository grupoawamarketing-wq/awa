<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Model\ResourceModel\Audience;

use GrupoAwamotos\MarketingIntelligence\Model\Audience as AudienceModel;
use GrupoAwamotos\MarketingIntelligence\Model\ResourceModel\Audience as AudienceResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'audience_id';

    protected function _construct(): void
    {
        $this->_init(AudienceModel::class, AudienceResource::class);
    }
}
