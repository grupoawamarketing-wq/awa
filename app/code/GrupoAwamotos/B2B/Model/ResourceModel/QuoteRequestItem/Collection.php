<?php

/**
 * Quote Request Item Collection
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\ResourceModel\QuoteRequestItem;

use GrupoAwamotos\B2B\Model\QuoteRequestItem;
use GrupoAwamotos\B2B\Model\ResourceModel\QuoteRequestItem as QuoteRequestItemResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'item_id';
    protected $_eventPrefix = 'grupoawamotos_b2b_quote_request_item_collection';

    protected function _construct()
    {
        $this->_init(QuoteRequestItem::class, QuoteRequestItemResource::class);
    }

    /**
     * Filter by request ID
     */
    public function addRequestFilter(int $requestId): self
    {
        return $this->addFieldToFilter('request_id', $requestId);
    }
}
