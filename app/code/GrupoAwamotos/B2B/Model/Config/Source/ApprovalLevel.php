<?php

/**
 * Approval Level Source for Admin Grid
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ApprovalLevel implements OptionSourceInterface
{
    /**
     * Get approval level options
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 1, 'label' => __('Comprador')],
            ['value' => 2, 'label' => __('Gerente')],
            ['value' => 3, 'label' => __('Financeiro')],
            ['value' => 4, 'label' => __('Diretor')],
        ];
    }
}
