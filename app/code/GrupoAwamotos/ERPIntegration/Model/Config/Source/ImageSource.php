<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ImageSource implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            [
                'value' => 'auto',
                'label' => __('Auto-detectar (tabela ERP > pasta local)'),
            ],
            [
                'value' => 'table',
                'label' => __('Tabela ERP (MT_MATERIALIMAGEM)'),
            ],
            [
                'value' => 'folder',
                'label' => __('Pasta local/rede'),
            ],
            [
                'value' => 'url',
                'label' => __('URL remota'),
            ],
        ];
    }
}
