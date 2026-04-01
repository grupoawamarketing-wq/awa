<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Test\Unit\Model;

use GrupoAwamotos\ERPIntegration\Model\PriceSync;
use GrupoAwamotos\ERPIntegration\Api\ConnectionInterface;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use GrupoAwamotos\ERPIntegration\Model\ResourceModel\SyncLog as SyncLogResource;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\ResourceModel\Product\Action as ProductAction;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class PriceSyncTest extends TestCase
{
    private PriceSync $priceSync;
    private ConnectionInterface|MockObject $connection;
    private Helper|MockObject $helper;
    private ProductRepositoryInterface|MockObject $productRepository;
    private ProductAction|MockObject $productAction;
    private SearchCriteriaBuilder|MockObject $searchCriteriaBuilder;
    private SyncLogResource|MockObject $syncLogResource;
    private LoggerInterface|MockObject $logger;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(ConnectionInterface::class);
        $this->helper = $this->createMock(Helper::class);
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->productAction = $this->createMock(ProductAction::class);
        $this->searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $this->syncLogResource = $this->createMock(SyncLogResource::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        // Default helper config
        $this->helper->method('getDefaultPriceList')->willReturn(24);
        $this->helper->method('getStockFilial')->willReturn(1);

        $this->priceSync = new PriceSync(
            $this->connection,
            $this->helper,
            $this->productRepository,
            $this->searchCriteriaBuilder,
            $this->syncLogResource,
            $this->logger,
            $this->productAction
        );
    }

    // ─── getErpPrice / getPriceBySku ──────────────────────────────────

    public function testGetErpPriceReturnsDataForValidSku(): void
    {
        $erpRow = [
            'CODIGO'     => 'SKU-001',
            'VLRVENDA'   => '150.99',
            'VLRVDMIN'   => '120.00',
            'VLRVDMAX'   => '180.00',
            'VLRCUSTO'   => '80.50',
            'VLRTABELA'  => '160.00',
            'FATORPRECO' => '24',
        ];

        $this->connection->method('fetchOne')
            ->willReturn($erpRow);

        $result = $this->priceSync->getErpPrice('SKU-001');

        $this->assertNotNull($result);
        $this->assertEquals('150.99', $result['VLRVENDA']);
        $this->assertEquals('24', $result['FATORPRECO']);
    }

    public function testGetErpPriceReturnsNullWhenNotFound(): void
    {
        $this->connection->method('fetchOne')
            ->willReturn(null);

        $result = $this->priceSync->getErpPrice('NONEXISTENT');
        $this->assertNull($result);
    }

    public function testGetErpPriceTriesBaseSkuOnMiss(): void
    {
        $erpRow = [
            'CODIGO'     => '1119',
            'VLRVENDA'   => '99.00',
            'VLRVDMIN'   => '80.00',
            'VLRVDMAX'   => '120.00',
            'VLRCUSTO'   => '50.00',
            'VLRTABELA'  => '100.00',
            'FATORPRECO' => '24',
        ];

        // First call (exact SKU "1119 RS") returns null, second call (base "1119") returns data
        $this->connection->method('fetchOne')
            ->willReturnOnConsecutiveCalls(null, $erpRow);

        $result = $this->priceSync->getErpPrice('1119 RS');

        $this->assertNotNull($result);
        $this->assertEquals('99.00', $result['VLRVENDA']);
    }

    public function testGetErpPriceDoesNotRetryWhenBaseSkuIsSame(): void
    {
        $this->connection->expects($this->once())
            ->method('fetchOne')
            ->willReturn(null);

        // "12345" has no variant suffix, so base SKU = "12345" — no retry
        $result = $this->priceSync->getErpPrice('12345');
        $this->assertNull($result);
    }

    public function testGetErpPriceWithCustomPriceList(): void
    {
        $erpRow = [
            'CODIGO'     => 'SKU-X',
            'VLRVENDA'   => '200.00',
            'VLRVDMIN'   => '180.00',
            'VLRVDMAX'   => '250.00',
            'VLRCUSTO'   => '100.00',
            'VLRTABELA'  => '210.00',
            'FATORPRECO' => '5',
        ];

        $this->connection->expects($this->once())
            ->method('fetchOne')
            ->with(
                $this->stringContains('MT_MATERIALLISTA'),
                $this->equalTo(['SKU-X', 5, 1]) // priceList=5, filial=1
            )
            ->willReturn($erpRow);

        $result = $this->priceSync->getErpPrice('SKU-X', 5);
        $this->assertNotNull($result);
        $this->assertEquals('5', $result['FATORPRECO']);
    }

    public function testGetErpPriceLogsOnException(): void
    {
        $this->connection->method('fetchOne')
            ->willThrowException(new \RuntimeException('DB error'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Price query error'));

        $result = $this->priceSync->getErpPrice('SKU-ERR');
        $this->assertNull($result);
    }

    public function testGetPriceBySkuDelegatesToGetErpPrice(): void
    {
        $erpRow = [
            'CODIGO'     => 'SKU-D',
            'VLRVENDA'   => '50.00',
            'VLRVDMIN'   => '40.00',
            'VLRVDMAX'   => '60.00',
            'VLRCUSTO'   => '25.00',
            'VLRTABELA'  => '55.00',
            'FATORPRECO' => '24',
        ];

        $this->connection->method('fetchOne')->willReturn($erpRow);

        $result = $this->priceSync->getPriceBySku('SKU-D');
        $this->assertNotNull($result);
        $this->assertEquals('50.00', $result['VLRVENDA']);
    }

    // ─── getErpPrices ─────────────────────────────────────────────────

    public function testGetErpPricesReturnsBatch(): void
    {
        $rows = [
            ['CODIGO' => 'A', 'VLRVENDA' => '10.00', 'VLRVDMIN' => '8', 'VLRVDMAX' => '12', 'VLRCUSTO' => '5', 'FATORPRECO' => '24'],
            ['CODIGO' => 'B', 'VLRVENDA' => '20.00', 'VLRVDMIN' => '18', 'VLRVDMAX' => '22', 'VLRCUSTO' => '10', 'FATORPRECO' => '24'],
        ];

        $this->connection->method('query')
            ->willReturn($rows);

        $result = $this->priceSync->getErpPrices(100, 0);
        $this->assertCount(2, $result);
    }

    public function testGetErpPricesReturnsEmptyOnError(): void
    {
        $this->connection->method('query')
            ->willThrowException(new \RuntimeException('timeout'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Price list query error'));

        $result = $this->priceSync->getErpPrices();
        $this->assertEmpty($result);
    }

    // ─── getAvailablePriceLists ───────────────────────────────────────

    public function testGetAvailablePriceListsReturnsData(): void
    {
        $lists = [
            ['CODIGO' => 24, 'DESCRICAO' => 'NACIONAL', 'ATIVO' => 'S', 'total_produtos' => 3341, 'total_clientes' => 0],
            ['CODIGO' => 5,  'DESCRICAO' => 'ATACADO',  'ATIVO' => 'S', 'total_produtos' => 1200, 'total_clientes' => 50],
        ];

        $this->connection->method('query')
            ->with($this->stringContains('VE_FATORPRECO'))
            ->willReturn($lists);

        $result = $this->priceSync->getAvailablePriceLists();
        $this->assertCount(2, $result);
        $this->assertEquals('NACIONAL', $result[0]['DESCRICAO']);
    }

    public function testGetAvailablePriceListsReturnsEmptyOnError(): void
    {
        $this->connection->method('query')
            ->willThrowException(new \RuntimeException('connection lost'));

        $result = $this->priceSync->getAvailablePriceLists();
        $this->assertEmpty($result);
    }

    // ─── syncBySku ────────────────────────────────────────────────────

    public function testSyncBySkuUpdatesPrice(): void
    {
        $erpRow = [
            'CODIGO'     => 'SKU-UP',
            'VLRVENDA'   => '150.00',
            'VLRVDMIN'   => '130.00',
            'VLRVDMAX'   => '170.00',
            'VLRCUSTO'   => '80.00',
            'VLRTABELA'  => '155.00',
            'FATORPRECO' => '24',
        ];

        $this->connection->method('fetchOne')->willReturn($erpRow);

        $product = $this->createMock(ProductInterface::class);
        $product->method('getPrice')->willReturn(100.00);
        $product->expects($this->once())
            ->method('setPrice')
            ->with(150.00);

        $this->productRepository->method('get')
            ->with('SKU-UP')
            ->willReturn($product);

        $this->productRepository->expects($this->once())
            ->method('save')
            ->with($product);

        $this->syncLogResource->expects($this->once())
            ->method('addLog')
            ->with('price', 'import', 'success', $this->stringContains('SKU-UP'));

        $result = $this->priceSync->syncBySku('SKU-UP');
        $this->assertTrue($result);
    }

    public function testSyncBySkuSkipsWhenPriceUnchanged(): void
    {
        $erpRow = [
            'CODIGO'     => 'SKU-SAME',
            'VLRVENDA'   => '100.00',
            'VLRVDMIN'   => '90.00',
            'VLRVDMAX'   => '110.00',
            'VLRCUSTO'   => '50.00',
            'VLRTABELA'  => '105.00',
            'FATORPRECO' => '24',
        ];

        $this->connection->method('fetchOne')->willReturn($erpRow);

        $product = $this->createMock(ProductInterface::class);
        $product->method('getPrice')->willReturn(100.00); // same as VLRVENDA

        $this->productRepository->method('get')->willReturn($product);

        // Should NOT save because price difference < 0.01
        $this->productRepository->expects($this->never())->method('save');

        $result = $this->priceSync->syncBySku('SKU-SAME');
        $this->assertTrue($result);
    }

    public function testSyncBySkuReturnsFalseWhenErpPriceNotFound(): void
    {
        $this->connection->method('fetchOne')->willReturn(null);

        $result = $this->priceSync->syncBySku('MISSING');
        $this->assertFalse($result);
    }

    public function testSyncBySkuReturnsFalseWhenPriceIsZero(): void
    {
        $erpRow = [
            'CODIGO'     => 'SKU-ZERO',
            'VLRVENDA'   => '0',
            'VLRVDMIN'   => '0',
            'VLRVDMAX'   => '0',
            'VLRCUSTO'   => '0',
            'VLRTABELA'  => '0',
            'FATORPRECO' => '24',
        ];

        $this->connection->method('fetchOne')->willReturn($erpRow);

        $result = $this->priceSync->syncBySku('SKU-ZERO');
        $this->assertFalse($result);
    }

    public function testSyncBySkuReturnsFalseWhenProductNotFound(): void
    {
        $erpRow = [
            'CODIGO'     => 'SKU-NF',
            'VLRVENDA'   => '50.00',
            'VLRVDMIN'   => '40.00',
            'VLRVDMAX'   => '60.00',
            'VLRCUSTO'   => '25.00',
            'VLRTABELA'  => '55.00',
            'FATORPRECO' => '24',
        ];

        $this->connection->method('fetchOne')->willReturn($erpRow);
        $this->productRepository->method('get')
            ->willThrowException(new NoSuchEntityException(__('Not found')));

        $this->logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('Product not found for price sync'));

        $result = $this->priceSync->syncBySku('SKU-NF');
        $this->assertFalse($result);
    }

    public function testSyncBySkuSetsCostWhenAvailable(): void
    {
        $erpRow = [
            'CODIGO'     => 'SKU-COST',
            'VLRVENDA'   => '200.00',
            'VLRVDMIN'   => '180.00',
            'VLRVDMAX'   => '250.00',
            'VLRCUSTO'   => '95.00',
            'VLRTABELA'  => '210.00',
            'FATORPRECO' => '24',
        ];

        $this->connection->method('fetchOne')->willReturn($erpRow);

        $product = $this->createMock(ProductInterface::class);
        $product->method('getPrice')->willReturn(100.00);

        // Expect setCustomAttribute for cost and msrp
        $setCalls = [];
        $product->expects($this->atLeastOnce())
            ->method('setCustomAttribute')
            ->willReturnCallback(function (string $attr, $val) use (&$setCalls, $product): ProductInterface {
                $setCalls[$attr] = $val;
                return $product;
            });

        $this->productRepository->method('get')->willReturn($product);
        $this->productRepository->method('save');

        $this->priceSync->syncBySku('SKU-COST');

        $this->assertEquals(95.0, $setCalls['cost'] ?? null);
        $this->assertEquals(250.0, $setCalls['msrp'] ?? null);
    }

    public function testSyncBySkuSetsMsrpWhenMaxPriceSignificantlyHigher(): void
    {
        $erpRow = [
            'CODIGO'     => 'SKU-MSRP',
            'VLRVENDA'   => '100.00',
            'VLRVDMIN'   => '80.00',
            'VLRVDMAX'   => '200.00', // 200 > 100*1.05 = 105
            'VLRCUSTO'   => '0',
            'VLRTABELA'  => '110.00',
            'FATORPRECO' => '24',
        ];

        $this->connection->method('fetchOne')->willReturn($erpRow);

        $product = $this->createMock(ProductInterface::class);
        $product->method('getPrice')->willReturn(50.00);

        $product->expects($this->atLeastOnce())
            ->method('setCustomAttribute')
            ->with('msrp', 200.0);

        $this->productRepository->method('get')->willReturn($product);
        $this->productRepository->method('save');

        $this->priceSync->syncBySku('SKU-MSRP');
    }

    public function testSyncBySkuLogsSaveError(): void
    {
        $erpRow = [
            'CODIGO'     => 'SKU-ERR',
            'VLRVENDA'   => '75.00',
            'VLRVDMIN'   => '60.00',
            'VLRVDMAX'   => '80.00',
            'VLRCUSTO'   => '30.00',
            'VLRTABELA'  => '78.00',
            'FATORPRECO' => '24',
        ];

        $this->connection->method('fetchOne')->willReturn($erpRow);

        $product = $this->createMock(ProductInterface::class);
        $product->method('getPrice')->willReturn(10.00);

        $this->productRepository->method('get')->willReturn($product);
        $this->productRepository->method('save')
            ->willThrowException(new \RuntimeException('Save failed'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Price sync error for SKU'));

        $this->syncLogResource->expects($this->once())
            ->method('addLog')
            ->with('price', 'import', 'error', $this->anything(), 'SKU-ERR');

        $result = $this->priceSync->syncBySku('SKU-ERR');
        $this->assertFalse($result);
    }

    // ─── syncAll ──────────────────────────────────────────────────────

    public function testSyncAllReturnsResultArray(): void
    {
        // ERP returns 2 rows
        $erpRows = [
            ['MATERIAL' => 'SKU-A', 'VLRVENDA' => '100.00', 'VLRVDMIN' => '90', 'VLRVDMAX' => '110', 'VLRCUSTO' => '50'],
            ['MATERIAL' => 'SKU-B', 'VLRVENDA' => '200.00', 'VLRVDMIN' => '180', 'VLRVDMAX' => '220', 'VLRCUSTO' => '100'],
        ];

        $this->connection->method('query')
            ->willReturn($erpRows);

        // Products in Magento
        $productA = $this->createMock(ProductInterface::class);
        $productA->method('getSku')->willReturn('SKU-A');
        $productA->method('getPrice')->willReturn(80.00); // different → updated
        $productA->method('getId')->willReturn(1);

        $productB = $this->createMock(ProductInterface::class);
        $productB->method('getSku')->willReturn('SKU-B');
        $productB->method('getPrice')->willReturn(200.00); // same → skipped
        $productB->method('getId')->willReturn(2);

        $productC = $this->createMock(ProductInterface::class);
        $productC->method('getSku')->willReturn('SKU-C');
        $productC->method('getPrice')->willReturn(50.00); // no ERP price → not_found
        $productC->method('getId')->willReturn(3);

        $searchCriteria = $this->createMock(SearchCriteriaInterface::class);
        $this->searchCriteriaBuilder->method('setPageSize')->willReturnSelf();
        $this->searchCriteriaBuilder->method('setCurrentPage')->willReturnSelf();
        $this->searchCriteriaBuilder->method('create')->willReturn($searchCriteria);

        $searchResults = $this->createMock(SearchResultsInterface::class);
        $searchResults->method('getItems')
            ->willReturnOnConsecutiveCalls([$productA, $productB, $productC], []);
        $searchResults->method('getTotalCount')->willReturn(3);

        $this->productRepository->method('getList')
            ->willReturn($searchResults);

        // productAction should be called once for SKU-A (updated)
        $this->productAction->expects($this->once())
            ->method('updateAttributes')
            ->with([1], $this->callback(function (array $data): bool {
                return isset($data['price']) && abs($data['price'] - 100.00) < 0.01;
            }), 0);

        $result = $this->priceSync->syncAll();

        $this->assertArrayHasKey('updated', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('skipped', $result);
        $this->assertArrayHasKey('not_found', $result);
        $this->assertEquals(1, $result['updated']);   // SKU-A
        $this->assertEquals(1, $result['skipped']);    // SKU-B
        $this->assertEquals(1, $result['not_found']);  // SKU-C
        $this->assertEquals(0, $result['errors']);
    }

    public function testSyncAllReturnsEmptyResultWhenNoErpPrices(): void
    {
        $this->connection->method('query')->willReturn([]);

        $this->logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('No prices found'));

        $result = $this->priceSync->syncAll();
        $this->assertEquals(0, $result['updated']);
    }

    public function testSyncAllCountsErrorsCorrectly(): void
    {
        $erpRows = [
            ['MATERIAL' => 'SKU-FAIL', 'VLRVENDA' => '150.00', 'VLRVDMIN' => '130', 'VLRVDMAX' => '170', 'VLRCUSTO' => '70'],
        ];

        $this->connection->method('query')->willReturn($erpRows);

        $product = $this->createMock(ProductInterface::class);
        $product->method('getSku')->willReturn('SKU-FAIL');
        $product->method('getPrice')->willReturn(10.00);
        $product->method('getId')->willReturn(99);

        $this->productAction->method('updateAttributes')
            ->willThrowException(new \RuntimeException('DB error'));

        $searchCriteria = $this->createMock(SearchCriteriaInterface::class);
        $this->searchCriteriaBuilder->method('setPageSize')->willReturnSelf();
        $this->searchCriteriaBuilder->method('setCurrentPage')->willReturnSelf();
        $this->searchCriteriaBuilder->method('create')->willReturn($searchCriteria);

        $searchResults = $this->createMock(SearchResultsInterface::class);
        $searchResults->method('getItems')
            ->willReturnOnConsecutiveCalls([$product], []);
        $searchResults->method('getTotalCount')->willReturn(1);

        $this->productRepository->method('getList')->willReturn($searchResults);

        $result = $this->priceSync->syncAll();
        $this->assertEquals(1, $result['errors']);
        $this->assertEquals(0, $result['updated']);
    }

    public function testSyncAllWithCustomPriceList(): void
    {
        $erpRows = [
            ['MATERIAL' => 'SKU-CL', 'VLRVENDA' => '90.00', 'VLRVDMIN' => '80', 'VLRVDMAX' => '100', 'VLRCUSTO' => '45'],
        ];

        $this->connection->expects($this->once())
            ->method('query')
            ->with(
                $this->stringContains('MT_MATERIALLISTA'),
                $this->equalTo([7, 1]) // priceList=7, filial=1
            )
            ->willReturn($erpRows);

        $product = $this->createMock(ProductInterface::class);
        $product->method('getSku')->willReturn('SKU-CL');
        $product->method('getPrice')->willReturn(50.00);
        $product->method('getId')->willReturn(10);

        $searchCriteria = $this->createMock(SearchCriteriaInterface::class);
        $this->searchCriteriaBuilder->method('setPageSize')->willReturnSelf();
        $this->searchCriteriaBuilder->method('setCurrentPage')->willReturnSelf();
        $this->searchCriteriaBuilder->method('create')->willReturn($searchCriteria);

        $searchResults = $this->createMock(SearchResultsInterface::class);
        $searchResults->method('getItems')
            ->willReturnOnConsecutiveCalls([$product], []);
        $searchResults->method('getTotalCount')->willReturn(1);

        $this->productRepository->method('getList')->willReturn($searchResults);

        $result = $this->priceSync->syncAll(7);
        $this->assertEquals(1, $result['updated']);
    }

    public function testSyncAllLogsFatalError(): void
    {
        $this->connection->method('query')
            ->willThrowException(new \RuntimeException('Connection lost'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Price sync failed'));

        $this->syncLogResource->expects($this->once())
            ->method('addLog')
            ->with('price', 'import', 'error', $this->anything());

        $result = $this->priceSync->syncAll();
        $this->assertEquals(0, $result['updated']);
    }

    // ─── getCustomerPriceList ─────────────────────────────────────────

    public function testGetCustomerPriceListReturnsCode(): void
    {
        $this->connection->method('fetchOne')
            ->with(
                $this->stringContains('FN_FORNECEDORES'),
                $this->equalTo([42])
            )
            ->willReturn(['FATORPRECO' => '5']);

        $result = $this->priceSync->getCustomerPriceList(42);
        $this->assertEquals(5, $result);
    }

    public function testGetCustomerPriceListReturnsNullWhenNotFound(): void
    {
        $this->connection->method('fetchOne')->willReturn(null);

        $result = $this->priceSync->getCustomerPriceList(999);
        $this->assertNull($result);
    }

    public function testGetCustomerPriceListReturnsNullOnError(): void
    {
        $this->connection->method('fetchOne')
            ->willThrowException(new \RuntimeException('DB error'));

        $result = $this->priceSync->getCustomerPriceList(1);
        $this->assertNull($result);
    }

    // ─── getPricesForSkus ─────────────────────────────────────────────

    public function testGetPricesForSkusReturnsMappedPrices(): void
    {
        $rows = [
            ['CODIGO' => 'S1', 'VLRVENDA' => '10.00'],
            ['CODIGO' => 'S2', 'VLRVENDA' => '20.00'],
        ];

        $this->connection->method('query')
            ->with(
                $this->stringContains('MT_MATERIALLISTA'),
                $this->callback(function (array $params): bool {
                    // params: [fatorPreco=24, filial=1, 'S1', 'S2']
                    return $params[0] === 24 && $params[1] === 1
                        && in_array('S1', $params) && in_array('S2', $params);
                })
            )
            ->willReturn($rows);

        $result = $this->priceSync->getPricesForSkus(['S1', 'S2']);
        $this->assertCount(2, $result);
        $this->assertEquals(10.00, $result['S1']);
        $this->assertEquals(20.00, $result['S2']);
    }

    public function testGetPricesForSkusReturnsEmptyArrayForEmptyInput(): void
    {
        $this->connection->expects($this->never())->method('query');

        $result = $this->priceSync->getPricesForSkus([]);
        $this->assertEmpty($result);
    }

    public function testGetPricesForSkusReturnsEmptyOnError(): void
    {
        $this->connection->method('query')
            ->willThrowException(new \RuntimeException('timeout'));

        $result = $this->priceSync->getPricesForSkus(['X']);
        $this->assertEmpty($result);
    }

    // ─── Base SKU extraction (tested indirectly via getErpPrice) ──────

    public function testBaseSkuExtractionSpaceSeparated(): void
    {
        // "1119 RS" → first try fails, base SKU "1119" succeeds
        $erpRow = ['CODIGO' => '1119', 'VLRVENDA' => '50', 'VLRVDMIN' => '40', 'VLRVDMAX' => '60', 'VLRCUSTO' => '25', 'VLRTABELA' => '55', 'FATORPRECO' => '24'];
        $this->connection->method('fetchOne')
            ->willReturnOnConsecutiveCalls(null, $erpRow);

        $result = $this->priceSync->getErpPrice('1119 RS');
        $this->assertNotNull($result);
    }

    public function testBaseSkuExtractionDotSeparated(): void
    {
        // "0045.01" → base "0045"
        $erpRow = ['CODIGO' => '0045', 'VLRVENDA' => '30', 'VLRVDMIN' => '20', 'VLRVDMAX' => '40', 'VLRCUSTO' => '15', 'VLRTABELA' => '35', 'FATORPRECO' => '24'];
        $this->connection->method('fetchOne')
            ->willReturnOnConsecutiveCalls(null, $erpRow);

        $result = $this->priceSync->getErpPrice('0045.01');
        $this->assertNotNull($result);
    }

    public function testBaseSkuExtractionAlphaSuffix(): void
    {
        // "1125NAO" → base "1125"
        $erpRow = ['CODIGO' => '1125', 'VLRVENDA' => '70', 'VLRVDMIN' => '60', 'VLRVDMAX' => '80', 'VLRCUSTO' => '35', 'VLRTABELA' => '75', 'FATORPRECO' => '24'];
        $this->connection->method('fetchOne')
            ->willReturnOnConsecutiveCalls(null, $erpRow);

        $result = $this->priceSync->getErpPrice('1125NAO');
        $this->assertNotNull($result);
    }
}
