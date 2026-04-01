<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Api\Data;

/**
 * Interface for Order Sync Queue Message
 */
interface OrderSyncMessageInterface
{
    public const ORDER_ID = 'order_id';
    public const INCREMENT_ID = 'increment_id';
    public const RETRY_COUNT = 'retry_count';
    public const QUEUED_AT = 'queued_at';
    public const LAST_ERROR = 'last_error';

    /**
     * Get Magento order entity ID
     *
     * @return int
     */
    public function getOrderId(): int;

    /**
     * Set order ID
     *
     * @param int $orderId
     * @return $this
     */
    public function setOrderId(int $orderId): self;

    /**
     * Get order increment ID
     *
     * @return string
     */
    public function getIncrementId(): string;

    /**
     * Set order increment ID
     *
     * @param string $incrementId
     * @return $this
     */
    public function setIncrementId(string $incrementId): self;

    /**
     * Get retry count
     *
     * @return int
     */
    public function getRetryCount(): int;

    /**
     * Set retry count
     *
     * @param int $retryCount
     * @return $this
     */
    public function setRetryCount(int $retryCount): self;

    /**
     * Get queued timestamp
     *
     * @return string
     */
    public function getQueuedAt(): string;

    /**
     * Set queued timestamp
     *
     * @param string $queuedAt
     * @return $this
     */
    public function setQueuedAt(string $queuedAt): self;

    /**
     * Get last error message
     *
     * @return string|null
     */
    public function getLastError(): ?string;

    /**
     * Set last error message
     *
     * @param string|null $lastError
     * @return $this
     */
    public function setLastError(?string $lastError): self;
}
