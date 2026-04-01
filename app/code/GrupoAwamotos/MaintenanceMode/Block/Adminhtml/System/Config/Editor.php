<?php

declare(strict_types=1);

namespace GrupoAwamotos\MaintenanceMode\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class Editor extends Field
{
    protected function _getElementHtml(AbstractElement $element): string
    {
        $element->setWysiwyg(true);
        $element->setConfig(
            $this->_wysiwygConfig->getConfig([
                'add_variables' => false,
                'add_widgets' => false,
                'add_images' => true,
                'height' => '300px'
            ])
        );
        return parent::_getElementHtml($element);
    }

    /**
     * @var \Magento\Cms\Model\Wysiwyg\Config
     */
    protected $_wysiwygConfig;

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Cms\Model\Wysiwyg\Config $wysiwygConfig,
        array $data = []
    ) {
        $this->_wysiwygConfig = $wysiwygConfig;
        parent::__construct($context, $data);
    }
}
