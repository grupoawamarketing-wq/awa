<?php

declare(strict_types=1);

namespace GrupoAwamotos\SmartSuggestions\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * WhatsApp Provider Source Model
 */
class WhatsappProvider implements OptionSourceInterface
{
    /**
     * Get options array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'meta', 'label' => __('Meta Cloud API (WhatsApp Business)')],
            ['value' => 'twilio', 'label' => __('Twilio WhatsApp')],
            ['value' => 'evolution', 'label' => __('Evolution API')],
            ['value' => 'custom', 'label' => __('API Personalizada')]
        ];
    }
}
