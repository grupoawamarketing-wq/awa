<?php

declare(strict_types=1);

namespace GrupoAwamotos\Chatwoot\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Position implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'right', 'label' => __('Direita')],
            ['value' => 'left', 'label' => __('Esquerda')],
        ];
    }
}
