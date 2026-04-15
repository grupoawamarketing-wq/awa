<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model;

use GrupoAwamotos\B2B\Model\ResourceModel\Attendant as AttendantResource;
use Magento\Framework\Model\AbstractModel;

class Attendant extends AbstractModel
{
    protected function _construct(): void
    {
        $this->_init(AttendantResource::class);
    }
}
