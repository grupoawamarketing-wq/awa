<?php

declare(strict_types=1);

namespace Ayo\Curriculo\Controller\Adminhtml\Submission;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    public const ADMIN_RESOURCE = 'Ayo_Curriculo::submission';

    /**
     * @var PageFactory
     */
    private $resultPageFactory;

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
        $resultPage->setActiveMenu('Ayo_Curriculo::submission');
        $resultPage->getConfig()->getTitle()->prepend(__('Candidaturas - Trabalhe Conosco'));
        return $resultPage;
    }
}
