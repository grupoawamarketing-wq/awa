<?php

/**
 * Quote Request Collection
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\ResourceModel\QuoteRequest;

use GrupoAwamotos\B2B\Model\QuoteRequest;
use GrupoAwamotos\B2B\Model\ResourceModel\QuoteRequest as QuoteRequestResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'request_id';

    /**
     * @var string
     */
    protected $_eventPrefix = 'grupoawamotos_b2b_quote_request_collection';

    /**
     * Initialize collection
     */
    protected function _construct()
    {
        $this->_init(QuoteRequest::class, QuoteRequestResource::class);
    }

    /**
     * Filter by customer ID
     *
     * @param int $customerId
     * @return $this
     */
    public function addCustomerFilter(int $customerId)
    {
        return $this->addFieldToFilter('customer_id', $customerId);
    }

    /**
     * Filter by status
     *
     * @param string|array $status
     * @return $this
     */
    public function addStatusFilter($status)
    {
        if (is_array($status)) {
            return $this->addFieldToFilter('status', ['in' => $status]);
        }
        return $this->addFieldToFilter('status', $status);
    }

    /**
     * Filter only active (not expired/rejected/converted)
     *
     * @return $this
     */
    public function addActiveFilter()
    {
        return $this->addFieldToFilter('status', [
            'in' => [
                QuoteRequest::STATUS_PENDING,
                QuoteRequest::STATUS_PROCESSING,
                QuoteRequest::STATUS_QUOTED,
                QuoteRequest::STATUS_ACCEPTED,
            ]
        ]);
    }
}
