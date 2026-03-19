<?php
declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Test\Unit\Model\Api;

use GrupoAwamotos\ERPIntegration\Api\ConnectionInterface;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use GrupoAwamotos\ERPIntegration\Model\Api\OrderPullManagement;
use GrupoAwamotos\ERPIntegration\Model\B2BClientRegistration;
use GrupoAwamotos\ERPIntegration\Model\CnpjResolver;
use GrupoAwamotos\ERPIntegration\Model\CustomerSync;
use GrupoAwamotos\ERPIntegration\Model\ResourceModel\SyncLog as SyncLogResource;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class OrderPullManagementTest extends TestCase
{
    private ConnectionInterface&MockObject $connection;
    private Helper&MockObject $helper;
    private CustomerSync&MockObject $customerSync;
    private B2BClientRegistration&MockObject $b2bRegistration;
    private OrderRepositoryInterface&MockObject $orderRepository;
    private SearchCriteriaBuilder&MockObject $searchCriteriaBuilder;
    private SortOrderBuilder&MockObject $sortOrderBuilder;
    private SyncLogResource&MockObject $syncLogResource;
    private CustomerRepositoryInterface&MockObject $customerRepository;
    private RegionFactory&MockObject $regionFactory;
    private CnpjResolver $cnpjResolver;
    private LoggerInterface&MockObject $logger;
    private SearchCriteria&MockObject $searchCriteria;
    private SortOrder&MockObject $sortOrder;
    private SearchResultInterface&MockObject $searchResult;
    private OrderPullManagement $subject;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(ConnectionInterface::class);
        $this->helper = $this->createMock(Helper::class);
        $this->customerSync = $this->createMock(CustomerSync::class);
        $this->b2bRegistration = $this->createMock(B2BClientRegistration::class);
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $this->sortOrderBuilder = $this->createMock(SortOrderBuilder::class);
        $this->syncLogResource = $this->createMock(SyncLogResource::class);
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->regionFactory = $this->createMock(RegionFactory::class);
        $this->cnpjResolver = new CnpjResolver();
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->searchCriteria = $this->createMock(SearchCriteria::class);
        $this->sortOrder = $this->createMock(SortOrder::class);
        $this->searchResult = $this->createMock(SearchResultInterface::class);

        $this->searchCriteriaBuilder->method('addFilter')->willReturnSelf();
        $this->searchCriteriaBuilder->method('setSortOrders')->willReturnSelf();
        $this->searchCriteriaBuilder->method('setPageSize')->willReturnSelf();
        $this->searchCriteriaBuilder->method('create')->willReturn($this->searchCriteria);
        $this->sortOrderBuilder->method('setField')->willReturnSelf();
        $this->sortOrderBuilder->method('setAscendingDirection')->willReturnSelf();
        $this->sortOrderBuilder->method('create')->willReturn($this->sortOrder);
        $this->orderRepository->method('getList')->willReturn($this->searchResult);
        $this->syncLogResource->method('getConnection')->willThrowException(new \RuntimeException('sem db local para teste'));
        $this->helper->method('getStockFilial')->willReturn(1);

        $this->subject = new OrderPullManagement(
            $this->connection,
            $this->helper,
            $this->customerSync,
            $this->b2bRegistration,
            $this->orderRepository,
            $this->searchCriteriaBuilder,
            $this->sortOrderBuilder,
            $this->syncLogResource,
            $this->customerRepository,
            $this->regionFactory,
            $this->cnpjResolver,
            $this->logger
        );
    }

    public function testGetPendingOrdersHoldsUnregisteredClientWhenWriteAccessIsUnavailable(): void
    {
        $order = $this->createOrderMock('100000001', '999', 'Cliente Teste');

        $this->searchResult->method('getItems')->willReturn([$order]);
        $this->helper->method('getOrderImportMode')->willReturn('api_pull');
        $this->helper->method('requiresSectraClientRegistration')->willReturn(true);
        $this->b2bRegistration->method('hasWriteAccess')->willReturn(false);
        $this->b2bRegistration->method('isClientRegistered')->with(999)->willReturn(false);
        $this->b2bRegistration->expects($this->never())->method('registerClient');
        $this->syncLogResource->expects($this->once())->method('addLog');

        $result = $this->subject->getPendingOrders(10);

        $this->assertSame(0, $result[0]['total_count']);
        $this->assertSame(1, $result[0]['held_count']);
        $this->assertSame('100000001', $result[0]['held_orders'][0]['increment_id']);
        $this->assertSame(999, $result[0]['held_orders'][0]['erp_code']);
        $this->assertStringContainsString('sem conexao de escrita', $result[0]['held_orders'][0]['reason']);
    }

    public function testGetPendingOrdersIncludesOrderAfterSuccessfulAutoRegistration(): void
    {
        $order = $this->createOrderMock('100000002', '1001', 'Cliente Registrado');

        $this->searchResult->method('getItems')->willReturn([$order]);
        $this->helper->method('getOrderImportMode')->willReturn('api_pull');
        $this->helper->method('requiresSectraClientRegistration')->willReturn(true);
        $this->b2bRegistration->method('hasWriteAccess')->willReturn(true);
        $this->b2bRegistration->method('isClientRegistered')->with(1001)->willReturn(false);
        $this->b2bRegistration->expects($this->once())->method('registerClient')->with(1001)->willReturn(true);
        $this->connection->method('fetchOne')->willReturn([]);
        $this->syncLogResource->expects($this->once())->method('addLog');

        $result = $this->subject->getPendingOrders(10);

        $this->assertSame(1, $result[0]['total_count']);
        $this->assertSame(0, $result[0]['held_count']);
        $this->assertSame('100000002', $result[0]['orders'][0]['increment_id']);
        $this->assertSame(1001, $result[0]['orders'][0]['customer']['erp_code']);
        $this->assertTrue($result[0]['orders'][0]['customer']['registered_in_b2b']);
    }

    public function testGetPendingOrdersDoesNotHoldOrderForSectraRegistrationInOpenCartBridgeMode(): void
    {
        $order = $this->createOrderMock('100000003', '1002', 'Cliente Bridge');

        $this->searchResult->method('getItems')->willReturn([$order]);
        $this->helper->method('getOrderImportMode')->willReturn('opencart_bridge');
        $this->helper->method('requiresSectraClientRegistration')->willReturn(false);
        $this->b2bRegistration->method('hasWriteAccess')->willReturn(false);
        $this->b2bRegistration->expects($this->never())->method('isClientRegistered');
        $this->b2bRegistration->expects($this->never())->method('registerClient');
        $this->connection->method('fetchOne')->willReturn([]);
        $this->syncLogResource->expects($this->once())->method('addLog');

        $result = $this->subject->getPendingOrders(10);

        $this->assertSame(1, $result[0]['total_count']);
        $this->assertSame(0, $result[0]['held_count']);
        $this->assertSame('opencart_bridge', $result[0]['import_mode']);
        $this->assertFalse($result[0]['requires_sectra_registration']);
        $this->assertTrue($result[0]['orders'][0]['customer']['registered_in_b2b']);
    }

    private function createOrderMock(string $incrementId, string $erpCode, string $customerName): Order&MockObject
    {
        $order = $this->createMock(Order::class);
        $order->method('getData')
            ->willReturnCallback(static function (?string $key = null) use ($erpCode) {
                return $key === 'customer_erp_code' ? $erpCode : null;
            });
        $order->method('getIncrementId')->willReturn($incrementId);
        $order->method('getCustomerFirstname')->willReturn($customerName);
        $order->method('getCustomerLastname')->willReturn('');
        $order->method('getEntityId')->willReturn((int) substr($incrementId, -3));
        $order->method('getCreatedAt')->willReturn('2026-03-16 12:00:00');
        $order->method('getStatus')->willReturn('processing');
        $order->method('getState')->willReturn('processing');
        $order->method('getStoreId')->willReturn(1);
        $order->method('getSubtotal')->willReturn(100.0);
        $order->method('getDiscountAmount')->willReturn(0.0);
        $order->method('getShippingAmount')->willReturn(15.0);
        $order->method('getGrandTotal')->willReturn(115.0);
        $order->method('getCustomerTaxvat')->willReturn('12345678901');
        $order->method('getCustomerEmail')->willReturn('cliente@awa.com');
        $order->method('getShippingAddress')->willReturn(null);
        $order->method('getPayment')->willReturn(null);
        $order->method('getItems')->willReturn([]);

        return $order;
    }
}
