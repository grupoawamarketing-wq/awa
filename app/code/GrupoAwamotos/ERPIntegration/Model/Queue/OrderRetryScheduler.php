<?php
declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Model\Queue;

use GrupoAwamotos\ERPIntegration\Api\Data\OrderSyncMessageInterface;
use GrupoAwamotos\ERPIntegration\Model\ResourceModel\OrderRetrySchedule;
use Psr\Log\LoggerInterface;

class OrderRetryScheduler
{
    private const BASE_DELAY_SECONDS = 30;
    private const MAX_DELAY_SECONDS = 3600;

    public function __construct(
        private readonly OrderRetrySchedule $orderRetrySchedule,
        private readonly LoggerInterface $logger
    ) {
    }

    public function schedule(OrderSyncMessageInterface $message, int $retryCount, ?string $lastError): string
    {
        $nextAttemptAt = $this->calculateNextAttemptAt($retryCount);

        $this->orderRetrySchedule->scheduleRetry(
            $message->getOrderId(),
            $message->getIncrementId(),
            $retryCount,
            $lastError,
            $nextAttemptAt
        );

        $this->logger->info('[ERP Queue] Retry scheduled', [
            'order_id' => $message->getOrderId(),
            'increment_id' => $message->getIncrementId(),
            'retry_count' => $retryCount,
            'next_attempt_at' => $nextAttemptAt,
        ]);

        return $nextAttemptAt;
    }

    public function getDueRetries(int $limit): array
    {
        return $this->orderRetrySchedule->getDueRetries($limit);
    }

    public function deleteRetry(int $retryId): void
    {
        $this->orderRetrySchedule->deleteRetry($retryId);
    }

    public function getRetryDelay(int $retryCount): int
    {
        return min(self::BASE_DELAY_SECONDS * (2 ** max(0, $retryCount - 1)), self::MAX_DELAY_SECONDS);
    }

    private function calculateNextAttemptAt(int $retryCount): string
    {
        $delaySeconds = $this->getRetryDelay($retryCount);

        return (new \DateTimeImmutable())
            ->modify(sprintf('+%d seconds', $delaySeconds))
            ->format('Y-m-d H:i:s');
    }
}