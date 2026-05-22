<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Model;

use Magento\Framework\Model\AbstractModel;
use GrupoAwamotos\ERPIntegration\Model\ResourceModel\Goal as ResourceModel;

class Goal extends AbstractModel
{
    protected function _construct(): void
    {
        $this->_init(ResourceModel::class);
    }
}
