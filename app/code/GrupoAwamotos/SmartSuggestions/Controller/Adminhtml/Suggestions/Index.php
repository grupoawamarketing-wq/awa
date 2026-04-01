<?php

declare(strict_types=1);

namespace GrupoAwamotos\SmartSuggestions\Controller\Adminhtml\Suggestions;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_SmartSuggestions::suggestions';

    private PageFactory $resultPageFactory;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('GrupoAwamotos_SmartSuggestions::suggestions');
        $resultPage->getConfig()->getTitle()->prepend(__('Sugestões de Carrinho'));

        return $resultPage;
    }
}
