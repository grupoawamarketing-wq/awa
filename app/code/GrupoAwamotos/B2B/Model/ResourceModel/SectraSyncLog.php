<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class SectraSyncLog extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('grupoawamotos_b2b_sectra_sync_log', 'log_id');
    }

    public function addEvent(
        string $eventType,
        string $message,
        ?int $customerId = null,
        ?int $orderId = null,
        ?string $cnpj = null,
        ?int $sectraChave = null,
        string $level = 'info'
    ): void {
        $this->getConnection()->insert(
            $this->getMainTable(),
            [
                'event_type' => $eventType,
                'level' => $level,
                'customer_id' => $customerId,
                'order_id' => $orderId,
                'cnpj' => $cnpj,
                'sectra_chave' => $sectraChave,
                'message' => $message,
            ]
        );
    }
}
