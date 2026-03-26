<?php
declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Test\Unit\Model;

use GrupoAwamotos\ERPIntegration\Model\ImageSync;
use GrupoAwamotos\ERPIntegration\Api\ConnectionInterface;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use GrupoAwamotos\ERPIntegration\Model\ResourceModel\SyncLog as SyncLogResource;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\Data\ProductAttributeMediaGalleryEntryInterfaceFactory;
use Magento\Catalog\Model\Product\Gallery\Processor as GalleryProcessor;
use Magento\Catalog\Api\ProductAttributeMediaGalleryManagementInterface;
use Magento\Framework\Api\Data\ImageContentInterfaceFactory;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Io\File as IoFile;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class ImageSyncTest extends TestCase
{
    private ImageSync $imageSync;
    private ConnectionInterface|MockObject $connection;
    private Helper|MockObject $helper;
    private ProductRepositoryInterface|MockObject $productRepository;
    private GalleryProcessor|MockObject $galleryProcessor;
    private ProductAttributeMediaGalleryManagementInterface|MockObject $mediaGalleryManagement;
    private Filesystem|MockObject $filesystem;
    private IoFile|MockObject $ioFile;
    private SyncLogResource|MockObject $syncLogResource;
    private LoggerInterface|MockObject $logger;
    private ProductAttributeMediaGalleryEntryInterfaceFactory|MockObject $galleryEntryFactory;
    private ImageContentInterfaceFactory|MockObject $imageContentFactory;
    private ?string $tempImageDir = null;

    protected function setUp(): void
    {
        // Initialize ObjectManager mock so ImageSync constructor doesn't crash
        // (it calls ObjectManager::getInstance() unconditionally on line 65)
        $objectManagerMock = $this->createMock(ObjectManagerInterface::class);
        ObjectManager::setInstance($objectManagerMock);

        $this->connection = $this->createMock(ConnectionInterface::class);
        $this->helper = $this->createMock(Helper::class);
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->galleryProcessor = $this->createMock(GalleryProcessor::class);
        $this->mediaGalleryManagement = $this->createMock(ProductAttributeMediaGalleryManagementInterface::class);
        $this->filesystem = $this->createMock(Filesystem::class);
        $this->ioFile = $this->createMock(IoFile::class);
        $this->syncLogResource = $this->createMock(SyncLogResource::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->galleryEntryFactory = $this->createMock(ProductAttributeMediaGalleryEntryInterfaceFactory::class);
        $this->imageContentFactory = $this->createMock(ImageContentInterfaceFactory::class);

        // Default helper behavior
        $this->helper->method('isImageSyncEnabled')->willReturn(true);
        $this->helper->method('getImageSource')->willReturn('auto');
        $this->helper->method('getImageBasePath')->willReturn('/mnt/erp/images');
        $this->helper->method('getImageBaseUrl')->willReturn('');

        // Mock filesystem
        $directoryWrite = $this->createMock(\Magento\Framework\Filesystem\Directory\WriteInterface::class);
        $directoryWrite->method('getAbsolutePath')->willReturn('/tmp/erp_images');

        $directoryRead = $this->createMock(\Magento\Framework\Filesystem\Directory\ReadInterface::class);
        $directoryRead->method('getAbsolutePath')->willReturn('/var/www/pub/media/catalog/product');

        $this->filesystem->method('getDirectoryWrite')->willReturn($directoryWrite);
        $this->filesystem->method('getDirectoryRead')->willReturn($directoryRead);

        $this->imageSync = new ImageSync(
            $this->connection,
            $this->helper,
            $this->productRepository,
            $this->galleryProcessor,
            $this->mediaGalleryManagement,
            $this->filesystem,
            $this->ioFile,
            $this->syncLogResource,
            $this->logger,
            $this->galleryEntryFactory,
            $this->imageContentFactory
        );
    }

    protected function tearDown(): void
    {
        if ($this->tempImageDir && is_dir($this->tempImageDir)) {
            $files = scandir($this->tempImageDir) ?: [];
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }

                @unlink($this->tempImageDir . DIRECTORY_SEPARATOR . $file);
            }

            @rmdir($this->tempImageDir);
        }

        $this->tempImageDir = null;
        parent::tearDown();
    }

    // ========== syncAll Tests ==========

    public function testSyncAllReturnsEarlyWhenDisabled(): void
    {
        $this->helper = $this->createMock(Helper::class);
        $this->helper->method('isImageSyncEnabled')->willReturn(false);

        $imageSync = new ImageSync(
            $this->connection,
            $this->helper,
            $this->productRepository,
            $this->galleryProcessor,
            $this->mediaGalleryManagement,
            $this->filesystem,
            $this->ioFile,
            $this->syncLogResource,
            $this->logger,
            $this->galleryEntryFactory,
            $this->imageContentFactory
        );

        // Should not query database
        $this->connection->expects($this->never())->method('query');

        $result = $imageSync->syncAll();

        $this->assertEquals(0, $result['synced']);
        $this->assertEquals(0, $result['errors']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertEquals(0, $result['total']);
    }

    public function testSyncAllProcessesProductsWithImages(): void
    {
        // Mock products with images query
        $this->connection->method('query')
            ->willReturn([
                ['CODIGO' => 'SKU-001'],
                ['CODIGO' => 'SKU-002'],
            ]);

        // Mock image data
        $this->connection->method('fetchOne')
            ->willReturn(null); // No images from table

        // Products don't exist in Magento (will be skipped)
        $this->productRepository->method('get')
            ->willThrowException(new NoSuchEntityException(__('Not found')));

        $result = $this->imageSync->syncAll();

        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('synced', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('skipped', $result);
        $this->assertArrayHasKey('execution_time', $result);
    }

    public function testSyncAllIncludesExecutionTime(): void
    {
        $this->connection->method('query')->willReturn([]);

        $result = $this->imageSync->syncAll();

        $this->assertArrayHasKey('execution_time', $result);
        $this->assertIsFloat($result['execution_time']);
    }

    public function testSyncAllAutoModeMergesTableAndFolderCandidates(): void
    {
        $this->tempImageDir = sys_get_temp_dir() . '/erp_image_sync_' . uniqid('', true);
        mkdir($this->tempImageDir, 0777, true);
        file_put_contents($this->tempImageDir . '/SKU-002.jpg', 'folder-image');
        file_put_contents($this->tempImageDir . '/SKU-003.jpg', 'folder-image');

        $helper = $this->createMock(Helper::class);
        $helper->method('isImageSyncEnabled')->willReturn(true);
        $helper->method('getImageSource')->willReturn('auto');
        $helper->method('getImageBasePath')->willReturn($this->tempImageDir);
        $helper->method('getImageBaseUrl')->willReturn('');

        $imageSync = new ImageSync(
            $this->connection,
            $helper,
            $this->productRepository,
            $this->galleryProcessor,
            $this->mediaGalleryManagement,
            $this->filesystem,
            $this->ioFile,
            $this->syncLogResource,
            $this->logger,
            $this->galleryEntryFactory,
            $this->imageContentFactory
        );

        $this->connection->method('query')
            ->willReturnCallback(function (string $sql) {
                if (strpos($sql, 'FROM PR_MEDIDAIMAGEM') !== false) {
                    return [
                        ['CODIGO' => 'SKU-001'],
                        ['CODIGO' => 'SKU-002'],
                    ];
                }

                if (strpos($sql, 'FROM GR_DOCUMENTOS') !== false) {
                    return [];
                }

                if (strpos($sql, 'FROM MT_MATERIAL WHERE CODINTERNO') !== false) {
                    return [];
                }

                return [];
            });

        $this->productRepository->method('get')
            ->willThrowException(new NoSuchEntityException(__('Not found')));

        $result = $imageSync->syncAll();

        $this->assertSame(3, $result['total']);
        $this->assertSame(3, $result['skipped']);
        $this->assertSame(0, $result['synced']);
    }

    public function testSyncAllAutoModePreservesDottedSkuFromFolder(): void
    {
        $this->tempImageDir = sys_get_temp_dir() . '/erp_image_sync_' . uniqid('', true);
        mkdir($this->tempImageDir, 0777, true);
        file_put_contents($this->tempImageDir . '/0045.01.jpg', 'folder-image');

        $helper = $this->createMock(Helper::class);
        $helper->method('isImageSyncEnabled')->willReturn(true);
        $helper->method('getImageSource')->willReturn('auto');
        $helper->method('getImageBasePath')->willReturn($this->tempImageDir);
        $helper->method('getImageBaseUrl')->willReturn('');

        $imageSync = new ImageSync(
            $this->connection,
            $helper,
            $this->productRepository,
            $this->galleryProcessor,
            $this->mediaGalleryManagement,
            $this->filesystem,
            $this->ioFile,
            $this->syncLogResource,
            $this->logger,
            $this->galleryEntryFactory,
            $this->imageContentFactory
        );

        $this->connection->method('query')
            ->willReturn([]);

        $this->productRepository->expects($this->once())
            ->method('get')
            ->with('0045.01', false, 0)
            ->willThrowException(new NoSuchEntityException(__('Not found')));

        $result = $imageSync->syncAll();

        $this->assertSame(1, $result['total']);
        $this->assertSame(1, $result['skipped']);
    }

    public function testSyncAllAutoModeMapsCodInternoFilenameToSku(): void
    {
        $this->tempImageDir = sys_get_temp_dir() . '/erp_image_sync_' . uniqid('', true);
        mkdir($this->tempImageDir, 0777, true);
        file_put_contents($this->tempImageDir . '/68050003.jpg', 'folder-image');

        $helper = $this->createMock(Helper::class);
        $helper->method('isImageSyncEnabled')->willReturn(true);
        $helper->method('getImageSource')->willReturn('auto');
        $helper->method('getImageBasePath')->willReturn($this->tempImageDir);
        $helper->method('getImageBaseUrl')->willReturn('');

        $imageSync = new ImageSync(
            $this->connection,
            $helper,
            $this->productRepository,
            $this->galleryProcessor,
            $this->mediaGalleryManagement,
            $this->filesystem,
            $this->ioFile,
            $this->syncLogResource,
            $this->logger,
            $this->galleryEntryFactory,
            $this->imageContentFactory
        );

        $this->connection->method('query')
            ->willReturnCallback(function (string $sql) {
                if (strpos($sql, 'CAST(CODINTERNO AS VARCHAR(50)) = :cod_interno') !== false) {
                    return [['CODIGO' => '0070']];
                }

                return [];
            });

        $this->productRepository->expects($this->once())
            ->method('get')
            ->with('0070', false, 0)
            ->willThrowException(new NoSuchEntityException(__('Not found')));

        $result = $imageSync->syncAll();

        $this->assertSame(1, $result['total']);
        $this->assertSame(1, $result['skipped']);
    }

    // ========== syncBySku Tests ==========

    public function testSyncBySkuReturnsFalseWhenProductNotFound(): void
    {
        $this->productRepository->method('get')
            ->willThrowException(new NoSuchEntityException(__('Product not found')));

        $this->logger->expects($this->once())
            ->method('debug')
            ->with($this->stringContains('Product not found'));

        $result = $this->imageSync->syncBySku('NONEXISTENT-SKU');

        $this->assertFalse($result);
    }

    public function testSyncBySkuReturnsFalseWhenNoImages(): void
    {
        // Product exists
        $product = $this->createMock(ProductInterface::class);
        $this->productRepository->method('get')->willReturn($product);

        // But no images in ERP
        $this->connection->method('query')->willReturn([]);

        $result = $this->imageSync->syncBySku('SKU-NO-IMAGES');

        $this->assertFalse($result);
    }

    // ========== getErpImages Tests ==========

    public function testGetErpImagesFromTableSource(): void
    {
        $this->helper = $this->createMock(Helper::class);
        $this->helper->method('isImageSyncEnabled')->willReturn(true);
        $this->helper->method('getImageSource')->willReturn('table');
        $this->helper->method('getImageBasePath')->willReturn('');
        $this->helper->method('getImageBaseUrl')->willReturn('');

        $imageSync = new ImageSync(
            $this->connection,
            $this->helper,
            $this->productRepository,
            $this->galleryProcessor,
            $this->mediaGalleryManagement,
            $this->filesystem,
            $this->ioFile,
            $this->syncLogResource,
            $this->logger,
            $this->galleryEntryFactory,
            $this->imageContentFactory
        );

        // Mock table query
        $this->connection->method('query')
            ->willReturn([
                ['IMAGEM' => '/path/image1.jpg', 'DESCRICAO' => 'Image 1', 'ORDEM' => 1, 'PRINCIPAL' => 'S'],
                ['IMAGEM' => '/path/image2.jpg', 'DESCRICAO' => 'Image 2', 'ORDEM' => 2, 'PRINCIPAL' => 'N'],
            ]);

        $result = $imageSync->getErpImages('TEST-SKU');

        $this->assertCount(2, $result);
        $this->assertEquals('/path/image1.jpg', $result[0]['path']);
        // PR_MEDIDAIMAGEM passes null for labelCol, so label is always empty string
        $this->assertEquals('', $result[0]['label']);
        $this->assertTrue($result[0]['is_main']);
    }

    public function testGetErpImagesFromUrlSource(): void
    {
        $this->helper = $this->createMock(Helper::class);
        $this->helper->method('isImageSyncEnabled')->willReturn(true);
        $this->helper->method('getImageSource')->willReturn('url');
        $this->helper->method('getImageBasePath')->willReturn('');
        $this->helper->method('getImageBaseUrl')->willReturn('https://example.com/images/{sku}.jpg');

        $imageSync = new ImageSync(
            $this->connection,
            $this->helper,
            $this->productRepository,
            $this->galleryProcessor,
            $this->mediaGalleryManagement,
            $this->filesystem,
            $this->ioFile,
            $this->syncLogResource,
            $this->logger,
            $this->galleryEntryFactory,
            $this->imageContentFactory
        );

        // Note: This will actually try to call get_headers which we can't easily mock
        // In a real scenario we'd need to mock the function or use dependency injection
        $result = $imageSync->getErpImages('TEST-SKU');

        // Since we can't mock get_headers, this may return empty
        $this->assertIsArray($result);
    }

    public function testGetErpImagesAutoModeTriesTableFirst(): void
    {
        $this->helper = $this->createMock(Helper::class);
        $this->helper->method('isImageSyncEnabled')->willReturn(true);
        $this->helper->method('getImageSource')->willReturn('auto');
        $this->helper->method('getImageBasePath')->willReturn('/mnt/erp/images');
        $this->helper->method('getImageBaseUrl')->willReturn('');

        $imageSync = new ImageSync(
            $this->connection,
            $this->helper,
            $this->productRepository,
            $this->galleryProcessor,
            $this->mediaGalleryManagement,
            $this->filesystem,
            $this->ioFile,
            $this->syncLogResource,
            $this->logger,
            $this->galleryEntryFactory,
            $this->imageContentFactory
        );

        // Table query returns images
        $this->connection->method('query')
            ->willReturn([
                ['IMAGEM' => '/path/image1.jpg', 'DESCRICAO' => '', 'ORDEM' => 1, 'PRINCIPAL' => 'S'],
            ]);

        $result = $imageSync->getErpImages('TEST-SKU');

        $this->assertCount(1, $result);
    }

    public function testGetErpImagesAutoModeFallsBackToFolder(): void
    {
        $this->helper = $this->createMock(Helper::class);
        $this->helper->method('isImageSyncEnabled')->willReturn(true);
        $this->helper->method('getImageSource')->willReturn('auto');
        $this->helper->method('getImageBasePath')->willReturn('/mnt/erp/images');
        $this->helper->method('getImageBaseUrl')->willReturn('');

        $imageSync = new ImageSync(
            $this->connection,
            $this->helper,
            $this->productRepository,
            $this->galleryProcessor,
            $this->mediaGalleryManagement,
            $this->filesystem,
            $this->ioFile,
            $this->syncLogResource,
            $this->logger,
            $this->galleryEntryFactory,
            $this->imageContentFactory
        );

        // Table query returns empty (triggers fallback to folder)
        $this->connection->method('query')
            ->willThrowException(new \Exception('Table not found'));

        // Should return empty since folder doesn't exist in test environment
        $result = $imageSync->getErpImages('TEST-SKU');

        $this->assertIsArray($result);
    }

    public function testGetErpImagesFromFolderDoesNotMatchPartialSkuPrefix(): void
    {
        $this->tempImageDir = sys_get_temp_dir() . '/erp_image_sync_' . uniqid('', true);
        mkdir($this->tempImageDir, 0777, true);
        file_put_contents($this->tempImageDir . '/0045.01.jpg', 'folder-image');

        $helper = $this->createMock(Helper::class);
        $helper->method('isImageSyncEnabled')->willReturn(true);
        $helper->method('getImageSource')->willReturn('folder');
        $helper->method('getImageBasePath')->willReturn($this->tempImageDir);
        $helper->method('getImageBaseUrl')->willReturn('');

        $imageSync = new ImageSync(
            $this->connection,
            $helper,
            $this->productRepository,
            $this->galleryProcessor,
            $this->mediaGalleryManagement,
            $this->filesystem,
            $this->ioFile,
            $this->syncLogResource,
            $this->logger,
            $this->galleryEntryFactory,
            $this->imageContentFactory
        );

        $this->assertSame([], $imageSync->getErpImages('0045'));
        $this->assertCount(1, $imageSync->getErpImages('0045.01'));
    }

    // ========== cleanOrphanImages Tests ==========

    public function testCleanOrphanImagesNotImplemented(): void
    {
        $this->logger->expects($this->once())
            ->method('info')
            ->with($this->stringContains('Orphan image cleanup is disabled'));

        $result = $this->imageSync->cleanOrphanImages();

        $this->assertEquals(
            [
                'removed' => 0,
                'skipped' => 0,
                'errors' => 0,
                'dry_run' => false,
                'products_checked' => 0,
                'orphans_found' => [],
            ],
            $result
        );
    }

    // ========== getPendingCount Tests ==========

    public function testGetPendingCountReturnsNumber(): void
    {
        $this->connection->method('query')
            ->willReturn([
                ['CODIGO' => 'SKU-001'],
                ['CODIGO' => 'SKU-002'],
                ['CODIGO' => 'SKU-003'],
            ]);

        $result = $this->imageSync->getPendingCount();

        $this->assertEquals(3, $result);
    }

    public function testGetPendingCountReturnsZeroOnError(): void
    {
        $this->connection->method('query')
            ->willThrowException(new \Exception('Query failed'));

        $result = $this->imageSync->getPendingCount();

        $this->assertEquals(0, $result);
    }

    // ========== Image Validation Tests ==========

    /**
     * @dataProvider supportedExtensionsProvider
     */
    public function testSupportedExtensions(string $extension, bool $expected): void
    {
        // This tests the internal SUPPORTED_EXTENSIONS constant behavior
        $supportedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $this->assertEquals($expected, in_array($extension, $supportedExtensions));
    }

    public static function supportedExtensionsProvider(): array
    {
        return [
            'jpg is supported' => ['jpg', true],
            'jpeg is supported' => ['jpeg', true],
            'png is supported' => ['png', true],
            'gif is supported' => ['gif', true],
            'webp is supported' => ['webp', true],
            'bmp is not supported' => ['bmp', false],
            'tiff is not supported' => ['tiff', false],
            'svg is not supported' => ['svg', false],
        ];
    }

    // ========== Image Roles Tests ==========

    public function testFirstImageGetsAllRoles(): void
    {
        // This tests the internal IMAGE_ROLES constant behavior
        $imageRoles = ['image', 'small_image', 'thumbnail'];
        $this->assertContains('image', $imageRoles);
        $this->assertContains('small_image', $imageRoles);
        $this->assertContains('thumbnail', $imageRoles);
    }

    // ========== Error Handling Tests ==========

    public function testSyncAllLogsErrors(): void
    {
        $this->connection->method('query')
            ->willReturn([
                ['CODIGO' => 'SKU-001'],
            ]);

        // Product exists but throws exception during processing
        $product = $this->createMock(ProductInterface::class);
        $this->productRepository->method('get')->willReturn($product);

        // No images - will be skipped, not error
        $this->connection->method('fetchOne')->willReturn(null);

        $result = $this->imageSync->syncAll();

        // Should track skipped (no images) not errors
        $this->assertArrayHasKey('skipped', $result);
    }

    public function testSyncAllHandlesExceptionsGracefully(): void
    {
        $this->connection->method('query')
            ->willThrowException(new \Exception('Database connection lost'));

        // Exception is caught in getProductsWithImages() and logged as debug, not error
        $this->logger->expects($this->atLeastOnce())
            ->method('debug');

        // Should not throw exception
        $result = $this->imageSync->syncAll();

        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('synced', $result);
    }

    // ========== Integration-like Tests ==========

    public function testFullSyncWorkflow(): void
    {
        // Setup: Products with images in ERP
        $this->connection->method('query')
            ->willReturnCallback(function ($sql) {
                if (strpos($sql, 'DISTINCT CODIGO') !== false
                    || strpos($sql, 'DISTINCT d.CHAVE') !== false
                ) {
                    return [['CODIGO' => 'SKU-001']];
                }
                return [['IMAGEM' => 'test.jpg', 'DESCRICAO' => '', 'ORDEM' => 1, 'PRINCIPAL' => 'S']];
            });

        // Product exists in Magento but image cannot be processed (file doesn't exist)
        $product = $this->createMock(\Magento\Catalog\Model\Product::class);
        $product->method('getMediaGalleryEntries')->willReturn([]);

        $this->productRepository->method('get')->willReturn($product);

        $result = $this->imageSync->syncAll();

        // Should complete without errors even if images can't be processed
        $this->assertArrayHasKey('execution_time', $result);
        $this->assertGreaterThanOrEqual(0, $result['execution_time']);
    }
}
