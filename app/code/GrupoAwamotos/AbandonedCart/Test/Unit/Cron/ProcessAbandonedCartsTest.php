<?php
declare(strict_types=1);

namespace GrupoAwamotos\AbandonedCart\Test\Unit\Cron;

use GrupoAwamotos\AbandonedCart\Api\AbandonedCartRepositoryInterface;
use GrupoAwamotos\AbandonedCart\Api\Data\AbandonedCartInterface;
use GrupoAwamotos\AbandonedCart\Api\Data\AbandonedCartInterfaceFactory;
use GrupoAwamotos\AbandonedCart\Cron\ProcessAbandonedCarts;
use GrupoAwamotos\AbandonedCart\Helper\Data as Helper;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\ResourceModel\Quote\Collection as QuoteCollection;
use Magento\Quote\Model\ResourceModel\Quote\CollectionFactory as QuoteCollectionFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\Collection as OrderCollection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \GrupoAwamotos\AbandonedCart\Cron\ProcessAbandonedCarts
 *
 * Verifica especificamente que os padrões N+1 foram eliminados:
 *   - getByQuoteId() nunca chamado em loop
 *   - markAsRecovered() nunca chamado por pedido
 *   - Batch SELECT e batch UPDATE usados no lugar
 */
class ProcessAbandonedCartsTest extends TestCase
{
    private ProcessAbandonedCarts $cron;
    private Helper&MockObject $helper;
    private QuoteCollectionFactory&MockObject $quoteCollectionFactory;
    private OrderCollectionFactory&MockObject $orderCollectionFactory;
    private AbandonedCartRepositoryInterface&MockObject $abandonedCartRepository;
    private AbandonedCartInterfaceFactory&MockObject $abandonedCartFactory;
    private ResourceConnection&MockObject $resource;
    private AdapterInterface&MockObject $connection;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->helper = $this->createMock(Helper::class);
        $this->quoteCollectionFactory = $this->createMock(QuoteCollectionFactory::class);
        $this->orderCollectionFactory = $this->createMock(OrderCollectionFactory::class);
        $this->abandonedCartRepository = $this->createMock(AbandonedCartRepositoryInterface::class);
        $this->abandonedCartFactory = $this->createMock(AbandonedCartInterfaceFactory::class);
        $this->resource = $this->createMock(ResourceConnection::class);
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->resource->method('getConnection')->willReturn($this->connection);
        $this->resource->method('getTableName')
            ->with('grupoawamotos_abandoned_cart')
            ->willReturn('grupoawamotos_abandoned_cart');

