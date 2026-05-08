<?php

declare(strict_types=1);

namespace GrupoAwamotos\TawkIntegration\Model\ResourceModel\Attendant;

use GrupoAwamotos\TawkIntegration\Model\Attendant;
use GrupoAwamotos\TawkIntegration\Model\ResourceModel\Attendant as AttendantResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'entity_id';

    protected function _construct(): void
    {
        $this->_init(Attendant::class, AttendantResource::class);
    }
}
