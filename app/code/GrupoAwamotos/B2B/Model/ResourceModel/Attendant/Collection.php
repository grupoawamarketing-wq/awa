<?php

/**
 * B2B Attendant Collection
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\ResourceModel\Attendant;

use GrupoAwamotos\B2B\Model\Attendant as AttendantModel;
use GrupoAwamotos\B2B\Model\ResourceModel\Attendant as AttendantResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'attendant_id';

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(AttendantModel::class, AttendantResource::class);
    }
}
