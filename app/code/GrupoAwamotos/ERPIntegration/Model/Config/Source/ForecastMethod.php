<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Forecast Method Options
 */
class ForecastMethod implements OptionSourceInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'hybrid', 'label' => __('Hibrido (Recomendado)')],
            ['value' => 'moving_average', 'label' => __('Media Movel Ponderada')],
            ['value' => 'linear_trend', 'label' => __('Tendencia Linear')],
            ['value' => 'seasonal', 'label' => __('Sazonal')],
            ['value' => 'simple', 'label' => __('Simples (Media)')],
        ];
    }
}
