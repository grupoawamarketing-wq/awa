<?php

declare(strict_types=1);

namespace GrupoAwamotos\Fitment\Block\Adminhtml\Brand\Edit;

use GrupoAwamotos\Fitment\Block\Adminhtml\GenericButton;

class DeleteButton extends GenericButton
{
    /**
     * @return array<string, mixed>
     */
    public function getButtonData(): array
    {
        if (!($id = $this->getId())) {
            return [];
        }

        return [
            'label' => __('Excluir'),
            'class' => 'delete',
            'on_click' => 'deleteConfirm(\'' . __('Tem certeza que deseja excluir este item?') . '\', \''
                . $this->getUrl('fitment/brand/delete', ['id' => $id]) . '\')',
            'sort_order' => 20,
        ];
    }
}
