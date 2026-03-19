<?php
declare(strict_types=1);

namespace GrupoAwamotos\B2B\Test\Unit\Plugin\AdminOrder;

use GrupoAwamotos\B2B\Helper\CnpjValidator;
use GrupoAwamotos\B2B\Helper\Data as B2BHelper;
use GrupoAwamotos\B2B\Plugin\AdminOrder\ValidateAddressCnpjOnCreateOrderPlugin;
use GrupoAwamotos\ERPIntegration\Helper\Data as ErpHelper;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Sales\Model\AdminOrder\Create;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ValidateAddressCnpjOnCreateOrderPluginTest extends TestCase
{
    private CnpjValidator&MockObject $cnpjValidator;
    private ErpHelper&MockObject $erpHelper;
    private B2BHelper&MockObject $b2bHelper;
    private ValidateAddressCnpjOnCreateOrderPlugin $subject;

    protected function setUp(): void
    {
        $this->cnpjValidator = $this->createMock(CnpjValidator::class);
        $this->erpHelper = $this->createMock(ErpHelper::class);
        $this->b2bHelper = $this->createMock(B2BHelper::class);

        $this->subject = new ValidateAddressCnpjOnCreateOrderPlugin(
            $this->cnpjValidator,
            $this->erpHelper,
            $this->b2bHelper
        );
    }

    public function testSkipsB2BRuleWhenErpIsNotInPullMode(): void
    {
        $quote = $this->createConfiguredMock(Quote::class, [
            'getBillingAddress' => null,
            'isVirtual' => true,
        ]);
        $subject = $this->createConfiguredMock(Create::class, [
            'getQuote' => $quote,
        ]);

        $this->erpHelper->expects($this->once())->method('isOrderSyncEnabled')->willReturn(false);

        $this->subject->beforeCreateOrder($subject);

        $this->assertTrue(true);
    }

    public function testThrowsWhenPullModeB2BQuoteHasNoValidCnpj(): void
    {
        $billingAddress = $this->createConfiguredMock(Address::class, [
            'getCountryId' => 'BR',
            'getVatId' => '',
        ]);
        $shippingAddress = $this->createConfiguredMock(Address::class, [
            'getCountryId' => 'BR',
            'getVatId' => '',
        ]);
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->addMethods(['getCustomerId'])
            ->onlyMethods(['getBillingAddress', 'getShippingAddress', 'isVirtual', 'getData'])
            ->getMock();
        $quote->method('getCustomerId')->willReturn(77);
        $quote->method('getBillingAddress')->willReturn($billingAddress);
        $quote->method('getShippingAddress')->willReturn($shippingAddress);
        $quote->method('isVirtual')->willReturn(false);
        $quote->method('getData')->willReturn('');
        $subject = $this->createConfiguredMock(Create::class, [
            'getQuote' => $quote,
        ]);

        $this->cnpjValidator->method('clean')->willReturnCallback(
            static fn (string $value): string => preg_replace('/\D+/', '', $value) ?? ''
        );
        $this->erpHelper->method('isOrderSyncEnabled')->willReturn(true);
        $this->erpHelper->method('sendOrderOnPlace')->willReturn(false);
        $this->b2bHelper->method('isB2BCustomerById')->with(77)->willReturn(true);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('cliente sem CNPJ válido');

        $this->subject->beforeCreateOrder($subject);
    }

    public function testFormatsAndStampsQuoteWhenPullModeB2BHasValidCnpj(): void
    {
        $billingAddress = $this->createMock(Address::class);
        $billingAddress->method('getCountryId')->willReturn('BR');
        $billingAddress->method('getVatId')->willReturn('11.222.333/0001-81');
        $billingAddress->expects($this->exactly(2))->method('setVatId')->with('11.222.333/0001-81');

        $shippingAddress = $this->createMock(Address::class);
        $shippingAddress->method('getCountryId')->willReturn('BR');
        $shippingAddress->method('getVatId')->willReturn('11.222.333/0001-81');
        $shippingAddress->expects($this->exactly(2))->method('setVatId')->with('11.222.333/0001-81');

        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->addMethods(['getCustomerId'])
            ->onlyMethods(['getBillingAddress', 'getShippingAddress', 'isVirtual', 'getData', 'setData'])
            ->getMock();
        $quote->method('getCustomerId')->willReturn(88);
        $quote->method('getBillingAddress')->willReturn($billingAddress);
        $quote->method('getShippingAddress')->willReturn($shippingAddress);
        $quote->method('isVirtual')->willReturn(false);
        $quote->method('getData')->willReturn('');
        $quote->expects($this->exactly(2))->method('setData')->willReturnCallback(
            function (string $key, string $value): void {
                TestCase::assertContains($key, ['b2b_cnpj', 'customer_taxvat']);
                TestCase::assertSame('11.222.333/0001-81', $value);
            }
        );

        $subject = $this->createConfiguredMock(Create::class, [
            'getQuote' => $quote,
        ]);

        $this->cnpjValidator->method('clean')->willReturnCallback(
            static fn (string $value): string => preg_replace('/\D+/', '', $value) ?? ''
        );
        $this->cnpjValidator->method('validateLocal')->with('11222333000181')->willReturn(true);
        $this->cnpjValidator->method('format')->with('11222333000181')->willReturn('11.222.333/0001-81');
        $this->erpHelper->method('isOrderSyncEnabled')->willReturn(true);
        $this->erpHelper->method('sendOrderOnPlace')->willReturn(false);
        $this->b2bHelper->method('isB2BCustomerById')->with(88)->willReturn(true);

        $this->subject->beforeCreateOrder($subject);

        $this->assertTrue(true);
    }
}
