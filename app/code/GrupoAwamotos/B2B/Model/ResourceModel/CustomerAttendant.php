<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class CustomerAttendant extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('grupoawamotos_b2b_customer_attendant', 'id');
    }

    /**
     * Get attendant ID assigned to a customer.
     */
    public function getAttendantIdByCustomerId(int $customerId): ?int
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), ['attendant_id'])
            ->where('customer_id = ?', $customerId);

        $result = $connection->fetchOne($select);

        return $result ? (int) $result : null;
    }

    /**
     * Assign or update customer-attendant link.
     */
    public function assignCustomer(int $customerId, int $attendantId): void
    {
        $connection = $this->getConnection();
        $connection->insertOnDuplicate(
            $this->getMainTable(),
            [
                'customer_id'       => $customerId,
                'attendant_id'      => $attendantId,
                'assigned_at'       => date('Y-m-d H:i:s'),
                'commercial_status' => 'ativo',
            ],
            ['attendant_id', 'assigned_at']
        );
    }
}
