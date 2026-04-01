<?php

/**
 * Source Model para Tipo de Pessoa (PF/PJ)
 */

declare(strict_types=1);

namespace GrupoAwamotos\BrazilCustomer\Model\Config\Source;

use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;

class PersonType extends AbstractSource
{
    /**
     * Get all options
     *
     * @return array
     */
    public function getAllOptions(): array
    {
        if ($this->_options === null) {
            $this->_options = [
                ['value' => 'pf', 'label' => __('Pessoa Física')],
                ['value' => 'pj', 'label' => __('Pessoa Jurídica')],
            ];
        }
        return $this->_options;
    }

    /**
     * Get option text by value
     *
     * @param string $value
     * @return string|false
     */
    public function getOptionText($value)
    {
        foreach ($this->getAllOptions() as $option) {
            if ($option['value'] == $value) {
                return $option['label'];
            }
        }
        return false;
    }
}
