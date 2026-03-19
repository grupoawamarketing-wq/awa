<?php
declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Model\Queue;

use GrupoAwamotos\ERPIntegration\Api\Data\OrderSyncMessageInterface;
use GrupoAwamotos\ERPIntegration\Api\OrderSyncInterface;
use GrupoAwamotos\ERPIntegration\Model\CircuitBreaker;
use GrupoAwamotos\ERPIntegration\Model\CircuitBreakerOpenException;
use GrupoAwamotos\ERPIntegration\Model\ResourceModel\SyncLog as SyncLogResource;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Consumer for Order Sync Queue Messages
 *
 * Processes orders from the queue and sends them to the ERP.
 */
class OrderSyncConsumer
{
    private OrderSyncInterface $orderSync;
    private OrderSyncPublisher $publisher;
    private OrderRepositoryInterface $orderRepository;
    private CircuitBreaker $circuitBreaker;
    private SyncLogResource $syncLogResource;
    private LoggerInterface $logger;

    public function __construct(
        OrderSyncInterface $orderSync,
        OrderSyncPublisher $publisher,
        OrderRepositoryInterface $orderRepository,
        CircuitBreaker $circuitBreaker,
        SyncLogResource $syncLogResource,
        LoggerInterface $logger
    ) {
        $this->orderSync = $orderSync;
        $this->publisher = $publisher;
        $this->orderRepository = $orderRepository;
        $this->circuitBreaker = $circuitBreaker;
        $this->syncLogResource = $syncLogResource;
        $this->logger = $logger;
    }

    /**
     * Process order sync message from main queue
     */
    public function process(OrderSyncMessageInterface $message): void
    {
        $orderId = $message->getOrderId();
        $incrementId = $message->getIncrementId();

        $this->logger->info('[ERP Queue Consumer] Processing order', [
            'order_id' => $orderId,
            'increment_id' => $incrementId,
            'retry_count' => $message->getRetryCount(),
        ]);

        try {
            // Check circuit breaker before processing
            if (!$this->circuitBreaker->isAvailable()) {
                $stats = $this->circuitBreaker->getStats();
                $this->logger->warning('[ERP Queue Consumer] Circuit breaker is open, requeuing order', [
                    'order_id' => $orderId,
                    'circuit_state' => $stats['state'],
                    'time_until_half_open' => $stats['time_until_half_open'],
                ]);

                // Requeue for later processing
                $this->publisher->publishForRetry(
                    $message,
                    sprintf('Circuit breaker is open. Retry in %d seconds.', $stats['time_until_half_open'])
                );
                return;
            }

            // Load order
            $order = $this->orderRepository->get($orderId);

            // Send to ERP
            $result = $this->orderSync->sendOrder($order);

            if ($result['success']) {
                $this->logger->info('[ERP Queue Consumer] Order synced successfully', [
                    'order_id' => $orderId,
                    'increment_id' => $incrementId,
                    'erp_order_id' => $result['erp_order_id'],
                ]);

                // Update order with comment
                $order->addCommentToStatusHistory(
                    __('Pedido sincronizado com ERP via fila. ID ERP: %1', $result['erp_order_id'])
                );
                $this->orderRepository->save($order);

                // Log success
                $this->syncLogResource->addLog(
                    'order',
                    'queue',
                    'success',
                    sprintf(
                        'Pedido %s sincronizado com sucesso. ID ERP: %d',
                        $incrementId,
                        $result['erp_order_id']
                    ),
                    (string) $result['erp_order_id'],
                    $orderId
                );
            } else {
                // Check if error is retryable (e.g. permission denied is NOT retryable)
                $retryable = $result['retryable'] ?? true;

                if ($retryable) {
                    $this->logger->warning('[ERP Queue Consumer] Order sync failed, requeuing', [
                        'order_id' => $orderId,
                        'increment_id' => $incrementId,
                        'error' => $result['message'],
                    ]);
                    $this->publisher->publishForRetry($message, $result['message']);
                } else {
                    $this->logger->error('[ERP Queue Consumer] Non-retryable error, discarding', [
                        'order_id' => $orderId,
                        'increment_id' => $incrementId,
                        'error' => $result['message'],
                    ]);
                }
            }
        } catch (CircuitBreakerOpenException $e) {
            $this->logger->warning('[ERP Queue Consumer] Circuit breaker triggered, requeuing', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
            $this->publisher->publishForRetry($message, $e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error('[ERP Queue Consumer] Error processing order', [
                'order_id' => $orderId,
                'increment_id' => $incrementId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Requeue for retry
            $this->publisher->publishForRetry($message, $e->getMessage());
        }
    }

    /**
     * Process order sync message from retry queue.
     *
     * Legacy retry messages are migrated to the local schedule so workers never
     * block waiting for backoff to expire.
     */
    public function processRetry(OrderSyncMessageInterface $message): void
    {
        $this->logger->info('[ERP Queue Consumer] Migrating legacy retry message to scheduled retry', [
            'order_id' => $message->getOrderId(),
            'increment_id' => $message->getIncrementId(),
            'retry_count' => $message->getRetryCount(),
            'last_error' => $message->getLastError(),
        ]);

        $this->publisher->scheduleRetry(
            $message,
            $message->getLastError() ?? 'Retry legado migrado da fila para agenda local.',
            max(1, $message->getRetryCount())
        );
    }
}
