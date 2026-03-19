<?php
declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Test\Unit\Model;

use GrupoAwamotos\ERPIntegration\Model\OrderSync;
use GrupoAwamotos\ERPIntegration\Model\CustomerSync;
use GrupoAwamotos\ERPIntegration\Api\ConnectionInterface;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use GrupoAwamotos\ERPIntegration\Model\ResourceModel\SyncLog as SyncLogResource;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment\TrackFactory;
use Magento\Sales\Model\Convert\Order as OrderConverter;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\DB\Transaction;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class OrderSyncTest extends TestCase
{
    private OrderSync $orderSync;
    private ConnectionInterface|MockObject $connection;
    private Helper|MockObject $helper;
    private SyncLogResource|MockObject $syncLogResource;
    private CustomerSync|MockObject $customerSync;
    private OrderRepositoryInterface|MockObject $orderRepository;
    private ShipmentRepositoryInterface|MockObject $shipmentRepository;
    private TrackFactory|MockObject $trackFactory;
    private OrderConverter|MockObject $orderConverter;
    private SearchCriteriaBuilder|MockObject $searchCriteriaBuilder;
    private Transaction|MockObject $transaction;
    private LoggerInterface|MockObject $logger;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(ConnectionInterface::class);
        $this->helper = $this->createMock(Helper::class);
        $this->syncLogResource = $this->createMock(SyncLogResource::class);
        $this->customerSync = $this->createMock(CustomerSync::class);
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->shipmentRepository = $this->createMock(ShipmentRepositoryInterface::class);
        $this->trackFactory = $this->createMock(TrackFactory::class);
        $this->orderConverter = $this->createMock(OrderConverter::class);
        $this->searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $this->transaction = $this->createMock(Transaction::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->orderSync = new OrderSync(
            $this->connection,
            $this->helper,
            $this->syncLogResource,
            $this->customerSync,
            $this->orderRepository,
            $this->shipmentRepository,
            $this->trackFactory,
            $this->orderConverter,
            $this->searchCriteriaBuilder,
            $this->transaction,
            $this->logger
        );
    }

    // ========== sendOrder Tests ==========

    public function testSendOrderRejectsOrderWithoutErpCustomer(): void
    {
        $order = $this->createOrderMock([
            'taxvat' => '12345678901',
            'customer_id' => 123,
            'increment_id' => '100000001',
            'entity_id' => 1,
        ]);

        // No ERP customer found
        $this->customerSync->method('getErpCustomerByTaxvat')->willReturn(null);
        $this->syncLogResource->method('getErpCodeByMagentoId')->willReturn(null);

        // Should log the rejection
        $this->syncLogResource->expects($this->once())
            ->method('addLog')
            ->with(
                'order',
                'export',
                'error',
                $this->stringContains('Cliente não encontrado no ERP')
            );

        $result = $this->orderSync->sendOrder($order);

        $this->assertFalse($result['success']);
        $this->assertNull($result['erp_order_id']);
        $this->assertStringContainsString('Cliente não encontrado', $result['message']);
    }

    public function testSendOrderSuccessWithTransaction(): void
    {
        $order = $this->createOrderMock([
            'taxvat' => '12345678901',
            'customer_id' => 123,
            'increment_id' => '100000001',
            'entity_id' => 1,
            'subtotal' => 100.00,
            'discount_amount' => -10.00,
            'grand_total' => 95.00,
            'shipping_amount' => 5.00,
        ]);

        $orderItem = $this->createMock(OrderItemInterface::class);
        $orderItem->method('getParentItemId')->willReturn(null);
        $orderItem->method('getQtyOrdered')->willReturn(2.0);
        $orderItem->method('getSku')->willReturn('TEST-SKU');
        $orderItem->method('getPrice')->willReturn(50.00);
        $orderItem->method('getRowTotal')->willReturn(100.00);
        $orderItem->method('getDiscountAmount')->willReturn(10.00);

        $order->method('getItems')->willReturn([$orderItem]);

        // ERP customer found
        $this->customerSync->method('getErpCustomerByTaxvat')
            ->willReturn(['CODIGO' => 999]);

        $this->helper->method('getStockFilial')->willReturn(1);

        // Transaction should be started
        $this->connection->expects($this->once())->method('beginTransaction');

        // Order header insert should return new ID
        $this->connection->method('fetchOne')
            ->willReturn(['new_id' => 12345]);

        // Header insert + item insert
        $this->connection->expects($this->exactly(2))->method('execute');

        // Transaction should be committed
        $this->connection->expects($this->once())->method('commit');

        // Should never rollback on success
        $this->connection->expects($this->never())->method('rollback');

        $result = $this->orderSync->sendOrder($order);

        $this->assertTrue($result['success']);
        $this->assertEquals(12345, $result['erp_order_id']);
        $this->assertEquals(1, $result['items_synced']);
    }

    public function testSendOrderRollsBackOnError(): void
    {
        $order = $this->createOrderMock([
            'taxvat' => '12345678901',
            'customer_id' => 123,
            'increment_id' => '100000001',
            'entity_id' => 1,
        ]);

        $orderItem = $this->createMock(OrderItemInterface::class);
        $orderItem->method('getParentItemId')->willReturn(null);
        $orderItem->method('getQtyOrdered')->willReturn(2.0);

        $order->method('getItems')->willReturn([$orderItem]);

        // ERP customer found
        $this->customerSync->method('getErpCustomerByTaxvat')
            ->willReturn(['CODIGO' => 999]);

        $this->helper->method('getStockFilial')->willReturn(1);

        // Transaction starts
        $this->connection->expects($this->once())->method('beginTransaction');

        // Header insert throws exception
        $this->connection->method('fetchOne')
            ->willThrowException(new \PDOException('Connection lost'));

        // Transaction should be rolled back
        $this->connection->expects($this->once())->method('rollback');

        // Should never commit on error
        $this->connection->expects($this->never())->method('commit');

        $result = $this->orderSync->sendOrder($order);

        $this->assertFalse($result['success']);
        // PDOException is caught by specific catch block with 'Erro de banco' prefix
        $this->assertStringContainsString('enviar pedido ao ERP', $result['message']);
    }

    public function testSendOrderSkipsChildItems(): void
    {
        $order = $this->createOrderMock([
            'taxvat' => '12345678901',
            'customer_id' => 123,
            'increment_id' => '100000001',
            'entity_id' => 1,
        ]);

        // Parent item
        $parentItem = $this->createMock(OrderItemInterface::class);
        $parentItem->method('getParentItemId')->willReturn(null);
        $parentItem->method('getQtyOrdered')->willReturn(1.0);
        $parentItem->method('getSku')->willReturn('PARENT-SKU');
        $parentItem->method('getPrice')->willReturn(100.00);
        $parentItem->method('getRowTotal')->willReturn(100.00);
        $parentItem->method('getDiscountAmount')->willReturn(0.00);

        // Child item (should be skipped)
        $childItem = $this->createMock(OrderItemInterface::class);
        $childItem->method('getParentItemId')->willReturn(1); // Has parent
        $childItem->method('getQtyOrdered')->willReturn(1.0);

        $order->method('getItems')->willReturn([$parentItem, $childItem]);

        $this->customerSync->method('getErpCustomerByTaxvat')
            ->willReturn(['CODIGO' => 999]);

        $this->helper->method('getStockFilial')->willReturn(1);

        $this->connection->method('fetchOne')
            ->willReturn(['new_id' => 12345]);

        // Header insert + one valid parent item insert
        $this->connection->expects($this->exactly(2))->method('execute');

        $this->connection->method('beginTransaction')->willReturn(true);
        $this->connection->method('commit')->willReturn(true);

        $result = $this->orderSync->sendOrder($order);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['items_synced']);
    }

    public function testSendOrderRejectsEmptyOrder(): void
    {
        $order = $this->createOrderMock([
            'taxvat' => '12345678901',
            'customer_id' => 123,
            'increment_id' => '100000001',
            'entity_id' => 1,
        ]);

        // No items
        $order->method('getItems')->willReturn([]);

        $this->customerSync->method('getErpCustomerByTaxvat')
            ->willReturn(['CODIGO' => 999]);

        $result = $this->orderSync->sendOrder($order);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('sem itens válidos', $result['message']);
    }

    public function testSendOrderIncludesExecutionTime(): void
    {
        $order = $this->createOrderMock([
            'taxvat' => '12345678901',
            'customer_id' => 123,
            'increment_id' => '100000001',
            'entity_id' => 1,
        ]);

        // No ERP customer - quick rejection
        $this->customerSync->method('getErpCustomerByTaxvat')->willReturn(null);
        $this->syncLogResource->method('getErpCodeByMagentoId')->willReturn(null);

        $result = $this->orderSync->sendOrder($order);

        $this->assertArrayHasKey('execution_time', $result);
        $this->assertIsFloat($result['execution_time']);
        $this->assertGreaterThanOrEqual(0, $result['execution_time']);
    }

    // ========== syncOrderStatuses Tests ==========

    public function testSyncOrderStatusesReturnsEarlyWhenDisabled(): void
    {
        $this->helper->method('isOrderSyncEnabled')->willReturn(false);

        // Should not query database
        $this->connection->expects($this->never())->method('fetchOne');

        $result = $this->orderSync->syncOrderStatuses();

        $this->assertEquals(['synced' => 0, 'errors' => 0, 'skipped' => 0], $result);
    }

    public function testSyncOrderStatusesProcessesPendingOrders(): void
    {
        $this->helper->method('isOrderSyncEnabled')->willReturn(true);

        // Mock the connection for SyncLogResource
        $mockConnection = $this->createMock(\Magento\Framework\DB\Adapter\AdapterInterface::class);
        $mockConnection->method('fetchAll')
            ->willReturn([
                ['erp_code' => 100, 'magento_entity_id' => 1],
                ['erp_code' => 101, 'magento_entity_id' => 2],
            ]);
        $this->syncLogResource->method('getConnection')->willReturn($mockConnection);

        // Mock order repository to return orders
        $order1 = $this->createMock(Order::class);
        $order1->method('getStatus')->willReturn('pending');
        $order1->method('getState')->willReturn(Order::STATE_NEW);
        $order1->method('getIncrementId')->willReturn('100000001');

        $order2 = $this->createMock(Order::class);
        $order2->method('getStatus')->willReturn('processing');
        $order2->method('getState')->willReturn(Order::STATE_COMPLETE); // Already complete

        $this->orderRepository->method('get')
            ->willReturnCallback(function ($id) use ($order1, $order2) {
                return $id === 1 ? $order1 : $order2;
            });

        // Mock ERP status for order 1
        $this->syncLogResource->method('getErpCodeByMagentoId')
            ->willReturn('100');

        $this->connection->method('fetchOne')
            ->willReturn(['STATUS' => 'P', 'CODRASTREIO' => null]);

        $result = $this->orderSync->syncOrderStatuses();

        $this->assertArrayHasKey('synced', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('skipped', $result);
    }

    // ========== getErpOrderStatus Tests ==========

    public function testGetErpOrderStatusReturnsData(): void
    {
        $expectedData = [
            'CODIGO' => 100,
            'STATUS' => 'P',
            'NFNUMERO' => '12345',
            'NFCHAVE' => str_repeat('1', 44),
            'CODRASTREIO' => 'BR123456789',
        ];

        $this->connection->method('fetchOne')
            ->willReturn($expectedData);

        $result = $this->orderSync->getErpOrderStatus(100);

        $this->assertEquals($expectedData, $result);
    }

    public function testGetErpOrderStatusReturnsNullOnError(): void
    {
        $this->connection->method('fetchOne')
            ->willThrowException(new \Exception('Database error'));

        $result = $this->orderSync->getErpOrderStatus(100);

        $this->assertNull($result);
    }

    // ========== syncOrderTracking Tests ==========

    public function testSyncOrderTrackingReturnsFalseWithNoMapping(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('getIncrementId')->willReturn('100000001');
        $this->orderRepository->method('get')->willReturn($order);

        // No ERP mapping
        $this->syncLogResource->method('getErpCodeByMagentoId')->willReturn(null);

        $this->logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('No ERP order mapping'));

        $result = $this->orderSync->syncOrderTracking(1);

        $this->assertFalse($result);
    }

    public function testSyncOrderTrackingReturnsFalseWithNoTracking(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('getIncrementId')->willReturn('100000001');
        $this->orderRepository->method('get')->willReturn($order);

        // Has ERP mapping
        $this->syncLogResource->method('getErpCodeByMagentoId')->willReturn('100');

        // But no tracking in ERP
        $this->connection->method('fetchOne')
            ->willReturn(['STATUS' => 'P', 'CODRASTREIO' => null]);

        $result = $this->orderSync->syncOrderTracking(1);

        $this->assertFalse($result);
    }

    public function testSyncOrderTrackingReturnsTrueWithValidTracking(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('hasShipments')->willReturn(false);
        $order->method('canShip')->willReturn(true);
        $order->method('getIncrementId')->willReturn('100000001');
        $order->method('getAllItems')->willReturn([]);

        $this->orderRepository->method('get')->willReturn($order);
        $this->syncLogResource->method('getErpCodeByMagentoId')->willReturn('100');

        $this->connection->method('fetchOne')
            ->willReturn([
                'STATUS' => 'E',
                'CODRASTREIO' => 'BR123456789',
                'TRANSPORTADORA_NOME' => 'Correios',
            ]);

        // Mock shipment creation
        $shipment = $this->createMock(\Magento\Sales\Model\Order\Shipment::class);
        $this->orderConverter->method('toShipment')->willReturn($shipment);

        $track = $this->createMock(\Magento\Sales\Model\Order\Shipment\Track::class);
        $this->trackFactory->method('create')->willReturn($track);

        $this->transaction->method('addObject')->willReturnSelf();

        $result = $this->orderSync->syncOrderTracking(1);

        $this->assertTrue($result);
    }

    // ========== getOrderInvoiceData Tests ==========

    public function testGetOrderInvoiceDataReturnsFormattedData(): void
    {
        $erpData = [
            'NFNUMERO' => '12345',
            'NFSERIE' => '1',
            'NFCHAVE' => str_repeat('1', 44),
            'DTFATURAMENTO' => '2024-01-15',
            'VLRTOTAL' => 100.50,
            'VLRFRETE' => 15.00,
            'VLRDESCONTO' => 10.00,
            'EMITENTE_RAZAO' => 'Empresa LTDA',
            'EMITENTE_CNPJ' => '12345678000190',
        ];

        $this->connection->method('fetchOne')
            ->willReturn($erpData);

        $result = $this->orderSync->getOrderInvoiceData(100);

        $this->assertNotNull($result);
        $this->assertEquals('12345', $result['numero']);
        $this->assertEquals('1', $result['serie']);
        $this->assertEquals(100.50, $result['valor_total']);
        $this->assertArrayHasKey('emitente', $result);
        $this->assertArrayHasKey('url_danfe', $result);
        $this->assertStringContainsString('nfe.fazenda.gov.br', $result['url_danfe']);
    }

    public function testGetOrderInvoiceDataReturnsNullWhenNoInvoice(): void
    {
        $this->connection->method('fetchOne')
            ->willReturn(['NFNUMERO' => null]);

        $result = $this->orderSync->getOrderInvoiceData(100);

        $this->assertNull($result);
    }

    // ========== updateOrderStatus Tests ==========

    public function testUpdateOrderStatusSkipsCompletedOrders(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('getStatus')->willReturn('complete');
        $order->method('getState')->willReturn(Order::STATE_COMPLETE);

        $this->orderRepository->method('get')->willReturn($order);

        $result = $this->orderSync->updateOrderStatus(1);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('já finalizado', $result['message']);
    }

    public function testUpdateOrderStatusSkipsWhenNoMapping(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('getStatus')->willReturn('pending');
        $order->method('getState')->willReturn(Order::STATE_NEW);

        $this->orderRepository->method('get')->willReturn($order);
        $this->syncLogResource->method('getErpCodeByMagentoId')->willReturn(null);

        $result = $this->orderSync->updateOrderStatus(1);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('não encontrado no mapeamento', $result['message']);
    }

    public function testUpdateOrderStatusMapsErpStatusToMagento(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('getStatus')->willReturn('pending');
        $order->method('getState')->willReturn(Order::STATE_NEW);
        $order->method('getIncrementId')->willReturn('100000001');

        $order->expects($this->once())->method('setState')->with(Order::STATE_PROCESSING);
        $order->expects($this->once())->method('setStatus')->with('processing');
        $order->expects($this->once())->method('addCommentToStatusHistory');

        $this->orderRepository->method('get')->willReturn($order);
        $this->orderRepository->method('save')->willReturn($order);

        $this->syncLogResource->method('getErpCodeByMagentoId')->willReturn('100');

        // ERP status 'P' = processing
        $this->connection->method('fetchOne')
            ->willReturn(['STATUS' => 'P', 'CODRASTREIO' => null]);

        $result = $this->orderSync->updateOrderStatus(1);

        $this->assertTrue($result['success']);
        $this->assertEquals('processing', $result['new_status']);
        $this->assertEquals('pending', $result['previous_status']);
    }

    /**
     * @dataProvider erpStatusMappingProvider
     */
    public function testUpdateOrderStatusMapsAllStatuses(string $erpStatus, string $currentStatus, string $expectedState, string $expectedStatus): void
    {
        $order = $this->createMock(Order::class);
        $order->method('getStatus')->willReturn($currentStatus);
        $order->method('getState')->willReturn(Order::STATE_NEW);
        $order->method('getIncrementId')->willReturn('100000001');

        $order->expects($this->once())->method('setState')->with($expectedState);
        $order->expects($this->once())->method('setStatus')->with($expectedStatus);

        $this->orderRepository->method('get')->willReturn($order);
        $this->orderRepository->method('save')->willReturn($order);

        $this->syncLogResource->method('getErpCodeByMagentoId')->willReturn('100');

        $this->connection->method('fetchOne')
            ->willReturn(['STATUS' => $erpStatus, 'CODRASTREIO' => null]);

        $result = $this->orderSync->updateOrderStatus(1);

        $this->assertTrue($result['success']);
    }

    public static function erpStatusMappingProvider(): array
    {
        return [
            // Use different starting status to ensure update happens
            'A -> pending' => ['A', 'processing', Order::STATE_NEW, 'pending'],
            'P -> processing' => ['P', 'pending', Order::STATE_PROCESSING, 'processing'],
            'F -> faturado' => ['F', 'pending', Order::STATE_PROCESSING, 'faturado'],
            'E -> complete' => ['E', 'pending', Order::STATE_COMPLETE, 'complete'],
            'C -> canceled' => ['C', 'pending', Order::STATE_CANCELED, 'canceled'],
            'D -> holded' => ['D', 'pending', Order::STATE_HOLDED, 'holded'],
        ];
    }

    public function testUpdateOrderStatusHandlesUnknownErpStatus(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('getStatus')->willReturn('pending');
        $order->method('getState')->willReturn(Order::STATE_NEW);
        $order->method('getIncrementId')->willReturn('100000001');

        $this->orderRepository->method('get')->willReturn($order);
        $this->syncLogResource->method('getErpCodeByMagentoId')->willReturn('100');

        // Unknown status 'X'
        $this->connection->method('fetchOne')
            ->willReturn(['STATUS' => 'X', 'CODRASTREIO' => null]);

        $result = $this->orderSync->updateOrderStatus(1);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('não mapeado', $result['message']);
    }

    // ========== getOrderHistory Tests ==========

    public function testGetOrderHistoryReturnsArray(): void
    {
        $expectedHistory = [
            ['pedido_id' => 1, 'data_pedido' => '2024-01-01', 'valor_total' => 100.00],
            ['pedido_id' => 2, 'data_pedido' => '2024-01-15', 'valor_total' => 200.00],
        ];

        $this->connection->method('query')
            ->willReturn($expectedHistory);

        $result = $this->orderSync->getOrderHistory(999, 50);

        $this->assertEquals($expectedHistory, $result);
    }

    public function testGetOrderHistoryReturnsEmptyArrayOnError(): void
    {
        $this->connection->method('query')
            ->willThrowException(new \Exception('Query failed'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Order history error'));

        $result = $this->orderSync->getOrderHistory(999, 50);

        $this->assertEquals([], $result);
    }

    // ========== Helper Methods ==========

    private function createOrderMock(array $data): Order|MockObject
    {
        $order = $this->createMock(Order::class);

        $order->method('getCustomerTaxvat')->willReturn($data['taxvat'] ?? null);
        $order->method('getCustomerId')->willReturn($data['customer_id'] ?? null);
        $order->method('getIncrementId')->willReturn($data['increment_id'] ?? '000000001');
        $order->method('getEntityId')->willReturn($data['entity_id'] ?? 1);
        $order->method('getSubtotal')->willReturn($data['subtotal'] ?? 0);
        $order->method('getDiscountAmount')->willReturn($data['discount_amount'] ?? 0);
        $order->method('getGrandTotal')->willReturn($data['grand_total'] ?? 0);
        $order->method('getShippingAmount')->willReturn($data['shipping_amount'] ?? 0);

        $shippingAddress = $this->createMock(OrderAddressInterface::class);
        $shippingAddress->method('getStreet')->willReturn(['Rua Teste', '123', 'Centro']);
        $shippingAddress->method('getCity')->willReturn('São Paulo');
        $shippingAddress->method('getPostcode')->willReturn('01234-567');
        $shippingAddress->method('getRegionCode')->willReturn('SP');

        $order->method('getShippingAddress')->willReturn($shippingAddress);

        return $order;
    }
}
