<?php

declare(strict_types=1);

namespace GrupoAwamotos\FakePurchase\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Position implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'bottom-left', 'label' => __('Inferior Esquerdo')],
            ['value' => 'bottom-right', 'label' => __('Inferior Direito')],
            ['value' => 'top-left', 'label' => __('Superior Esquerdo')],
            ['value' => 'top-right', 'label' => __('Superior Direito')],
        ];
    }
}
