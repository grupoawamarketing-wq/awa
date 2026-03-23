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
        $this->assertSame('home5_header_v1', $payload['seed']);
    }
}
