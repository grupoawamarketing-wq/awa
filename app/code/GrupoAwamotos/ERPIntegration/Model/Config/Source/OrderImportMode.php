<?php
declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class OrderImportMode implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            [
                'value' => 'opencart_bridge',
                'label' => __('OpenCart Bridge (legado)'),
            ],
            [
                'value' => 'api_pull',
                'label' => __('API Pull + validacao Sectra'),
            ],
        ];
    }
}
