<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Controller\Adminhtml\Audiences;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_MarketingIntelligence::audiences';

    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('GrupoAwamotos_MarketingIntelligence::audiences');
        $resultPage->getConfig()->getTitle()->prepend(__('Audiências Meta'));
        return $resultPage;
    }
}
