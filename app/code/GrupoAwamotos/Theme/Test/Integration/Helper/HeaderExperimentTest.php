<?php
declare(strict_types=1);

namespace GrupoAwamotos\Theme\Test\Integration\Helper;

use GrupoAwamotos\Theme\Helper\HeaderExperiment;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class HeaderExperimentTest extends TestCase
{
    public function testDefaultConfigurationKeepsExperimentDisabled(): void
    {
        /** @var HeaderExperiment $helper */
        $helper = Bootstrap::getObjectManager()->get(HeaderExperiment::class);
        $payload = $helper->getPayload();

        $this->assertFalse($helper->isEnabled());
        $this->assertSame(0, $helper->getRolloutPercentage());
        $this->assertSame('v2', $helper->getVariantCode());
        $this->assertSame('control', $payload['variant']);
        $this->assertFalse($payload['active']);
    }
}