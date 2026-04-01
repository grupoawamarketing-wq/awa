<?php

declare(strict_types=1);

namespace Ayo\Curriculo\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ContractType implements OptionSourceInterface
{
    /**
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'CLT', 'label' => __('CLT')],
            ['value' => 'PJ', 'label' => __('PJ')],
            ['value' => 'Estágio', 'label' => __('Estágio')],
            ['value' => 'Temporário', 'label' => __('Temporário')],
            ['value' => 'Ambos (CLT ou PJ)', 'label' => __('Ambos (CLT ou PJ)')],
        ];
    }
}
