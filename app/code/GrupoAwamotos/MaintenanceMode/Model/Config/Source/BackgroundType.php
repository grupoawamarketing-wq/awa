<?php

declare(strict_types=1);

namespace GrupoAwamotos\MaintenanceMode\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class BackgroundType implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'color', 'label' => __('Cor Sólida')],
            ['value' => 'gradient', 'label' => __('Gradiente')],
            ['value' => 'image', 'label' => __('Imagem')],
            ['value' => 'video', 'label' => __('Vídeo')]
        ];
    }
}
