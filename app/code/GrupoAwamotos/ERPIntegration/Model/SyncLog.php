<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Model;

use Magento\Framework\Model\AbstractModel;

class SyncLog extends AbstractModel
{
    protected $_eventPrefix = 'grupoawamotos_erp_sync_log';

    protected function _construct(): void
    {
        $this->_init(ResourceModel\SyncLog::class);
    }

    /**
     * Create and persist a sync log entry.
     *
     * Prefer injecting SyncLogFactory via DI and calling this method
     * on the created instance instead of using static helpers.
     */
    public function createEntry(
        string $entityType,
        string $direction,
        string $status,
        string $message = '',
        ?string $erpCode = null,
        ?int $magentoId = null,
        ?int $recordsProcessed = null
    ): self {
        $this->setData([
            'entity_type' => $entityType,
            'direction' => $direction,
            'status' => $status,
            'message' => $message,
            'erp_code' => $erpCode,
            'magento_id' => $magentoId,
            'records_processed' => $recordsProcessed,
        ]);
        $this->save();
        return $this;
    }
}
