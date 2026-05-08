<?php

declare(strict_types=1);

namespace GrupoAwamotos\TawkIntegration\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class ChatLog extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('awamotos_tawk_chat_log', 'entity_id');
    }
}
