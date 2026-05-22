<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Test\Unit\CommercialPanel\Ui\DataProvider;

use GrupoAwamotos\AbandonedCart\Model\ResourceModel\AbandonedCart\CollectionFactory as AbandonedCartCollectionFactory;
use GrupoAwamotos\B2B\CommercialPanel\Api\PortfolioScopeInterface;
use GrupoAwamotos\B2B\CommercialPanel\Ui\DataProvider\AbandonedCartDataProvider;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GrupoAwamotos\B2B\CommercialPanel\Ui\DataProvider\AbandonedCartDataProvider
 */
class AbandonedCartDataProviderScopeTest extends TestCase
{
    private PortfolioScopeInterface&MockObject $portfolioScope;
    private ResourceConnection&MockObject $resourceConnection;
    private CustomerCollectionFactory&MockObject $customerCollectionFactory;
    private AbandonedCartCollectionFactory&MockObject $abandonedCartCollectionFactory;

    protected function setUp(): void
    {
        $this->portfolioScope = $this->createMock(PortfolioScopeInterface::class);
        $this->resourceConnection = $this->createMock(ResourceConnection::class);
        $this->customerCollectionFactory = $this->createMock(CustomerCollectionFactory::class);
        $this->abandonedCartCollectionFactory = $this->createMock(AbandonedCartCollectionFactory::class);
        $emptyCollection = $this->createMock(\GrupoAwamotos\AbandonedCart\Model\ResourceModel\AbandonedCart\Collection::class);
        $this->abandonedCartCollectionFactory->method('create')->willReturn($emptyCollection);
    }

    public function testSellerSeesOnlyPortfolioCarts(): void
    {
        $this->portfolioScope->method('canBypassPortfolioScope')->willReturn(false);
        $this->portfolioScope->method('getVisibleCustomerIds')->willReturn([10, 20]);

        $provider = $this->createProviderWithRows([
            ['entity_id' => 1, 'customer_id' => 10, 'cart_value' => 100],
            ['entity_id' => 2, 'customer_id' => 99, 'cart_value' => 200],
        ], filterCustomerIds: [10, 20]);

        $data = $provider->getData();

        $this->assertSame(1, $data['totalRecords']);
        $this->assertSame(10, $data['items'][0]['customer_id']);
    }

    public function testSupervisorSeesTeamCarts(): void
    {
        $this->portfolioScope->method('canBypassPortfolioScope')->willReturn(false);
        $this->portfolioScope->method('getVisibleCustomerIds')->willReturn([10, 20, 30]);

        $provider = $this->createProviderWithRows([
            ['entity_id' => 1, 'customer_id' => 10],
            ['entity_id' => 2, 'customer_id' => 20],
            ['entity_id' => 3, 'customer_id' => 99],
        ], filterCustomerIds: [10, 20, 30]);

        $data = $provider->getData();

        $this->assertSame(2, $data['totalRecords']);
    }

    public function testTiSeesAllCarts(): void
    {
        $this->portfolioScope->method('canBypassPortfolioScope')->willReturn(true);
        $this->portfolioScope->method('getVisibleCustomerIds')->willReturn([]);

        $provider = $this->createProviderWithRows([
            ['entity_id' => 1, 'customer_id' => 10],
            ['entity_id' => 2, 'customer_id' => 99],
        ], filterCustomerIds: null);

        $data = $provider->getData();

        $this->assertSame(2, $data['totalRecords']);
    }

    public function testSellerWithEmptyPortfolioSeesNothing(): void
    {
        $this->portfolioScope->method('canBypassPortfolioScope')->willReturn(false);
        $this->portfolioScope->method('getVisibleCustomerIds')->willReturn([]);

        $connection = $this->createMock(AdapterInterface::class);
        $this->resourceConnection->method('getConnection')->willReturn($connection);
        $this->resourceConnection->method('getTableName')->willReturn('grupoawamotos_abandoned_cart');
        $connection->method('isTableExists')->willReturn(true);

        $provider = new AbandonedCartDataProvider(
            'awa_commercial_abandoned_cart_listing_data_source',
            'entity_id',
            'entity_id',
            $this->abandonedCartCollectionFactory,
            $this->portfolioScope,
            $this->resourceConnection,
            $this->customerCollectionFactory
        );

        $data = $provider->getData();

        $this->assertSame(0, $data['totalRecords']);
        $this->assertSame([], $data['items']);
    }

    /**
     * @param array<int, array<string, mixed>> $allRows
     * @param int[]|null $filterCustomerIds
     */
    private function createProviderWithRows(array $allRows, ?array $filterCustomerIds): AbandonedCartDataProvider
    {
        $connection = $this->createMock(AdapterInterface::class);
        $select = $this->createMock(Select::class);

        $this->resourceConnection->method('getConnection')->willReturn($connection);
        $this->resourceConnection->method('getTableName')
            ->willReturnCallback(static fn (string $table): string => $table);

        $connection->method('isTableExists')->willReturn(true);
        $connection->method('select')->willReturn($select);
        $select->method('from')->willReturnSelf();
        $select->method('joinLeft')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $select->method('order')->willReturnSelf();

        $filteredRows = $allRows;
        if ($filterCustomerIds !== null) {
            $filteredRows = array_values(array_filter(
                $allRows,
                static fn (array $row): bool => in_array((int) $row['customer_id'], $filterCustomerIds, true)
            ));
        }

        $connection->method('fetchAll')->with($select)->willReturn($filteredRows);

        $customerCollection = $this->createMock(\Magento\Customer\Model\ResourceModel\Customer\Collection::class);
        $customerCollection->method('addFieldToFilter')->willReturnSelf();
        $customerCollection->method('addAttributeToSelect')->willReturnSelf();
        $customerCollection->method('getIterator')->willReturn(new \ArrayIterator([]));
        $this->customerCollectionFactory->method('create')->willReturn($customerCollection);

        return new AbandonedCartDataProvider(
            'awa_commercial_abandoned_cart_listing_data_source',
            'entity_id',
            'entity_id',
            $this->abandonedCartCollectionFactory,
            $this->portfolioScope,
            $this->resourceConnection,
            $this->customerCollectionFactory
        );
    }
}
