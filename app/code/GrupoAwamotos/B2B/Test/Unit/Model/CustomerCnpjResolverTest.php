<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Test\Unit\Model;

use GrupoAwamotos\B2B\Helper\CnpjValidator;
use GrupoAwamotos\B2B\Model\CustomerCnpjResolver;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Api\AttributeInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CustomerCnpjResolverTest extends TestCase
{
    private CnpjValidator&MockObject $cnpjValidator;
    private CustomerCnpjResolver $resolver;

    protected function setUp(): void
    {
        $this->cnpjValidator = $this->createMock(CnpjValidator::class);
        $this->cnpjValidator->method('clean')->willReturnCallback(
            static fn (string $value): string => (string) preg_replace('/\D+/', '', $value)
        );
        $this->cnpjValidator->method('validateLocal')->willReturnCallback(
            static fn (string $digits): bool => strlen($digits) === 14
        );

        $this->resolver = new CustomerCnpjResolver($this->cnpjValidator);
    }

    public function testResolveDigitsPrioritizesB2bCnpj(): void
    {
        $customer = $this->createCustomerWithAttributes([
            'b2b_cnpj' => '66.437.059/0001-50',
            'cnpj' => '11.111.111/0001-11',
        ]);

        $this->assertSame('66437059000150', $this->resolver->resolveDigits($customer));
    }

    public function testResolveDigitsFallsBackToCnpjAttribute(): void
    {
        $customer = $this->createCustomerWithAttributes([
            'cnpj' => '55.212.158/0001-17',
        ]);

        $this->assertSame('55212158000117', $this->resolver->resolveDigits($customer));
    }

    public function testResolveDigitsFallsBackToTaxvat(): void
    {
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getCustomAttribute')->willReturn(null);
        $customer->method('getTaxvat')->willReturn('66437059000150');

        $this->assertSame('66437059000150', $this->resolver->resolveDigits($customer));
    }

    public function testResolveDigitsReturnsNullWhenMissing(): void
    {
        $customer = $this->createCustomerWithAttributes([]);
        $customer->method('getTaxvat')->willReturn(null);

        $this->assertNull($this->resolver->resolveDigits($customer));
    }

    /**
     * @param array<string, string> $attributes
     */
    private function createCustomerWithAttributes(array $attributes): CustomerInterface&MockObject
    {
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getTaxvat')->willReturn(null);

        $customer->method('getCustomAttribute')->willReturnCallback(
            function (string $code) use ($attributes): ?AttributeInterface {
                if (!isset($attributes[$code])) {
                    return null;
                }

                $attr = $this->createMock(AttributeInterface::class);
                $attr->method('getValue')->willReturn($attributes[$code]);

                return $attr;
            }
        );

        return $customer;
    }
}
