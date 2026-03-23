<?php
declare(strict_types=1);

namespace GrupoAwamotos\Theme\Test\Unit\ViewModel;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use GrupoAwamotos\Theme\ViewModel\HeaderData;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\UrlInterface;

class HeaderDataTest extends TestCase
{
    private HeaderData $viewModel;

    /** @var ScopeConfigInterface|MockObject */
    private $scopeConfigMock;

    /** @var StoreManagerInterface|MockObject */
    private $storeManagerMock;

    protected function setUp(): void
    {
        $this->scopeConfigMock = $this->createMock(ScopeConfigInterface::class);
        $this->storeManagerMock = $this->createMock(StoreManagerInterface::class);

        $this->viewModel = new HeaderData(
            $this->scopeConfigMock,
            $this->storeManagerMock
        );
    }

    public function testIsStickyHeaderEnabledReturnsTrueWhenConfigured(): void
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('isSetFlag')
            ->with('themeoption/header/sticky_enable', ScopeInterface::SCOPE_STORE)
            ->willReturn(true);

        $this->assertTrue($this->viewModel->isStickyHeaderEnabled());
    }

    public function testIsStickyHeaderEnabledReturnsFalseWhenNotConfigured(): void
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('isSetFlag')
            ->with('themeoption/header/sticky_enable', ScopeInterface::SCOPE_STORE)
            ->willReturn(false);

        $this->assertFalse($this->viewModel->isStickyHeaderEnabled());
    }

    public function testGetStickyLogoUrlReturnsEmptyWhenNoLogoConfigured(): void
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with('themeoption/header/sticky_logo', ScopeInterface::SCOPE_STORE)
            ->willReturn(null);

        $this->assertEquals('', $this->viewModel->getStickyLogoUrl());
    }

    public function testGetStickyLogoUrlReturnsCorrectUrl(): void
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with('themeoption/header/sticky_logo', ScopeInterface::SCOPE_STORE)
            ->willReturn('my-logo.png');

        $storeMock = $this->createMock(\Magento\Store\Model\Store::class);
        $storeMock->expects($this->once())
            ->method('getBaseUrl')
            ->with(UrlInterface::URL_TYPE_MEDIA)
            ->willReturn('https://example.com/media/');

        $this->storeManagerMock->expects($this->once())
            ->method('getStore')
            ->willReturn($storeMock);

        $expectedUrl = 'https://example.com/media/rokanthemes/stickylogo/my-logo.png';
        $this->assertEquals($expectedUrl, $this->viewModel->getStickyLogoUrl());
    }

    public function testGetStickyLogoUrlNormalizesLeadingSlash(): void
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with('themeoption/header/sticky_logo', ScopeInterface::SCOPE_STORE)
            ->willReturn('/my-logo.png');

        $storeMock = $this->createMock(\Magento\Store\Model\Store::class);
        $storeMock->expects($this->once())
            ->method('getBaseUrl')
            ->with(UrlInterface::URL_TYPE_MEDIA)
            ->willReturn('https://example.com/media/');

        $this->storeManagerMock->expects($this->once())
            ->method('getStore')
            ->willReturn($storeMock);

        $this->assertEquals(
            'https://example.com/media/rokanthemes/stickylogo/my-logo.png',
            $this->viewModel->getStickyLogoUrl()
        );
    }

    public function testGetStickyLogoUrlNormalizesUploadDirPrefix(): void
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with('themeoption/header/sticky_logo', ScopeInterface::SCOPE_STORE)
            ->willReturn('rokanthemes/stickylogo/my-logo.png');

        $storeMock = $this->createMock(\Magento\Store\Model\Store::class);
        $storeMock->expects($this->once())
            ->method('getBaseUrl')
            ->with(UrlInterface::URL_TYPE_MEDIA)
            ->willReturn('https://example.com/media/');

        $this->storeManagerMock->expects($this->once())
            ->method('getStore')
            ->willReturn($storeMock);

        $this->assertEquals(
            'https://example.com/media/rokanthemes/stickylogo/my-logo.png',
            $this->viewModel->getStickyLogoUrl()
        );
    }

    public function testGetStickyLogoUrlHandlesStoreManagerExceptionGracefully(): void
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with('themeoption/header/sticky_logo', ScopeInterface::SCOPE_STORE)
            ->willReturn('my-logo.png');

        $this->storeManagerMock->expects($this->once())
            ->method('getStore')
            ->willThrowException(new \Exception('Store not found'));

        $this->assertEquals('', $this->viewModel->getStickyLogoUrl());
    }

    public function testGetStickyLogoUrlReturnsEmptyForWhitespaceValue(): void
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with('themeoption/header/sticky_logo', ScopeInterface::SCOPE_STORE)
            ->willReturn('   ');

        $this->storeManagerMock->expects($this->never())
            ->method('getStore');

        $this->assertSame('', $this->viewModel->getStickyLogoUrl());
    }

    public function testGetStickyLogoUrlReturnsEmptyWhenOnlyUploadDirIsConfigured(): void
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with('themeoption/header/sticky_logo', ScopeInterface::SCOPE_STORE)
            ->willReturn('rokanthemes/stickylogo/');

        $this->storeManagerMock->expects($this->never())
            ->method('getStore');

        $this->assertSame('', $this->viewModel->getStickyLogoUrl());
    }

    public function testIsHeaderExperimentEnabledReturnsTrueWhenConfigured(): void
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('isSetFlag')
            ->with('grupoawamotos_theme/header_experiment/enabled', ScopeInterface::SCOPE_STORE)
            ->willReturn(true);

        $this->assertTrue($this->viewModel->isHeaderExperimentEnabled());
    }

    public function testGetHeaderExperimentRolloutPercentageClampsLowerBound(): void
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with('grupoawamotos_theme/header_experiment/rollout_percentage', ScopeInterface::SCOPE_STORE)
            ->willReturn('-5');

        $this->assertSame(0, $this->viewModel->getHeaderExperimentRolloutPercentage());
    }

    public function testGetHeaderExperimentRolloutPercentageClampsUpperBound(): void
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with('grupoawamotos_theme/header_experiment/rollout_percentage', ScopeInterface::SCOPE_STORE)
            ->willReturn('150');

        $this->assertSame(100, $this->viewModel->getHeaderExperimentRolloutPercentage());
    }

    public function testGetHeaderExperimentRolloutPercentageReturnsValidValue(): void
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with('grupoawamotos_theme/header_experiment/rollout_percentage', ScopeInterface::SCOPE_STORE)
            ->willReturn('35');

        $this->assertSame(35, $this->viewModel->getHeaderExperimentRolloutPercentage());
    }

    public function testGetHeaderExperimentSeedReturnsDefaultWhenEmpty(): void
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with('grupoawamotos_theme/header_experiment/variant_seed', ScopeInterface::SCOPE_STORE)
            ->willReturn(' ');

        $this->assertSame('home5_header_v1', $this->viewModel->getHeaderExperimentSeed());
    }

    public function testGetHeaderExperimentSeedReturnsConfiguredValue(): void
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with('grupoawamotos_theme/header_experiment/variant_seed', ScopeInterface::SCOPE_STORE)
            ->willReturn('home5_header_v2');

        $this->assertSame('home5_header_v2', $this->viewModel->getHeaderExperimentSeed());
    }
}
