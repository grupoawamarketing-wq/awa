<?php

declare(strict_types=1);

/**
 * Controller do Dashboard Admin
 */

namespace GrupoAwamotos\ProductIntelligence\Controller\Adminhtml\Dashboard;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\App\Action\HttpGetActionInterface;

class Index extends Action implements HttpGetActionInterface
{
    const ADMIN_RESOURCE = 'GrupoAwamotos_ProductIntelligence::dashboard';

    protected $resultPageFactory;

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
        $resultPage->setActiveMenu('GrupoAwamotos_ProductIntelligence::dashboard');
        $resultPage->getConfig()->getTitle()->prepend(__('REXIS ML - Dashboard'));

        return $resultPage;
    }
}