        $this->cron = new ProcessAbandonedCarts(
            $this->helper,
            $this->quoteCollectionFactory,
            $this->orderCollectionFactory,
            $this->abandonedCartRepository,
            $this->abandonedCartFactory,
            $this->resource,
            $this->logger
        );
    }

    // =========================================================================
    // processNewAbandonedCarts — N+1 elimination
    // =========================================================================

    /**
     * Verifica que getByQuoteId() NUNCA é chamado dentro do loop.
     * O código refatorado usa um único SELECT batch em vez disso.
     */
    public function testProcessDoesNotCallGetByQuoteIdInLoop(): void
    {
        $this->helper->method('getEmailDelay')->with(1)->willReturn(1);
        $this->helper->method('getMinCartValue')->willReturn(50.0);
        $this->helper->method('excludeGuest')->willReturn(false);

        // 2 quotes: 201 (novo), 202 (já existe na tabela)
        $quoteCollection = $this->buildQuoteCollectionMock([201, 202]);
        $this->quoteCollectionFactory->method('create')->willReturn($quoteCollection);

        // Batch SELECT retorna [202] = já existe
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $this->connection->method('select')->willReturn($select);
        $this->connection->method('fetchCol')->willReturn([202]);

        // AbandonedCart para salvar o quote 201
        $abandonedCart = $this->createMock(AbandonedCartInterface::class);
        foreach (['setQuoteId','setCustomerId','setCustomerEmail','setCustomerName',
                  'setStoreId','setCartValue','setItemsCount','setAbandonedAt','setStatus'] as $m) {
            $abandonedCart->method($m)->willReturnSelf();
        }
        $this->abandonedCartFactory->method('create')->willReturn($abandonedCart);

        // Nenhum pedido recente
        $orderCollection = $this->buildOrderCollectionMock([]);
        $this->orderCollectionFactory->method('create')->willReturn($orderCollection);

        // getByQuoteId NUNCA deve ser chamado (N+1 eliminado)
        $this->abandonedCartRepository->expects($this->never())
            ->method('getByQuoteId');

        // Apenas 1 save (quote 201 — quote 202 pulado por já existir)
        $this->abandonedCartRepository->expects($this->once())
            ->method('save');

        $this->cron->execute();
    }

    /**
     * Verifica que quotes que não atingem o valor mínimo são ignoradas.
     */
    public function testQuotesBelowMinValueAreSkipped(): void
    {
        $this->helper->method('getEmailDelay')->with(1)->willReturn(1);
        $this->helper->method('getMinCartValue')->willReturn(500.0); // Valor alto
        $this->helper->method('excludeGuest')->willReturn(false);

        $quoteCollection = $this->buildQuoteCollectionMock([301]); // GrandTotal = 200 < 500
        $this->quoteCollectionFactory->method('create')->willReturn($quoteCollection);

        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $this->connection->method('select')->willReturn($select);
        $this->connection->method('fetchCol')->willReturn([]); // Nenhuma existe

        $orderCollection = $this->buildOrderCollectionMock([]);
        $this->orderCollectionFactory->method('create')->willReturn($orderCollection);

        // Nenhum save pois valor abaixo do mínimo
        $this->abandonedCartRepository->expects($this->never())->method('save');

        $this->cron->execute();
    }

    // =========================================================================
    // checkRecoveredCarts — N+1 elimination
    // =========================================================================

    /**
     * Verifica que markAsRecovered() NUNCA é chamado por pedido.
     * O código refatorado usa um único UPDATE batch em vez disso.
     */
    public function testRecoveryDoesNotCallMarkAsRecoveredPerOrder(): void
    {
        $this->helper->method('getEmailDelay')->with(1)->willReturn(1);

        // Nenhuma nova quote
        $quoteCollection = $this->buildQuoteCollectionMock([]);
        $this->quoteCollectionFactory->method('create')->willReturn($quoteCollection);

        // 2 pedidos recentes com quote IDs
        $orderCollection = $this->buildOrderCollectionMock([501, 502]);
        $this->orderCollectionFactory->method('create')->willReturn($orderCollection);

        // Batch UPDATE retorna 2 recuperados
        $this->connection->expects($this->once())
            ->method('update')
            ->willReturn(2);

        // markAsRecovered NUNCA deve ser chamado (N+1 eliminado)
        $this->abandonedCartRepository->expects($this->never())
            ->method('markAsRecovered');

        $this->cron->execute();
    }

    /**
     * Verifica que quando não há pedidos recentes, nenhum UPDATE é executado.
     */
    public function testNoUpdateWhenNoRecentOrders(): void
    {
        $this->helper->method('getEmailDelay')->with(1)->willReturn(1);

        $quoteCollection = $this->buildQuoteCollectionMock([]);
        $this->quoteCollectionFactory->method('create')->willReturn($quoteCollection);

        $orderCollection = $this->buildOrderCollectionMock([]);
        $this->orderCollectionFactory->method('create')->willReturn($orderCollection);

        // update() nunca deve ser chamado sem pedidos
        $this->connection->expects($this->never())->method('update');

        $this->cron->execute();
    }

    /**
     * Verifica retorno antecipado quando a coleção de quotes está vazia.
     */
    public function testEarlyReturnWhenNoAbandonedQuotes(): void
    {
        $this->helper->method('getEmailDelay')->with(1)->willReturn(1);

        $quoteCollection = $this->buildQuoteCollectionMock([]);
        $this->quoteCollectionFactory->method('create')->willReturn($quoteCollection);

        $orderCollection = $this->buildOrderCollectionMock([]);
        $this->orderCollectionFactory->method('create')->willReturn($orderCollection);

        // fetchCol (batch SELECT) nunca chamado quando collection vazia
        $this->connection->expects($this->never())->method('fetchCol');

        $this->cron->execute();
    }

    /**
     * Verifica que orders com quoteId = 0 são ignoradas na recuperação.
     */
    public function testOrdersWithZeroQuoteIdAreIgnored(): void
    {
        $this->helper->method('getEmailDelay')->with(1)->willReturn(1);

        $quoteCollection = $this->buildQuoteCollectionMock([]);
        $this->quoteCollectionFactory->method('create')->willReturn($quoteCollection);

        // Order com quoteId = 0 (inválido)
        $orderCollection = $this->buildOrderCollectionMock([0]);
        $this->orderCollectionFactory->method('create')->willReturn($orderCollection);

        // Não deve executar UPDATE com quoteId inválido
        $this->connection->expects($this->never())->method('update');

        $this->cron->execute();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /** @param int[] $quoteIds */
    private function buildQuoteCollectionMock(array $quoteIds): QuoteCollection&MockObject
    {
        $quotes = array_map(function (int $id): Quote&MockObject {
            $quote = $this->createMock(Quote::class);
            $quote->method('getId')->willReturn($id);
            $quote->method('getStoreId')->willReturn(1);
            $quote->method('getGrandTotal')->willReturn(200.0);
            $quote->method('getItemsCount')->willReturn(2);
            $quote->method('getCustomerEmail')->willReturn("customer{$id}@test.com");
            $quote->method('getCustomerId')->willReturn(10);
            $quote->method('getUpdatedAt')->willReturn('2025-03-01 10:00:00');
            $quote->method('getCustomerFirstname')->willReturn('Test');
            $quote->method('getCustomerLastname')->willReturn('User');
            return $quote;
        }, $quoteIds);

        $collection = $this->createMock(QuoteCollection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('setPageSize')->willReturnSelf();
        $collection->method('load')->willReturnSelf();
        $collection->method('count')->willReturn(count($quoteIds));
        $collection->method('getColumnValues')
            ->with('entity_id')
            ->willReturn(array_map('strval', $quoteIds));
        $collection->method('getIterator')
            ->willReturn(new \ArrayIterator($quotes));

        return $collection;
    }

    /** @param int[] $quoteIds */
    private function buildOrderCollectionMock(array $quoteIds): OrderCollection&MockObject
    {
        $orders = array_map(function (int $quoteId): Order&MockObject {
            $order = $this->createMock(Order::class);
            $order->method('getQuoteId')->willReturn($quoteId);
            return $order;
        }, $quoteIds);

        $collection = $this->createMock(OrderCollection::class);
        $collection->method('addFieldToSelect')->willReturnSelf();
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('setPageSize')->willReturnSelf();
        $collection->method('getIterator')
            ->willReturn(new \ArrayIterator($orders));

        return $collection;
    }
}
