<?php

declare(strict_types=1);

namespace GrupoAwamotos\Fitment\Block\Adminhtml\Application\Edit;

use GrupoAwamotos\Fitment\Block\Adminhtml\GenericButton;

class SaveButton extends GenericButton
{
    /**
     * @return array<string, mixed>
     */
    public function getButtonData(): array
    {
        return [
            'label' => __('Salvar Aplicação'),
            'class' => 'save primary',
            'data_attribute' => [
                'mage-init' => ['button' => ['event' => 'save']],
                'form-role' => 'save',
            ],
            'sort_order' => 90,
        ];
    }
}
