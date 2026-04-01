<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Test\Unit\Model;

use GrupoAwamotos\ERPIntegration\Api\ConnectionInterface;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use GrupoAwamotos\ERPIntegration\Model\OrderHistory;
use GrupoAwamotos\ERPIntegration\Model\ResourceModel\SyncLog as SyncLogResource;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for OrderHistory model
 *
 * @covers \GrupoAwamotos\ERPIntegration\Model\OrderHistory
 */
class OrderHistoryTest extends TestCase
{
    private OrderHistory $model;
    private ConnectionInterface&MockObject $connection;
    private Helper&MockObject $helper;
    private SyncLogResource&MockObject $syncLogResource;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(ConnectionInterface::class);
        $this->helper = $this->createMock(Helper::class);
        $this->syncLogResource = $this->createMock(SyncLogResource::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->helper->method('isSuggestionsEnabled')->willReturn(true);
        $this->helper->method('getMaxSuggestions')->willReturn(10);

        $this->model = new OrderHistory(
            $this->connection,
            $this->helper,
            $this->syncLogResource,
            $this->logger
        );
    }

    // ─── getProductSuggestions ──────────────────────────────────────

    public function testGetProductSuggestionsReturnsSuggestions(): void
    {
        $expected = [
            [
                'sku' => 'SKU-001',
                'name' => 'Bagageiro CG 160',
                'total_qty' => 50,
                'total_orders' => 5,
                'last_order_date' => '2025-12-01',
                'avg_price' => 89.90,
            ],
        ];

        $this->connection->expects($this->once())
            ->method('query')
            ->with(
                $this->stringContains('VE_PEDIDO'),
                $this->callback(function (array $params): bool {
                    return $params[':cliente'] === 100 && $params[':limit'] === 10;
                })
            )
            ->willReturn($expected);

        $result = $this->model->getProductSuggestions(100);
        $this->assertCount(1, $result);
        $this->assertSame('SKU-001', $result[0]['sku']);
    }

    public function testGetProductSuggestionsReturnsEmptyWhenDisabled(): void
    {
        $helper = $this->createMock(Helper::class);
        $helper->method('isSuggestionsEnabled')->willReturn(false);

        $model = new OrderHistory(
            $this->connection,
            $helper,
            $this->syncLogResource,
            $this->logger
        );

        $this->connection->expects($this->never())->method('query');

        $result = $model->getProductSuggestions(100);
        $this->assertSame([], $result);
    }

    public function testGetProductSuggestionsHandlesException(): void
    {
        $this->connection->method('query')
            ->willThrowException(new \Exception('Timeout'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Product suggestions error'));

        $result = $this->model->getProductSuggestions(100);
        $this->assertSame([], $result);
    }

    public function testGetProductSuggestionsUsesMaxFromHelper(): void
    {
        $helper = $this->createMock(Helper::class);
        $helper->method('isSuggestionsEnabled')->willReturn(true);
        $helper->method('getMaxSuggestions')->willReturn(5);

        $model = new OrderHistory(
            $this->connection,
            $helper,
            $this->syncLogResource,
            $this->logger
        );

        $this->connection->expects($this->once())
            ->method('query')
            ->with(
                $this->anything(),
                $this->callback(fn(array $p) => $p[':limit'] === 5)
            )
            ->willReturn([]);

        $model->getProductSuggestions(100);
    }

    // ─── getReorderSuggestions ─────────────────────────────────────

    public function testGetReorderSuggestionsReturnsItems(): void
    {
        $expected = [
            [
                'sku' => 'SKU-002',
                'name' => 'Retrovisor Fazer 250',
                'last_qty' => 10,
                'last_price' => 45.00,
                'last_order_date' => '2025-11-15',
                'last_order_id' => 500,
            ],
        ];

        $this->connection->expects($this->once())
            ->method('query')
            ->with(
                $this->stringContains('VE_PEDIDOITENS'),
                $this->callback(fn(array $p) => $p[':cliente'] === 200)
            )
            ->willReturn($expected);

        $result = $this->model->getReorderSuggestions(200);
        $this->assertCount(1, $result);
        $this->assertSame('SKU-002', $result[0]['sku']);
    }

    public function testGetReorderSuggestionsReturnsEmptyWhenDisabled(): void
    {
        $helper = $this->createMock(Helper::class);
        $helper->method('isSuggestionsEnabled')->willReturn(false);

        $model = new OrderHistory(
            $this->connection,
            $helper,
            $this->syncLogResource,
            $this->logger
        );

        $this->connection->expects($this->never())->method('query');

        $result = $model->getReorderSuggestions(200);
        $this->assertSame([], $result);
    }

    public function testGetReorderSuggestionsHandlesException(): void
    {
        $this->connection->method('query')
            ->willThrowException(new \Exception('Connection lost'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Reorder suggestions error'));

        $result = $this->model->getReorderSuggestions(200);
        $this->assertSame([], $result);
    }

    // ─── getErpClientCodeByCustomerId ──────────────────────────────

    public function testGetErpClientCodeByCustomerIdReturnsCode(): void
    {
        $this->syncLogResource->expects($this->once())
            ->method('getErpCodeByMagentoId')
            ->with('customer', 42)
            ->willReturn('500');

        $result = $this->model->getErpClientCodeByCustomerId(42);
        $this->assertSame(500, $result);
    }

    public function testGetErpClientCodeByCustomerIdReturnsNullWhenNotFound(): void
    {
        $this->syncLogResource->method('getErpCodeByMagentoId')
            ->willReturn(null);

        $result = $this->model->getErpClientCodeByCustomerId(99);
        $this->assertNull($result);
    }

    public function testGetErpClientCodeByCustomerIdReturnsNullForEmptyString(): void
    {
        $this->syncLogResource->method('getErpCodeByMagentoId')
            ->willReturn('');

        $result = $this->model->getErpClientCodeByCustomerId(99);
        $this->assertNull($result);
    }
}
