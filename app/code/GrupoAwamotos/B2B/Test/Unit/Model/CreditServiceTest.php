<?php
declare(strict_types=1);

namespace GrupoAwamotos\B2B\Test\Unit\Model;

use GrupoAwamotos\B2B\Model\CreditLimit;
use GrupoAwamotos\B2B\Model\CreditLimitFactory;
use GrupoAwamotos\B2B\Model\CreditService;
use GrupoAwamotos\B2B\Model\CreditTransaction;
use GrupoAwamotos\B2B\Model\CreditTransactionFactory;
use GrupoAwamotos\B2B\Model\ResourceModel\CreditLimit as CreditLimitResource;
use GrupoAwamotos\B2B\Model\ResourceModel\CreditTransaction as CreditTransactionResource;
use GrupoAwamotos\B2B\Model\ResourceModel\CreditLimit\CollectionFactory as CreditCollectionFactory;
use GrupoAwamotos\B2B\Model\ResourceModel\CreditLimit\Collection as CreditCollection;
use GrupoAwamotos\B2B\Model\ResourceModel\CreditTransaction\CollectionFactory as TxnCollectionFactory;
use GrupoAwamotos\B2B\Model\ResourceModel\CreditTransaction\Collection as TxnCollection;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \GrupoAwamotos\B2B\Model\CreditService
 */
class CreditServiceTest extends TestCase
{
    private CreditService $service;
    private CreditLimitFactory&MockObject $creditFactory;
    private CreditTransactionFactory&MockObject $txnFactory;
    private CreditLimitResource&MockObject $creditResource;
    private CreditTransactionResource&MockObject $txnResource;
    private CreditCollectionFactory&MockObject $creditCollectionFactory;
    private TxnCollectionFactory&MockObject $txnCollectionFactory;
    private ScopeConfigInterface&MockObject $scopeConfig;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->creditFactory = $this->createMock(CreditLimitFactory::class);
        $this->txnFactory = $this->createMock(CreditTransactionFactory::class);
        $this->creditResource = $this->createMock(CreditLimitResource::class);
        $this->txnResource = $this->createMock(CreditTransactionResource::class);
        $this->creditCollectionFactory = $this->createMock(CreditCollectionFactory::class);
        $this->txnCollectionFactory = $this->createMock(TxnCollectionFactory::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new CreditService(
            $this->creditFactory,
            $this->txnFactory,
            $this->creditResource,
            $this->txnResource,
            $this->creditCollectionFactory,
            $this->txnCollectionFactory,
            $this->scopeConfig,
            $this->logger
        );
    }

    // ====================================================================
    // Helper: create a CreditLimit mock with a given collection lookup
    // ====================================================================

    private function mockCreditLookup(
        ?int $id,
        float $creditLimit = 0.0,
        float $usedCredit = 0.0
    ): CreditLimit&MockObject {
        $credit = $this->getMockBuilder(CreditLimit::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getCreditLimit', 'getUsedCredit', 'getAvailableCredit',
                           'setCustomerId', 'setCreditLimit', 'setUsedCredit', 'setCurrencyCode'])
            ->getMock();

        $credit->method('getId')->willReturn($id);
        $credit->method('getCreditLimit')->willReturn($creditLimit);
        $credit->method('getUsedCredit')->willReturn($usedCredit);
        $credit->method('getAvailableCredit')->willReturn($creditLimit - $usedCredit);

        $collection = $this->createMock(CreditCollection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('getFirstItem')->willReturn($credit);
        $this->creditCollectionFactory->method('create')->willReturn($collection);

        return $credit;
    }

    private function mockTxnFactory(): CreditTransaction&MockObject
    {
        $txn = $this->createMock(CreditTransaction::class);
        $txn->method('setData')->willReturnSelf();
        $this->txnFactory->method('create')->willReturn($txn);
        return $txn;
    }

    // ====================================================================
    // isEnabled
    // ====================================================================

    public function testIsEnabledReturnsTrue(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('grupoawamotos_b2b/credit/enabled')
            ->willReturn('1');

        $this->assertTrue($this->service->isEnabled());
    }

    public function testIsEnabledReturnsFalse(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('grupoawamotos_b2b/credit/enabled')
            ->willReturn('0');

        $this->assertFalse($this->service->isEnabled());
    }

    public function testIsEnabledReturnsFalseWhenNull(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('grupoawamotos_b2b/credit/enabled')
            ->willReturn(null);

        $this->assertFalse($this->service->isEnabled());
    }

