<?php
declare(strict_types=1);

namespace GrupoAwamotos\Theme\Test\Unit\ViewModel;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use GrupoAwamotos\Theme\ViewModel\FooterData;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class FooterDataTest extends TestCase
{
    private FooterData $viewModel;

    /** @var ScopeConfigInterface|MockObject */
    private $scopeConfigMock;

    protected function setUp(): void
    {
        $this->scopeConfigMock = $this->createMock(ScopeConfigInterface::class);

        $this->viewModel = new FooterData(
            $this->scopeConfigMock
        );
    }

    public function testIsMobileMenuEnabledReturnsTrueWhenConfigured(): void
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('isSetFlag')
            ->with('themeoption/footer/footer_menu_mobile', ScopeInterface::SCOPE_STORE)
            ->willReturn(true);

        $this->assertTrue($this->viewModel->isMobileMenuEnabled());
    }

    public function testIsMobileMenuEnabledReturnsFalseWhenNotConfigured(): void
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('isSetFlag')
            ->with('themeoption/footer/footer_menu_mobile', ScopeInterface::SCOPE_STORE)
            ->willReturn(false);

        $this->assertFalse($this->viewModel->isMobileMenuEnabled());
    }

    public function testGetPhoneUsesContactPhoneWhenAvailable(): void
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with('grupoawamotos_theme/contact/phone', ScopeInterface::SCOPE_STORE)
            ->willReturn(' +55 16 99999-9999 ');

        $this->assertSame('+55 16 99999-9999', $this->viewModel->getPhone());
    }

    public function testGetPhoneFallsBackToStorePhone(): void
    {
        $this->scopeConfigMock->expects($this->exactly(2))
            ->method('getValue')
            ->willReturnCallback(static function (string $path): string {
                return match ($path) {
                    'grupoawamotos_theme/contact/phone' => '',
                    'general/store_information/phone' => '(16) 3333-2222',
                    default => '',
                };
            });

        $this->assertSame('(16) 3333-2222', $this->viewModel->getPhone());
    }

    public function testGetPhoneFallsBackToDefaultWhenBothConfigsAreEmpty(): void
    {
        $this->scopeConfigMock->expects($this->exactly(2))
            ->method('getValue')
            ->willReturnMap([
                ['grupoawamotos_theme/contact/phone', ScopeInterface::SCOPE_STORE, ' '],
                ['general/store_information/phone', ScopeInterface::SCOPE_STORE, ''],
            ]);

        $this->assertSame('(16) 3322-0000', $this->viewModel->getPhone());
    }

    public function testGetPhoneUrlNormalizesDigitsAndAddsPlusPrefix(): void
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->willReturnCallback(static function (string $path): string {
                return match ($path) {
                    'grupoawamotos_theme/contact/phone' => '(16) 99736-7588',
                    default => '',
                };
            });

        $this->assertSame('tel:+16997367588', $this->viewModel->getPhoneUrl());
    }

    public function testGetWhatsAppUrlUsesNormalizedNumber(): void
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with('grupoawamotos_theme/contact/whatsapp_number', ScopeInterface::SCOPE_STORE)
            ->willReturn('+55 (16) 99736-7588');

        $this->assertSame('https://wa.me/5516997367588', $this->viewModel->getWhatsAppUrl());
    }

    public function testGetEmailUrlBuildsMailtoWithFallbackEmail(): void
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with('grupoawamotos_theme/contact/email', ScopeInterface::SCOPE_STORE)
            ->willReturn('');

        $this->assertSame('mailto:contato@awamotos.com.br', $this->viewModel->getEmailUrl());
    }

    public function testGetFormattedAddressIncludesPostcodeWhenPresent(): void
    {
        $this->scopeConfigMock->expects($this->exactly(3))
            ->method('getValue')
            ->willReturnCallback(static function (string $path): string {
                return match ($path) {
                    'general/store_information/street_line1' => 'Rua A, 123',
                    'general/store_information/city' => 'Araraquara-SP',
                    'general/store_information/postcode' => '14800-000',
                    default => '',
                };
            });

        $this->assertSame('Rua A, 123 - Araraquara-SP - CEP: 14800-000', $this->viewModel->getFormattedAddress());
    }

    public function testGetInstagramUrlUsesConfiguredValue(): void
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with('grupoawamotos_theme/social/instagram_url', ScopeInterface::SCOPE_STORE)
            ->willReturn('https://instagram.com/awa_teste');

        $this->assertSame('https://instagram.com/awa_teste', $this->viewModel->getInstagramUrl());
    }

    public function testGetFooterExperimentEnabledReadsFlag(): void
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('isSetFlag')
            ->with('grupoawamotos_theme/footer_experiment/enabled', ScopeInterface::SCOPE_STORE)
            ->willReturn(true);

        $this->assertTrue($this->viewModel->isFooterExperimentEnabled());
    }

    public function testGetFooterExperimentRolloutPercentageClampsBounds(): void
    {
        $this->scopeConfigMock->expects($this->exactly(2))
            ->method('getValue')
            ->willReturnOnConsecutiveCalls('150', '-10');

        $this->assertSame(100, $this->viewModel->getFooterExperimentRolloutPercentage());
        $this->assertSame(0, $this->viewModel->getFooterExperimentRolloutPercentage());
    }

    public function testGetFooterExperimentSeedFallsBackWhenEmpty(): void
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with('grupoawamotos_theme/footer_experiment/variant_seed', ScopeInterface::SCOPE_STORE)
            ->willReturn('   ');

        $this->assertSame('home5_footer_v1', $this->viewModel->getFooterExperimentSeed());
    }
}
