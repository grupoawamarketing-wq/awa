<?php

/**
 * Quote Status Source Model
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Config\Source;

use GrupoAwamotos\B2B\Api\Data\QuoteRequestInterface;
use Magento\Framework\Data\OptionSourceInterface;

class QuoteStatus implements OptionSourceInterface
{
    /**
     * Get options
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => QuoteRequestInterface::STATUS_PENDING,
                'label' => __('Aguardando Análise'),
            ],
            [
                'value' => QuoteRequestInterface::STATUS_PROCESSING,
                'label' => __('Em Análise'),
            ],
            [
                'value' => QuoteRequestInterface::STATUS_QUOTED,
                'label' => __('Orçamento Enviado'),
            ],
            [
                'value' => QuoteRequestInterface::STATUS_ACCEPTED,
                'label' => __('Aceito'),
            ],
            [
                'value' => QuoteRequestInterface::STATUS_REJECTED,
                'label' => __('Recusado'),
            ],
            [
                'value' => QuoteRequestInterface::STATUS_EXPIRED,
                'label' => __('Expirado'),
            ],
            [
                'value' => QuoteRequestInterface::STATUS_CONVERTED,
                'label' => __('Convertido em Pedido'),
            ],
        ];
    }
}
