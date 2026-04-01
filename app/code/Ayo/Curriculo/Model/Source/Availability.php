<?php

declare(strict_types=1);

namespace Ayo\Curriculo\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Availability implements OptionSourceInterface
{
    /**
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'Imediata', 'label' => __('Imediata')],
            ['value' => '15 dias', 'label' => __('15 dias')],
            ['value' => '30 dias', 'label' => __('30 dias')],
            ['value' => 'A combinar', 'label' => __('A combinar')],
        ];
    }
}
