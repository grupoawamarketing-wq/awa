<?php
declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Model\Queue;

use GrupoAwamotos\ERPIntegration\Api\Data\OrderSyncMessageInterface;
use GrupoAwamotos\ERPIntegration\Api\Data\OrderSyncMessageInterfaceFactory;
use GrupoAwamotos\ERPIntegration\Model\ResourceModel\SyncLog as SyncLogResource;
use Magento\Framework\MessageQueue\PublisherInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Psr\Log\LoggerInterface;

/**
 * Publisher for Order Sync Queue Messages
 *
 * Publishes orders to the message queue for asynchronous processing.
 */
class OrderSyncPublisher
{
    private const TOPIC_ORDER_SYNC = 'erp.order.sync';
    private const TOPIC_ORDER_RETRY = 'erp.order.sync.retry';
    private const MAX_RETRIES = 5;

    private PublisherInterface $publisher;
    private OrderSyncMessageInterfaceFactory $messageFactory;
    private SyncLogResource $syncLogResource;
    private LoggerInterface $logger;

    public function __construct(
        PublisherInterface $publisher,
        OrderSyncMessageInterfaceFactory $messageFactory,
        SyncLogResource $syncLogResource,
        LoggerInterface $logger
    ) {
        $this->publisher = $publisher;
        $this->messageFactory = $messageFactory;
        $this->syncLogResource = $syncLogResource;
        $this->logger = $logger;
    }

    /**
     * Publish order to sync queue
     */
    public function publish(OrderInterface $order): bool
    {
        try {
            $message = $this->createMessage($order);

            $this->publisher->publish(self::TOPIC_ORDER_SYNC, $message);

            $this->logger->info('[ERP Queue] Order published to sync queue', [
                'order_id' => $order->getEntityId(),
                'increment_id' => $order->getIncrementId(),
            ]);

            // Log queue event
            $this->syncLogResource->addLog(
                'order',
                'queue',
                'queued',
                sprintf('Pedido %s enviado para fila de sincronização', $order->getIncrementId()),
                null,
                (int) $order->getEntityId()
            );

            return true;
        } catch (\Exception $e) {
            $this->logger->error('[ERP Queue] Failed to publish order to queue', [
                'order_id' => $order->getEntityId(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Publish order to retry queue
     */
    public function publishForRetry(OrderSyncMessageInterface $originalMessage, string $error): bool
    {
        $retryCount = $originalMessage->getRetryCount() + 1;

        if ($retryCount > self::MAX_RETRIES) {
            $this->logger->error('[ERP Queue] Order exceeded max retry attempts', [
                'order_id' => $originalMessage->getOrderId(),
                'increment_id' => $originalMessage->getIncrementId(),
                'retry_count' => $retryCount,
                'last_error' => $error,
            ]);

            // Log final failure
            $this->syncLogResource->addLog(
                'order',
                'queue',
                'max_retries_exceeded',
                sprintf(
                    'Pedido %s excedeu máximo de %d tentativas. Último erro: %s',
                    $originalMessage->getIncrementId(),
                    self::MAX_RETRIES,
                    substr($error, 0, 200)
                ),
                null,
                $originalMessage->getOrderId()
            );

            return false;
        }

        try {
            /** @var OrderSyncMessageInterface $retryMessage */
            $retryMessage = $this->messageFactory->create();
            $retryMessage->setOrderId($originalMessage->getOrderId());
            $retryMessage->setIncrementId($originalMessage->getIncrementId());
            $retryMessage->setRetryCount($retryCount);
            $retryMessage->setQueuedAt(date('Y-m-d H:i:s'));
            $retryMessage->setLastError($error);

            $this->publisher->publish(self::TOPIC_ORDER_RETRY, $retryMessage);

            $this->logger->info('[ERP Queue] Order published to retry queue', [
                'order_id' => $originalMessage->getOrderId(),
                'increment_id' => $originalMessage->getIncrementId(),
                'retry_count' => $retryCount,
            ]);

            // Log retry event
            $this->syncLogResource->addLog(
                'order',
                'queue',
                'retry_queued',
                sprintf(
                    'Pedido %s reenfileirado (tentativa %d/%d). Erro: %s',
                    $originalMessage->getIncrementId(),
                    $retryCount,
                    self::MAX_RETRIES,
                    substr($error, 0, 100)
                ),
                null,
                $originalMessage->getOrderId()
            );

            return true;
        } catch (\Exception $e) {
            $this->logger->error('[ERP Queue] Failed to publish order to retry queue', [
                'order_id' => $originalMessage->getOrderId(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Create message from order
     */
    private function createMessage(OrderInterface $order): OrderSyncMessageInterface
    {
        /** @var OrderSyncMessageInterface $message */
        $message = $this->messageFactory->create();
        $message->setOrderId((int) $order->getEntityId());
        $message->setIncrementId($order->getIncrementId());
        $message->setRetryCount(0);
        $message->setQueuedAt(date('Y-m-d H:i:s'));
        $message->setLastError(null);

        return $message;
    }

    /**
     * Get max retry count
     */
    public function getMaxRetries(): int
    {
        return self::MAX_RETRIES;
    }
}
