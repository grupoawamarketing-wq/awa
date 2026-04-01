<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class AggregationMode implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            [
                'value' => 'sum',
                'label' => __('Somar (recomendado) - Soma o estoque de todas as filiais'),
            ],
            [
                'value' => 'min',
                'label' => __('Minimo - Usa o menor estoque entre as filiais'),
            ],
            [
                'value' => 'max',
                'label' => __('Maximo - Usa o maior estoque entre as filiais'),
            ],
            [
                'value' => 'avg',
                'label' => __('Media - Calcula a media do estoque'),
            ],
        ];
    }
}
