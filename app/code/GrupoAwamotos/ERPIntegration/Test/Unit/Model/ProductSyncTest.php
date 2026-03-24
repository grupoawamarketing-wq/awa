<?php
declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Test\Unit\Model;

use GrupoAwamotos\ERPIntegration\Model\ProductSync;
use GrupoAwamotos\ERPIntegration\Api\ConnectionInterface;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use GrupoAwamotos\ERPIntegration\Model\ResourceModel\SyncLog as SyncLogResource;
use GrupoAwamotos\ERPIntegration\Model\Validator\ProductValidator;
use GrupoAwamotos\ERPIntegration\Model\Validator\ValidationResult;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Model\Product;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Catalog\Api\CategoryLinkManagementInterface;
use Magento\Framework\App\State as AppState;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class ProductSyncTest extends TestCase
{
    private ProductSync $productSync;
    private ConnectionInterface|MockObject $connection;
    private Helper|MockObject $helper;
    private ProductRepositoryInterface|MockObject $productRepository;
    private ProductInterfaceFactory|MockObject $productFactory;
    private StoreManagerInterface|MockObject $storeManager;
    private SyncLogResource|MockObject $syncLogResource;
    private ProductValidator|MockObject $productValidator;
    private LoggerInterface|MockObject $logger;
    private AppState|MockObject $appState;
    private CategoryLinkManagementInterface|MockObject $categoryLinkManagement;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(ConnectionInterface::class);
        $this->helper = $this->createMock(Helper::class);
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->productFactory = $this->createMock(ProductInterfaceFactory::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->syncLogResource = $this->createMock(SyncLogResource::class);
        $this->productValidator = $this->createMock(ProductValidator::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->appState = $this->createMock(AppState::class);
        $this->categoryLinkManagement = $this->createMock(CategoryLinkManagementInterface::class);
        $this->productValidator->method('validate')->willReturn(ValidationResult::success());

        // Setup common mocks
        $this->helper->method('getStockFilial')->willReturn(1);
        $this->helper->method('getDefaultPriceList')->willReturn(24);
        $this->helper->method('filterComercializa')->willReturn(true);

        $store = $this->createMock(StoreInterface::class);
        $store->method('getWebsiteId')->willReturn(1);
        $this->storeManager->method('getDefaultStoreView')->willReturn($store);

        $this->productSync = new ProductSync(
            $this->connection,
            $this->helper,
            $this->productRepository,
            $this->productFactory,
            $this->storeManager,
            $this->syncLogResource,
            $this->productValidator,
            $this->logger,
            $this->appState,
            $this->categoryLinkManagement
        );
    }

    public function testGetErpProductCountReturnsInteger(): void
    {
        $this->connection->method('fetchOne')
            ->willReturn(['total' => 1500]);

        $count = $this->productSync->getErpProductCount();

        $this->assertEquals(1500, $count);
    }

    public function testGetErpProductCountReturnsZeroOnEmpty(): void
    {
        $this->connection->method('fetchOne')->willReturn(null);

        $count = $this->productSync->getErpProductCount();

        $this->assertEquals(0, $count);
    }

    public function testGetErpProductsWithPagination(): void
    {
        $expectedProducts = [
            ['CODIGO' => 'SKU-001', 'DESCRICAO' => 'Product 1'],
            ['CODIGO' => 'SKU-002', 'DESCRICAO' => 'Product 2'],
        ];

        $this->connection->expects($this->once())
            ->method('query')
            ->with(
                $this->callback(function ($sql) {
                    return strpos($sql, 'OFFSET') !== false
                        && strpos($sql, 'FETCH NEXT') !== false
                        && strpos($sql, 'OFFSET 100 ROWS FETCH NEXT 50 ROWS ONLY') !== false;
                }),
                $this->callback(function ($params) {
                    return isset($params[':filial1']) && $params[':filial1'] === 1
                        && isset($params[':filial2']) && $params[':filial2'] === 1
                        && isset($params[':priceList']) && $params[':priceList'] === 24;
                })
            )
            ->willReturn($expectedProducts);

        $result = $this->productSync->getErpProducts(50, 100);

        $this->assertEquals($expectedProducts, $result);
    }

    public function testGetErpProductsWithoutPagination(): void
    {
        $this->connection->expects($this->once())
            ->method('query')
            ->with(
                $this->callback(function ($sql) {
                    return strpos($sql, 'OFFSET') === false;
                }),
                $this->anything()
            )
            ->willReturn([]);

        $this->productSync->getErpProducts();
    }

    public function testSyncAllProcessesInBatches(): void
    {
        // Simulate 1200 products (should process in 3 batches of 500)
        $this->connection->method('fetchOne')
            ->willReturn(['total' => 1200]);

        // Return products for each batch
        $batchProducts = [];
        for ($i = 0; $i < 500; $i++) {
            $batchProducts[] = [
                'CODIGO' => 'SKU-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'DESCRICAO' => 'Product ' . $i,
                'CCKATIVO' => 'S',
                'VLRVENDA' => 100.00,
            ];
        }

        $this->connection->method('query')
            ->willReturnOnConsecutiveCalls(
                $batchProducts, // First batch (500)
                $batchProducts, // Second batch (500)
                array_slice($batchProducts, 0, 200) // Third batch (200)
            );

        // All products are new
        $this->productRepository->method('get')
            ->willThrowException(new NoSuchEntityException(__('Not found')));

        $newProduct = $this->createMock(Product::class);
        $newProduct->method('getId')->willReturn(1);
        $this->productFactory->method('create')->willReturn($newProduct);

        // Track batches processed through logging
        $batchLogs = [];
        $this->logger->method('info')
            ->willReturnCallback(function ($message, $context = []) use (&$batchLogs) {
                if (isset($context['batch_number'])) {
                    $batchLogs[] = $context['batch_number'];
                }
            });

        $result = $this->productSync->syncAll();

        // Should have processed 3 batches
        $this->assertEquals(3, $result['batches_processed']);
        $this->assertEquals(1200, $result['total_products']);
        $this->assertArrayHasKey('execution_time', $result);
    }

    public function testSyncAllSkipsProductsWithEmptySku(): void
    {
        $this->connection->method('fetchOne')
            ->willReturn(['total' => 2]);

        $erpProducts = [
            ['CODIGO' => '', 'DESCRICAO' => 'Product with empty SKU'],
            ['CODIGO' => 'VALID-SKU', 'DESCRICAO' => 'Valid Product', 'CCKATIVO' => 'S', 'VLRVENDA' => 50.00],
        ];

        $this->connection->method('query')->willReturn($erpProducts);

        $this->productRepository->method('get')
            ->willThrowException(new NoSuchEntityException(__('Not found')));

        $newProduct = $this->createMock(Product::class);
        $newProduct->method('getId')->willReturn(1);
        $this->productFactory->method('create')->willReturn($newProduct);

        $result = $this->productSync->syncAll();

        $this->assertEquals(1, $result['skipped']);
        $this->assertEquals(1, $result['created']);
    }

    public function testSyncAllSkipsProductsWithEmptyName(): void
    {
        $this->connection->method('fetchOne')
            ->willReturn(['total' => 2]);

        $erpProducts = [
            ['CODIGO' => 'SKU-001', 'DESCRICAO' => ''],
            ['CODIGO' => 'SKU-002', 'DESCRICAO' => '   '], // Whitespace only
        ];

        $this->connection->method('query')->willReturn($erpProducts);

        $result = $this->productSync->syncAll();

        $this->assertEquals(2, $result['skipped']);
        $this->assertEquals(0, $result['created']);
    }

    public function testSyncAllSkipsUnchangedProducts(): void
    {
        $this->connection->method('fetchOne')
            ->willReturn(['total' => 1]);

        $erpProduct = [
            'CODIGO' => 'SKU-001',
            'DESCRICAO' => 'Product 1',
            'CCKATIVO' => 'S',
            'VLRVENDA' => 100.00,
        ];

        $this->connection->method('query')->willReturn([$erpProduct]);

        // Same hash exists in entity map
        $expectedHash = md5(json_encode($erpProduct));
        $this->syncLogResource->method('getEntityMapHash')
            ->with('product', 'SKU-001')
            ->willReturn($expectedHash);

        $result = $this->productSync->syncAll();

        // Product should be skipped since hash matches
        $this->assertEquals(1, $result['skipped']);
        $this->assertEquals(0, $result['updated']);
    }

    public function testSyncAllCreatesNewProduct(): void
    {
        $this->connection->method('fetchOne')
            ->willReturn(['total' => 1]);

        $erpProduct = [
            'CODIGO' => 'NEW-SKU',
            'DESCRICAO' => 'New Product',
            'COMPLEMENTO' => 'Short description',
            'CCKATIVO' => 'S',
            'VLRVENDA' => 150.00,
            'VPESO' => 2.5,
            'CODINTERNO' => 'INT-001',
            'NCM' => '12345678',
        ];

        $this->connection->method('query')->willReturn([$erpProduct]);
        $this->syncLogResource->method('getEntityMapHash')->willReturn(null);

        // Product doesn't exist
        $this->productRepository->method('get')
            ->willThrowException(new NoSuchEntityException(__('Not found')));

        $newProduct = $this->createMock(Product::class);
        $newProduct->method('getId')->willReturn(99);

        // Verify product is configured correctly
        $newProduct->expects($this->once())->method('setSku')->with('NEW-SKU');
        $newProduct->expects($this->once())->method('setName')->with('New Product');
        $newProduct->expects($this->once())->method('setPrice')->with(150.00);
        $newProduct->expects($this->once())->method('setWeight')->with(2.5);
        // Note: setShortDescription is a magic method and cannot be strictly mocked

        $this->productFactory->method('create')->willReturn($newProduct);
        $this->productRepository->expects($this->once())->method('save')->with($newProduct);

        $result = $this->productSync->syncAll();

        $this->assertEquals(1, $result['created']);
        $this->assertEquals(0, $result['updated']);
    }

    public function testSyncAllUsesBaseSkuPriceFallback(): void
    {
        $this->connection->method('fetchOne')
            ->willReturn(['total' => 1]);

        $erpProduct = [
            'CODIGO' => '576 ASS',
            'DESCRICAO' => 'Variant Product',
            'CCKATIVO' => 'S',
            'VLRVENDA' => null,
        ];

        $this->connection->method('query')
            ->willReturnOnConsecutiveCalls(
                [$erpProduct],
                [['CODIGO' => '576', 'VLRVENDA' => 19.62]]
            );

        $this->syncLogResource->method('getEntityMapHash')->willReturn(null);

        $this->productRepository->method('get')
            ->willThrowException(new NoSuchEntityException(__('Not found')));

        $newProduct = $this->createMock(Product::class);
        $newProduct->method('getId')->willReturn(101);
        $newProduct->expects($this->once())->method('setSku')->with('576 ASS');
        $newProduct->expects($this->once())->method('setPrice')->with(19.62);

        $this->productFactory->method('create')->willReturn($newProduct);
        $this->productRepository->expects($this->once())->method('save')->with($newProduct);

        $result = $this->productSync->syncAll();

        $this->assertEquals(1, $result['created']);
        $this->assertEquals(0, $result['validation_failed']);
    }

    public function testSyncAllUpdatesExistingProduct(): void
    {
        $this->connection->method('fetchOne')
            ->willReturn(['total' => 1]);

        $erpProduct = [
            'CODIGO' => 'EXISTING-SKU',
            'DESCRICAO' => 'Updated Product Name',
            'CCKATIVO' => 'S',
            'VLRVENDA' => 200.00,
        ];

        $this->connection->method('query')->willReturn([$erpProduct]);
        $this->syncLogResource->method('getEntityMapHash')->willReturn('different-hash');

        // Product exists
        $existingProduct = $this->createMock(Product::class);
        $existingProduct->method('getId')->willReturn(50);
        $existingProduct->expects($this->once())->method('setName')->with('Updated Product Name');
        $existingProduct->expects($this->once())->method('setPrice')->with(200.00);

        $this->productRepository->method('get')
            ->with('EXISTING-SKU')
            ->willReturn($existingProduct);

        $result = $this->productSync->syncAll();

        $this->assertEquals(0, $result['created']);
        $this->assertEquals(1, $result['updated']);
    }

    public function testSyncAllHandlesErrors(): void
    {
        $this->connection->method('fetchOne')
            ->willReturn(['total' => 2]);

        $erpProducts = [
            ['CODIGO' => 'SKU-001', 'DESCRICAO' => 'Product 1', 'CCKATIVO' => 'S', 'VLRVENDA' => 100.00],
            ['CODIGO' => 'SKU-002', 'DESCRICAO' => 'Product 2', 'CCKATIVO' => 'S', 'VLRVENDA' => 100.00],
        ];

        $this->connection->method('query')->willReturn($erpProducts);
        $this->syncLogResource->method('getEntityMapHash')->willReturn(null);

        $this->productRepository->method('get')
            ->willThrowException(new NoSuchEntityException(__('Not found')));

        $newProduct = $this->createMock(Product::class);
        $newProduct->method('getId')->willReturn(1);
        $this->productFactory->method('create')->willReturn($newProduct);

        // First save succeeds, second fails
        $this->productRepository->method('save')
            ->willReturnCallback(function ($product) {
                static $callCount = 0;
                $callCount++;
                if ($callCount === 2) {
                    throw new \Exception('Save failed');
                }
            });

        $this->logger->expects($this->atLeastOnce())
            ->method('error');

        $result = $this->productSync->syncAll();

        $this->assertEquals(1, $result['created']);
        $this->assertEquals(1, $result['errors']);
    }

    public function testSyncAllReturnsEmptyOnZeroProducts(): void
    {
        $this->connection->method('fetchOne')
            ->willReturn(['total' => 0]);

        $this->logger->expects($this->once())
            ->method('info')
            ->with($this->stringContains('No products to sync'));

        $result = $this->productSync->syncAll();

        $this->assertEquals(0, $result['total_products']);
        $this->assertEquals(0, $result['batches_processed']);
    }

    public function testSyncBySkuReturnsTrueWhenFound(): void
    {
        $erpProduct = [
            'CODIGO' => 'TEST-SKU',
            'DESCRICAO' => 'Test Product',
            'CCKATIVO' => 'S',
            'VLRVENDA' => 100.00,
        ];

        $this->connection->method('fetchOne')->willReturn($erpProduct);
        $this->syncLogResource->method('getEntityMapHash')->willReturn(null);

        $this->productRepository->method('get')
            ->willThrowException(new NoSuchEntityException(__('Not found')));

        $newProduct = $this->createMock(Product::class);
        $newProduct->method('getId')->willReturn(10);
        $newProduct->expects($this->once())->method('setSku')->with('TEST-SKU');
        $this->productFactory->method('create')->willReturn($newProduct);
        $this->productRepository->expects($this->once())->method('save')->with($newProduct);

        $result = $this->productSync->syncBySku('TEST-SKU');

        $this->assertTrue($result);
    }

    public function testSyncBySkuReturnsFalseWhenNotFound(): void
    {
        $this->connection->method('fetchOne')->willReturn(null);

        $result = $this->productSync->syncBySku('NONEXISTENT-SKU');

        $this->assertFalse($result);
    }
}
