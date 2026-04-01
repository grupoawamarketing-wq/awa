<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class SyncFrequency implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => '15', 'label' => __('A cada 15 minutos')],
            ['value' => '30', 'label' => __('A cada 30 minutos')],
            ['value' => '60', 'label' => __('A cada 1 hora')],
            ['value' => '120', 'label' => __('A cada 2 horas')],
            ['value' => '240', 'label' => __('A cada 4 horas')],
            ['value' => '360', 'label' => __('A cada 6 horas')],
            ['value' => '720', 'label' => __('A cada 12 horas')],
            ['value' => '1440', 'label' => __('Uma vez por dia')],
        ];
    }
}
