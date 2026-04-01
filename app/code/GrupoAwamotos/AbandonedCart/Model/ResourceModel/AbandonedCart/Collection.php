<?php

declare(strict_types=1);

namespace GrupoAwamotos\AbandonedCart\Model\ResourceModel\AbandonedCart;

use GrupoAwamotos\AbandonedCart\Model\AbandonedCart;
use GrupoAwamotos\AbandonedCart\Model\ResourceModel\AbandonedCart as ResourceModel;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'entity_id';
    protected $_eventPrefix = 'grupoawamotos_abandoned_cart_collection';
    protected $_eventObject = 'abandoned_cart_collection';

    protected function _construct()
    {
        $this->_init(AbandonedCart::class, ResourceModel::class);
    }

    public function addPendingFilter(): self
    {
        return $this->addFieldToFilter('status', ['in' => ['pending', 'processing']])
                    ->addFieldToFilter('recovered', 0);
    }

    public function addEmailNotSentFilter(int $emailNumber): self
    {
        return $this->addFieldToFilter("email_{$emailNumber}_sent", 0);
    }

    public function addTimeFilter(int $emailNumber, int $delayHours): self
    {
        $cutoffTime = date('Y-m-d H:i:s', strtotime("-{$delayHours} hours"));
        return $this->addFieldToFilter('abandoned_at', ['lteq' => $cutoffTime]);
    }
}
