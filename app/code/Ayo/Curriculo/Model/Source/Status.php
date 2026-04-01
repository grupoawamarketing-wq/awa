<?php

declare(strict_types=1);

namespace Ayo\Curriculo\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Status implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'pending', 'label' => __('Pendente')],
            ['value' => 'reviewing', 'label' => __('Em Análise')],
            ['value' => 'interview', 'label' => __('Entrevista')],
            ['value' => 'approved', 'label' => __('Aprovado')],
            ['value' => 'rejected', 'label' => __('Não Aprovado')],
        ];
    }
}
