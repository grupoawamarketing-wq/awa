<?php
declare(strict_types=1);

namespace GrupoAwamotos\B2B\Test\Unit\Observer;

use GrupoAwamotos\B2B\Helper\CnpjValidator;
use GrupoAwamotos\B2B\Helper\Data as B2BHelper;
use GrupoAwamotos\B2B\Observer\ValidatePullOrderCnpj;
use GrupoAwamotos\ERPIntegration\Helper\Data as ErpHelper;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Api\AttributeInterface;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address as OrderAddress;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ValidatePullOrderCnpjTest extends TestCase
{
    private ErpHelper&MockObject $erpHelper;
    private B2BHelper&MockObject $b2bHelper;
    private CustomerRepositoryInterface&MockObject $customerRepository;
    private CnpjValidator&MockObject $cnpjValidator;
    private LoggerInterface&MockObject $logger;
    private ValidatePullOrderCnpj $subject;

    protected function setUp(): void
    {
        $this->erpHelper = $this->createMock(ErpHelper::class);
        $this->b2bHelper = $this->createMock(B2BHelper::class);
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->cnpjValidator = $this->createMock(CnpjValidator::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->subject = new ValidatePullOrderCnpj(
            $this->erpHelper,
            $this->b2bHelper,
            $this->customerRepository,
            $this->cnpjValidator,
            $this->logger
        );
    }

    public function testSkipsValidationWhenErpIsNotInPullMode(): void
    {
        $this->erpHelper->method('isOrderSyncEnabled')->willReturn(true);
        $this->erpHelper->method('sendOrderOnPlace')->willReturn(true);
        $this->b2bHelper->expects($this->never())->method('isB2BCustomerById');

        $this->subject->execute($this->createObserver($this->createMock(Quote::class), $this->createMock(Order::class)));

        $this->assertTrue(true);
    }

    public function testThrowsWhenB2BCustomerHasNoValidCnpj(): void
    {
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->addMethods(['getCustomerTaxvat'])
            ->onlyMethods(['getId', 'getData', 'getBillingAddress'])
            ->getMock();
        $quote->method('getId')->willReturn(11);
        $quote->method('getData')->willReturn('');
        $quote->method('getCustomerTaxvat')->willReturn('');
        $quote->method('getBillingAddress')->willReturn(null);
        $order = $this->createConfiguredMock(Order::class, [
            'getCustomerId' => 77,
            'getData' => '',
            'getCustomerTaxvat' => '',
            'getBillingAddress' => null,
        ]);
        $customer = $this->createConfiguredMock(CustomerInterface::class, [
            'getCustomAttribute' => null,
            'getTaxvat' => '12345678901',
        ]);

        $this->erpHelper->method('isOrderSyncEnabled')->willReturn(true);
        $this->erpHelper->method('sendOrderOnPlace')->willReturn(false);
        $this->b2bHelper->method('isB2BCustomerById')->with(77)->willReturn(true);
        $this->customerRepository->method('getById')->with(77)->willReturn($customer);
        $this->cnpjValidator->method('clean')->willReturnCallback(
            static fn (string $value): string => preg_replace('/\D+/', '', $value) ?? ''
        );
        $this->logger->expects($this->once())->method('warning');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('cliente sem CNPJ válido');

        $this->subject->execute($this->createObserver($quote, $order));
    }

    public function testFormatsAndStampsValidCnpjOnOrder(): void
    {
        $quoteBilling = $this->createConfiguredMock(QuoteAddress::class, [
            'getVatId' => '11.222.333/0001-81',
        ]);
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->addMethods(['getCustomerTaxvat'])
            ->onlyMethods(['getId', 'getData', 'getBillingAddress'])
            ->getMock();
        $quote->method('getId')->willReturn(22);
        $quote->method('getData')->willReturn('');
        $quote->method('getCustomerTaxvat')->willReturn('');
        $quote->method('getBillingAddress')->willReturn($quoteBilling);
        $orderBilling = $this->createMock(OrderAddress::class);
        $order = $this->createMock(Order::class);
        $order->method('getCustomerId')->willReturn(88);
        $order->method('getData')->willReturn('');
        $order->method('getCustomerTaxvat')->willReturn('');
        $order->method('getBillingAddress')->willReturn($orderBilling);
        $order->method('getIncrementId')->willReturn('100000123');
        $order->expects($this->once())->method('setData')->with('b2b_cnpj', '11.222.333/0001-81');
        $order->expects($this->once())->method('setCustomerTaxvat')->with('11.222.333/0001-81');
        $orderBilling->expects($this->once())->method('setVatId')->with('11.222.333/0001-81');

        $attribute = $this->createConfiguredMock(AttributeInterface::class, [
            'getValue' => '',
        ]);
        $customer = $this->createConfiguredMock(CustomerInterface::class, [
            'getCustomAttribute' => $attribute,
            'getTaxvat' => '',
        ]);

        $this->erpHelper->method('isOrderSyncEnabled')->willReturn(true);
        $this->erpHelper->method('sendOrderOnPlace')->willReturn(false);
        $this->b2bHelper->method('isB2BCustomerById')->with(88)->willReturn(true);
        $this->customerRepository->method('getById')->with(88)->willReturn($customer);
        $this->cnpjValidator->method('clean')->willReturnCallback(
            static fn (string $value): string => preg_replace('/\D+/', '', $value) ?? ''
        );
        $this->cnpjValidator->method('validateLocal')->with('11222333000181')->willReturn(true);
        $this->cnpjValidator->method('format')->with('11222333000181')->willReturn('11.222.333/0001-81');
        $this->logger->expects($this->once())->method('info');

        $this->subject->execute($this->createObserver($quote, $order));

        $this->assertTrue(true);
    }

    private function createObserver(Quote $quote, Order $order): Observer
    {
        return new Observer([
            'event' => new Event([
                'quote' => $quote,
                'order' => $order,
            ]),
        ]);
    }
}
