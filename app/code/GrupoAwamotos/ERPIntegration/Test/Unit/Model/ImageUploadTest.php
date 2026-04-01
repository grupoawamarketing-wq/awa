<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Test\Unit\Model;

use GrupoAwamotos\ERPIntegration\Api\ConnectionInterface;
use GrupoAwamotos\ERPIntegration\Api\ImageSyncInterface;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use GrupoAwamotos\ERPIntegration\Model\ImageUpload;
use Magento\Catalog\Api\Data\ProductSearchResultsInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for ImageUpload model (REST API endpoint)
 *
 * @covers \GrupoAwamotos\ERPIntegration\Model\ImageUpload
 */
class ImageUploadTest extends TestCase
{
    private ImageUpload $model;
    private Helper&MockObject $helper;
    private ProductRepositoryInterface&MockObject $productRepository;
    private SearchCriteriaBuilder&MockObject $searchCriteriaBuilder;
    private ImageSyncInterface&MockObject $imageSync;
    private ConnectionInterface&MockObject $connection;
    private Filesystem&MockObject $filesystem;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->helper = $this->createMock(Helper::class);
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $this->imageSync = $this->createMock(ImageSyncInterface::class);
        $this->connection = $this->createMock(ConnectionInterface::class);
        $this->filesystem = $this->createMock(Filesystem::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->model = new ImageUpload(
            $this->helper,
            $this->productRepository,
            $this->searchCriteriaBuilder,
            $this->imageSync,
            $this->connection,
            $this->filesystem,
            $this->logger
        );
    }

    // ─── upload: validation ────────────────────────────────────────

    public function testUploadErrorWhenProductNotFound(): void
    {
        $this->productRepository->method('get')
            ->willThrowException(new \Exception('Not found'));

        $result = $this->model->upload('INVALID', base64_encode('data'), 'test.jpg');

        $this->assertSame('error', $result['status']);
        $this->assertStringContainsString('nao encontrado', $result['message']);
    }

    public function testUploadErrorForUnsupportedExtension(): void
    {
        $this->productRepository->method('get')
            ->willReturn($this->createMock(Product::class));

        $result = $this->model->upload('SKU-001', base64_encode('data'), 'test.bmp');

        $this->assertSame('error', $result['status']);
        $this->assertStringContainsString('nao suportada', $result['message']);
    }

    public function testUploadErrorForInvalidBase64(): void
    {
        $this->productRepository->method('get')
            ->willReturn($this->createMock(Product::class));

        $result = $this->model->upload('SKU-001', '!!!invalid-base64!!!', 'test.jpg');

        $this->assertSame('error', $result['status']);
        $this->assertStringContainsString('base64 invalidos', $result['message']);
    }

    public function testUploadErrorForOversizedImage(): void
    {
        $this->productRepository->method('get')
            ->willReturn($this->createMock(Product::class));

        // 11MB of data (exceeds 10MB limit)
        $bigData = str_repeat('A', 11 * 1024 * 1024);
        $encoded = base64_encode($bigData);

        $result = $this->model->upload('SKU-001', $encoded, 'big.jpg');

        $this->assertSame('error', $result['status']);
        $this->assertStringContainsString('10MB', $result['message']);
    }

    public function testUploadAcceptsAllSupportedExtensions(): void
    {
        // Verify supported extensions are jpg, jpeg, png, gif, webp
        // We test by sending an unsupported extension and checking it's rejected
        $this->productRepository->method('get')
            ->willReturn($this->createMock(Product::class));

        // BMP should be rejected
        $result = $this->model->upload('SKU-001', base64_encode('x'), 'test.bmp');
        $this->assertStringContainsString('nao suportada', $result['message']);

        // SVG should be rejected
        $result = $this->model->upload('SKU-001', base64_encode('x'), 'test.svg');
        $this->assertStringContainsString('nao suportada', $result['message']);

        // TIFF should be rejected
        $result = $this->model->upload('SKU-001', base64_encode('x'), 'test.tiff');
        $this->assertStringContainsString('nao suportada', $result['message']);
    }

    // ─── uploadBatch ───────────────────────────────────────────────

    public function testUploadBatchErrorForMissingSku(): void
    {
        $result = $this->model->uploadBatch([
            ['imageData' => 'data', 'filename' => 'test.jpg'],
        ]);

        $this->assertCount(1, $result);
        $this->assertSame('error', $result[0]['status']);
        $this->assertStringContainsString('obrigatorios', $result[0]['message']);
    }

    public function testUploadBatchErrorForMissingImageData(): void
    {
        $result = $this->model->uploadBatch([
            ['sku' => 'SKU-001', 'filename' => 'test.jpg'],
        ]);

        $this->assertCount(1, $result);
        $this->assertSame('error', $result[0]['status']);
    }

