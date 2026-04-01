<?php

declare(strict_types=1);

namespace GrupoAwamotos\MaintenanceMode\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Mode implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'maintenance', 'label' => __('🔧 Modo de Manutenção')],
            ['value' => 'coming_soon', 'label' => __('🚀 Página "Em Breve"')]
        ];
    }
}
