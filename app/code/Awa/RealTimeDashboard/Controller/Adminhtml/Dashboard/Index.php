<?php

namespace Awa\RealTimeDashboard\Controller\Adminhtml\Dashboard;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    const ADMIN_RESOURCE = 'Awa_RealTimeDashboard::dashboard';

    protected $resultPageFactory;

    public function __construct(Context $context, PageFactory $resultPageFactory)
    {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Awa_RealTimeDashboard::dashboard');
        $resultPage->getConfig()->getTitle()->prepend(__('Monitor em Tempo Real'));
        return $resultPage;
    }
}
