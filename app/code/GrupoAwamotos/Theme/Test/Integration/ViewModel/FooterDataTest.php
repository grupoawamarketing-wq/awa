<?php
declare(strict_types=1);

namespace GrupoAwamotos\Theme\Test\Integration\ViewModel;

use GrupoAwamotos\Theme\ViewModel\FooterData;
use PHPUnit\Framework\TestCase;

class FooterDataTest extends TestCase
{
    public function testDefaultFooterExperimentConfigurationIsSafeForProduction(): void
    {
        if (!class_exists('Magento\\TestFramework\\Helper\\Bootstrap')) {
            self::markTestSkipped('Magento integration bootstrap indisponivel neste runner de testes.');
        }

        /** @var class-string $bootstrapClass */
        $bootstrapClass = 'Magento\\TestFramework\\Helper\\Bootstrap';
        /** @var FooterData $viewModel */
        $viewModel = $bootstrapClass::getObjectManager()->get(FooterData::class);

        $this->assertFalse($viewModel->isFooterExperimentEnabled());
        $this->assertSame(0, $viewModel->getFooterExperimentRolloutPercentage());
        $this->assertSame('home5_footer_v1', $viewModel->getFooterExperimentSeed());
    }
}
