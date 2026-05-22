<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Test\Unit\CommercialPanel\Model;

use GrupoAwamotos\B2B\CommercialPanel\Api\CommercialTaskManagementInterface;
use GrupoAwamotos\B2B\CommercialPanel\Model\Config\TaskConfig;
use GrupoAwamotos\B2B\CommercialPanel\Model\TaskGenerator;
use GrupoAwamotos\B2B\CommercialPanel\Model\TaskType;
use GrupoAwamotos\B2B\Model\ResourceModel\CustomerAttendant\CollectionFactory as CustomerAttendantCollectionFactory;
use GrupoAwamotos\B2B\Model\ResourceModel\QuoteRequest\CollectionFactory as QuoteRequestCollectionFactory;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \GrupoAwamotos\B2B\CommercialPanel\Model\TaskGenerator
 */
class TaskGeneratorTest extends TestCase
{
    private CommercialTaskManagementInterface&MockObject $taskManagement;
    private TaskConfig&MockObject $taskConfig;
    private ResourceConnection&MockObject $resourceConnection;
    private TaskGenerator $generator;

    protected function setUp(): void
    {
        $this->taskManagement = $this->createMock(CommercialTaskManagementInterface::class);
        $this->taskConfig = $this->createMock(TaskConfig::class);
        $this->resourceConnection = $this->createMock(ResourceConnection::class);

        $this->taskConfig->method('getDaysNoPurchase')->willReturn(90);
        $this->taskConfig->method('getDaysNewCustomerNoContact')->willReturn(3);
        $this->taskConfig->method('getDaysPendingNoContact')->willReturn(1);
        $this->taskConfig->method('getDaysQuoteNoResponse')->willReturn(2);

        $customerAttendantCollectionFactory = $this->createMock(CustomerAttendantCollectionFactory::class);
        $emptyCollection = new \Magento\Framework\Data\Collection(
            $this->createMock(\Magento\Framework\Data\Collection\EntityFactory::class)
        );
        $customerAttendantCollectionFactory->method('create')->willReturn($emptyCollection);

        $quoteCollectionFactory = $this->createMock(QuoteRequestCollectionFactory::class);
        $quoteCollection = $this->createMock(\GrupoAwamotos\B2B\Model\ResourceModel\QuoteRequest\Collection::class);
        $quoteCollection->method('addFieldToFilter')->willReturnSelf();
        $quoteCollection->method('getIterator')->willReturn(new \ArrayIterator([]));
        $quoteCollectionFactory->method('create')->willReturn($quoteCollection);

        $this->generator = new TaskGenerator(
            $this->taskManagement,
            $this->taskConfig,
            $customerAttendantCollectionFactory,
            $this->createMock(OrderCollectionFactory::class),
            $this->createMock(CustomerCollectionFactory::class),
            $quoteCollectionFactory,
            $this->resourceConnection,
            $this->createMock(LoggerInterface::class)
        );
    }

    public function testGenerateAllDoesNotDuplicateWhenDedupExists(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $this->resourceConnection->method('getConnection')->willReturn($connection);
        $this->resourceConnection->method('getTableName')
            ->willReturnCallback(static fn (string $table): string => $table);

        $connection->method('isTableExists')->willReturn(false);

        $this->taskManagement->expects($this->never())->method('createAutomatic');

        $result = $this->generator->generateAll();

        $this->assertSame(0, $result['created']);
    }

    public function testExistsByDedupKeyPreventsSecondCreation(): void
    {
        $dedupKey = sprintf('%s:%d:%s', TaskType::NO_PURCHASE, 42, date('Y-m'));

        $this->taskManagement->method('existsByDedupKey')
            ->with($dedupKey)
            ->willReturn(true);

        $this->taskManagement->expects($this->never())->method('createAutomatic');

        $this->assertTrue($this->taskManagement->existsByDedupKey($dedupKey));
    }

    public function testCreateAutomaticReturnsNullOnDuplicate(): void
    {
        $dedupKey = 'abandoned_cart:42:100:' . date('Y-m');

        $this->taskManagement->method('existsByDedupKey')->with($dedupKey)->willReturn(true);
        $this->taskManagement->method('createAutomatic')->willReturn(null);

        $result = $this->taskManagement->createAutomatic([
            'dedup_key' => $dedupKey,
            'customer_id' => 42,
            'attendant_id' => 7,
            'task_type' => TaskType::ABANDONED_CART,
            'title' => 'Teste',
        ]);

        $this->assertNull($result);
    }
}
