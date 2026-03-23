<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Ui\Component\Listing\Column;

use Magento\Framework\Data\OptionSourceInterface;

class ProspectStatusOptions implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'new', 'label' => __('Novo')],
            ['value' => 'contacted', 'label' => __('Contatado')],
            ['value' => 'interested', 'label' => __('Interessado')],
            ['value' => 'converted', 'label' => __('Convertido')],
            ['value' => 'rejected', 'label' => __('Rejeitado')],
        ];
    }
}
