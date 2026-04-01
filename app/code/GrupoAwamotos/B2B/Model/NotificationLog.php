<?php

/**
 * B2B Notification Log Model
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model;

use Magento\Framework\Model\AbstractModel;

class NotificationLog extends AbstractModel
{
    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(\GrupoAwamotos\B2B\Model\ResourceModel\NotificationLog::class);
    }

    public function getType(): string
    {
        return (string)$this->getData('type');
    }

    public function getEvent(): string
    {
        return (string)$this->getData('event');
    }

    public function getRecipient(): string
    {
        return (string)$this->getData('recipient');
    }

    public function getStatus(): string
    {
        return (string)$this->getData('status');
    }

    public function getMessage(): string
    {
        return (string)$this->getData('message');
    }

    public function getErrorMessage(): ?string
    {
        return $this->getData('error_message');
    }

    public function getEntityType(): ?string
    {
        return $this->getData('entity_type');
    }

    public function getEntityId(): ?int
    {
        $val = $this->getData('entity_id');
        return $val !== null ? (int)$val : null;
    }
}
