<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class CustomerApprovalLog extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('grupoawamotos_b2b_customer_approval_log', 'log_id');
    }

    public function getDailyTimeline(int $days): array
    {
        $connection = $this->getConnection();
        $table = $this->getMainTable();

        $sql = "SELECT
                    DATE(created_at) AS day,
                    SUM(CASE WHEN action = 'registered' THEN 1 ELSE 0 END) AS registered,
                    SUM(CASE WHEN action = 'approved' THEN 1 ELSE 0 END) AS approved,
                    SUM(CASE WHEN action = 'rejected' THEN 1 ELSE 0 END) AS rejected
                FROM {$table}
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(created_at)
                ORDER BY day ASC";

        return $connection->fetchAll($sql, [$days]);
    }

    public function getRecentLogsWithJoin(?string $fromDate = null, int $limit = 25): array
    {
        return $this->getLogsWithJoin($fromDate, $limit);
    }

    public function getLogsForExport(?string $fromDate = null): array
    {
        return $this->getLogsWithJoin($fromDate, null);
    }

    private function getLogsWithJoin(?string $fromDate = null, ?int $limit = null): array
    {
        $connection = $this->getConnection();
        $logTable = $this->getMainTable();
        $customerTable = $connection->getTableName('customer_entity');
        $companyTable = $connection->getTableName('grupoawamotos_b2b_company');

        $select = $connection->select()
            ->from(['l' => $logTable], [
                'l.log_id',
                'l.action',
                'l.comment',
                'l.created_at',
            ])
            ->join(
                ['ce' => $customerTable],
                'ce.entity_id = l.customer_id',
                ['email' => 'ce.email', 'customer_name' => new \Zend_Db_Expr("CONCAT(ce.firstname, ' ', ce.lastname)")]
            )
            ->joinLeft(
                ['co' => $companyTable],
                'co.admin_customer_id = l.customer_id',
                ['company_name' => 'co.razao_social', 'cnpj' => 'co.cnpj']
            )
            ->order('l.log_id DESC');

        if ($fromDate) {
            $select->where('l.created_at >= ?', $fromDate);
        }

        if ($limit) {
            $select->limit($limit);
        }

        return $connection->fetchAll($select);
    }
}