    // ====================================================================
    // getPaymentTitle
    // ====================================================================

    public function testGetPaymentTitleReturnsConfigValue(): void
    {
        $this->scopeConfig->method('getValue')->willReturnCallback(function (string $path) {
            return str_contains($path, 'payment_title') ? 'Faturamento Corporativo' : null;
        });

        $this->assertSame('Faturamento Corporativo', $this->service->getPaymentTitle());
    }

    public function testGetPaymentTitleReturnsDefault(): void
    {
        $this->scopeConfig->method('getValue')->willReturn(null);

        $this->assertSame('Crédito B2B (Faturamento)', $this->service->getPaymentTitle());
    }

    // ====================================================================
    // getCreditLimit
    // ====================================================================

    public function testGetCreditLimitReturnsExistingRecord(): void
    {
        $credit = $this->mockCreditLookup(1, 5000.0, 1000.0);

        $result = $this->service->getCreditLimit(42);
        $this->assertSame($credit, $result);
    }

    public function testGetCreditLimitCreatesNewRecordWhenNotFound(): void
    {
        // Existing (from collection) has no ID
        $existingCredit = $this->getMockBuilder(CreditLimit::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId'])
            ->getMock();
        $existingCredit->method('getId')->willReturn(null);

        $collection = $this->createMock(CreditCollection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('getFirstItem')->willReturn($existingCredit);
        $this->creditCollectionFactory->method('create')->willReturn($collection);

        // New credit from factory
        $newCredit = $this->getMockBuilder(CreditLimit::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setCustomerId', 'setCreditLimit', 'setUsedCredit', 'setCurrencyCode'])
            ->getMock();
        $newCredit->expects($this->once())->method('setCustomerId')->with(42)->willReturnSelf();
        $newCredit->expects($this->once())->method('setCreditLimit')->with(0)->willReturnSelf();
        $newCredit->expects($this->once())->method('setUsedCredit')->with(0)->willReturnSelf();
        $newCredit->expects($this->once())->method('setCurrencyCode')->with('BRL')->willReturnSelf();

        $this->creditFactory->method('create')->willReturn($newCredit);
        $this->creditResource->expects($this->once())->method('save')->with($newCredit);

        $result = $this->service->getCreditLimit(42);
        $this->assertSame($newCredit, $result);
    }

    // ====================================================================
    // charge
    // ====================================================================

    public function testChargeDebitsCreditSuccessfully(): void
    {
        $credit = $this->mockCreditLookup(1, 5000.0, 0.0);
        $credit->expects($this->once())->method('setUsedCredit')->with(1500.0)->willReturnSelf();

        $this->mockTxnFactory();
        $this->creditResource->expects($this->once())->method('save');
        $this->txnResource->expects($this->once())->method('save');

        $this->service->charge(42, 1500.0, 1001);
    }

    public function testChargeThrowsOnInsufficientCredit(): void
    {
        $this->mockCreditLookup(1, 5000.0, 4800.0); // available = 200

        $this->expectException(LocalizedException::class);
        $this->service->charge(42, 500.0, 1001);
    }

    public function testChargeSucceedsWithExactAvailableCredit(): void
    {
        $credit = $this->mockCreditLookup(1, 5000.0, 4000.0); // available = 1000
        $credit->expects($this->once())->method('setUsedCredit')->with(5000.0)->willReturnSelf();

        $this->mockTxnFactory();

        $this->service->charge(42, 1000.0, 1001);
    }

    public function testChargeLogsTransaction(): void
    {
        $credit = $this->mockCreditLookup(1, 5000.0, 0.0);
        $credit->method('setUsedCredit')->willReturnSelf();

        $this->mockTxnFactory();

        $this->logger->expects($this->once())
            ->method('info')
            ->with($this->stringContains('B2B Credit charged'));

        $this->service->charge(42, 1500.0, 1001);
    }

    // ====================================================================
    // refund
    // ====================================================================

    public function testRefundReducesUsedCredit(): void
    {
        $credit = $this->mockCreditLookup(1, 5000.0, 3000.0);
        $credit->expects($this->once())->method('setUsedCredit')->with(2000.0)->willReturnSelf();

        $this->mockTxnFactory();
        $this->creditResource->expects($this->once())->method('save');
        $this->txnResource->expects($this->once())->method('save');

        $this->service->refund(42, 1000.0, 1001);
    }

    public function testRefundDoesNotGoNegative(): void
    {
        $credit = $this->mockCreditLookup(1, 5000.0, 500.0);
        $credit->expects($this->once())->method('setUsedCredit')->with(0.0)->willReturnSelf();

        $this->mockTxnFactory();

        $this->service->refund(42, 2000.0, 1001);
    }

    public function testRefundFullAmount(): void
    {
        $credit = $this->mockCreditLookup(1, 5000.0, 3000.0);
        $credit->expects($this->once())->method('setUsedCredit')->with(0.0)->willReturnSelf();

        $this->mockTxnFactory();

        $this->service->refund(42, 3000.0, 1001);
    }

    // ====================================================================
    // recordPayment
    // ====================================================================

    public function testRecordPaymentReducesUsedCredit(): void
    {
        $credit = $this->mockCreditLookup(1, 5000.0, 3000.0);
        $credit->expects($this->once())->method('setUsedCredit')->with(2000.0)->willReturnSelf();

        $this->mockTxnFactory();

        $this->service->recordPayment(42, 1000.0, 1, 'Boleto pago');
    }

    public function testRecordPaymentDoesNotGoNegative(): void
    {
        $credit = $this->mockCreditLookup(1, 5000.0, 500.0);
        $credit->expects($this->once())->method('setUsedCredit')->with(0.0)->willReturnSelf();

        $this->mockTxnFactory();

        $this->service->recordPayment(42, 1000.0, 1, 'Pagamento excedente');
    }

    // ====================================================================
    // hasSufficientCredit
    // ====================================================================

    public function testHasSufficientCreditReturnsTrue(): void
    {
        $this->mockCreditLookup(1, 5000.0, 0.0);
        $this->assertTrue($this->service->hasSufficientCredit(42, 3000.0));
    }

    public function testHasSufficientCreditReturnsFalse(): void
    {
        $this->mockCreditLookup(1, 5000.0, 4500.0); // available = 500
        $this->assertFalse($this->service->hasSufficientCredit(42, 1000.0));
    }

    public function testHasSufficientCreditExactAmount(): void
    {
        $this->mockCreditLookup(1, 5000.0, 4000.0); // available = 1000
        $this->assertTrue($this->service->hasSufficientCredit(42, 1000.0));
    }

    public function testHasSufficientCreditZeroAmount(): void
    {
        $this->mockCreditLookup(1, 5000.0, 5000.0); // available = 0
        $this->assertTrue($this->service->hasSufficientCredit(42, 0.0));
    }

    // ====================================================================
    // getTransactions
    // ====================================================================

    public function testGetTransactionsReturnsFilteredCollection(): void
    {
        $txnCollection = $this->createMock(TxnCollection::class);
        $txnCollection->expects($this->once())->method('filterByCustomer')->with(42)->willReturnSelf();
        $txnCollection->expects($this->once())->method('setOrder')->with('created_at', 'DESC')->willReturnSelf();
        $txnCollection->expects($this->once())->method('setPageSize')->with(10)->willReturnSelf();

        $this->txnCollectionFactory->method('create')->willReturn($txnCollection);

        $result = $this->service->getTransactions(42, 10);
        $this->assertSame($txnCollection, $result);
    }

    public function testGetTransactionsUsesDefaultLimit(): void
    {
        $txnCollection = $this->createMock(TxnCollection::class);
        $txnCollection->method('filterByCustomer')->willReturnSelf();
        $txnCollection->method('setOrder')->willReturnSelf();
        $txnCollection->expects($this->once())->method('setPageSize')->with(20)->willReturnSelf();

        $this->txnCollectionFactory->method('create')->willReturn($txnCollection);

        $this->service->getTransactions(42);
    }

    // ====================================================================
    // setLimit
    // ====================================================================

    public function testSetLimitUpdatesAndCreatesTransaction(): void
    {
        $credit = $this->mockCreditLookup(1, 5000.0, 0.0);
        $credit->expects($this->once())->method('setCreditLimit')->with(10000.0)->willReturnSelf();

        $this->mockTxnFactory();

        $this->creditResource->expects($this->atLeastOnce())->method('save');
        $this->txnResource->expects($this->once())->method('save');

        $result = $this->service->setLimit(42, 10000.0, 1, 'Aumento de limite');
        $this->assertSame($credit, $result);
    }

    public function testSetLimitWithoutAdmin(): void
    {
        $credit = $this->mockCreditLookup(1, 5000.0, 0.0);
        $credit->method('setCreditLimit')->willReturnSelf();

        $this->mockTxnFactory();

        $result = $this->service->setLimit(42, 8000.0);
        $this->assertSame($credit, $result);
    }
}
