<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Test\Unit\Model;

use GrupoAwamotos\B2B\Api\CustomerApprovalInterface;
use GrupoAwamotos\B2B\Api\Data\ApprovalScoreResultInterface;
use GrupoAwamotos\B2B\Helper\CnpjValidator;
use GrupoAwamotos\B2B\Helper\Config;
use GrupoAwamotos\B2B\Model\ApprovalScoreService;
use GrupoAwamotos\B2B\Model\CnaeClassifier;
use GrupoAwamotos\B2B\Model\CnpjDuplicateChecker;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Api\AttributeInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \GrupoAwamotos\B2B\Model\ApprovalScoreService
 */
class ApprovalScoreServiceTest extends TestCase
{
    private ApprovalScoreService $service;
    private CustomerRepositoryInterface&MockObject $customerRepository;
    private AddressRepositoryInterface&MockObject $addressRepository;
    private Config&MockObject $config;
    private CnpjValidator&MockObject $cnpjValidator;
    private CnaeClassifier&MockObject $cnaeClassifier;
    private CnpjDuplicateChecker&MockObject $duplicateChecker;

    protected function setUp(): void
    {
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->addressRepository = $this->createMock(AddressRepositoryInterface::class);
        $this->config = $this->createMock(Config::class);
        $this->cnpjValidator = $this->createMock(CnpjValidator::class);
        $this->cnaeClassifier = $this->createMock(CnaeClassifier::class);
        $this->duplicateChecker = $this->createMock(CnpjDuplicateChecker::class);

        $this->service = new ApprovalScoreService(
            $this->customerRepository,
            $this->addressRepository,
            $this->config,
            $this->cnpjValidator,
            $this->cnaeClassifier,
            $this->duplicateChecker,
            $this->createMock(CustomerApprovalInterface::class),
            $this->createMock(LoggerInterface::class)
        );

        $this->config->method('getDefaultB2BGroupId')->willReturn(4);
        $this->config->method('getDirectProfileGroupId')->willReturn(6);
        $this->config->method('getAdjacentProfileGroupId')->willReturn(4);
        $this->config->method('isApprovalScoringEnabled')->willReturn(true);
    }

    public function testDuplicateCnpjReturnsRedScore(): void
    {
        $customer = $this->createCustomer([
            'b2b_cnpj' => '11222333000181',
            'b2b_razao_social' => 'Empresa Teste LTDA',
            'b2b_phone' => '16999999999',
            'b2b_cnae_profile' => CnaeClassifier::PROFILE_DIRECT,
        ]);

        $this->customerRepository->method('getById')->willReturn($customer);
        $this->duplicateChecker->method('findConflict')->willReturn([
            'customer_id' => 99,
            'email' => 'outro@empresa.com',
            'cnpj' => '11.222.333/0001-81',
        ]);

        $result = $this->service->evaluate(1);

        $this->assertSame(ApprovalScoreResultInterface::SCORE_RED, $result->getScore());
        $this->assertFalse($result->shouldAutoApprove());
    }

    public function testDirectProfileWithAutoApproveReturnsGreen(): void
    {
        $customer = $this->createCustomer([
            'b2b_cnpj' => '11222333000181',
            'b2b_razao_social' => 'Moto Pecas LTDA',
            'b2b_phone' => '16999999999',
            'b2b_cnae_profile' => CnaeClassifier::PROFILE_DIRECT,
            'b2b_cnae_code' => '4541-2/05',
            'b2b_cnae_description' => 'Comércio varejo peças motos',
        ]);

        $this->customerRepository->method('getById')->willReturn($customer);
        $this->duplicateChecker->method('findConflict')->willReturn(null);
        $this->cnpjValidator->method('validateLocal')->willReturn(true);
        $this->cnpjValidator->method('validateApi')->willReturn([
            'valid' => true,
            'data' => ['situacao' => 'ATIVA'],
        ]);
        $this->cnaeClassifier->method('isAutoApproveDirectEnabled')->willReturn(true);

        $result = $this->service->evaluate(1);

        $this->assertSame(ApprovalScoreResultInterface::SCORE_GREEN, $result->getScore());
        $this->assertTrue($result->shouldAutoApprove());
        $this->assertSame(6, $result->getSuggestedGroupId());
    }

    public function testAdjacentProfileReturnsYellow(): void
    {
        $customer = $this->createCustomer([
            'b2b_cnpj' => '11222333000181',
            'b2b_razao_social' => 'Auto Pecas LTDA',
            'b2b_phone' => '16999999999',
            'b2b_cnae_profile' => CnaeClassifier::PROFILE_ADJACENT,
        ]);

        $this->customerRepository->method('getById')->willReturn($customer);
        $this->duplicateChecker->method('findConflict')->willReturn(null);
        $this->cnpjValidator->method('validateLocal')->willReturn(true);
        $this->cnpjValidator->method('validateApi')->willReturn([
            'valid' => true,
            'data' => ['situacao' => 'ATIVA'],
        ]);

        $result = $this->service->evaluate(1);

        $this->assertSame(ApprovalScoreResultInterface::SCORE_YELLOW, $result->getScore());
        $this->assertFalse($result->shouldAutoApprove());
    }

    public function testOffProfileReturnsRed(): void
    {
        $customer = $this->createCustomer([
            'b2b_cnpj' => '11222333000181',
            'b2b_razao_social' => 'Padaria LTDA',
            'b2b_phone' => '16999999999',
            'b2b_cnae_profile' => CnaeClassifier::PROFILE_OFF,
            'b2b_cnae_code' => '1091-1/01',
        ]);

        $this->customerRepository->method('getById')->willReturn($customer);
        $this->duplicateChecker->method('findConflict')->willReturn(null);
        $this->cnpjValidator->method('validateLocal')->willReturn(true);
        $this->cnpjValidator->method('validateApi')->willReturn([
            'valid' => true,
            'data' => ['situacao' => 'ATIVA'],
        ]);

        $result = $this->service->evaluate(1);

        $this->assertSame(ApprovalScoreResultInterface::SCORE_RED, $result->getScore());
        $this->assertFalse($result->shouldAutoApprove());
    }

    /**
     * @param array<string, string> $attributes
     */
    private function createCustomer(array $attributes): CustomerInterface&MockObject
    {
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getFirstname')->willReturn('João');
        $customer->method('getLastname')->willReturn('Silva');
        $customer->method('getEmail')->willReturn('joao@empresa.com');
        $customer->method('getDefaultBilling')->willReturn('10');

        $address = $this->createMock(AddressInterface::class);
        $address->method('getStreet')->willReturn(['Rua Teste', '100']);
        $address->method('getCity')->willReturn('Araraquara');
        $address->method('getPostcode')->willReturn('14800000');
        $address->method('getRegionId')->willReturn(508);
        $this->addressRepository->method('getById')->willReturn($address);

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
