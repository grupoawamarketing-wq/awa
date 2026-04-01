<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Test\Unit\Model;

use GrupoAwamotos\ERPIntegration\Api\ConnectionInterface;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use GrupoAwamotos\ERPIntegration\Model\CustomerPriceProvider;
use Magento\Framework\App\CacheInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for CustomerPriceProvider model
 *
 * @covers \GrupoAwamotos\ERPIntegration\Model\CustomerPriceProvider
 */
class CustomerPriceProviderTest extends TestCase
{
    private CustomerPriceProvider $model;
    private ConnectionInterface&MockObject $connection;
    private Helper&MockObject $helper;
    private CacheInterface&MockObject $cache;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(ConnectionInterface::class);
        $this->helper = $this->createMock(Helper::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        // Default: cache miss
        $this->cache->method('load')->willReturn(false);

        // Default filial
        $this->helper->method('getStockFilial')->willReturn(1);

        // Default price list = 24
        $this->helper->method('getDefaultPriceList')->willReturn(24);

        $this->model = new CustomerPriceProvider(
            $this->connection,
            $this->helper,
            $this->cache,
            $this->logger
        );
    }

    // ─── getCustomerPriceListCode ──────────────────────────────────

    public function testGetCustomerPriceListCodeFromErp(): void
    {
        $this->connection->expects($this->once())
            ->method('fetchOne')
            ->with(
                $this->stringContains('FATORPRECO'),
                $this->equalTo([100])
            )
            ->willReturn(['FATORPRECO' => 5]);

        $result = $this->model->getCustomerPriceListCode(100);
        $this->assertSame(5, $result);
    }

    public function testGetCustomerPriceListCodeNullWhenEmpty(): void
    {
        $this->connection->method('fetchOne')
            ->willReturn(['FATORPRECO' => '']);

        $result = $this->model->getCustomerPriceListCode(200);
        $this->assertNull($result);
    }

    public function testGetCustomerPriceListCodeNullWhenNoRow(): void
    {
        $this->connection->method('fetchOne')
            ->willReturn(null);

        $result = $this->model->getCustomerPriceListCode(300);
        $this->assertNull($result);
    }

    public function testGetCustomerPriceListCodeUsesInMemoryCache(): void
    {
        $this->connection->expects($this->once())
            ->method('fetchOne')
            ->willReturn(['FATORPRECO' => 7]);

        $this->model->getCustomerPriceListCode(100);
        // Second call should NOT hit connection again
        $result = $this->model->getCustomerPriceListCode(100);
        $this->assertSame(7, $result);
    }

    public function testGetCustomerPriceListCodeUsesPersistentCache(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('load')
            ->willReturnCallback(function (string $key): string|false {
                if (str_contains($key, 'list_')) {
                    return '12';
                }
                return false;
            });

        $model = new CustomerPriceProvider(
            $this->connection,
            $this->helper,
            $cache,
            $this->logger
        );

        // Should NOT hit connection at all — persistent cache hit
        $this->connection->expects($this->never())->method('fetchOne');

        $result = $model->getCustomerPriceListCode(400);
        $this->assertSame(12, $result);
    }

    public function testGetCustomerPriceListCodeSavesToPersistentCache(): void
    {
        $this->connection->method('fetchOne')
            ->willReturn(['FATORPRECO' => 9]);

        $this->cache->expects($this->atLeastOnce())
            ->method('save')
            ->with('9', $this->stringContains('erp_customer_price_list_'), [], 900);

        $this->model->getCustomerPriceListCode(500);
    }

