<?php

declare(strict_types=1);

namespace GrupoAwamotos\Chatwoot\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class DarkMode implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'auto', 'label' => __('Automático (conforme sistema do visitante)')],
            ['value' => 'light', 'label' => __('Claro')],
            ['value' => 'dark', 'label' => __('Escuro')],
        ];
    }
}
