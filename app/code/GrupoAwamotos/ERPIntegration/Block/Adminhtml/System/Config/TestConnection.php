<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Backend\Block\Template\Context;

class TestConnection extends Field
{
    protected $_template = 'GrupoAwamotos_ERPIntegration::system/config/test_connection.phtml';

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
        return $this->getUrl('erpintegration/sync/testConnection');
    }

    public function getButtonHtml(): string
    {
        $button = $this->getLayout()->createBlock(
            \Magento\Backend\Block\Widget\Button::class
        )->setData([
            'id' => 'erp_test_connection_btn',
            'label' => __('Testar Conexao'),
            'class' => 'primary'
        ]);
        return $button->toHtml();
    }
}
