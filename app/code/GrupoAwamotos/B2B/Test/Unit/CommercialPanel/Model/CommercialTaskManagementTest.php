<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Test\Unit\CommercialPanel\Model;

use GrupoAwamotos\B2B\CommercialPanel\Api\Data\CommercialTaskInterface;
use GrupoAwamotos\B2B\CommercialPanel\Api\PortfolioScopeInterface;
use GrupoAwamotos\B2B\CommercialPanel\Model\CommercialTask;
use GrupoAwamotos\B2B\CommercialPanel\Model\CommercialTaskFactory;
use GrupoAwamotos\B2B\CommercialPanel\Model\CommercialTaskManagement;
use GrupoAwamotos\B2B\CommercialPanel\Model\ResourceModel\CommercialTask\Collection;
use GrupoAwamotos\B2B\CommercialPanel\Model\ResourceModel\CommercialTask\CollectionFactory as TaskCollectionFactory;
use GrupoAwamotos\B2B\CommercialPanel\Model\ResourceModel\CommercialTaskResource;
use GrupoAwamotos\B2B\Helper\CurrentAttendant;
use GrupoAwamotos\B2B\Model\ResourceModel\CustomerAttendant\CollectionFactory as CustomerAttendantCollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \GrupoAwamotos\B2B\CommercialPanel\Model\CommercialTaskManagement
 */
class CommercialTaskManagementTest extends TestCase
{
    private PortfolioScopeInterface&MockObject $portfolioScope;
    private CurrentAttendant&MockObject $currentAttendant;
    private CustomerAttendantCollectionFactory&MockObject $customerAttendantCollectionFactory;
    private CommercialTaskFactory&MockObject $taskFactory;
    private CommercialTaskResource&MockObject $taskResource;
    private TaskCollectionFactory&MockObject $taskCollectionFactory;
    private CommercialTaskManagement $service;

    protected function setUp(): void
    {
        $this->portfolioScope = $this->createMock(PortfolioScopeInterface::class);
        $this->currentAttendant = $this->createMock(CurrentAttendant::class);
        $this->customerAttendantCollectionFactory = $this->createMock(CustomerAttendantCollectionFactory::class);
        $this->taskFactory = $this->createMock(CommercialTaskFactory::class);
        $this->taskResource = $this->createMock(CommercialTaskResource::class);
        $this->taskCollectionFactory = $this->createMock(TaskCollectionFactory::class);

        $this->service = new CommercialTaskManagement(
            $this->portfolioScope,
            $this->currentAttendant,
            $this->customerAttendantCollectionFactory,
            $this->taskFactory,
            $this->taskResource,
            $this->taskCollectionFactory,
            $this->createMock(LoggerInterface::class)
        );
    }

    public function testCreateManualSuccess(): void
    {
        $this->portfolioScope->method('canAccessCustomer')->with(42)->willReturn(true);
        $this->currentAttendant->method('getId')->willReturn(7);

        $task = $this->createMock(CommercialTask::class);
        $task->method('setCustomerId')->willReturnSelf();
        $task->method('setAttendantId')->willReturnSelf();
        $task->method('setTaskType')->willReturnSelf();
        $task->method('setPriority')->willReturnSelf();
        $task->method('setStatus')->willReturnSelf();
        $task->method('setTitle')->willReturnSelf();
        $task->method('setObservation')->willReturnSelf();
        $task->method('setDedupKey')->willReturnSelf();
        $task->method('setDueAt')->willReturnSelf();
        $task->method('setData')->willReturnSelf();
        $task->method('getCustomerId')->willReturn(42);
        $task->method('getTitle')->willReturn('Ligar para cliente');

        $this->taskFactory->method('create')->willReturn($task);
        $this->taskResource->expects($this->once())->method('save')->with($task);

        $result = $this->service->createManual([
            'customer_id' => 42,
            'title' => 'Ligar para cliente',
            'observation' => 'Retorno combinado',
            'priority' => 'high',
        ], 99);

        $this->assertSame(42, $result->getCustomerId());
    }

    public function testCreateManualDeniedOutsidePortfolio(): void
    {
        $this->portfolioScope->method('canAccessCustomer')->with(42)->willReturn(false);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Cliente fora da sua carteira comercial.');

        $this->service->createManual([
            'customer_id' => 42,
            'title' => 'Teste',
        ], 1);
    }

    public function testCreateAutomaticSkipsWhenDedupExists(): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('setPageSize')->willReturnSelf();
        $collection->method('getSize')->willReturn(1);

        $this->taskCollectionFactory->method('create')->willReturn($collection);

        $result = $this->service->createAutomatic([
            'dedup_key' => 'no_purchase:42:2026-05',
            'customer_id' => 42,
            'attendant_id' => 7,
            'task_type' => 'no_purchase',
            'title' => 'Teste',
        ]);

        $this->assertNull($result);
    }

    public function testCompleteTaskSuccess(): void
    {
        $this->portfolioScope->method('canAccessCustomer')->with(42)->willReturn(true);

        $task = $this->createMock(CommercialTask::class);
        $task->method('getTaskId')->willReturn(10);
        $task->method('getCustomerId')->willReturn(42);
        $task->method('getStatus')->willReturn('open');
        $task->method('setStatus')->willReturnSelf();
        $task->method('setCompletedAt')->willReturnSelf();

        $this->taskFactory->method('create')->willReturn($task);
        $this->taskResource->expects($this->once())->method('load')->with($task, 10);
        $this->taskResource->expects($this->once())->method('save')->with($task);

        $result = $this->service->complete(10, 99);
        $this->assertSame(42, $result->getCustomerId());
    }

    public function testRescheduleTaskSuccess(): void
    {
        $this->portfolioScope->method('canAccessCustomer')->with(42)->willReturn(true);

        $task = $this->createMock(CommercialTask::class);
        $task->method('getTaskId')->willReturn(10);
        $task->method('getCustomerId')->willReturn(42);
        $task->method('getStatus')->willReturn('open');
        $task->method('setDueAt')->willReturnSelf();
        $task->method('setStatus')->willReturnSelf();

        $this->taskFactory->method('create')->willReturn($task);
        $this->taskResource->method('load')->with($task, 10);
        $this->taskResource->expects($this->once())->method('save')->with($task);

        $this->service->reschedule(10, '2026-06-01 10:00:00', 99);
    }

    public function testCompleteTaskDeniedOutsideScope(): void
    {
        $task = $this->createMock(CommercialTask::class);
        $task->method('getTaskId')->willReturn(10);
        $task->method('getCustomerId')->willReturn(42);

        $this->taskFactory->method('create')->willReturn($task);
        $this->taskResource->method('load')->with($task, 10);
        $this->portfolioScope->method('canAccessCustomer')->with(42)->willReturn(false);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Tarefa fora do seu escopo comercial.');

        $this->service->complete(10, 99);
    }
}
