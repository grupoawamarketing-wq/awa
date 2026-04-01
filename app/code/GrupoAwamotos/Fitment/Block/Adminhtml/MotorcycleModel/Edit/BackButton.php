<?php

declare(strict_types=1);

namespace GrupoAwamotos\Fitment\Block\Adminhtml\MotorcycleModel\Edit;

use GrupoAwamotos\Fitment\Block\Adminhtml\GenericButton;

class BackButton extends GenericButton
{
    /**
     * @return array<string, mixed>
     */
    public function getButtonData(): array
    {
        return [
            'label' => __('Voltar'),
            'on_click' => sprintf("location.href = '%s';", $this->getUrl('fitment/model/index')),
            'class' => 'back',
            'sort_order' => 10,
        ];
    }
}
