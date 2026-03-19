<?php
declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Test\Unit\Model\Queue;

use GrupoAwamotos\ERPIntegration\Api\Data\OrderSyncMessageInterface;
use GrupoAwamotos\ERPIntegration\Model\Queue\OrderRetryScheduler;
use GrupoAwamotos\ERPIntegration\Model\ResourceModel\OrderRetrySchedule;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class OrderRetrySchedulerTest extends TestCase
{
    private OrderRetrySchedule&MockObject $resource;
    private LoggerInterface&MockObject $logger;
    private OrderRetryScheduler $scheduler;

    protected function setUp(): void
    {
        $this->resource = $this->createMock(OrderRetrySchedule::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->scheduler = new OrderRetryScheduler($this->resource, $this->logger);
    }

    public function testSchedulePersistsRetryWithBackoff(): void
    {
        $message = $this->createMock(OrderSyncMessageInterface::class);
        $message->method('getOrderId')->willReturn(10);
        $message->method('getIncrementId')->willReturn('100000010');

        $this->resource->expects($this->once())
            ->method('scheduleRetry')
            ->with(
                10,
                '100000010',
                3,
                'erro temporario',
                $this->callback(static function (string $dateTime): bool {
                    return strtotime($dateTime) >= time() + 119;
                })
            );

        $nextAttemptAt = $this->scheduler->schedule($message, 3, 'erro temporario');

        $this->assertNotSame('', $nextAttemptAt);
        $this->assertSame(120, $this->scheduler->getRetryDelay(3));
    }
}