<?php

declare(strict_types=1);

namespace GrupoAwamotos\SmartSuggestions\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * CSV Delimiter Source Model
 */
class CsvDelimiter implements OptionSourceInterface
{
    /**
     * Get options array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => ';', 'label' => __('Ponto e vírgula (;)')],
            ['value' => ',', 'label' => __('Vírgula (,)')],
            ['value' => "\t", 'label' => __('Tab')],
            ['value' => '|', 'label' => __('Pipe (|)')]
        ];
    }
}
