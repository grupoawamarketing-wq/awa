<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Model\ResourceModel\Goal;

use GrupoAwamotos\ERPIntegration\Model\Goal as Model;
use GrupoAwamotos\ERPIntegration\Model\ResourceModel\Goal as ResourceModel;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(Model::class, ResourceModel::class);
    }
}
