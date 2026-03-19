<?php
declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Test\Unit\Model\Queue;

use GrupoAwamotos\ERPIntegration\Api\Data\OrderSyncMessageInterface;
use GrupoAwamotos\ERPIntegration\Api\OrderSyncInterface;
use GrupoAwamotos\ERPIntegration\Model\CircuitBreaker;
use GrupoAwamotos\ERPIntegration\Model\Queue\OrderSyncConsumer;
use GrupoAwamotos\ERPIntegration\Model\Queue\OrderSyncPublisher;
use GrupoAwamotos\ERPIntegration\Model\ResourceModel\SyncLog;
use Magento\Sales\Api\OrderRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class OrderSyncConsumerTest extends TestCase
{
    private OrderSyncInterface&MockObject $orderSync;
    private OrderSyncPublisher&MockObject $publisher;
    private OrderRepositoryInterface&MockObject $orderRepository;
    private CircuitBreaker&MockObject $circuitBreaker;
    private SyncLog&MockObject $syncLog;
    private LoggerInterface&MockObject $logger;
    private OrderSyncConsumer $consumer;

    protected function setUp(): void
    {
        $this->orderSync = $this->createMock(OrderSyncInterface::class);
        $this->publisher = $this->createMock(OrderSyncPublisher::class);
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->circuitBreaker = $this->createMock(CircuitBreaker::class);
        $this->syncLog = $this->createMock(SyncLog::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->consumer = new OrderSyncConsumer(
            $this->orderSync,
            $this->publisher,
            $this->orderRepository,
            $this->circuitBreaker,
            $this->syncLog,
            $this->logger
        );
    }

    public function testProcessRetrySchedulesLegacyMessageWithoutProcessingImmediately(): void
    {
        $message = $this->createMock(OrderSyncMessageInterface::class);
        $message->method('getOrderId')->willReturn(11);
        $message->method('getIncrementId')->willReturn('100000011');
        $message->method('getRetryCount')->willReturn(2);
        $message->method('getLastError')->willReturn('falha de conexao');

        $this->publisher->expects($this->once())
            ->method('scheduleRetry')
            ->with($message, 'falha de conexao', 2)
            ->willReturn(true);

        $this->orderRepository->expects($this->never())->method('get');
        $this->orderSync->expects($this->never())->method('sendOrder');

        $this->consumer->processRetry($message);
    }
}