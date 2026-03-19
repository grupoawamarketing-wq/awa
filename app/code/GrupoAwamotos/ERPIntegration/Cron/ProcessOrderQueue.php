<?php
declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Cron;

use GrupoAwamotos\ERPIntegration\Model\Queue\OrderSyncConsumer;
use GrupoAwamotos\ERPIntegration\Api\Data\OrderSyncMessageInterfaceFactory;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Serialize\SerializerInterface;
use Psr\Log\LoggerInterface;

/**
 * Cron job to process ERP order sync queue
 *
 * Uses direct database access to process queue messages
 * instead of ConsumerFactory to avoid class generation issues
 */
class ProcessOrderQueue
{
    private const MAX_MESSAGES_PER_RUN = 50;
    private const RETRY_MAX_MESSAGES = 20;
    private const QUEUE_NAME = 'erp.order.sync.queue';
    private const RETRY_QUEUE_NAME = 'erp.order.sync.retry.queue';

    /**
     * @var OrderSyncConsumer
     */
    private OrderSyncConsumer $consumer;

    /**
     * @var Helper
     */
    private Helper $helper;

    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resourceConnection;

    /**
     * @var SerializerInterface
     */
    private SerializerInterface $serializer;

    /**
     * @var OrderSyncMessageInterfaceFactory
     */
    private OrderSyncMessageInterfaceFactory $messageFactory;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param OrderSyncConsumer $consumer
     * @param Helper $helper
     * @param ResourceConnection $resourceConnection
     * @param SerializerInterface $serializer
     * @param OrderSyncMessageInterfaceFactory $messageFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        OrderSyncConsumer $consumer,
        Helper $helper,
        ResourceConnection $resourceConnection,
        SerializerInterface $serializer,
        OrderSyncMessageInterfaceFactory $messageFactory,
        LoggerInterface $logger
    ) {
        $this->consumer = $consumer;
        $this->helper = $helper;
        $this->resourceConnection = $resourceConnection;
        $this->serializer = $serializer;
        $this->messageFactory = $messageFactory;
        $this->logger = $logger;
    }

    /**
     * Process pending orders in the queue
     *
     * @return void
     */
    public function execute(): void
    {
        if (!$this->helper->isEnabled() || !$this->helper->isOrderQueueEnabled()) {
            return;
        }

        $this->logger->info('[ERP Cron] Starting order queue processing');

        $processedMain = 0;
        $processedRetry = 0;
        $errors = 0;

        try {
            // Process main queue
            $processedMain = $this->processQueue(self::QUEUE_NAME, self::MAX_MESSAGES_PER_RUN, false);
        } catch (\Exception $e) {
            $this->logger->error('[ERP Cron] Error processing main queue: ' . $e->getMessage());
            $errors++;
        }

        try {
            // Process retry queue with lower priority
            $processedRetry = $this->processQueue(self::RETRY_QUEUE_NAME, self::RETRY_MAX_MESSAGES, true);
        } catch (\Exception $e) {
            $this->logger->error('[ERP Cron] Error processing retry queue: ' . $e->getMessage());
            $errors++;
        }

        $this->logger->info('[ERP Cron] Order queue processing completed', [
            'main_processed' => $processedMain,
            'retry_processed' => $processedRetry,
            'errors' => $errors,
        ]);
    }

    /**
     * Process messages from a specific queue
     *
     * @param string $queueName
     * @param int $maxMessages
     * @param bool $isRetry
     * @return int Number of processed messages
     */
    private function processQueue(string $queueName, int $maxMessages, bool $isRetry): int
    {
        $connection = $this->resourceConnection->getConnection();
        $queueTable = $this->resourceConnection->getTableName('queue_message');
        $queueStatusTable = $this->resourceConnection->getTableName('queue_message_status');

        // Get pending messages
        $select = $connection->select()
            ->from(['qm' => $queueTable], ['id', 'body'])
            ->join(
                ['qms' => $queueStatusTable],
                'qm.id = qms.message_id',
                ['status_id' => 'id']
            )
            ->where('qms.status = ?', 2) // 2 = NEW
            ->where('qm.topic_name = ?', $isRetry ? 'erp.order.sync.retry' : 'erp.order.sync')
            ->order('qm.id ASC')
            ->limit($maxMessages);

        $messages = $connection->fetchAll($select);
        $processed = 0;

        foreach ($messages as $messageData) {
            try {
                // Decode message - Magento uses JSON serialization for queue messages
                $body = $messageData['body'];

                try {
                    $decodedBody = $this->serializer->unserialize($body);
                } catch (\Exception $e) {
                    // Fallback to json_decode if serializer fails
                    $decodedBody = json_decode($body, true);
                }

                if (!$decodedBody || !is_array($decodedBody)) {
                    $this->logger->warning('[ERP Cron] Invalid message body', [
                        'message_id' => $messageData['id'],
                        'body' => substr($body, 0, 200)
                    ]);
                    $this->markMessageComplete($connection, $queueStatusTable, (int)$messageData['status_id']);
                    continue;
                }

                // Create message object - Magento MessageEncoder uses snake_case keys
                $message = $this->messageFactory->create();
                $message->setOrderId((int)($decodedBody['order_id'] ?? $decodedBody['orderId'] ?? 0));
                $message->setIncrementId((string)($decodedBody['increment_id'] ?? $decodedBody['incrementId'] ?? ''));
                $message->setRetryCount((int)($decodedBody['retry_count'] ?? $decodedBody['retryCount'] ?? 0));
                $message->setQueuedAt((string)($decodedBody['queued_at'] ?? $decodedBody['queuedAt'] ?? date('Y-m-d H:i:s')));
                $message->setLastError($decodedBody['last_error'] ?? $decodedBody['lastError'] ?? null);

                // Process using our consumer
                if ($isRetry) {
                    $this->consumer->processRetry($message);
                } else {
                    $this->consumer->process($message);
                }

                // Mark as complete
                $this->markMessageComplete($connection, $queueStatusTable, (int)$messageData['status_id']);
                $processed++;

            } catch (\Exception $e) {
                $this->logger->error('[ERP Cron] Error processing message', [
                    'message_id' => $messageData['id'],
                    'error' => $e->getMessage(),
                ]);
                // Mark as error (status 4)
                $this->markMessageError($connection, $queueStatusTable, (int)$messageData['status_id']);
            }
        }

        return $processed;
    }

    /**
     * Mark message as complete
     *
     * @param \Magento\Framework\DB\Adapter\AdapterInterface $connection
     * @param string $table
     * @param int $statusId
     * @return void
     */
    private function markMessageComplete($connection, string $table, int $statusId): void
    {
        $connection->update(
            $table,
            ['status' => 4, 'updated_at' => date('Y-m-d H:i:s')], // 4 = COMPLETE
            ['id = ?' => $statusId]
        );
    }

    /**
     * Mark message as error
     *
     * @param \Magento\Framework\DB\Adapter\AdapterInterface $connection
     * @param string $table
     * @param int $statusId
     * @return void
     */
    private function markMessageError($connection, string $table, int $statusId): void
    {
        $connection->update(
            $table,
            [
                'status' => 3, // 3 = ERROR, will be retried
                'number_of_trials' => new \Magento\Framework\DB\Sql\Expression('number_of_trials + 1'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            ['id = ?' => $statusId]
        );
    }
}
