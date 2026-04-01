<?php

declare(strict_types=1);

namespace GrupoAwamotos\AbandonedCart\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class DiscountType implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'percent', 'label' => __('Porcentagem (%)')],
            ['value' => 'fixed', 'label' => __('Valor Fixo (R$)')],
        ];
    }
}
