<?php

declare(strict_types=1);

namespace GrupoAwamotos\SmartSuggestions\Test\Unit\Helper;

use GrupoAwamotos\SmartSuggestions\Helper\Config;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Config Helper
 *
 * @covers \GrupoAwamotos\SmartSuggestions\Helper\Config
 */
class ConfigTest extends TestCase
{
    private Config $subject;
    private ScopeConfigInterface&MockObject $scopeConfigMock;
    private EncryptorInterface&MockObject $encryptorMock;

    protected function setUp(): void
    {
        $this->scopeConfigMock = $this->createMock(ScopeConfigInterface::class);
        $this->encryptorMock = $this->createMock(EncryptorInterface::class);

        $contextMock = $this->createMock(Context::class);
        $contextMock->method('getScopeConfig')->willReturn($this->scopeConfigMock);

        $this->subject = new Config($contextMock, $this->encryptorMock);
    }

    // ============ GENERAL ============

    public function testIsEnabledReturnsTrue(): void
    {
        $this->scopeConfigMock
            ->method('isSetFlag')
            ->with('smart_suggestions/general/enabled', 'store', null)
            ->willReturn(true);

        $this->assertTrue($this->subject->isEnabled());
    }

    public function testIsEnabledReturnsFalse(): void
    {
        $this->scopeConfigMock
            ->method('isSetFlag')
            ->willReturn(false);

        $this->assertFalse($this->subject->isEnabled());
    }

    public function testIsDebugModeReturnsBool(): void
    {
        $this->scopeConfigMock
            ->method('isSetFlag')
            ->willReturn(true);

        $this->assertTrue($this->subject->isDebugMode());
    }

    // ============ RFM ============

    public function testGetRfmAnalysisPeriodReturnsConfiguredValue(): void
    {
        $this->scopeConfigMock
            ->method('getValue')
            ->willReturn('730');

        $this->assertEquals(730, $this->subject->getRfmAnalysisPeriod());
    }

    public function testGetRfmAnalysisPeriodDefaultsTo365(): void
    {
        $this->scopeConfigMock
            ->method('getValue')
            ->willReturn(null);

        $this->assertEquals(365, $this->subject->getRfmAnalysisPeriod());
    }

    public function testGetRfmRecencyWeightDefaultsTo035(): void
    {
        $this->scopeConfigMock
            ->method('getValue')
            ->willReturn(null);

        $this->assertEquals(0.35, $this->subject->getRfmRecencyWeight());
    }

    public function testGetRfmFrequencyWeightDefaultsTo035(): void
    {
        $this->scopeConfigMock
            ->method('getValue')
            ->willReturn(null);

        $this->assertEquals(0.35, $this->subject->getRfmFrequencyWeight());
    }

    public function testGetRfmMonetaryWeightDefaultsTo030(): void
    {
        $this->scopeConfigMock
            ->method('getValue')
            ->willReturn(null);

        $this->assertEquals(0.30, $this->subject->getRfmMonetaryWeight());
    }

    public function testGetRfmMinOrdersDefaultsTo2(): void
    {
        $this->scopeConfigMock
            ->method('getValue')
            ->willReturn(null);

        $this->assertEquals(2, $this->subject->getRfmMinOrders());
    }

    // ============ SUGGESTIONS ============

    public function testGetMaxRepurchaseItemsDefaultsTo10(): void
    {
        $this->scopeConfigMock
            ->method('getValue')
            ->willReturn(null);

        $this->assertEquals(10, $this->subject->getMaxRepurchaseItems());
    }

    public function testGetMaxCrossSellItemsDefaultsTo5(): void
    {
        $this->scopeConfigMock
            ->method('getValue')
            ->willReturn(null);

        $this->assertEquals(5, $this->subject->getMaxCrossSellItems());
    }

    public function testGetMaxSimilarItemsDefaultsTo5(): void
    {
        $this->scopeConfigMock
            ->method('getValue')
            ->willReturn(null);

        $this->assertEquals(5, $this->subject->getMaxSimilarItems());
    }

    public function testGetCycleTolerancePercentDefaultsTo20(): void
    {
        $this->scopeConfigMock
            ->method('getValue')
            ->willReturn(null);

        $this->assertEquals(20, $this->subject->getCycleTolerancePercent());
    }

    public function testGetMinSupportCrossSellDefaultsTo3(): void
    {
        $this->scopeConfigMock
            ->method('getValue')
            ->willReturn(null);

        $this->assertEquals(3, $this->subject->getMinSupportCrossSell());
    }

    // ============ FORECAST ============

    public function testGetMonteCarloIterationsDefaultsTo1000(): void
    {
        $this->scopeConfigMock
            ->method('getValue')
            ->willReturn(null);

        $this->assertEquals(1000, $this->subject->getMonteCarloIterations());
    }

    public function testGetMonteCarloIterationsReturnsConfiguredValue(): void
    {
        $this->scopeConfigMock
            ->method('getValue')
            ->willReturn('5000');

        $this->assertEquals(5000, $this->subject->getMonteCarloIterations());
    }

    public function testGetMovingAvgDaysDefaultsTo14(): void
    {
        $this->scopeConfigMock
            ->method('getValue')
            ->willReturn(null);

        $this->assertEquals(14, $this->subject->getMovingAvgDays());
    }

    public function testGetTrendWeightDefaultsTo03(): void
    {
        $this->scopeConfigMock
            ->method('getValue')
            ->willReturn(null);

        $this->assertEquals(0.3, $this->subject->getTrendWeight());
    }

