<?php
declare(strict_types=1);

namespace GrupoAwamotos\LiveChat\Test\Unit\ViewModel;

use GrupoAwamotos\LiveChat\Model\Chat\WidgetExperimentAssigner;
use GrupoAwamotos\LiveChat\Model\Config\WidgetExperimentConfig;
use GrupoAwamotos\LiveChat\ViewModel\WidgetBootstrapConfig;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class WidgetBootstrapConfigTest extends TestCase
{
    private WidgetBootstrapConfig $subject;

    private WidgetExperimentConfig&MockObject $config;

    private WidgetExperimentAssigner&MockObject $assigner;

    protected function setUp(): void
    {
        $this->config = $this->createMock(WidgetExperimentConfig::class);
        $this->assigner = $this->createMock(WidgetExperimentAssigner::class);

        $this->subject = new WidgetBootstrapConfig(
            $this->config,
            $this->assigner,
            new Json()
        );
    }

    public function testGetConfigReturnsExpectedStructure(): void
    {
        $this->config->method('isExperimentEnabled')->willReturn(true);
        $this->config->method('getRolloutPercentage')->willReturn(35);
        $this->config->method('getVariantSeed')->willReturn('livechat_widget_v2');
        $this->assigner->method('getVariant')->willReturn(WidgetExperimentAssigner::VARIANT_TREATMENT);
        $this->assigner->method('shouldDeferInit')->willReturn(true);

        $result = $this->subject->getConfig();

        $this->assertTrue($result['experimentEnabled']);
        $this->assertSame(35, $result['rolloutPercentage']);
        $this->assertSame('livechat_widget_v2', $result['variantSeed']);
        $this->assertSame('treatment', $result['variant']);
        $this->assertTrue($result['deferInit']);
        $this->assertSame(4000, $result['idleTimeoutMs']);
        $this->assertContains('pointerdown', $result['initEvents']);
        $this->assertSame('LiveChat variante', $result['customVariables'][1]['name']);
        $this->assertSame('treatment', $result['customVariables'][1]['value']);
    }

    public function testGetConfigJsonSerializesPayload(): void
    {
        $this->config->method('isExperimentEnabled')->willReturn(false);
        $this->config->method('getRolloutPercentage')->willReturn(0);
        $this->config->method('getVariantSeed')->willReturn('livechat_widget_v1');
        $this->assigner->method('getVariant')->willReturn(WidgetExperimentAssigner::VARIANT_CONTROL);
        $this->assigner->method('shouldDeferInit')->willReturn(false);

        $json = $this->subject->getConfigJson();

        $this->assertStringContainsString('"variant":"control"', $json);
        $this->assertStringContainsString('"deferInit":false', $json);
    }
}
