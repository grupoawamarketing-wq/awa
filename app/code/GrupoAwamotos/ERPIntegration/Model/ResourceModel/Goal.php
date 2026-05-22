<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Goal extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('grupoawamotos_erp_goals', 'goal_id');
    }

    public function getGoalIdByYearMonth(string $yearMonth): ?int
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), 'goal_id')
            ->where('year_month = ?', $yearMonth);

        $result = $connection->fetchOne($select);
        return $result ? (int) $result : null;
    }
}
