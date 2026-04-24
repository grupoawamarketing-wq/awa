<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class CommercialStatus implements OptionSourceInterface
{
    public const STATUSES = [
        'novo'          => 'Novo',
        'contato'       => 'Em Contato',
        'negociando'    => 'Negociando',
        'proposta'      => 'Proposta Enviada',
        'aguardando'    => 'Aguardando Decisão',
        'ganho'         => 'Ganho',
        'perdido'       => 'Perdido',
        'inativo'       => 'Inativo',
        'reativacao'    => 'Em Reativação',
        'vip'           => 'VIP',
    ];

    public function toOptionArray(): array
    {
        $options = [];
        foreach (self::STATUSES as $value => $label) {
            $options[] = ['value' => $value, 'label' => __($label)];
        }
        return $options;
    }
}
