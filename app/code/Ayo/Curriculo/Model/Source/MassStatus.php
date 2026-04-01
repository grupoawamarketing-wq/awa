<?php

declare(strict_types=1);

namespace Ayo\Curriculo\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class MassStatus implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            [
                'value' => 'pending',
                'label' => __('Pendente'),
                'url' => 'curriculo/submission/massStatus/status/pending'
            ],
            [
                'value' => 'reviewing',
                'label' => __('Em Análise'),
                'url' => 'curriculo/submission/massStatus/status/reviewing'
            ],
            [
                'value' => 'interview',
                'label' => __('Entrevista'),
                'url' => 'curriculo/submission/massStatus/status/interview'
            ],
            [
                'value' => 'approved',
                'label' => __('Aprovado'),
                'url' => 'curriculo/submission/massStatus/status/approved'
            ],
            [
                'value' => 'rejected',
                'label' => __('Não Aprovado'),
                'url' => 'curriculo/submission/massStatus/status/rejected'
            ],
        ];
    }
}
