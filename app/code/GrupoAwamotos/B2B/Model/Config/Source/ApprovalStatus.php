<?php

/**
 * Order Approval Status Options for Admin Grid
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ApprovalStatus implements OptionSourceInterface
{
    /**
     * Get approval status options
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'pending', 'label' => __('Pendente')],
            ['value' => 'approved', 'label' => __('Aprovado')],
            ['value' => 'rejected', 'label' => __('Rejeitado')],
            ['value' => 'cancelled', 'label' => __('Cancelado')],
        ];
    }
}
