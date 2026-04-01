<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\Test\Integration\Helper;

use GrupoAwamotos\Theme\Helper\HeaderExperiment;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class HeaderExperimentTest extends TestCase
{
    public function testDefaultConfigurationIsLoadedIntoPayload(): void
    {
        if (!class_exists('Magento\\TestFramework\\Helper\\Bootstrap')) {
            self::markTestSkipped('Magento integration bootstrap indisponivel neste runner de testes.');
        }

        /** @var HeaderExperiment $helper */
        $helper = Bootstrap::getObjectManager()->get(HeaderExperiment::class);
        $payload = $helper->getPayload();

        $this->assertTrue($helper->isEnabled());
        $this->assertSame(35, $helper->getRolloutPercentage());
        $this->assertSame('v2', $helper->getVariantCode());
        $this->assertSame('home5_header_v1', $helper->getVariantSeed());
        $this->assertArrayHasKey('seed', $payload);
        $this->assertArrayHasKey('bucket', $payload);
        $this->assertArrayHasKey('is_active', $payload);
        $this->assertArrayHasKey('experiment', $payload);
        $this->assertArrayHasKey('control_variant', $payload);
        $this->assertSame('home5_header_v1', $payload['seed']);
        $this->assertSame('header_progressive_rollout', $payload['experiment']);
        $this->assertSame('control', $payload['control_variant']);

        $payloadAgain = $helper->getPayload();
        $this->assertSame($payload, $payloadAgain);
    }
}
