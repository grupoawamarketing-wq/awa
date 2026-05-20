<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Test\Unit\Model\Registration;

use GrupoAwamotos\B2B\Model\Customer\Attribute\Source\ErpCustomerSyncStatus;
use GrupoAwamotos\B2B\Model\Registration\B2bPhoneNormalizer;
use GrupoAwamotos\B2B\Model\Registration\B2bRegistrationGuard;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\CustomerExtensionInterface;
use Magento\Framework\Exception\InputException;
use PHPUnit\Framework\TestCase;

class B2bRegistrationGuardTest extends TestCase
{
    private B2bRegistrationGuard $guard;

    protected function setUp(): void
    {
        $this->guard = new B2bRegistrationGuard(new B2bPhoneNormalizer());
    }

    public function testValidateNewRegistrationAcceptsCompleteCustomer(): void
    {
        $customer = $this->createB2bCustomer([
            'b2b_cnpj' => '11.222.333/0001-81',
            'b2b_razao_social' => 'Empresa Teste LTDA',
            'b2b_phone' => '(11) 99999-8888',
            'erp_customer_sync_status' => ErpCustomerSyncStatus::CUSTOMER_PENDING_ERP_VALIDATION,
            'b2b_origin_host' => 'awamotos.com',
            'b2b_registration_campaign' => 'google-ads',
        ]);

        $this->guard->validateNewRegistration($customer);
        $this->addToAssertionCount(1);
    }

    public function testValidateNewRegistrationRejectsMissingPhone(): void
    {
        $this->expectException(InputException::class);

        $customer = $this->createB2bCustomer([
            'b2b_cnpj' => '11.222.333/0001-81',
            'b2b_razao_social' => 'Empresa Teste LTDA',
            'b2b_phone' => '',
            'erp_customer_sync_status' => ErpCustomerSyncStatus::CUSTOMER_PENDING_ERP_VALIDATION,
            'b2b_origin_host' => 'awamotos.com',
            'b2b_registration_campaign' => 'direct',
        ]);

        $this->guard->validateNewRegistration($customer);
    }

    public function testValidateNewRegistrationRejectsMissingRazaoSocial(): void
    {
        $this->expectException(InputException::class);

        $customer = $this->createB2bCustomer([
            'b2b_cnpj' => '11.222.333/0001-81',
            'b2b_razao_social' => '',
            'b2b_phone' => '(11) 99999-8888',
            'erp_customer_sync_status' => ErpCustomerSyncStatus::CUSTOMER_PENDING_ERP_VALIDATION,
            'b2b_origin_host' => 'awamotos.com',
            'b2b_registration_campaign' => 'direct',
        ]);

        $this->guard->validateNewRegistration($customer);
    }

    public function testApplyDefaultsDoesNotInventPhoneOrRazao(): void
    {
        $customer = $this->createB2bCustomer([
            'b2b_cnpj' => '11.222.333/0001-81',
            'b2b_razao_social' => '',
            'b2b_phone' => '',
        ]);

        $this->guard->applyNewRegistrationDefaults($customer, 'loja.awamotos.com');

        $this->assertSame('', $this->guard->getAttributeValue($customer, 'b2b_phone'));
        $this->assertSame('', $this->guard->getAttributeValue($customer, 'b2b_razao_social'));
        $this->assertSame(
            ErpCustomerSyncStatus::CUSTOMER_PENDING_ERP_VALIDATION,
            $this->guard->getAttributeValue($customer, 'erp_customer_sync_status')
        );
        $this->assertSame('loja.awamotos.com', $this->guard->getAttributeValue($customer, 'b2b_origin_host'));
        $this->assertSame('direct_b2b_register', $this->guard->getAttributeValue($customer, 'b2b_registration_campaign'));
    }

    public function testSkipsValidationForExistingCustomer(): void
    {
        $customer = $this->createB2bCustomer([
            'b2b_cnpj' => '11.222.333/0001-81',
            'b2b_razao_social' => '',
            'b2b_phone' => '',
        ], 999);

        $this->assertFalse($this->guard->isNewCustomer($customer));
    }

    public function testIsTestOrQaAccount(): void
    {
        $this->assertTrue($this->guard->isTestOrQaAccount(8714, 'qa.b2b.aprovado@awamotos.com.br'));
        $this->assertTrue($this->guard->isTestOrQaAccount(2, 'j@jesssestain.com.br'));
        $this->assertFalse($this->guard->isTestOrQaAccount(2310, 'sergiomotos@bol.com.br'));
    }

    /**
     * @param array<string, string> $attributes
     */
    private function createB2bCustomer(array $attributes, ?int $id = null): CustomerInterface
    {
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getId')->willReturn($id);
        $customer->method('getExtensionAttributes')->willReturn(null);

        $stored = [];
        $customer->method('getCustomAttribute')->willReturnCallback(
            function (string $code) use (&$stored) {
                if (!array_key_exists($code, $stored)) {
                    return null;
                }

                $attr = $this->createMock(\Magento\Framework\Api\AttributeInterface::class);
                $attr->method('getValue')->willReturn($stored[$code]);

                return $attr;
            }
        );
        $customer->method('setCustomAttribute')->willReturnCallback(
            function (string $code, $value) use (&$stored, $customer) {
                $stored[$code] = $value;

                return $customer;
            }
        );

        foreach ($attributes as $code => $value) {
            $customer->setCustomAttribute($code, $value);
        }

        return $customer;
    }
}
