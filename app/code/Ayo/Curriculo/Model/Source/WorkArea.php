<?php

declare(strict_types=1);

namespace Ayo\Curriculo\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class WorkArea implements OptionSourceInterface
{
    /**
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'Vendas / Comercial', 'label' => __('Vendas / Comercial')],
            ['value' => 'Estoque / Logística', 'label' => __('Estoque / Logística')],
            ['value' => 'Atendimento ao Cliente', 'label' => __('Atendimento ao Cliente')],
            ['value' => 'Marketing / E-commerce', 'label' => __('Marketing / E-commerce')],
            ['value' => 'Financeiro / Administrativo', 'label' => __('Financeiro / Administrativo')],
            ['value' => 'TI / Tecnologia', 'label' => __('TI / Tecnologia')],
            ['value' => 'Mecânica / Oficina', 'label' => __('Mecânica / Oficina')],
            ['value' => 'Compras', 'label' => __('Compras')],
            ['value' => 'RH / Gestão de Pessoas', 'label' => __('RH / Gestão de Pessoas')],
        ];
    }
}
