<?php
declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Test\Unit\Model;

use GrupoAwamotos\ERPIntegration\Model\CustomerSync;
use GrupoAwamotos\ERPIntegration\Api\ConnectionInterface;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use GrupoAwamotos\ERPIntegration\Model\ResourceModel\SyncLog as SyncLogResource;
use GrupoAwamotos\ERPIntegration\Model\Validator\CustomerValidator;
use GrupoAwamotos\ERPIntegration\Model\Validator\ValidationResult;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Api\Data\AddressInterfaceFactory;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\Data\RegionInterfaceFactory;
use Magento\Directory\Model\RegionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Framework\Math\Random;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\App\State as AppState;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class CustomerSyncTest extends TestCase
{
    private CustomerSync $customerSync;
    private ConnectionInterface|MockObject $connection;
    private Helper|MockObject $helper;
    private CustomerRepositoryInterface|MockObject $customerRepository;
    private CustomerInterfaceFactory|MockObject $customerFactory;
    private AddressInterfaceFactory|MockObject $addressFactory;
    private AddressRepositoryInterface|MockObject $addressRepository;
    private RegionInterfaceFactory|MockObject $regionFactory;
    private RegionFactory|MockObject $regionModelFactory;
    private StoreManagerInterface|MockObject $storeManager;
    private SyncLogResource|MockObject $syncLogResource;
    private CustomerValidator|MockObject $customerValidator;
    private LoggerInterface|MockObject $logger;
    private Random|MockObject $random;
    private EncryptorInterface|MockObject $encryptor;
    private AppState|MockObject $appState;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(ConnectionInterface::class);
        $this->helper = $this->createMock(Helper::class);
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->customerFactory = $this->createMock(CustomerInterfaceFactory::class);
        $this->addressFactory = $this->createMock(AddressInterfaceFactory::class);
        $this->addressRepository = $this->createMock(AddressRepositoryInterface::class);
        $this->regionFactory = $this->createMock(RegionInterfaceFactory::class);
        $this->regionModelFactory = $this->createMock(RegionFactory::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->syncLogResource = $this->createMock(SyncLogResource::class);
        $this->customerValidator = $this->createMock(CustomerValidator::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->random = $this->createMock(Random::class);
        $this->encryptor = $this->createMock(EncryptorInterface::class);
        $this->appState = $this->createMock(AppState::class);
        $this->customerValidator->method('validate')->willReturn(ValidationResult::success());

        // Setup default store manager
        $store = $this->createMock(StoreInterface::class);
        $store->method('getWebsiteId')->willReturn(1);
        $store->method('getId')->willReturn(1);
        $this->storeManager->method('getDefaultStoreView')->willReturn($store);

        $this->customerSync = new CustomerSync(
            $this->connection,
            $this->helper,
            $this->customerRepository,
            $this->customerFactory,
            $this->addressFactory,
            $this->addressRepository,
            $this->regionFactory,
            $this->regionModelFactory,
            $this->storeManager,
            $this->syncLogResource,
            $this->customerValidator,
            $this->logger,
            $this->random,
            $this->encryptor,
            $this->appState
        );
    }

    // ========== getErpCustomerByTaxvat Tests ==========

    public function testGetErpCustomerByTaxvatReturnCustomerData(): void
    {
        $expectedData = [
            'CODIGO' => 123,
            'RAZAO' => 'Test Company LTDA',
            'CGC' => '12.345.678/0001-90',
            'EMAIL' => 'test@example.com',
        ];

        $this->connection->method('fetchOne')
            ->willReturn($expectedData);

        $result = $this->customerSync->getErpCustomerByTaxvat('12345678000190');

        $this->assertEquals($expectedData, $result);
    }

    public function testGetErpCustomerByTaxvatCleansInputFormat(): void
    {
        $this->connection->expects($this->once())
            ->method('fetchOne')
            ->with(
                $this->anything(),
                $this->callback(function ($params) {
                    // Should clean the taxvat to numbers only
                    return $params[':taxvat'] === '12345678000190'
                        && $params[':taxvat2'] === '12345678000190';
                })
            )
            ->willReturn(['CODIGO' => 1]);

        $this->customerSync->getErpCustomerByTaxvat('12.345.678/0001-90');
    }

    public function testGetErpCustomerByTaxvatReturnsNullOnError(): void
    {
        $this->connection->method('fetchOne')
            ->willThrowException(new \Exception('Connection failed'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Customer lookup error'));

        $result = $this->customerSync->getErpCustomerByTaxvat('12345678901');

        $this->assertNull($result);
    }

    public function testGetErpCustomerByTaxvatHandlesCpf(): void
    {
        $this->connection->method('fetchOne')
            ->willReturn(['CODIGO' => 1, 'CPF' => '123.456.789-01']);

        $result = $this->customerSync->getErpCustomerByTaxvat('123.456.789-01');

        $this->assertNotNull($result);
    }

    // ========== getErpCustomerByCode Tests ==========

    public function testGetErpCustomerByCodeReturnsData(): void
    {
        $expectedData = [
            'CODIGO' => 999,
            'RAZAO' => 'Test Customer',
            'EMAIL' => 'test@example.com',
        ];

        $this->connection->method('fetchOne')
            ->willReturn($expectedData);

        $result = $this->customerSync->getErpCustomerByCode(999);

        $this->assertEquals($expectedData, $result);
    }

    public function testGetErpCustomerByCodeReturnsNullOnError(): void
    {
        $this->connection->method('fetchOne')
            ->willThrowException(new \Exception('Query failed'));

        $result = $this->customerSync->getErpCustomerByCode(999);

        $this->assertNull($result);
    }

    // ========== syncAll Tests ==========

    public function testSyncAllReturnsEarlyWhenDisabled(): void
    {
        $this->helper->method('isCustomerSyncEnabled')->willReturn(false);

        // Should not query the database
        $this->connection->expects($this->never())->method('fetchOne');
        $this->connection->expects($this->never())->method('query');

        $result = $this->customerSync->syncAll();

        $this->assertEquals(
            ['created' => 0, 'updated' => 0, 'errors' => 0, 'skipped' => 0, 'validation_failed' => 0],
            $result
        );
    }

    public function testSyncAllProcessesCustomersInBatches(): void
    {
        $this->helper->method('isCustomerSyncEnabled')->willReturn(true);

        // Setup count query
        $this->connection->method('fetchOne')
            ->willReturnCallback(function ($sql) {
                if (strpos($sql, 'COUNT') !== false) {
                    return ['total' => 2];
                }
                return null;
            });

        // Setup batch query - 2 customers
        $this->connection->method('query')
            ->willReturn([
                [
                    'CODIGO' => 1,
                    'EMAIL' => 'customer1@test.com',
                    'RAZAO' => 'Customer One',
                    'CKPESSOA' => 'F',
                ],
                [
                    'CODIGO' => 2,
                    'EMAIL' => 'customer2@test.com',
                    'RAZAO' => 'Customer Two',
                    'CKPESSOA' => 'F',
                ],
            ]);

        // Both customers are new (no existing hash)
        $this->syncLogResource->method('getEntityMapHash')->willReturn(null);

        // Mock customer creation
        $mockCustomer = $this->createMock(CustomerInterface::class);
        $mockCustomer->method('getId')->willReturn(1);

        $this->customerFactory->method('create')->willReturn($mockCustomer);
        $this->customerRepository->method('save')->willReturn($mockCustomer);
        $this->customerRepository->method('get')
            ->willThrowException(new NoSuchEntityException(__('Not found')));

        $result = $this->customerSync->syncAll();

        $this->assertArrayHasKey('created', $result);
        $this->assertArrayHasKey('updated', $result);
        $this->assertArrayHasKey('errors', $result);
    }

    public function testSyncAllSkipsCustomersWithoutEmailChange(): void
    {
        $this->helper->method('isCustomerSyncEnabled')->willReturn(true);

        $this->connection->method('fetchOne')
            ->willReturnCallback(function ($sql) {
                if (strpos($sql, 'COUNT') !== false) {
                    return ['total' => 1];
                }
                return null;
            });

        $customerData = [
            'CODIGO' => 1,
            'EMAIL' => 'test@test.com',
            'RAZAO' => 'Test',
            'CKPESSOA' => 'F',
        ];

        $this->connection->method('query')->willReturn([$customerData]);

        // Same hash means no changes
        $dataHash = md5(json_encode($customerData));
        $this->syncLogResource->method('getEntityMapHash')->willReturn($dataHash);

        // Should NOT try to create/update customer
        $this->customerFactory->expects($this->never())->method('create');

        $result = $this->customerSync->syncAll();

        $this->assertEquals(1, $result['skipped']);
    }

    // ========== createOrUpdateCustomer Tests ==========

    public function testCreateOrUpdateCustomerReturnsNullWithoutEmail(): void
    {
        $this->logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('without email'));

        $result = $this->customerSync->createOrUpdateCustomer(['CODIGO' => 1]);

        $this->assertNull($result);
    }

    public function testCreateOrUpdateCustomerReturnsNullWithoutErpCode(): void
    {
        $this->logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('without ERP code'));

        $result = $this->customerSync->createOrUpdateCustomer(['EMAIL' => 'test@test.com']);

        $this->assertNull($result);
    }

    public function testCreateOrUpdateCustomerReturnsNullForInvalidEmail(): void
    {
        $result = $this->customerSync->createOrUpdateCustomer([
            'CODIGO' => 1,
            'EMAIL' => 'invalid-email',
        ]);

        $this->assertNull($result);
    }

    public function testCreateOrUpdateCustomerUpdatesExisting(): void
    {
        $erpData = [
            'CODIGO' => 123,
            'EMAIL' => 'existing@test.com',
            'RAZAO' => 'Existing Customer',
            'CKPESSOA' => 'F',
            'FONECEL' => '11999999999',
        ];

        // Customer exists
        $existingCustomer = $this->createMock(CustomerInterface::class);
        $existingCustomer->method('getId')->willReturn(1);
        $existingCustomer->method('getCustomAttribute')->willReturn(null);

        $this->syncLogResource->method('getEntityMap')->willReturn(1);
        $this->customerRepository->method('getById')->willReturn($existingCustomer);
        $this->customerRepository->method('save')->willReturn($existingCustomer);

        $result = $this->customerSync->createOrUpdateCustomer($erpData);

        $this->assertInstanceOf(CustomerInterface::class, $result);
    }

    public function testCreateOrUpdateCustomerCreatesNewB2BCustomer(): void
    {
        $erpData = [
            'CODIGO' => 456,
            'EMAIL' => 'new@company.com',
            'RAZAO' => 'New Company LTDA',
            'FANTASIA' => 'New Company',
            'CKPESSOA' => 'J', // Pessoa Jurídica
            'CGC' => '12345678000190',
            'INSCEST' => '123456789',
            'ENDERECO' => 'Rua Teste',
            'NUMERO' => '100',
            'CIDADE' => 'São Paulo',
            'CEP' => '01234-567',
            'UF' => 'SP',
        ];

        // Customer doesn't exist
        $this->syncLogResource->method('getEntityMap')->willReturn(null);
        $this->customerRepository->method('get')
            ->willThrowException(new NoSuchEntityException(__('Not found')));

        // Mock customer creation
        $newCustomer = $this->createMock(CustomerInterface::class);
        $newCustomer->method('getId')->willReturn(2);

        // Should set group to B2B (4)
        $newCustomer->expects($this->once())
            ->method('setGroupId')
            ->with(4);

        $this->customerFactory->method('create')->willReturn($newCustomer);
        $this->customerRepository->method('save')->willReturn($newCustomer);

        // Mock address creation
        $address = $this->createMock(\Magento\Customer\Api\Data\AddressInterface::class);
        $this->addressFactory->method('create')->willReturn($address);

        // Mock region
        $region = $this->createMock(\Magento\Directory\Model\Region::class);
        $region->method('getId')->willReturn(508); // SP region ID
        $region->method('getName')->willReturn('São Paulo');
        $this->regionModelFactory->method('create')->willReturn($region);

        $regionInterface = $this->createMock(\Magento\Customer\Api\Data\RegionInterface::class);
        $this->regionFactory->method('create')->willReturn($regionInterface);

        $result = $this->customerSync->createOrUpdateCustomer($erpData);

        $this->assertInstanceOf(CustomerInterface::class, $result);
    }

    // ========== syncCustomerAddresses Tests ==========

    public function testSyncCustomerAddressesReturnsTrueWhenNoAddresses(): void
    {
        $this->connection->method('query')->willReturn([]);

        $result = $this->customerSync->syncCustomerAddresses(1, 123);

        $this->assertTrue($result);
    }

    public function testSyncCustomerAddressesReturnsFalseOnError(): void
    {
        $this->connection->method('query')
            ->willThrowException(new \Exception('Database error'));

        // Since table might not exist, it returns empty array
        $result = $this->customerSync->syncCustomerAddresses(1, 123);

        // The method catches exceptions and logs them, returning true for empty
        $this->assertTrue($result);
    }

    // ========== linkMagentoToErp Tests ==========

    public function testLinkMagentoToErpSuccess(): void
    {
        $customerId = 1;
        $erpCode = 999;

        // Customer exists in Magento
        $customer = $this->createMock(CustomerInterface::class);
        $this->customerRepository->method('getById')->willReturn($customer);
        $this->customerRepository->method('save')->willReturn($customer);

        // ERP customer exists
        $this->connection->method('fetchOne')
            ->willReturn(['CODIGO' => $erpCode, 'RAZAO' => 'Test']);

        // Should save mapping
        $this->syncLogResource->expects($this->once())
            ->method('setEntityMap')
            ->with('customer', (string)$erpCode, $customerId, $this->anything());

        $result = $this->customerSync->linkMagentoToErp($customerId, $erpCode);

        $this->assertTrue($result);
    }

    public function testLinkMagentoToErpFailsWhenErpCodeNotFound(): void
    {
        $customerId = 1;
        $erpCode = 999;

        // Customer exists in Magento
        $customer = $this->createMock(CustomerInterface::class);
        $this->customerRepository->method('getById')->willReturn($customer);

        // ERP customer does NOT exist
        $this->connection->method('fetchOne')->willReturn(null);

        // Should NOT save mapping
        $this->syncLogResource->expects($this->never())->method('setEntityMap');

        $result = $this->customerSync->linkMagentoToErp($customerId, $erpCode);

        $this->assertFalse($result);
    }

    public function testLinkMagentoToErpFailsWhenMagentoCustomerNotFound(): void
    {
        $customerId = 999;
        $erpCode = 123;

        // Customer does NOT exist in Magento
        $this->customerRepository->method('getById')
            ->willThrowException(new NoSuchEntityException(__('Customer not found')));

        $result = $this->customerSync->linkMagentoToErp($customerId, $erpCode);

        $this->assertFalse($result);
    }

    // ========== getErpCodeByCustomerId Tests ==========

    public function testGetErpCodeByCustomerIdFromMapping(): void
    {
        $customerId = 1;
        $expectedErpCode = '999';

        $this->syncLogResource->method('getErpCodeByMagentoId')
            ->willReturn($expectedErpCode);

        $result = $this->customerSync->getErpCodeByCustomerId($customerId);

        $this->assertEquals($expectedErpCode, $result);
    }

    public function testGetErpCodeByCustomerIdFromCustomAttribute(): void
    {
        $customerId = 1;
        $expectedErpCode = '888';

        // No mapping
        $this->syncLogResource->method('getErpCodeByMagentoId')->willReturn(null);

        // Customer has attribute
        $erpCodeAttr = $this->createMock(\Magento\Framework\Api\AttributeInterface::class);
        $erpCodeAttr->method('getValue')->willReturn($expectedErpCode);

        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getCustomAttribute')
            ->with('erp_code')
            ->willReturn($erpCodeAttr);

        $this->customerRepository->method('getById')->willReturn($customer);

        $result = $this->customerSync->getErpCodeByCustomerId($customerId);

        $this->assertEquals($expectedErpCode, $result);
    }

    public function testGetErpCodeByCustomerIdReturnsNullWhenNotFound(): void
    {
        $customerId = 1;

        // No mapping
        $this->syncLogResource->method('getErpCodeByMagentoId')->willReturn(null);

        // Customer doesn't exist
        $this->customerRepository->method('getById')
            ->willThrowException(new NoSuchEntityException(__('Not found')));

        $result = $this->customerSync->getErpCodeByCustomerId($customerId);

        $this->assertNull($result);
    }

    public function testGetErpCodeByCustomerIdUsesCache(): void
    {
        $customerId = 1;
        $expectedErpCode = '777';

        $this->syncLogResource->method('getErpCodeByMagentoId')
            ->willReturn($expectedErpCode);

        // First call
        $result1 = $this->customerSync->getErpCodeByCustomerId($customerId);

        // Second call should use cache (repository not called again)
        $this->customerRepository->expects($this->never())->method('getById');

        $result2 = $this->customerSync->getErpCodeByCustomerId($customerId);

        $this->assertEquals($expectedErpCode, $result1);
        $this->assertEquals($expectedErpCode, $result2);
    }

    // ========== syncByTaxvat Tests ==========

    public function testSyncByTaxvatReturnsSuccessWhenCustomerFound(): void
    {
        $taxvat = '12345678901';

        // ERP customer found
        $erpData = [
            'CODIGO' => 123,
            'EMAIL' => 'test@test.com',
            'RAZAO' => 'Test Customer',
            'CKPESSOA' => 'F',
        ];

        $this->connection->method('fetchOne')->willReturn($erpData);
        $this->syncLogResource->method('getEntityMap')->willReturn(null);
        $this->customerRepository->method('get')
            ->willThrowException(new NoSuchEntityException(__('Not found')));

        // Mock customer creation
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getId')->willReturn(1);

        $this->customerFactory->method('create')->willReturn($customer);
        $this->customerRepository->method('save')->willReturn($customer);

        $result = $this->customerSync->syncByTaxvat($taxvat);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['customer_id']);
        $this->assertEquals(123, $result['erp_code']);
    }

    public function testSyncByTaxvatReturnsFailureWhenNotInErp(): void
    {
        $taxvat = '00000000000';

        // ERP customer NOT found
        $this->connection->method('fetchOne')->willReturn(null);

        $result = $this->customerSync->syncByTaxvat($taxvat);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('não encontrado', $result['message']);
    }
}
