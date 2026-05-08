<?php

declare(strict_types=1);

namespace GrupoAwamotos\TawkIntegration\Controller\Adminhtml\ChatLog;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_TawkIntegration::config';

    private PageFactory $resultPageFactory;

    public function __construct(Context $context, PageFactory $resultPageFactory)
    {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    public function execute(): Page
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('GrupoAwamotos_TawkIntegration::config');
        $resultPage->getConfig()->getTitle()->prepend(__('Logs de Chat — Tawk.to'));
        return $resultPage;
    }
}
