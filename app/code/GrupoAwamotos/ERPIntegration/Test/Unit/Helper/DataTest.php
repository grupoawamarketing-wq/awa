<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Test\Unit\Helper;

use GrupoAwamotos\ERPIntegration\Helper\Data;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\App\DeploymentConfig;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class DataTest extends TestCase
{
    private Data $helper;
    private ScopeConfigInterface|MockObject $scopeConfig;
    private EncryptorInterface|MockObject $encryptor;
    private DeploymentConfig|MockObject $deploymentConfig;

    protected function setUp(): void
    {
        $context = $this->createMock(Context::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->encryptor = $this->createMock(EncryptorInterface::class);
        $this->deploymentConfig = $this->createMock(DeploymentConfig::class);

        $context->method('getScopeConfig')->willReturn($this->scopeConfig);

        $this->helper = new Data($context, $this->encryptor, $this->deploymentConfig);
    }

    protected function tearDown(): void
    {
        // Clean up any environment variables set during tests
        putenv('ERP_SQL_HOST');
        putenv('ERP_SQL_PORT');
        putenv('ERP_SQL_DATABASE');
        putenv('ERP_SQL_USERNAME');
        putenv('ERP_SQL_PASSWORD');
    }

    // ========== Basic Enabled Tests ==========

    public function testIsEnabledReturnsTrue(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->with('grupoawamotos_erp/connection/enabled', ScopeInterface::SCOPE_STORE, null)
            ->willReturn(true);

        $this->assertTrue($this->helper->isEnabled());
    }

    public function testIsEnabledReturnsFalse(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->with('grupoawamotos_erp/connection/enabled', ScopeInterface::SCOPE_STORE, null)
            ->willReturn(false);

        $this->assertFalse($this->helper->isEnabled());
    }

    // ========== Environment Variables Tests ==========

    public function testUseEnvCredentialsReturnsTrueWhenEnabled(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->with('grupoawamotos_erp/connection/use_env', ScopeInterface::SCOPE_STORE, null)
            ->willReturn(true);

        $this->assertTrue($this->helper->useEnvCredentials());
    }

    public function testGetHostFromEnvironmentVariable(): void
    {
        // Enable environment variables
        $this->scopeConfig->method('isSetFlag')
            ->willReturnMap([
                ['grupoawamotos_erp/connection/use_env', ScopeInterface::SCOPE_STORE, null, true],
            ]);

        // Set environment variable
        putenv('ERP_SQL_HOST=env.server.local');

        $this->assertEquals('env.server.local', $this->helper->getHost());
    }

    public function testGetHostFromDeploymentConfig(): void
    {
        // Disable environment variables
        $this->scopeConfig->method('isSetFlag')
            ->willReturnMap([
                ['grupoawamotos_erp/connection/use_env', ScopeInterface::SCOPE_STORE, null, false],
            ]);

        // Set deployment config
        $this->deploymentConfig->method('get')
            ->with('erp/host')
            ->willReturn('deploy.server.local');

        $this->assertEquals('deploy.server.local', $this->helper->getHost());
    }

    public function testGetHostFallbackToAdminConfig(): void
    {
        // Disable environment variables
        $this->scopeConfig->method('isSetFlag')
            ->willReturnMap([
                ['grupoawamotos_erp/connection/use_env', ScopeInterface::SCOPE_STORE, null, false],
            ]);

        // No deployment config
        $this->deploymentConfig->method('get')->willReturn(null);

        // Admin config value
        $this->scopeConfig->method('getValue')
            ->with('grupoawamotos_erp/connection/host', ScopeInterface::SCOPE_STORE, null)
            ->willReturn('admin.server.local');

        $this->assertEquals('admin.server.local', $this->helper->getHost());
    }

    public function testGetPortFromEnvironmentVariable(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->willReturnMap([
                ['grupoawamotos_erp/connection/use_env', ScopeInterface::SCOPE_STORE, null, true],
            ]);

        putenv('ERP_SQL_PORT=1444');

        $this->assertEquals(1444, $this->helper->getPort());
    }

    public function testGetPortReturnsDefaultWhenNotConfigured(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(false);
        $this->deploymentConfig->method('get')->willReturn(null);
        $this->scopeConfig->method('getValue')->willReturn(null);

        $this->assertEquals(1433, $this->helper->getPort());
    }

    public function testGetDatabaseFromEnvironmentVariable(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->willReturnMap([
                ['grupoawamotos_erp/connection/use_env', ScopeInterface::SCOPE_STORE, null, true],
            ]);

        putenv('ERP_SQL_DATABASE=ERP_PROD');

        $this->assertEquals('ERP_PROD', $this->helper->getDatabase());
    }

    public function testGetUsernameFromEnvironmentVariable(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->willReturnMap([
                ['grupoawamotos_erp/connection/use_env', ScopeInterface::SCOPE_STORE, null, true],
            ]);

        putenv('ERP_SQL_USERNAME=sa_user');

        $this->assertEquals('sa_user', $this->helper->getUsername());
    }

    public function testGetPasswordFromEnvironmentVariable(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->willReturnMap([
                ['grupoawamotos_erp/connection/use_env', ScopeInterface::SCOPE_STORE, null, true],
            ]);

        putenv('ERP_SQL_PASSWORD=secure_password_123');

        // Should NOT decrypt when from environment
        $this->encryptor->expects($this->never())->method('decrypt');

        $this->assertEquals('secure_password_123', $this->helper->getPassword());
    }

    public function testGetPasswordDecryptsFromAdminConfig(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(false);
        $this->deploymentConfig->method('get')->willReturn(null);

        $encryptedPassword = 'encrypted_password';
        $decryptedPassword = 'real_password';

        $this->scopeConfig->method('getValue')
            ->with('grupoawamotos_erp/connection/password', ScopeInterface::SCOPE_STORE, null)
            ->willReturn($encryptedPassword);

        $this->encryptor->method('decrypt')
            ->with($encryptedPassword)
            ->willReturn($decryptedPassword);

        $this->assertEquals($decryptedPassword, $this->helper->getPassword());
    }

    /**
     * @dataProvider credentialSourceProvider
     */
    public function testGetCredentialSource(bool $useEnv, bool $hasEnvHost, bool $hasDeployHost, string $expected): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->willReturnMap([
                ['grupoawamotos_erp/connection/use_env', ScopeInterface::SCOPE_STORE, null, $useEnv],
            ]);

        if ($hasEnvHost) {
            putenv('ERP_SQL_HOST=env.server.local');
        }

        $this->deploymentConfig->method('get')
            ->willReturn($hasDeployHost ? 'deploy.server.local' : null);

        $this->assertEquals($expected, $this->helper->getCredentialSource());
    }

    public static function credentialSourceProvider(): array
    {
        return [
            'from environment variables' => [true, true, false, 'environment'],
            'from env.php deployment config' => [false, false, true, 'env.php'],
            'from admin config (fallback)' => [false, false, false, 'admin_config'],
            'env enabled but no env var, uses deploy' => [true, false, true, 'env.php'],
        ];
    }

    // ========== Multi-Branch Stock Tests ==========

    public function testIsMultiBranchEnabledReturnsTrue(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->with('grupoawamotos_erp/sync_stock/multi_branch', ScopeInterface::SCOPE_STORE, null)
            ->willReturn(true);

        $this->assertTrue($this->helper->isMultiBranchEnabled());
    }

    public function testGetStockFiliaisReturnsSingleBranchWhenDisabled(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->willReturnMap([
                ['grupoawamotos_erp/sync_stock/multi_branch', ScopeInterface::SCOPE_STORE, null, false],
            ]);

        $this->scopeConfig->method('getValue')
            ->willReturnMap([
                ['grupoawamotos_erp/sync_stock/filial', ScopeInterface::SCOPE_STORE, null, '5'],
            ]);

        $result = $this->helper->getStockFiliais();

        $this->assertEquals([5], $result);
    }

    public function testGetStockFiliaisReturnsMultipleBranches(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->willReturnMap([
                ['grupoawamotos_erp/sync_stock/multi_branch', ScopeInterface::SCOPE_STORE, null, true],
            ]);

        $this->scopeConfig->method('getValue')
            ->willReturnMap([
                ['grupoawamotos_erp/sync_stock/filiais', ScopeInterface::SCOPE_STORE, null, '1,2,5,10'],
                ['grupoawamotos_erp/sync_stock/filial', ScopeInterface::SCOPE_STORE, null, '1'],
            ]);

        $result = $this->helper->getStockFiliais();

        $this->assertEquals([1, 2, 5, 10], $result);
    }

    public function testGetStockFiliasFallsBackToSingleWhenEmpty(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->willReturnMap([
                ['grupoawamotos_erp/sync_stock/multi_branch', ScopeInterface::SCOPE_STORE, null, true],
            ]);

        $this->scopeConfig->method('getValue')
            ->willReturnMap([
                ['grupoawamotos_erp/sync_stock/filiais', ScopeInterface::SCOPE_STORE, null, ''],
                ['grupoawamotos_erp/sync_stock/filial', ScopeInterface::SCOPE_STORE, null, '3'],
            ]);

        $result = $this->helper->getStockFiliais();

        $this->assertEquals([3], $result);
    }

    /**
     * @dataProvider aggregationModeProvider
     */
    public function testGetStockAggregationMode(?string $configured, string $expected): void
    {
        $this->scopeConfig->method('getValue')
            ->with('grupoawamotos_erp/sync_stock/aggregation_mode', ScopeInterface::SCOPE_STORE, null)
            ->willReturn($configured);

        $this->assertEquals($expected, $this->helper->getStockAggregationMode());
    }

    public static function aggregationModeProvider(): array
    {
        return [
            'default when null' => [null, 'sum'],
            'default when empty' => ['', 'sum'],
            'sum mode' => ['sum', 'sum'],
            'min mode' => ['min', 'min'],
            'max mode' => ['max', 'max'],
            'avg mode' => ['avg', 'avg'],
        ];
    }

    // ========== Image Sync Tests ==========

    public function testIsImageSyncEnabledWhenBothEnabled(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->willReturnMap([
                ['grupoawamotos_erp/connection/enabled', ScopeInterface::SCOPE_STORE, null, true],
                ['grupoawamotos_erp/sync_images/enabled', ScopeInterface::SCOPE_STORE, null, true],
            ]);

        $this->assertTrue($this->helper->isImageSyncEnabled());
    }

    public function testIsImageSyncEnabledReturnsFalseWhenConnectionDisabled(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->willReturnMap([
                ['grupoawamotos_erp/connection/enabled', ScopeInterface::SCOPE_STORE, null, false],
                ['grupoawamotos_erp/sync_images/enabled', ScopeInterface::SCOPE_STORE, null, true],
            ]);

        $this->assertFalse($this->helper->isImageSyncEnabled());
    }

    /**
     * @dataProvider imageSourceProvider
     */
    public function testGetImageSource(?string $configured, string $expected): void
    {
        $this->scopeConfig->method('getValue')
            ->with('grupoawamotos_erp/sync_images/source', ScopeInterface::SCOPE_STORE, null)
            ->willReturn($configured);

        $this->assertEquals($expected, $this->helper->getImageSource());
    }

    public static function imageSourceProvider(): array
    {
        return [
            'default when null' => [null, 'auto'],
            'auto source' => ['auto', 'auto'],
            'table source' => ['table', 'table'],
            'folder source' => ['folder', 'folder'],
            'url source' => ['url', 'url'],
        ];
    }

    public function testGetImageBasePath(): void
    {
        $expectedPath = '/mnt/erp/images';

        $this->scopeConfig->method('getValue')
            ->with('grupoawamotos_erp/sync_images/base_path', ScopeInterface::SCOPE_STORE, null)
            ->willReturn($expectedPath);

        $this->assertEquals($expectedPath, $this->helper->getImageBasePath());
    }

    public function testGetImageBaseUrl(): void
    {
        $expectedUrl = 'https://erp.example.com/images/{sku}.jpg';

        $this->scopeConfig->method('getValue')
            ->with('grupoawamotos_erp/sync_images/base_url', ScopeInterface::SCOPE_STORE, null)
            ->willReturn($expectedUrl);

        $this->assertEquals($expectedUrl, $this->helper->getImageBaseUrl());
    }

    public function testShouldReplaceImages(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->with('grupoawamotos_erp/sync_images/replace_existing', ScopeInterface::SCOPE_STORE, null)
            ->willReturn(true);

        $this->assertTrue($this->helper->shouldReplaceImages());
    }

    public function testGetImageSyncFrequencyReturnsDefault(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('grupoawamotos_erp/sync_images/frequency', ScopeInterface::SCOPE_STORE, null)
            ->willReturn(null);

        $this->assertEquals(720, $this->helper->getImageSyncFrequency());
    }

    // ========== Connection Config Tests ==========

    public function testGetDriverReturnsAuto(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('grupoawamotos_erp/connection/driver', ScopeInterface::SCOPE_STORE, null)
            ->willReturn('auto');

        $this->assertEquals('auto', $this->helper->getDriver());
    }

    public function testGetConnectionTimeoutReturnsDefault(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('grupoawamotos_erp/connection/timeout', ScopeInterface::SCOPE_STORE, null)
            ->willReturn(null);

        $this->assertEquals(30, $this->helper->getConnectionTimeout());
    }

    public function testGetStockFilialReturnsDefaultWhenNotConfigured(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('grupoawamotos_erp/sync_stock/filial', ScopeInterface::SCOPE_STORE, null)
            ->willReturn(null);

        $this->assertEquals(1, $this->helper->getStockFilial());
    }

    public function testGetStockCacheTtlReturnsDefaultWhenNotConfigured(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('grupoawamotos_erp/sync_stock/cache_ttl', ScopeInterface::SCOPE_STORE, null)
            ->willReturn(null);

        $this->assertEquals(300, $this->helper->getStockCacheTtl());
    }

    public function testIsStockRealtimeReturnsFalseWhenDisabled(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->with('grupoawamotos_erp/sync_stock/realtime', ScopeInterface::SCOPE_STORE, null)
            ->willReturn(false);

        $this->assertFalse($this->helper->isStockRealtime());
    }

    // ========== RFM Configuration Tests ==========

    /**
     * @dataProvider rfmPeriodProvider
     */
    public function testGetRfmAnalysisPeriod(?string $configured, int $expected): void
    {
        $this->scopeConfig->method('getValue')
            ->with('grupoawamotos_erp/rfm/analysis_period', ScopeInterface::SCOPE_STORE, null)
            ->willReturn($configured);

        $this->assertEquals($expected, $this->helper->getRfmAnalysisPeriod());
    }

    public static function rfmPeriodProvider(): array
    {
        return [
            'default when null' => [null, 24],
            'default when empty' => ['', 24],
            'custom value' => ['12', 12],
            'another custom value' => ['36', 36],
        ];
    }

    // ========== Coupon Configuration Tests ==========

    /**
     * @dataProvider couponValidDaysProvider
     */
    public function testGetCouponValidDays(?string $configured, int $expected): void
    {
        $this->scopeConfig->method('getValue')
            ->with('grupoawamotos_erp/coupon/valid_days', ScopeInterface::SCOPE_STORE, null)
            ->willReturn($configured);

        $this->assertEquals($expected, $this->helper->getCouponValidDays());
    }

    public static function couponValidDaysProvider(): array
    {
        return [
            'default when null' => [null, 30],
            'custom value' => ['15', 15],
            'longer validity' => ['60', 60],
        ];
    }

    public function testGetMaxSuggestionsReturnsDefault(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('grupoawamotos_erp/suggestions/max_suggestions', ScopeInterface::SCOPE_STORE, null)
            ->willReturn(null);

        $this->assertEquals(10, $this->helper->getMaxSuggestions());
    }

    // ========== Sync Enabled Tests ==========

    public function testIsProductSyncEnabledReturnsBoolean(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->willReturnMap([
                ['grupoawamotos_erp/connection/enabled', ScopeInterface::SCOPE_STORE, null, true],
                ['grupoawamotos_erp/sync_products/enabled', ScopeInterface::SCOPE_STORE, null, true],
            ]);

        $this->assertTrue($this->helper->isProductSyncEnabled());
    }

    public function testIsCustomerSyncEnabledRequiresMainEnabled(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->willReturnMap([
                ['grupoawamotos_erp/connection/enabled', ScopeInterface::SCOPE_STORE, null, false],
                ['grupoawamotos_erp/sync_customers/enabled', ScopeInterface::SCOPE_STORE, null, true],
            ]);

        $this->assertFalse($this->helper->isCustomerSyncEnabled());
    }

    public function testIsOrderSyncEnabledRequiresMainEnabled(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->willReturnMap([
                ['grupoawamotos_erp/connection/enabled', ScopeInterface::SCOPE_STORE, null, true],
                ['grupoawamotos_erp/sync_orders/enabled', ScopeInterface::SCOPE_STORE, null, true],
            ]);

        $this->assertTrue($this->helper->isOrderSyncEnabled());
    }
}
