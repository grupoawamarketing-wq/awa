<?php

/**
 * B2B Order Approval Collection
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\ResourceModel\OrderApproval;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'approval_id';

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(
            \GrupoAwamotos\B2B\Model\OrderApproval::class,
            \GrupoAwamotos\B2B\Model\ResourceModel\OrderApproval::class
        );
    }

    /**
     * Filter by customer
     *
     * @param int $customerId
     * @return $this
     */
    public function filterByCustomer(int $customerId): self
    {
        $this->addFieldToFilter('customer_id', $customerId);
        return $this;
    }

    /**
     * Filter by status
     *
     * @param string $status
     * @return $this
     */
    public function filterByStatus(string $status): self
    {
        $this->addFieldToFilter('status', $status);
        return $this;
    }

    /**
     * Filter pending approvals
     *
     * @return $this
     */
    public function filterPending(): self
    {
        return $this->filterByStatus(\GrupoAwamotos\B2B\Model\OrderApproval::STATUS_PENDING);
    }

    /**
     * Filter by approval level
     *
     * @param int $level
     * @return $this
     */
    public function filterByLevel(int $level): self
    {
        $this->addFieldToFilter('current_level', ['lteq' => $level]);
        return $this;
    }
}