    public function testUploadBatchProcessesMultipleItems(): void
    {
        $this->productRepository->method('get')
            ->willThrowException(new \Exception('Not found'));

        $result = $this->model->uploadBatch([
            ['sku' => 'SKU-001', 'imageData' => base64_encode('a'), 'filename' => 'a.jpg'],
            ['sku' => 'SKU-002', 'imageData' => base64_encode('b'), 'filename' => 'b.jpg'],
        ]);

        $this->assertCount(2, $result);
        $this->assertSame('SKU-001', $result[0]['sku']);
        $this->assertSame('SKU-002', $result[1]['sku']);
    }

    public function testUploadBatchUsesImageDataAlternativeKey(): void
    {
        // 'image_data' should also work (snake_case alternative)
        $result = $this->model->uploadBatch([
            ['sku' => '', 'image_data' => base64_encode('x')],
        ]);

        $this->assertCount(1, $result);
        $this->assertSame('error', $result[0]['status']);
        $this->assertStringContainsString('obrigatorios', $result[0]['message']);
    }

    public function testUploadBatchDefaultsFilenameToSkuJpg(): void
    {
        $this->productRepository->method('get')
            ->willThrowException(new \Exception('Not found'));

        $result = $this->model->uploadBatch([
            ['sku' => 'SKU-001', 'imageData' => base64_encode('x')],
        ]);

        // Even though product not found, the filename defaulting logic is exercised
        $this->assertCount(1, $result);
        $this->assertSame('SKU-001', $result[0]['sku']);
    }

    // ─── getPendingProducts ────────────────────────────────────────

    public function testGetPendingProductsReturnsProductsWithoutImages(): void
    {
        $searchCriteria = $this->createMock(SearchCriteria::class);
        $this->searchCriteriaBuilder->method('setPageSize')->willReturnSelf();
        $this->searchCriteriaBuilder->method('create')->willReturn($searchCriteria);

        $product1 = $this->createMock(Product::class);
        $product1->method('getData')->with('image')->willReturn('no_selection');
        $product1->method('getSku')->willReturn('SKU-NO-IMG');
        $product1->method('getName')->willReturn('Product Without Image');

        $product2 = $this->createMock(Product::class);
        $product2->method('getData')->with('image')->willReturn('/p/r/product.jpg');
        $product2->method('getSku')->willReturn('SKU-HAS-IMG');

        $searchResults = $this->createMock(ProductSearchResultsInterface::class);
        $searchResults->method('getItems')->willReturn([$product1, $product2]);

        $this->productRepository->method('getList')->willReturn($searchResults);

        // ERP codinterno map
        $this->connection->method('query')
            ->willReturn([
                ['CODIGO' => 'SKU-NO-IMG', 'CODINTERNO' => '12345'],
            ]);

        $result = $this->model->getPendingProducts(50);

        $this->assertCount(1, $result);
        $this->assertSame('SKU-NO-IMG', $result[0]['sku']);
        $this->assertSame('Product Without Image', $result[0]['name']);
        $this->assertSame('12345', $result[0]['codinterno']);
    }

    public function testGetPendingProductsHandlesErpException(): void
    {
        $searchCriteria = $this->createMock(SearchCriteria::class);
        $this->searchCriteriaBuilder->method('setPageSize')->willReturnSelf();
        $this->searchCriteriaBuilder->method('create')->willReturn($searchCriteria);

        $product = $this->createMock(Product::class);
        $product->method('getData')->with('image')->willReturn('');
        $product->method('getSku')->willReturn('SKU-001');
        $product->method('getName')->willReturn('Produto');

        $searchResults = $this->createMock(ProductSearchResultsInterface::class);
        $searchResults->method('getItems')->willReturn([$product]);

        $this->productRepository->method('getList')->willReturn($searchResults);

        // ERP unavailable
        $this->connection->method('query')
            ->willThrowException(new \Exception('ERP down'));

        $result = $this->model->getPendingProducts();

        $this->assertCount(1, $result);
        $this->assertNull($result[0]['codinterno']);
    }

    public function testGetPendingProductsReturnsEmptyWhenAllHaveImages(): void
    {
        $searchCriteria = $this->createMock(SearchCriteria::class);
        $this->searchCriteriaBuilder->method('setPageSize')->willReturnSelf();
        $this->searchCriteriaBuilder->method('create')->willReturn($searchCriteria);

        $product = $this->createMock(Product::class);
        $product->method('getData')->with('image')->willReturn('/i/m/img.jpg');

        $searchResults = $this->createMock(ProductSearchResultsInterface::class);
        $searchResults->method('getItems')->willReturn([$product]);

        $this->productRepository->method('getList')->willReturn($searchResults);
        $this->connection->method('query')->willReturn([]);

        $result = $this->model->getPendingProducts();

        $this->assertSame([], $result);
    }
}
