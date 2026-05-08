<?php

declare(strict_types=1);

namespace GrupoAwamotos\TawkIntegration\Ui\Component\Listing\Column;

use Magento\Framework\Data\OptionSourceInterface;

class EventOptions implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'chat:start',    'label' => __('Chat Iniciado')],
            ['value' => 'chat:end',      'label' => __('Chat Encerrado')],
            ['value' => 'ticket:create', 'label' => __('Ticket Criado')],
        ];
    }
}
