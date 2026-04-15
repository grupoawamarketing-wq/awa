<?php

declare(strict_types=1);

namespace GrupoAwamotos\ProductIntelligence\Controller\Adminhtml\Recommendations;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    const ADMIN_RESOURCE = 'GrupoAwamotos_ProductIntelligence::recommendations';

    private PageFactory $resultPageFactory;

    public function __construct(Context $context, PageFactory $resultPageFactory)
    {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('GrupoAwamotos_ProductIntelligence::recommendations');
        $resultPage->getConfig()->getTitle()->prepend(__('REXIS ML - Recomendacoes'));
        return $resultPage;
    }
}
