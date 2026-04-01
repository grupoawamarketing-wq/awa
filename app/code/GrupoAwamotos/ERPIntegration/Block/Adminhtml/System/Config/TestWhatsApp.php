<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Backend\Block\Widget\Button;

/**
 * Admin button to test WhatsApp Z-API connection
 */
class TestWhatsApp extends Field
{
    protected $_template = 'GrupoAwamotos_ERPIntegration::system/config/test_whatsapp.phtml';

    /**
     * Remove scope label
     */
    public function render(AbstractElement $element): string
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * Return element html
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        $this->setElement($element);
        return $this->_toHtml();
    }

    /**
     * Return ajax url for button
     */
    public function getAjaxUrl(): string
    {
        return $this->getUrl('erpintegration/whatsapp/test');
    }

    /**
     * Generate button html
     */
    public function getButtonHtml(): string
    {
        /** @var Button $button */
        $button = $this->getLayout()->createBlock(Button::class);
        $button->setData([
            'id' => 'test_whatsapp_btn',
            'label' => __('Testar Conexao'),
            'class' => 'primary',
        ]);

        return $button->toHtml();
    }

    /**
     * Generate send test message button html
     */
    public function getSendTestButtonHtml(): string
    {
        /** @var Button $button */
        $button = $this->getLayout()->createBlock(Button::class);
        $button->setData([
            'id' => 'send_test_whatsapp_btn',
            'label' => __('Enviar Mensagem Teste'),
            'class' => 'secondary',
        ]);

        return $button->toHtml();
    }
}
