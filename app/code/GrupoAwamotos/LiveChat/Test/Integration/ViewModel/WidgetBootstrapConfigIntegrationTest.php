<?php
declare(strict_types=1);

namespace GrupoAwamotos\LiveChat\Test\Integration\ViewModel;

use GrupoAwamotos\LiveChat\ViewModel\WidgetBootstrapConfig;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @magentoAppArea frontend
 */
class WidgetBootstrapConfigIntegrationTest extends TestCase
{
    public function testViewModelIsInstantiableAndReturnsExpectedShape(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $subject = $objectManager->get(WidgetBootstrapConfig::class);

        $this->assertInstanceOf(WidgetBootstrapConfig::class, $subject);

        $config = $subject->getConfig();

        $this->assertArrayHasKey('variant', $config);
        $this->assertArrayHasKey('deferInit', $config);
        $this->assertArrayHasKey('rolloutPercentage', $config);
        $this->assertContains($config['variant'], ['control', 'treatment']);
        $this->assertGreaterThanOrEqual(0, $config['rolloutPercentage']);
        $this->assertLessThanOrEqual(100, $config['rolloutPercentage']);
    }
}
