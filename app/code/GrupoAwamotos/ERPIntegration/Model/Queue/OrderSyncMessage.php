<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Model\Queue;

use GrupoAwamotos\ERPIntegration\Api\Data\OrderSyncMessageInterface;

/**
 * Order Sync Queue Message Data Model
 */
class OrderSyncMessage implements OrderSyncMessageInterface
{
    private int $orderId = 0;
    private string $incrementId = '';
    private int $retryCount = 0;
    private string $queuedAt = '';
    private ?string $lastError = null;

    /**
     * @inheritDoc
     */
    public function getOrderId(): int
    {
        return $this->orderId;
    }

    /**
     * @inheritDoc
     */
    public function setOrderId(int $orderId): OrderSyncMessageInterface
    {
        $this->orderId = $orderId;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getIncrementId(): string
    {
        return $this->incrementId;
    }

    /**
     * @inheritDoc
     */
    public function setIncrementId(string $incrementId): OrderSyncMessageInterface
    {
        $this->incrementId = $incrementId;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getRetryCount(): int
    {
        return $this->retryCount;
    }

    /**
     * @inheritDoc
     */
    public function setRetryCount(int $retryCount): OrderSyncMessageInterface
    {
        $this->retryCount = $retryCount;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getQueuedAt(): string
    {
        return $this->queuedAt;
    }

    /**
     * @inheritDoc
     */
    public function setQueuedAt(string $queuedAt): OrderSyncMessageInterface
    {
        $this->queuedAt = $queuedAt;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * @inheritDoc
     */
    public function setLastError(?string $lastError): OrderSyncMessageInterface
    {
        $this->lastError = $lastError;
        return $this;
    }
}
