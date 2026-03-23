<?php
declare(strict_types=1);

namespace GrupoAwamotos\LiveChat\ViewModel;

use GrupoAwamotos\LiveChat\Model\Chat\WidgetExperimentAssigner;
use GrupoAwamotos\LiveChat\Model\Config\WidgetExperimentConfig;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class WidgetBootstrapConfig implements ArgumentInterface
{
    private const IDLE_TIMEOUT_MS = 4000;

    public function __construct(
        private readonly WidgetExperimentConfig $config,
        private readonly WidgetExperimentAssigner $assigner,
        private readonly Json $jsonSerializer
    ) {
    }

    /**
     * @return array{
     *     experimentEnabled: bool,
     *     rolloutPercentage: int,
     *     variantSeed: string,
     *     variant: string,
     *     deferInit: bool,
     *     idleTimeoutMs: int,
     *     initEvents: array<int, string>,
     *     customVariables: array<int, array{name: string, value: string}>
     * }
     */
    public function getConfig(): array
    {
        $variant = $this->assigner->getVariant();
        $deferInit = $this->assigner->shouldDeferInit();

        return [
            'experimentEnabled' => $this->config->isExperimentEnabled(),
            'rolloutPercentage' => $this->config->getRolloutPercentage(),
            'variantSeed' => $this->config->getVariantSeed(),
            'variant' => $variant,
            'deferInit' => $deferInit,
            'idleTimeoutMs' => self::IDLE_TIMEOUT_MS,
            'initEvents' => ['pointerdown', 'touchstart', 'keydown', 'mouseover', 'scroll'],
            'customVariables' => [
                [
                    'name' => 'LiveChat experimento',
                    'value' => $this->config->getVariantSeed(),
                ],
                [
                    'name' => 'LiveChat variante',
                    'value' => $variant,
                ],
                [
                    'name' => 'LiveChat carregamento',
                    'value' => $deferInit ? 'deferido' : 'imediato',
                ],
            ],
        ];
    }

    public function getConfigJson(): string
    {
        return $this->jsonSerializer->serialize($this->getConfig());
    }
}
