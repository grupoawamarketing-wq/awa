<?php

declare(strict_types=1);

namespace Ayo\Curriculo\Controller\Adminhtml\Submission;

use Ayo\Curriculo\Model\SubmissionFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class View extends Action
{
    public const ADMIN_RESOURCE = 'Ayo_Curriculo::submission_view';

    /**
     * @var PageFactory
     */
    private $resultPageFactory;

    /**
     * @var SubmissionFactory
     */
    private $submissionFactory;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        SubmissionFactory $submissionFactory
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->submissionFactory = $submissionFactory;
    }

    public function execute()
    {
        $id = (int)$this->getRequest()->getParam('id');

        if (!$id) {
            $this->messageManager->addErrorMessage(__('Candidatura não encontrada.'));
            return $this->resultRedirectFactory->create()->setPath('*/*/');
        }

        $submission = $this->submissionFactory->create();
        $submission->load($id);

        if (!$submission->getId()) {
            $this->messageManager->addErrorMessage(__('Candidatura não encontrada.'));
            return $this->resultRedirectFactory->create()->setPath('*/*/');
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Ayo_Curriculo::submission');
        $resultPage->getConfig()->getTitle()->prepend(__('Candidatura #%1', $submission->getTrackingCode()));

        return $resultPage;
    }
}
