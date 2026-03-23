<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Ui\Component\Listing\Column;

use Magento\Framework\Data\OptionSourceInterface;

class CnaeProfileOptions implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'direct', 'label' => __('Direto')],
            ['value' => 'adjacent', 'label' => __('Adjacente')],
            ['value' => 'off_profile', 'label' => __('Fora do Perfil')],
        ];
    }
}