    public function testGetSeasonalityWeightDefaultsTo02(): void
    {
        $this->scopeConfigMock
            ->method('getValue')
            ->willReturn(null);

        $this->assertEquals(0.2, $this->subject->getSeasonalityWeight());
    }

    public function testGetMonthlyGoalDefaultsTo500000(): void
    {
        $this->scopeConfigMock
            ->method('getValue')
            ->willReturn(null);

        $this->assertEquals(500000.0, $this->subject->getMonthlyGoal());
    }

    // ============ WHATSAPP ============

    public function testIsWhatsappEnabledReturnsBool(): void
    {
        $this->scopeConfigMock
            ->method('isSetFlag')
            ->willReturn(true);

        $this->assertTrue($this->subject->isWhatsappEnabled());
    }

    public function testGetWhatsappProviderDefaultsToMeta(): void
    {
        $this->scopeConfigMock
            ->method('getValue')
            ->willReturn(null);

        $this->assertEquals('meta', $this->subject->getWhatsappProvider());
    }

    public function testGetWhatsappProviderReturnsConfiguredValue(): void
    {
        $this->scopeConfigMock
            ->method('getValue')
            ->willReturn('twilio');

        $this->assertEquals('twilio', $this->subject->getWhatsappProvider());
    }

    public function testGetWhatsappApiUrlDefaultsToEmpty(): void
    {
        $this->scopeConfigMock
            ->method('getValue')
            ->willReturn(null);

        $this->assertEquals('', $this->subject->getWhatsappApiUrl());
    }

    public function testGetWhatsappApiTokenDecryptsValue(): void
    {
        $this->scopeConfigMock
            ->method('getValue')
            ->willReturn('encrypted_token_123');

        $this->encryptorMock
            ->expects($this->once())
            ->method('decrypt')
            ->with('encrypted_token_123')
            ->willReturn('decrypted_real_token');

        $this->assertEquals('decrypted_real_token', $this->subject->getWhatsappApiToken());
    }

    public function testGetWhatsappApiTokenReturnsEmptyWhenNotConfigured(): void
    {
        $this->scopeConfigMock
            ->method('getValue')
            ->willReturn('');

        $this->encryptorMock
            ->expects($this->never())
            ->method('decrypt');

        $this->assertEquals('', $this->subject->getWhatsappApiToken());
    }

    public function testGetWhatsappPhoneNumberIdDefaultsToEmpty(): void
    {
        $this->scopeConfigMock
            ->method('getValue')
            ->willReturn(null);

        $this->assertEquals('', $this->subject->getWhatsappPhoneNumberId());
    }

    public function testGetTwilioSidDefaultsToEmpty(): void
    {
        $this->scopeConfigMock
            ->method('getValue')
            ->willReturn(null);

        $this->assertEquals('', $this->subject->getTwilioSid());
    }

    public function testGetTwilioFromDefaultsToEmpty(): void
    {
        $this->scopeConfigMock
            ->method('getValue')
            ->willReturn(null);

        $this->assertEquals('', $this->subject->getTwilioFrom());
    }

    public function testGetWhatsappMessageTemplateDefaultsToEmpty(): void
    {
        $this->scopeConfigMock
            ->method('getValue')
            ->willReturn(null);

        $this->assertEquals('', $this->subject->getWhatsappMessageTemplate());
    }

    // ============ CRON ============

    public function testIsRfmCronEnabledReturnsBool(): void
    {
        $this->scopeConfigMock
            ->method('isSetFlag')
            ->willReturn(false);

        $this->assertFalse($this->subject->isRfmCronEnabled());
    }

    public function testGetRfmCronScheduleDefaultsTo2AM(): void
    {
        $this->scopeConfigMock
            ->method('getValue')
            ->willReturn(null);

        $this->assertEquals('0 2 * * *', $this->subject->getRfmCronSchedule());
    }

    public function testIsSuggestionsCronEnabledReturnsBool(): void
    {
        $this->scopeConfigMock
            ->method('isSetFlag')
            ->willReturn(true);

        $this->assertTrue($this->subject->isSuggestionsCronEnabled());
    }

    public function testGetSuggestionsCronScheduleDefaultsToMonday6AM(): void
    {
        $this->scopeConfigMock
            ->method('getValue')
            ->willReturn(null);

        $this->assertEquals('0 6 * * 1', $this->subject->getSuggestionsCronSchedule());
    }

    public function testIsAutoSendWhatsappEnabledReturnsBool(): void
    {
        $this->scopeConfigMock
            ->method('isSetFlag')
            ->willReturn(false);

        $this->assertFalse($this->subject->isAutoSendWhatsappEnabled());
    }

    // ============ EXPORT ============

    public function testGetCsvDelimiterDefaultsToSemicolon(): void
    {
        $this->scopeConfigMock
            ->method('getValue')
            ->willReturn(null);

        $this->assertEquals(';', $this->subject->getCsvDelimiter());
    }

    public function testGetCsvDelimiterReturnsConfiguredValue(): void
    {
        $this->scopeConfigMock
            ->method('getValue')
            ->willReturn(',');

        $this->assertEquals(',', $this->subject->getCsvDelimiter());
    }

    public function testIncludeHeadersReturnsBool(): void
    {
        $this->scopeConfigMock
            ->method('isSetFlag')
            ->willReturn(true);

        $this->assertTrue($this->subject->includeHeaders());
    }
}