    public function testGetCustomerPriceListCodeHandlesException(): void
    {
        $this->connection->method('fetchOne')
            ->willThrowException(new \Exception('DB fail'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('customer price list'));

        $result = $this->model->getCustomerPriceListCode(600);
        $this->assertNull($result);
    }

    // ─── getCustomerPrice ──────────────────────────────────────────

    public function testGetCustomerPriceReturnsNullForDefaultList(): void
    {
        // Customer has list 24 (= default)
        $this->connection->method('fetchOne')
            ->willReturn(['FATORPRECO' => 24]);

        $result = $this->model->getCustomerPrice(100, 'SKU-001');
        $this->assertNull($result);
    }

    public function testGetCustomerPriceReturnsNullWhenNoList(): void
    {
        $this->connection->method('fetchOne')
            ->willReturn(null);

        $result = $this->model->getCustomerPrice(100, 'SKU-001');
        $this->assertNull($result);
    }

    public function testGetCustomerPriceReturnsPrice(): void
    {
        $callCount = 0;
        $this->connection->method('fetchOne')
            ->willReturnCallback(function (string $sql) use (&$callCount) {
                $callCount++;
                if ($callCount === 1) {
                    // getCustomerPriceListCode
                    return ['FATORPRECO' => 5];
                }
                // getPriceFromList
                return ['VLRVDSUG' => 99.90];
            });

        $result = $this->model->getCustomerPrice(100, 'SKU-001');
        $this->assertSame(99.90, $result);
    }

    public function testGetCustomerPriceFallsBackToBaseSku(): void
    {
        $callCount = 0;
        $this->connection->method('fetchOne')
            ->willReturnCallback(function (string $sql, array $params = []) use (&$callCount) {
                $callCount++;
                if ($callCount === 1) {
                    return ['FATORPRECO' => 5];
                }
                if ($callCount === 2) {
                    // First try with "1119 RS" — not found
                    return null;
                }
                // Fallback with base SKU "1119"
                return ['VLRVDSUG' => 55.00];
            });

        $result = $this->model->getCustomerPrice(100, '1119 RS');
        $this->assertSame(55.00, $result);
    }

    public function testGetCustomerPriceReturnsNullWhenNotInList(): void
    {
        $callCount = 0;
        $this->connection->method('fetchOne')
            ->willReturnCallback(function () use (&$callCount) {
                $callCount++;
                if ($callCount === 1) {
                    return ['FATORPRECO' => 5];
                }
                return null;
            });

        $result = $this->model->getCustomerPrice(100, 'SIMPLE');
        $this->assertNull($result);
    }

    // ─── getCustomerPrices (batch) ─────────────────────────────────

    public function testGetCustomerPricesEmptyArrayForEmptySkus(): void
    {
        $result = $this->model->getCustomerPrices(100, []);
        $this->assertSame([], $result);
    }

    public function testGetCustomerPricesEmptyArrayForDefaultList(): void
    {
        $this->connection->method('fetchOne')
            ->willReturn(['FATORPRECO' => 24]);

        $result = $this->model->getCustomerPrices(100, ['SKU-001', 'SKU-002']);
        $this->assertSame([], $result);
    }

    public function testGetCustomerPricesBatchQuery(): void
    {
        $callCount = 0;
        $this->connection->method('fetchOne')
            ->willReturnCallback(function () use (&$callCount) {
                $callCount++;
                return ['FATORPRECO' => 5];
            });

        $this->connection->method('query')
            ->willReturn([
                ['MATERIAL' => 'SKU-001', 'VLRVDSUG' => 10.0],
                ['MATERIAL' => 'SKU-003', 'VLRVDSUG' => 30.0],
            ]);

        $result = $this->model->getCustomerPrices(100, ['SKU-001', 'SKU-002', 'SKU-003']);
        $this->assertSame(10.0, $result['SKU-001']);
        $this->assertArrayNotHasKey('SKU-002', $result);
        $this->assertSame(30.0, $result['SKU-003']);
    }

    public function testGetCustomerPricesBatchUsesBaseSkuFallback(): void
    {
        $this->connection->method('fetchOne')
            ->willReturn(['FATORPRECO' => 5]);

        // ERP returns price for base SKU "1119" but not for "1119 RS"
        $this->connection->method('query')
            ->willReturn([
                ['MATERIAL' => '1119', 'VLRVDSUG' => 42.0],
            ]);

        $result = $this->model->getCustomerPrices(100, ['1119 RS']);
        $this->assertSame(42.0, $result['1119 RS']);
    }

    public function testGetCustomerPricesBatchHandlesException(): void
    {
        $this->connection->method('fetchOne')
            ->willReturn(['FATORPRECO' => 5]);

        $this->connection->method('query')
            ->willThrowException(new \Exception('DB error'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('batch-fetching prices'));

        $result = $this->model->getCustomerPrices(100, ['SKU-001']);
        $this->assertSame([], $result);
    }

    // ─── getCustomerPriceListName ──────────────────────────────────

    public function testGetCustomerPriceListNameReturnsName(): void
    {
        $callCount = 0;
        $this->connection->method('fetchOne')
            ->willReturnCallback(function (string $sql) use (&$callCount) {
                $callCount++;
                if ($callCount === 1) {
                    return ['FATORPRECO' => 5];
                }
                return ['DESCRICAO' => '  NACIONAL ESPECIAL  '];
            });

        $result = $this->model->getCustomerPriceListName(100);
        $this->assertSame('NACIONAL ESPECIAL', $result);
    }

    public function testGetCustomerPriceListNameNullWhenNoList(): void
    {
        $this->connection->method('fetchOne')
            ->willReturn(null);

        $result = $this->model->getCustomerPriceListName(100);
        $this->assertNull($result);
    }

    public function testGetCustomerPriceListNameHandlesQueryException(): void
    {
        $callCount = 0;
        $this->connection->method('fetchOne')
            ->willReturnCallback(function () use (&$callCount) {
                $callCount++;
                if ($callCount === 1) {
                    return ['FATORPRECO' => 5];
                }
                throw new \Exception('DB error');
            });

        $result = $this->model->getCustomerPriceListName(100);
        $this->assertNull($result);
    }

    // ─── clearCustomerCache ────────────────────────────────────────

    public function testClearCustomerCacheRemovesPersistentCache(): void
    {
        // Populate in-memory cache first
        $this->connection->method('fetchOne')
            ->willReturn(['FATORPRECO' => 5]);
        $this->model->getCustomerPriceListCode(100);

        $this->cache->expects($this->once())
            ->method('remove')
            ->with('erp_customer_price_list_100');

        $this->model->clearCustomerCache(100);
    }

    public function testClearCustomerCacheResetsInMemoryCache(): void
    {
        // First call hits ERP
        $this->connection->expects($this->exactly(2))
            ->method('fetchOne')
            ->willReturn(['FATORPRECO' => 5]);

        $this->model->getCustomerPriceListCode(100);

        $this->model->clearCustomerCache(100);

        // After clear, should hit ERP again
        $this->model->getCustomerPriceListCode(100);
    }

    // ─── getBaseSku (tested indirectly) ────────────────────────────

    public function testBaseSkuExtractionSpaceSeparated(): void
    {
        // "1119 RS" → base "1119", ERP has price for "1119" but not "1119 RS"
        $callCount = 0;
        $this->connection->method('fetchOne')
            ->willReturnCallback(function (string $sql, array $params = []) use (&$callCount) {
                $callCount++;
                if ($callCount === 1) {
                    return ['FATORPRECO' => 5];
                }
                // First fetch: exact SKU — not found
                if ($callCount === 2) {
                    return null;
                }
                // Second fetch: base SKU — found
                return ['VLRVDSUG' => 42.0];
            });

        $result = $this->model->getCustomerPrice(100, '1119 RS');
        $this->assertSame(42.0, $result);
    }

    public function testBaseSkuExtractionDotSeparated(): void
    {
        $callCount = 0;
        $this->connection->method('fetchOne')
            ->willReturnCallback(function (string $sql, array $params = []) use (&$callCount) {
                $callCount++;
                if ($callCount === 1) {
                    return ['FATORPRECO' => 5];
                }
                if ($callCount === 2) {
                    return null;
                }
                // base SKU "0045" from "0045.01"
                return ['VLRVDSUG' => 18.0];
            });

        $result = $this->model->getCustomerPrice(100, '0045.01');
        $this->assertSame(18.0, $result);
    }

    public function testBaseSkuExtractionAlphaSuffix(): void
    {
        $callCount = 0;
        $this->connection->method('fetchOne')
            ->willReturnCallback(function (string $sql, array $params = []) use (&$callCount) {
                $callCount++;
                if ($callCount === 1) {
                    return ['FATORPRECO' => 5];
                }
                if ($callCount === 2) {
                    return null;
                }
                // base SKU "1125" from "1125NAO"
                return ['VLRVDSUG' => 33.0];
            });

        $result = $this->model->getCustomerPrice(100, '1125NAO');
        $this->assertSame(33.0, $result);
    }

    public function testBaseSkuNoFallbackForSimpleSku(): void
    {
        $callCount = 0;
        $this->connection->method('fetchOne')
            ->willReturnCallback(function () use (&$callCount) {
                $callCount++;
                if ($callCount === 1) {
                    return ['FATORPRECO' => 5];
                }
                return null;
            });

        // "41544" has no variant pattern, so no fallback attempt
        $result = $this->model->getCustomerPrice(100, '41544');
        $this->assertNull($result);
        // Only 2 calls: 1 for list code + 1 for price (no fallback since baseSku === sku)
        $this->assertSame(2, $callCount);
    }

    // ─── Price caching (in-memory) ─────────────────────────────────

    public function testPriceIsCachedInMemory(): void
    {
        $fetchCount = 0;
        $this->connection->method('fetchOne')
            ->willReturnCallback(function () use (&$fetchCount) {
                $fetchCount++;
                if ($fetchCount === 1) {
                    return ['FATORPRECO' => 5];
                }
                return ['VLRVDSUG' => 77.0];
            });

        $this->model->getCustomerPrice(100, 'SKU-001');
        // Second call should use in-memory cache
        $result = $this->model->getCustomerPrice(100, 'SKU-001');
        $this->assertSame(77.0, $result);
        // Only 2 fetchOne calls (1 list + 1 price), not 3
        $this->assertSame(2, $fetchCount);
    }

    // ─── Edge cases ────────────────────────────────────────────────

    public function testGetCustomerPricePersistentCacheHitForPrice(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('load')
            ->willReturnCallback(function (string $key): string|false {
                if (str_contains($key, 'list_')) {
                    return '5'; // List code 5
                }
                // Price cache hit
                return '88.50';
            });

        $model = new CustomerPriceProvider(
            $this->connection,
            $this->helper,
            $cache,
            $this->logger
        );

        $result = $model->getCustomerPrice(100, 'SKU-001');
        $this->assertSame(88.50, $result);
    }

    public function testGetCustomerPricePersistentCacheEmptyStringMeansNull(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('load')
            ->willReturnCallback(function (string $key): string|false {
                if (str_contains($key, 'list_')) {
                    return '5';
                }
                // Empty = no price
                return '';
            });

        $model = new CustomerPriceProvider(
            $this->connection,
            $this->helper,
            $cache,
            $this->logger
        );

        $result = $model->getCustomerPrice(100, 'SKU-001');
        $this->assertNull($result);
    }

    public function testGetCustomerPriceListCodeCachesEmptyStringForNull(): void
    {
        $this->connection->method('fetchOne')
            ->willReturn(null);

        $this->cache->expects($this->atLeastOnce())
            ->method('save')
            ->with('', $this->stringContains('erp_customer_price_list_'), [], 900);

        $this->model->getCustomerPriceListCode(700);
    }
}
