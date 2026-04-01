<?php

declare(strict_types=1);

namespace GrupoAwamotos\SmartSuggestions\Model\ResourceModel\WhatsappQueue;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use GrupoAwamotos\SmartSuggestions\Model\WhatsappQueue as WhatsappQueueModel;
use GrupoAwamotos\SmartSuggestions\Model\ResourceModel\WhatsappQueue as WhatsappQueueResource;

/**
 * WhatsApp Queue Collection
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'queue_id';

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(WhatsappQueueModel::class, WhatsappQueueResource::class);
    }

    /**
     * Filter by status
     */
    public function addStatusFilter(string $status): self
    {
        return $this->addFieldToFilter('status', $status);
    }

    /**
     * Filter pending messages
     */
    public function addPendingFilter(): self
    {
        return $this->addFieldToFilter('status', WhatsappQueueModel::STATUS_PENDING)
            ->addFieldToFilter(
                'scheduled_at',
                [
                    ['null' => true],
                    ['lteq' => date('Y-m-d H:i:s')]
                ]
            );
    }

    /**
     * Order by priority and creation date
     */
    public function orderByPriority(): self
    {
        return $this->setOrder('priority', 'DESC')
            ->setOrder('created_at', 'ASC');
    }

    /**
     * Filter by phone number
     */
    public function addPhoneFilter(string $phone): self
    {
        return $this->addFieldToFilter('phone_number', ['like' => "%{$phone}%"]);
    }

    /**
     * Get failed messages that can be retried
     */
    public function getRetryableMessages(int $maxRetries = 3): self
    {
        return $this->addFieldToFilter('status', WhatsappQueueModel::STATUS_FAILED)
            ->addFieldToFilter('retry_count', ['lt' => $maxRetries])
            ->orderByPriority();
    }
}
