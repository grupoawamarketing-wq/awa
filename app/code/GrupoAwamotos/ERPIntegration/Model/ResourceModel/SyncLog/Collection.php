<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Model\ResourceModel\SyncLog;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use GrupoAwamotos\ERPIntegration\Model\SyncLog;
use GrupoAwamotos\ERPIntegration\Model\ResourceModel\SyncLog as SyncLogResource;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'log_id';
    protected $_eventPrefix = 'grupoawamotos_erp_sync_log_collection';

    protected function _construct(): void
    {
        $this->_init(SyncLog::class, SyncLogResource::class);
    }
}
