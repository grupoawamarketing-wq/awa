<?php
declare(strict_types=1);

namespace GrupoAwamotos\LiveChat\Test\Unit\Model\Config;

use GrupoAwamotos\LiveChat\Model\Config\WidgetExperimentConfig;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class WidgetExperimentConfigTest extends TestCase
{
    private WidgetExperimentConfig $subject;

    private ScopeConfigInterface&MockObject $scopeConfig;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->subject = new WidgetExperimentConfig($this->scopeConfig);
    }

    public function testIsExperimentEnabledReturnsTrueWhenConfigured(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('isSetFlag')
            ->with('grupoawamotos_livechat/widget_experiment/enabled', ScopeInterface::SCOPE_STORE)
            ->willReturn(true);

        $this->assertTrue($this->subject->isExperimentEnabled());
    }

    public function testGetRolloutPercentageClampsLowerBound(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with('grupoawamotos_livechat/widget_experiment/rollout_percentage', ScopeInterface::SCOPE_STORE)
            ->willReturn('-15');

        $this->assertSame(0, $this->subject->getRolloutPercentage());
    }

    public function testGetRolloutPercentageClampsUpperBound(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with('grupoawamotos_livechat/widget_experiment/rollout_percentage', ScopeInterface::SCOPE_STORE)
            ->willReturn('150');

        $this->assertSame(100, $this->subject->getRolloutPercentage());
    }

    public function testGetRolloutPercentageReturnsConfiguredValue(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with('grupoawamotos_livechat/widget_experiment/rollout_percentage', ScopeInterface::SCOPE_STORE)
            ->willReturn('35');

        $this->assertSame(35, $this->subject->getRolloutPercentage());
    }

    public function testGetVariantSeedReturnsDefaultWhenEmpty(): void
    {
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->with('grupoawamotos_livechat/widget_experiment/variant_seed', ScopeInterface::SCOPE_STORE)
            ->willReturn(' ');

        $this->assertSame('livechat_widget_v1', $this->subject->getVariantSeed());
    }
}
