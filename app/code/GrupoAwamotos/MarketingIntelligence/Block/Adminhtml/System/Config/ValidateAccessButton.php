<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Renders "Validar Acesso Meta" button in system config.
 */
class ValidateAccessButton extends Field
{
    protected $_template = 'GrupoAwamotos_MarketingIntelligence::system/config/validate_access_button.phtml';

    public function render(AbstractElement $element): string
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    protected function _getElementHtml(AbstractElement $element): string
    {
        return $this->_toHtml();
    }

    public function getAjaxUrl(): string
    {
        return $this->getUrl('marketingintelligence/config/validateAccess');
    }

    public function getButtonHtml(): string
    {
        $button = $this->getLayout()->createBlock(
            \Magento\Backend\Block\Widget\Button::class
        )->setData([
            'id' => 'mktg_validate_access_btn',
            'label' => __('Validar Acesso Meta'),
            'class' => 'primary',
        ]);

        return $button->toHtml();
    }
}
