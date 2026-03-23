<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Renders readonly field showing detected API permissions.
 */
class ApiPermissions extends Field
{
    protected function _getElementHtml(AbstractElement $element): string
    {
        $element->setReadonly(true);
        $element->setComment(__('Preenchido automaticamente ao clicar "Validar Acesso Meta".'));
        return parent::_getElementHtml($element);
    }
}
