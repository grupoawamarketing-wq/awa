<?php

/**
 * Source model for CNAE Profile customer attribute
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Customer\Attribute\Source;

use GrupoAwamotos\B2B\Model\CnaeClassifier;
use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;

class CnaeProfile extends AbstractSource
{
    /**
     * @inheritDoc
     */
    public function getAllOptions(): array
    {
        if ($this->_options === null) {
            $this->_options = [
                ['value' => '', 'label' => __('-- Não classificado --')],
                ['value' => CnaeClassifier::PROFILE_DIRECT, 'label' => __('Perfil Direto (Motos)')],
                ['value' => CnaeClassifier::PROFILE_ADJACENT, 'label' => __('Perfil Adjacente (Automotivo)')],
                ['value' => CnaeClassifier::PROFILE_OFF, 'label' => __('Fora do Perfil')],
            ];
        }

        return $this->_options;
    }
}
