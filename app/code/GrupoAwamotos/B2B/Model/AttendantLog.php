<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model;

use Magento\Framework\Model\AbstractModel;
use GrupoAwamotos\B2B\Model\ResourceModel\AttendantLog as ResourceModel;

class AttendantLog extends AbstractModel
{
    protected function _construct(): void
    {
        $this->_init(ResourceModel::class);
    }
}
