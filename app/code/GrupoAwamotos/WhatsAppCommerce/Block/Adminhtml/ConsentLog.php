<?php

declare(strict_types=1);

namespace GrupoAwamotos\WhatsAppCommerce\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\ResourceConnection;

class ConsentLog extends Template
{
    public function __construct(
        Context $context,
        private readonly ResourceConnection $resourceConnection,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRecentLogs(int $limit = 50): array
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('awa_whatsapp_consent_log');

            if (!$connection->isTableExists($tableName)) {
                return [];
            }

            $select = $connection->select()
                ->from($tableName)
                ->order('created_at DESC')
                ->limit($limit);

            return $connection->fetchAll($select);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getOptinCountBySource(): array
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('awa_whatsapp_consent_log');

            if (!$connection->isTableExists($tableName)) {
                return [];
            }

            $select = $connection->select()
                ->from($tableName, [
                    'source',
                    'total' => new \Zend_Db_Expr('COUNT(*)'),
                    'opted_in' => new \Zend_Db_Expr('SUM(CASE WHEN optin = 1 THEN 1 ELSE 0 END)'),
                    'opted_out' => new \Zend_Db_Expr('SUM(CASE WHEN optin = 0 THEN 1 ELSE 0 END)'),
                ])
                ->group('source');

            return $connection->fetchAll($select);
        } catch (\Exception $e) {
            return [];
        }
    }
}
