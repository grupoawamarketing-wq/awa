<?php

/**
 * Source model para provedores de WhatsApp
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class WhatsAppProvider implements OptionSourceInterface
{
    /**
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'zapi', 'label' => __('Z-API (Recomendado)')],
            ['value' => 'evolution', 'label' => __('Evolution API (Self-hosted)')],
            ['value' => 'twilio', 'label' => __('Twilio')],
            ['value' => 'meta', 'label' => __('Meta Cloud API (Oficial)')]
        ];
    }
}
