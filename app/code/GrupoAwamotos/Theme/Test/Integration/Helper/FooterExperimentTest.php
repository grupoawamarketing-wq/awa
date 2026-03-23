<?php
declare(strict_types=1);

namespace GrupoAwamotos\Theme\Test\Integration\Helper;

use GrupoAwamotos\Theme\Helper\FooterExperiment;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class FooterExperimentTest extends TestCase
{
    public function testDefaultConfigurationIsLoadedIntoPayload(): void
    {
        if (!class_exists('Magento\\TestFramework\\Helper\\Bootstrap')) {
            self::markTestSkipped('Magento integration bootstrap indisponivel neste runner de testes.');
        }

        /** @var FooterExperiment $helper */
        $helper = Bootstrap::getObjectManager()->get(FooterExperiment::class);
        $payload = $helper->getPayload();

        $this->assertFalse($helper->isEnabled());
        $this->assertSame(0, $helper->getRolloutPercentage());
        $this->assertSame('treatment', $helper->getVariantCode());
        $this->assertSame('home5_footer_v1', $helper->getVariantSeed());
        $this->assertArrayHasKey('seed', $payload);
        $this->assertArrayHasKey('bucket', $payload);
        $this->assertArrayHasKey('is_active', $payload);
        $this->assertArrayHasKey('experiment', $payload);
        $this->assertArrayHasKey('control_variant', $payload);
        $this->assertSame('home5_footer_v1', $payload['seed']);
        $this->assertSame('footer_progressive_rollout', $payload['experiment']);
        $this->assertSame('control', $payload['control_variant']);
        $this->assertSame('control', $payload['variant']);

        $payloadAgain = $helper->getPayload();
        $this->assertSame($payload, $payloadAgain);
    }
}
