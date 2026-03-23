<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class InsightLevel implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'campaign', 'label' => __('Campaign')],
            ['value' => 'adset', 'label' => __('Ad Set')],
            ['value' => 'ad', 'label' => __('Ad')],
        ];
    }
}
