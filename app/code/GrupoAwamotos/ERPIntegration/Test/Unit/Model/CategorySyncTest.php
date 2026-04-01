<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Test\Unit\Model;

use GrupoAwamotos\ERPIntegration\Model\CategorySync;
use GrupoAwamotos\ERPIntegration\Api\ConnectionInterface;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use GrupoAwamotos\ERPIntegration\Model\ResourceModel\SyncLog as SyncLogResource;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Api\Data\CategoryInterfaceFactory;
use Magento\Catalog\Model\Category;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\State as AppState;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class CategorySyncTest extends TestCase
{
    private CategorySync $categorySync;
    private ConnectionInterface|MockObject $connection;
    private Helper|MockObject $helper;
    private SyncLogResource|MockObject $syncLogResource;
    private CategoryRepositoryInterface|MockObject $categoryRepository;
    private CategoryInterfaceFactory|MockObject $categoryFactory;
    private StoreManagerInterface|MockObject $storeManager;
    private LoggerInterface|MockObject $logger;
    private AppState|MockObject $appState;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(ConnectionInterface::class);
        $this->helper = $this->createMock(Helper::class);
        $this->syncLogResource = $this->createMock(SyncLogResource::class);
        $this->categoryRepository = $this->createMock(CategoryRepositoryInterface::class);
        $this->categoryFactory = $this->createMock(CategoryInterfaceFactory::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->appState = $this->createMock(AppState::class);

        // Default helper stubs
        $this->helper->method('getCategoryRootName')->willReturn('Catálogo ERP');
        $this->helper->method('getCategoryIncludeInMenu')->willReturn(true);

        $this->categorySync = new CategorySync(
            $this->connection,
            $this->helper,
            $this->syncLogResource,
            $this->categoryRepository,
            $this->categoryFactory,
            $this->storeManager,
            $this->logger,
            $this->appState
        );
    }

    // ─── getErpCategories ─────────────────────────────────────────────

    public function testGetErpCategoriesQueriesCorrectTable(): void
    {
        $rows = [
            ['CODIGO' => '1', 'DESCRICAO' => 'Bagageiros', 'NIVEL' => '01', 'NIVELPAI' => ''],
            ['CODIGO' => '2', 'DESCRICAO' => 'Retrovisores', 'NIVEL' => '02', 'NIVELPAI' => ''],
        ];

        $this->connection->expects($this->once())
            ->method('query')
            ->with($this->stringContains('MT_GRUPOCOMERCIAL'))
            ->willReturn($rows);

        $result = $this->categorySync->getErpCategories();
        $this->assertCount(2, $result);
        $this->assertEquals('Bagageiros', $result[0]['DESCRICAO']);
    }

    public function testGetErpCategoryCountReturnsTotal(): void
    {
        $this->connection->method('fetchOne')
            ->with($this->stringContains('COUNT'))
            ->willReturn(['total' => 25]);

        $result = $this->categorySync->getErpCategoryCount();
        $this->assertEquals(25, $result);
    }

    public function testGetErpCategoryCountReturnsZeroOnNull(): void
    {
        $this->connection->method('fetchOne')->willReturn(null);

        $result = $this->categorySync->getErpCategoryCount();
        $this->assertEquals(0, $result);
    }

    // ─── getOrCreateErpRootCategory ───────────────────────────────────

    public function testGetOrCreateErpRootCategoryReturnsExisting(): void
    {
        // Entity map returns an existing Magento ID
        $this->syncLogResource->method('getEntityMap')
            ->with('category_root', 'ROOT')
            ->willReturn(42);

        // Verify it still exists
        $category = $this->createMock(CategoryInterface::class);
        $this->categoryRepository->method('get')
            ->with(42)
            ->willReturn($category);

        $result = $this->categorySync->getOrCreateErpRootCategory();
        $this->assertEquals(42, $result);
    }

    public function testGetOrCreateErpRootCategoryCreatesNewWhenMissing(): void
    {
        // No existing mapping
        $this->syncLogResource->method('getEntityMap')
            ->with('category_root', 'ROOT')
            ->willReturn(null);

        $savedCategory = $this->createMock(Category::class);
        $savedCategory->method('getId')->willReturn(99);

        $newCategory = $this->createMock(Category::class);
        $this->categoryFactory->method('create')->willReturn($newCategory);

        $this->categoryRepository->expects($this->once())
            ->method('save')
            ->with($newCategory)
            ->willReturn($savedCategory);

        $this->syncLogResource->expects($this->once())
            ->method('setEntityMap')
            ->with('category_root', 'ROOT', 99);

        $result = $this->categorySync->getOrCreateErpRootCategory();
        $this->assertEquals(99, $result);
    }

    public function testGetOrCreateErpRootCategoryRecreatesDeletedRoot(): void
    {
        // Entity map returns old ID
        $this->syncLogResource->method('getEntityMap')
            ->with('category_root', 'ROOT')
            ->willReturn(42);

        // But category was deleted
        $this->categoryRepository->method('get')
            ->willThrowException(new NoSuchEntityException(__('Not found')));

        $savedCategory = $this->createMock(Category::class);
        $savedCategory->method('getId')->willReturn(100);

        $newCategory = $this->createMock(Category::class);
        $this->categoryFactory->method('create')->willReturn($newCategory);

        $this->categoryRepository->method('save')->willReturn($savedCategory);

        $result = $this->categorySync->getOrCreateErpRootCategory();
        $this->assertEquals(100, $result);
    }

    // ─── syncAll ──────────────────────────────────────────────────────

    public function testSyncAllReturnsResultArrayKeys(): void
    {
        $this->connection->method('query')->willReturn([]);

        $result = $this->categorySync->syncAll();

        $this->assertArrayHasKey('created', $result);
        $this->assertArrayHasKey('updated', $result);
        $this->assertArrayHasKey('deactivated', $result);
        $this->assertArrayHasKey('skipped', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('total_categories', $result);
    }

    public function testSyncAllReturnsZerosWhenNoErpCategories(): void
    {
        $this->connection->method('query')->willReturn([]);

        $result = $this->categorySync->syncAll();

        $this->assertEquals(0, $result['created']);
        $this->assertEquals(0, $result['updated']);
        $this->assertEquals(0, $result['total_categories']);
    }

    public function testSyncAllCreatesNewCategory(): void
    {
        $erpCategories = [
            ['CODIGO' => 'CAT01', 'DESCRICAO' => 'Bagageiros', 'NIVEL' => '01', 'NIVELPAI' => ''],
        ];

        $this->connection->method('query')->willReturn($erpCategories);

        // Root category exists
        $this->syncLogResource->method('getEntityMap')
            ->willReturnCallback(function (string $type, string $code) {
                if ($type === 'category_root') {
                    return 10;
                }
                return null; // No existing mapping for CAT01
            });

        $rootCategory = $this->createMock(CategoryInterface::class);
        $this->categoryRepository->method('get')
            ->with(10)
            ->willReturn($rootCategory);

        // Hash check: no existing hash → triggers sync
        $this->syncLogResource->method('getEntityMapHash')->willReturn(null);

        // New category will be created
        $savedCategory = $this->createMock(Category::class);
        $savedCategory->method('getId')->willReturn(50);

        $newCategory = $this->createMock(Category::class);
        $this->categoryFactory->method('create')->willReturn($newCategory);

        $this->categoryRepository->method('save')->willReturn($savedCategory);

        // Deactivation pass needs DB connection mock
        $dbConnection = $this->createMock(\Magento\Framework\DB\Adapter\AdapterInterface::class);
        $select = $this->createMock(\Magento\Framework\DB\Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $dbConnection->method('select')->willReturn($select);
        $dbConnection->method('fetchPairs')->willReturn([]);
        $this->syncLogResource->method('getConnection')->willReturn($dbConnection);

        $result = $this->categorySync->syncAll();

        $this->assertEquals(1, $result['created']);
        $this->assertEquals(1, $result['total_categories']);
    }

    public function testSyncAllSkipsUnchangedCategory(): void
    {
        $erpCategories = [
            ['CODIGO' => 'CAT01', 'DESCRICAO' => 'Bagageiros', 'NIVEL' => '01', 'NIVELPAI' => ''],
        ];

        $this->connection->method('query')->willReturn($erpCategories);

        $this->syncLogResource->method('getEntityMap')
            ->willReturnCallback(function (string $type, string $code) {
                if ($type === 'category_root') {
                    return 10;
                }
                if ($type === 'category' && $code === 'CAT01') {
                    return 50; // Existing mapping
                }
                return null;
            });

        $rootCategory = $this->createMock(CategoryInterface::class);
        $this->categoryRepository->method('get')->willReturn($rootCategory);

        // Same hash → skipped
        $dataHash = md5(json_encode($erpCategories[0]));
        $this->syncLogResource->method('getEntityMapHash')
            ->with('category', 'CAT01')
            ->willReturn($dataHash);

        $dbConnection = $this->createMock(\Magento\Framework\DB\Adapter\AdapterInterface::class);
        $select = $this->createMock(\Magento\Framework\DB\Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $dbConnection->method('select')->willReturn($select);
        $dbConnection->method('fetchPairs')->willReturn([]);
        $this->syncLogResource->method('getConnection')->willReturn($dbConnection);

        $result = $this->categorySync->syncAll();

        $this->assertEquals(0, $result['created']);
        $this->assertEquals(0, $result['updated']);
        $this->assertEquals(1, $result['skipped']);
    }

    public function testSyncAllCountsErrors(): void
    {
        $erpCategories = [
            ['CODIGO' => 'CAT01', 'DESCRICAO' => 'Bagageiros', 'NIVEL' => '01', 'NIVELPAI' => ''],
        ];

        $this->connection->method('query')->willReturn($erpCategories);

        $this->syncLogResource->method('getEntityMap')
            ->willReturnCallback(function (string $type, string $code) {
                if ($type === 'category_root') {
                    return 10;
                }
                return null;
            });

        $rootCategory = $this->createMock(CategoryInterface::class);
        $this->categoryRepository->method('get')
            ->with(10)
            ->willReturn($rootCategory);

        $this->syncLogResource->method('getEntityMapHash')->willReturn(null);

        // Save throws exception → error
        $this->categoryFactory->method('create')->willReturn($this->createMock(Category::class));
        $this->categoryRepository->method('save')
            ->willThrowException(new \RuntimeException('DB error'));

        $dbConnection = $this->createMock(\Magento\Framework\DB\Adapter\AdapterInterface::class);
        $select = $this->createMock(\Magento\Framework\DB\Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $dbConnection->method('select')->willReturn($select);
        $dbConnection->method('fetchPairs')->willReturn([]);
        $this->syncLogResource->method('getConnection')->willReturn($dbConnection);

        $result = $this->categorySync->syncAll();

        $this->assertEquals(1, $result['errors']);
    }

    public function testSyncAllLogsFatalException(): void
    {
        $this->connection->method('query')
            ->willThrowException(new \RuntimeException('Connection lost'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                '[ERP] Category sync failed',
                $this->callback(function (array $context): bool {
                    return str_contains($context['error'] ?? '', 'Connection lost');
                })
            );

        $this->syncLogResource->expects($this->once())
            ->method('addLog')
            ->with('category', 'import', 'error', $this->anything());

        $result = $this->categorySync->syncAll();
        $this->assertGreaterThan(0, $result['errors']);
    }

    public function testSyncAllSkipsCategoriesWithEmptyCode(): void
    {
        $erpCategories = [
            ['CODIGO' => '', 'DESCRICAO' => 'Empty', 'NIVEL' => '01', 'NIVELPAI' => ''],
            ['CODIGO' => 'CAT02', 'DESCRICAO' => 'Valid', 'NIVEL' => '02', 'NIVELPAI' => ''],
        ];

        $this->connection->method('query')->willReturn($erpCategories);

        $this->syncLogResource->method('getEntityMap')
            ->willReturnCallback(function (string $type) {
                if ($type === 'category_root') {
                    return 10;
                }
                return null;
            });

        $rootCategory = $this->createMock(CategoryInterface::class);
        $this->categoryRepository->method('get')
            ->with(10)
            ->willReturn($rootCategory);

        $this->syncLogResource->method('getEntityMapHash')->willReturn(null);

        $savedCategory = $this->createMock(Category::class);
        $savedCategory->method('getId')->willReturn(55);

        $newCategory = $this->createMock(Category::class);
        $this->categoryFactory->method('create')->willReturn($newCategory);

        $this->categoryRepository->method('save')->willReturn($savedCategory);

        $dbConnection = $this->createMock(\Magento\Framework\DB\Adapter\AdapterInterface::class);
        $select = $this->createMock(\Magento\Framework\DB\Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $dbConnection->method('select')->willReturn($select);
        $dbConnection->method('fetchPairs')->willReturn([]);
        $this->syncLogResource->method('getConnection')->willReturn($dbConnection);

        $result = $this->categorySync->syncAll();

        // Only CAT02 should be processed, empty code skipped
        $this->assertEquals(2, $result['total_categories']); // total includes all ERP rows
        $this->assertEquals(1, $result['created']); // only the valid one
    }
}
