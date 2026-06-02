<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Config\Source;

use GrupoAwamotos\B2B\Model\Sectra\SectraImportStatus;
use Magento\Framework\Data\OptionSourceInterface;

class SectraImportStatusOptions implements OptionSourceInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach (SectraImportStatus::labels() as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }

        return $options;
    }
}
