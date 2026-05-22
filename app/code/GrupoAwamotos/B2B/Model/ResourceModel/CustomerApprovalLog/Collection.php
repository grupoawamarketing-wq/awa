<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\ResourceModel\CustomerApprovalLog;

use GrupoAwamotos\B2B\Model\CustomerApprovalLog as Model;
use GrupoAwamotos\B2B\Model\ResourceModel\CustomerApprovalLog as ResourceModel;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(Model::class, ResourceModel::class);
    }
}
